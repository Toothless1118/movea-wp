(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
(function (process){
/**
 * This is the web browser implementation of `debug()`.
 *
 * Expose `debug()` as the module.
 */

exports = module.exports = require('./debug');
exports.log = log;
exports.formatArgs = formatArgs;
exports.save = save;
exports.load = load;
exports.useColors = useColors;
exports.storage = 'undefined' != typeof chrome
               && 'undefined' != typeof chrome.storage
                  ? chrome.storage.local
                  : localstorage();

/**
 * Colors.
 */

exports.colors = [
  'lightseagreen',
  'forestgreen',
  'goldenrod',
  'dodgerblue',
  'darkorchid',
  'crimson'
];

/**
 * Currently only WebKit-based Web Inspectors, Firefox >= v31,
 * and the Firebug extension (any Firefox version) are known
 * to support "%c" CSS customizations.
 *
 * TODO: add a `localStorage` variable to explicitly enable/disable colors
 */

function useColors() {
  // NB: In an Electron preload script, document will be defined but not fully
  // initialized. Since we know we're in Chrome, we'll just detect this case
  // explicitly
  if (typeof window !== 'undefined' && window && typeof window.process !== 'undefined' && window.process.type === 'renderer') {
    return true;
  }

  // is webkit? http://stackoverflow.com/a/16459606/376773
  // document is undefined in react-native: https://github.com/facebook/react-native/pull/1632
  return (typeof document !== 'undefined' && document && 'WebkitAppearance' in document.documentElement.style) ||
    // is firebug? http://stackoverflow.com/a/398120/376773
    (typeof window !== 'undefined' && window && window.console && (console.firebug || (console.exception && console.table))) ||
    // is firefox >= v31?
    // https://developer.mozilla.org/en-US/docs/Tools/Web_Console#Styling_messages
    (typeof navigator !== 'undefined' && navigator && navigator.userAgent && navigator.userAgent.toLowerCase().match(/firefox\/(\d+)/) && parseInt(RegExp.$1, 10) >= 31) ||
    // double check webkit in userAgent just in case we are in a worker
    (typeof navigator !== 'undefined' && navigator && navigator.userAgent && navigator.userAgent.toLowerCase().match(/applewebkit\/(\d+)/));
}

/**
 * Map %j to `JSON.stringify()`, since no Web Inspectors do that by default.
 */

exports.formatters.j = function(v) {
  try {
    return JSON.stringify(v);
  } catch (err) {
    return '[UnexpectedJSONParseError]: ' + err.message;
  }
};


/**
 * Colorize log arguments if enabled.
 *
 * @api public
 */

function formatArgs(args) {
  var useColors = this.useColors;

  args[0] = (useColors ? '%c' : '')
    + this.namespace
    + (useColors ? ' %c' : ' ')
    + args[0]
    + (useColors ? '%c ' : ' ')
    + '+' + exports.humanize(this.diff);

  if (!useColors) return;

  var c = 'color: ' + this.color;
  args.splice(1, 0, c, 'color: inherit')

  // the final "%c" is somewhat tricky, because there could be other
  // arguments passed either before or after the %c, so we need to
  // figure out the correct index to insert the CSS into
  var index = 0;
  var lastC = 0;
  args[0].replace(/%[a-zA-Z%]/g, function(match) {
    if ('%%' === match) return;
    index++;
    if ('%c' === match) {
      // we only are interested in the *last* %c
      // (the user may have provided their own)
      lastC = index;
    }
  });

  args.splice(lastC, 0, c);
}

/**
 * Invokes `console.log()` when available.
 * No-op when `console.log` is not a "function".
 *
 * @api public
 */

function log() {
  // this hackery is required for IE8/9, where
  // the `console.log` function doesn't have 'apply'
  return 'object' === typeof console
    && console.log
    && Function.prototype.apply.call(console.log, console, arguments);
}

/**
 * Save `namespaces`.
 *
 * @param {String} namespaces
 * @api private
 */

function save(namespaces) {
  try {
    if (null == namespaces) {
      exports.storage.removeItem('debug');
    } else {
      exports.storage.debug = namespaces;
    }
  } catch(e) {}
}

/**
 * Load `namespaces`.
 *
 * @return {String} returns the previously persisted debug modes
 * @api private
 */

function load() {
  try {
    return exports.storage.debug;
  } catch(e) {}

  // If debug isn't set in LS, and we're in Electron, try to load $DEBUG
  if (typeof process !== 'undefined' && 'env' in process) {
    return process.env.DEBUG;
  }
}

/**
 * Enable namespaces listed in `localStorage.debug` initially.
 */

exports.enable(load());

/**
 * Localstorage attempts to return the localstorage.
 *
 * This is necessary because safari throws
 * when a user disables cookies/localstorage
 * and you attempt to access it.
 *
 * @return {LocalStorage}
 * @api private
 */

function localstorage() {
  try {
    return window.localStorage;
  } catch (e) {}
}

}).call(this,require('_process'))

},{"./debug":2,"_process":4}],2:[function(require,module,exports){

/**
 * This is the common logic for both the Node.js and web browser
 * implementations of `debug()`.
 *
 * Expose `debug()` as the module.
 */

exports = module.exports = createDebug.debug = createDebug['default'] = createDebug;
exports.coerce = coerce;
exports.disable = disable;
exports.enable = enable;
exports.enabled = enabled;
exports.humanize = require('ms');

/**
 * The currently active debug mode names, and names to skip.
 */

exports.names = [];
exports.skips = [];

/**
 * Map of special "%n" handling functions, for the debug "format" argument.
 *
 * Valid key names are a single, lower or upper-case letter, i.e. "n" and "N".
 */

exports.formatters = {};

/**
 * Previous log timestamp.
 */

var prevTime;

/**
 * Select a color.
 * @param {String} namespace
 * @return {Number}
 * @api private
 */

function selectColor(namespace) {
  var hash = 0, i;

  for (i in namespace) {
    hash  = ((hash << 5) - hash) + namespace.charCodeAt(i);
    hash |= 0; // Convert to 32bit integer
  }

  return exports.colors[Math.abs(hash) % exports.colors.length];
}

/**
 * Create a debugger with the given `namespace`.
 *
 * @param {String} namespace
 * @return {Function}
 * @api public
 */

function createDebug(namespace) {

  function debug() {
    // disabled?
    if (!debug.enabled) return;

    var self = debug;

    // set `diff` timestamp
    var curr = +new Date();
    var ms = curr - (prevTime || curr);
    self.diff = ms;
    self.prev = prevTime;
    self.curr = curr;
    prevTime = curr;

    // turn the `arguments` into a proper Array
    var args = new Array(arguments.length);
    for (var i = 0; i < args.length; i++) {
      args[i] = arguments[i];
    }

    args[0] = exports.coerce(args[0]);

    if ('string' !== typeof args[0]) {
      // anything else let's inspect with %O
      args.unshift('%O');
    }

    // apply any `formatters` transformations
    var index = 0;
    args[0] = args[0].replace(/%([a-zA-Z%])/g, function(match, format) {
      // if we encounter an escaped % then don't increase the array index
      if (match === '%%') return match;
      index++;
      var formatter = exports.formatters[format];
      if ('function' === typeof formatter) {
        var val = args[index];
        match = formatter.call(self, val);

        // now we need to remove `args[index]` since it's inlined in the `format`
        args.splice(index, 1);
        index--;
      }
      return match;
    });

    // apply env-specific formatting (colors, etc.)
    exports.formatArgs.call(self, args);

    var logFn = debug.log || exports.log || console.log.bind(console);
    logFn.apply(self, args);
  }

  debug.namespace = namespace;
  debug.enabled = exports.enabled(namespace);
  debug.useColors = exports.useColors();
  debug.color = selectColor(namespace);

  // env-specific initialization logic for debug instances
  if ('function' === typeof exports.init) {
    exports.init(debug);
  }

  return debug;
}

/**
 * Enables a debug mode by namespaces. This can include modes
 * separated by a colon and wildcards.
 *
 * @param {String} namespaces
 * @api public
 */

function enable(namespaces) {
  exports.save(namespaces);

  exports.names = [];
  exports.skips = [];

  var split = (namespaces || '').split(/[\s,]+/);
  var len = split.length;

  for (var i = 0; i < len; i++) {
    if (!split[i]) continue; // ignore empty strings
    namespaces = split[i].replace(/\*/g, '.*?');
    if (namespaces[0] === '-') {
      exports.skips.push(new RegExp('^' + namespaces.substr(1) + '$'));
    } else {
      exports.names.push(new RegExp('^' + namespaces + '$'));
    }
  }
}

/**
 * Disable debug output.
 *
 * @api public
 */

function disable() {
  exports.enable('');
}

/**
 * Returns true if the given mode name is enabled, false otherwise.
 *
 * @param {String} name
 * @return {Boolean}
 * @api public
 */

function enabled(name) {
  var i, len;
  for (i = 0, len = exports.skips.length; i < len; i++) {
    if (exports.skips[i].test(name)) {
      return false;
    }
  }
  for (i = 0, len = exports.names.length; i < len; i++) {
    if (exports.names[i].test(name)) {
      return true;
    }
  }
  return false;
}

/**
 * Coerce `val`.
 *
 * @param {Mixed} val
 * @return {Mixed}
 * @api private
 */

function coerce(val) {
  if (val instanceof Error) return val.stack || val.message;
  return val;
}

},{"ms":3}],3:[function(require,module,exports){
/**
 * Helpers.
 */

var s = 1000
var m = s * 60
var h = m * 60
var d = h * 24
var y = d * 365.25

/**
 * Parse or format the given `val`.
 *
 * Options:
 *
 *  - `long` verbose formatting [false]
 *
 * @param {String|Number} val
 * @param {Object} options
 * @throws {Error} throw an error if val is not a non-empty string or a number
 * @return {String|Number}
 * @api public
 */

module.exports = function (val, options) {
  options = options || {}
  var type = typeof val
  if (type === 'string' && val.length > 0) {
    return parse(val)
  } else if (type === 'number' && isNaN(val) === false) {
    return options.long ?
			fmtLong(val) :
			fmtShort(val)
  }
  throw new Error('val is not a non-empty string or a valid number. val=' + JSON.stringify(val))
}

/**
 * Parse the given `str` and return milliseconds.
 *
 * @param {String} str
 * @return {Number}
 * @api private
 */

function parse(str) {
  str = String(str)
  if (str.length > 10000) {
    return
  }
  var match = /^((?:\d+)?\.?\d+) *(milliseconds?|msecs?|ms|seconds?|secs?|s|minutes?|mins?|m|hours?|hrs?|h|days?|d|years?|yrs?|y)?$/i.exec(str)
  if (!match) {
    return
  }
  var n = parseFloat(match[1])
  var type = (match[2] || 'ms').toLowerCase()
  switch (type) {
    case 'years':
    case 'year':
    case 'yrs':
    case 'yr':
    case 'y':
      return n * y
    case 'days':
    case 'day':
    case 'd':
      return n * d
    case 'hours':
    case 'hour':
    case 'hrs':
    case 'hr':
    case 'h':
      return n * h
    case 'minutes':
    case 'minute':
    case 'mins':
    case 'min':
    case 'm':
      return n * m
    case 'seconds':
    case 'second':
    case 'secs':
    case 'sec':
    case 's':
      return n * s
    case 'milliseconds':
    case 'millisecond':
    case 'msecs':
    case 'msec':
    case 'ms':
      return n
    default:
      return undefined
  }
}

/**
 * Short format for `ms`.
 *
 * @param {Number} ms
 * @return {String}
 * @api private
 */

function fmtShort(ms) {
  if (ms >= d) {
    return Math.round(ms / d) + 'd'
  }
  if (ms >= h) {
    return Math.round(ms / h) + 'h'
  }
  if (ms >= m) {
    return Math.round(ms / m) + 'm'
  }
  if (ms >= s) {
    return Math.round(ms / s) + 's'
  }
  return ms + 'ms'
}

/**
 * Long format for `ms`.
 *
 * @param {Number} ms
 * @return {String}
 * @api private
 */

function fmtLong(ms) {
  return plural(ms, d, 'day') ||
    plural(ms, h, 'hour') ||
    plural(ms, m, 'minute') ||
    plural(ms, s, 'second') ||
    ms + ' ms'
}

/**
 * Pluralization helper.
 */

function plural(ms, n, name) {
  if (ms < n) {
    return
  }
  if (ms < n * 1.5) {
    return Math.floor(ms / n) + ' ' + name
  }
  return Math.ceil(ms / n) + ' ' + name + 's'
}

},{}],4:[function(require,module,exports){
// shim for using process in browser
var process = module.exports = {};

// cached from whatever global is present so that test runners that stub it
// don't break things.  But we need to wrap it in a try catch in case it is
// wrapped in strict mode code which doesn't define any globals.  It's inside a
// function because try/catches deoptimize in certain engines.

var cachedSetTimeout;
var cachedClearTimeout;

function defaultSetTimout() {
    throw new Error('setTimeout has not been defined');
}
function defaultClearTimeout () {
    throw new Error('clearTimeout has not been defined');
}
(function () {
    try {
        if (typeof setTimeout === 'function') {
            cachedSetTimeout = setTimeout;
        } else {
            cachedSetTimeout = defaultSetTimout;
        }
    } catch (e) {
        cachedSetTimeout = defaultSetTimout;
    }
    try {
        if (typeof clearTimeout === 'function') {
            cachedClearTimeout = clearTimeout;
        } else {
            cachedClearTimeout = defaultClearTimeout;
        }
    } catch (e) {
        cachedClearTimeout = defaultClearTimeout;
    }
} ())
function runTimeout(fun) {
    if (cachedSetTimeout === setTimeout) {
        //normal enviroments in sane situations
        return setTimeout(fun, 0);
    }
    // if setTimeout wasn't available but was latter defined
    if ((cachedSetTimeout === defaultSetTimout || !cachedSetTimeout) && setTimeout) {
        cachedSetTimeout = setTimeout;
        return setTimeout(fun, 0);
    }
    try {
        // when when somebody has screwed with setTimeout but no I.E. maddness
        return cachedSetTimeout(fun, 0);
    } catch(e){
        try {
            // When we are in I.E. but the script has been evaled so I.E. doesn't trust the global object when called normally
            return cachedSetTimeout.call(null, fun, 0);
        } catch(e){
            // same as above but when it's a version of I.E. that must have the global object for 'this', hopfully our context correct otherwise it will throw a global error
            return cachedSetTimeout.call(this, fun, 0);
        }
    }


}
function runClearTimeout(marker) {
    if (cachedClearTimeout === clearTimeout) {
        //normal enviroments in sane situations
        return clearTimeout(marker);
    }
    // if clearTimeout wasn't available but was latter defined
    if ((cachedClearTimeout === defaultClearTimeout || !cachedClearTimeout) && clearTimeout) {
        cachedClearTimeout = clearTimeout;
        return clearTimeout(marker);
    }
    try {
        // when when somebody has screwed with setTimeout but no I.E. maddness
        return cachedClearTimeout(marker);
    } catch (e){
        try {
            // When we are in I.E. but the script has been evaled so I.E. doesn't  trust the global object when called normally
            return cachedClearTimeout.call(null, marker);
        } catch (e){
            // same as above but when it's a version of I.E. that must have the global object for 'this', hopfully our context correct otherwise it will throw a global error.
            // Some versions of I.E. have different rules for clearTimeout vs setTimeout
            return cachedClearTimeout.call(this, marker);
        }
    }



}
var queue = [];
var draining = false;
var currentQueue;
var queueIndex = -1;

function cleanUpNextTick() {
    if (!draining || !currentQueue) {
        return;
    }
    draining = false;
    if (currentQueue.length) {
        queue = currentQueue.concat(queue);
    } else {
        queueIndex = -1;
    }
    if (queue.length) {
        drainQueue();
    }
}

function drainQueue() {
    if (draining) {
        return;
    }
    var timeout = runTimeout(cleanUpNextTick);
    draining = true;

    var len = queue.length;
    while(len) {
        currentQueue = queue;
        queue = [];
        while (++queueIndex < len) {
            if (currentQueue) {
                currentQueue[queueIndex].run();
            }
        }
        queueIndex = -1;
        len = queue.length;
    }
    currentQueue = null;
    draining = false;
    runClearTimeout(timeout);
}

process.nextTick = function (fun) {
    var args = new Array(arguments.length - 1);
    if (arguments.length > 1) {
        for (var i = 1; i < arguments.length; i++) {
            args[i - 1] = arguments[i];
        }
    }
    queue.push(new Item(fun, args));
    if (queue.length === 1 && !draining) {
        runTimeout(drainQueue);
    }
};

// v8 likes predictible objects
function Item(fun, array) {
    this.fun = fun;
    this.array = array;
}
Item.prototype.run = function () {
    this.fun.apply(null, this.array);
};
process.title = 'browser';
process.browser = true;
process.env = {};
process.argv = [];
process.version = ''; // empty string to avoid regexp issues
process.versions = {};

function noop() {}

process.on = noop;
process.addListener = noop;
process.once = noop;
process.off = noop;
process.removeListener = noop;
process.removeAllListeners = noop;
process.emit = noop;

process.binding = function (name) {
    throw new Error('process.binding is not supported');
};

process.cwd = function () { return '/' };
process.chdir = function (dir) {
    throw new Error('process.chdir is not supported');
};
process.umask = function() { return 0; };

},{}],5:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.default = getAPI;

var _window = require('./window');

var _window2 = _interopRequireDefault(_window);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function getAPI() {
	if (!(0, _window2.default)().wp || !(0, _window2.default)().wp.customize) {
		throw new Error('No WordPress customizer API found');
	}
	return (0, _window2.default)().wp.customize;
}

},{"./window":13}],6:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.default = addClickHandler;

var _jquery = require('../helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var debug = (0, _debug2.default)('cdm:click-handler');
var $ = (0, _jquery2.default)();

function addClickHandler(clickTarget, handler) {
	debug('adding click handler to target', clickTarget);
	return $('body').on('click', clickTarget, handler);
}

},{"../helpers/jquery":8,"debug":1}],7:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.positionIcon = positionIcon;
exports.addClickHandlerToIcon = addClickHandlerToIcon;
exports.repositionIcons = repositionIcons;
exports.repositionAfterFontsLoad = repositionAfterFontsLoad;
exports.enableIconToggle = enableIconToggle;

var _window = require('../helpers/window');

var _window2 = _interopRequireDefault(_window);

var _jquery = require('../helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _messenger = require('../helpers/messenger');

var _underscore = require('../helpers/underscore');

var _underscore2 = _interopRequireDefault(_underscore);

var _clickHandler = require('../helpers/click-handler');

var _clickHandler2 = _interopRequireDefault(_clickHandler);

var _options = require('../helpers/options');

var _options2 = _interopRequireDefault(_options);

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var _ = (0, _underscore2.default)();
var debug = (0, _debug2.default)('cdm:icon-buttons');
var $ = (0, _jquery2.default)();

// Icons from: https://github.com/WordPress/dashicons/tree/master/svg
// Elements will default to using `editIcon` but if an element has the `icon`
// property set, it will use that as the key for one of these icons instead:
var icons = {
	headerIcon: '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" viewBox="0 0 20 20"><path d="M2.25 1h15.5c0.69 0 1.25 0.56 1.25 1.25v15.5c0 0.69-0.56 1.25-1.25 1.25h-15.5c-0.69 0-1.25-0.56-1.25-1.25v-15.5c0-0.69 0.56-1.25 1.25-1.25zM17 17v-14h-14v14h14zM10 6c0-1.1-0.9-2-2-2s-2 0.9-2 2 0.9 2 2 2 2-0.9 2-2zM13 11c0 0 0-6 3-6v10c0 0.55-0.45 1-1 1h-10c-0.55 0-1-0.45-1-1v-7c2 0 3 4 3 4s1-3 3-3 3 2 3 2z"></path></svg>',
	editIcon: '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" viewBox="0 0 20 20"><path d="M13.89 3.39l2.71 2.72c0.46 0.46 0.42 1.24 0.030 1.64l-8.010 8.020-5.56 1.16 1.16-5.58s7.6-7.63 7.99-8.030c0.39-0.39 1.22-0.39 1.68 0.070zM11.16 6.18l-5.59 5.61 1.11 1.11 5.54-5.65zM8.19 14.41l5.58-5.6-1.070-1.080-5.59 5.6z"></path></svg>',
	pageBuilderIcon: '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" viewBox="0 0 20 20"><path d="M19 16v-13c0-0.55-0.45-1-1-1h-15c-0.55 0-1 0.45-1 1v13c0 0.55 0.45 1 1 1h15c0.55 0 1-0.45 1-1zM4 4h13v4h-13v-4zM5 5v2h3v-2h-3zM9 5v2h3v-2h-3zM13 5v2h3v-2h-3zM4.5 10c0.28 0 0.5 0.22 0.5 0.5s-0.22 0.5-0.5 0.5-0.5-0.22-0.5-0.5 0.22-0.5 0.5-0.5zM6 10h4v1h-4v-1zM12 10h5v5h-5v-5zM4.5 12c0.28 0 0.5 0.22 0.5 0.5s-0.22 0.5-0.5 0.5-0.5-0.22-0.5-0.5 0.22-0.5 0.5-0.5zM6 12h4v1h-4v-1zM13 12v2h3v-2h-3zM4.5 14c0.28 0 0.5 0.22 0.5 0.5s-0.22 0.5-0.5 0.5-0.5-0.22-0.5-0.5 0.22-0.5 0.5-0.5zM6 14h4v1h-4v-1z"></path></svg>'
};

/**
 * Create (if necessary) and position an icon button relative to its target.
 *
 * See `makeFocusable` for the format of the `element` param.
 *
 * If positioning the icon was successful, this function returns a copy of the
 * element it was passed with the additional parameters `$target` and `$icon`
 * that are cached references to the DOM elements. If the positioning failed, it
 * just returns the element unchanged.
 *
 * @param {Object} element - The data to use when constructing the icon.
 * @return {Object} The element that was passed, with additional data included.
 */
function positionIcon(element) {
	var $target = getElementTarget(element);
	if (!$target.length) {
		debug('Could not find target element for icon ' + element.id + ' with selector ' + element.selector);
		return element;
	}
	var $icon = findOrCreateIcon(element);
	var css = getCalculatedCssForIcon(element, $target, $icon);
	debug('positioning icon for ' + element.id + ' with CSS ' + JSON.stringify(css));
	$icon.css(css);
	return _.extend({}, element, { $target: $target, $icon: $icon });
}

function addClickHandlerToIcon(element) {
	if (!element.$icon) {
		return element;
	}
	(0, _clickHandler2.default)('.' + getIconClassName(element.id), element.handler);
	return element;
}

var iconRepositioner = _.debounce(function (elements) {
	debug('repositioning ' + elements.length + ' icons');
	elements.map(positionIcon);
}, 350);

function repositionIcons(elements) {
	iconRepositioner(elements);
}

function repositionAfterFontsLoad(elements) {
	iconRepositioner(elements);

	if ((0, _window2.default)().document.fonts) {
		(0, _window2.default)().document.fonts.ready.then(iconRepositioner.bind(null, elements));
	}
}

/**
 * Toggle icons when customizer toggles preview mode.
 */
function enableIconToggle() {
	(0, _messenger.on)('cdm-toggle-visible', function () {
		return $('.cdm-icon').toggleClass('cdm-icon--hidden');
	});
}

function findOrCreateIcon(element) {
	if (element.$icon) {
		return element.$icon;
	}
	var $icon = $('.' + getIconClassName(element.id));
	if ($icon.length) {
		return $icon;
	}

	var $widget_location = getWidgetLocation(element.selector);

	var title = (0, _options2.default)().translations[element.type] || 'Click to edit the ' + element.title;

	return createAndAppendIcon(element.id, element.icon, title, $widget_location);
}

function getWidgetLocation(selector) {

	// Site info wrapper (below footer)
	if ($(selector).parents('.site-title-wrapper').length || $(selector).parents('.site-title').length) {

		return 'site-title-widget';
	}

	// Hero
	if ($(selector).hasClass('hero')) {

		return 'hero-widget';
	}

	// Page Builder (below footer)
	if (_Customizer_DM.beaver_builder) {

		return 'page-builder-widget';
	}

	// Footer Widget
	if ($(selector).parents('.footer-widget').length) {

		return 'footer-widget';
	}

	// Site info wrapper (below footer)
	if ($(selector).parents('.site-info-wrapper').length) {

		return 'site-info-wrapper-widget';
	}

	return 'default';
}

function getIconClassName(id) {
	return 'cdm-icon__' + id;
}

function getCalculatedCssForIcon(element, $target, $icon) {
	var position = element.position;
	var hiddenIconPos = 'rtl' === (0, _window2.default)().document.dir ? { right: -1000, left: 'auto' } : { left: -1000, right: 'auto' };

	if (!$target.is(':visible')) {
		debug('target is not visible when positioning ' + element.id + '. I will hide the icon. target:', $target);
		return hiddenIconPos;
	}
	var offset = $target.offset();
	var top = offset.top;
	var left = offset.left;
	var middle = $target.innerHeight() / 2;
	var iconMiddle = $icon.innerHeight() / 2;
	if (top < 0) {
		debug('target top offset ' + top + ' is unusually low when positioning ' + element.id + '. I will hide the icon. target:', $target);
		return hiddenIconPos;
	}
	if (middle < 0) {
		debug('target middle offset ' + middle + ' is unusually low when positioning ' + element.id + '. I will hide the icon. target:', $target);
		return hiddenIconPos;
	}
	if (top < 1) {
		debug('target top offset ' + top + ' is unusually low when positioning ' + element.id + '. I will adjust the icon downwards. target:', $target);
		top = 0;
	}
	if (middle < 1) {
		debug('target middle offset ' + middle + ' is unusually low when positioning ' + element.id + '. I will adjust the icon downwards. target:', $target);
		middle = 0;
		iconMiddle = 0;
	}
	if (position === 'middle') {
		return adjustCoordinates({ top: top + middle - iconMiddle, left: left, right: 'auto' });
	} else if (position === 'top-right') {
		return adjustCoordinates({ top: top, left: left + $target.width() + 70, right: 'auto' });
	}
	return adjustCoordinates({ top: top, left: left, right: 'auto' });
}

function adjustCoordinates(coords) {
	var minWidth = 35;
	// Try to avoid overlapping hamburger menus
	var maxWidth = (0, _window2.default)().innerWidth - 110;
	if (coords.left < minWidth) {
		coords.left = minWidth;
	}
	if (coords.left >= maxWidth) {
		coords.left = maxWidth;
	}
	return coords;
}

function createIcon(id, iconType, title, widget_location) {
	var iconClassName = getIconClassName(id);
	var scheme = (0, _options2.default)().icon_color;
	var theme = (0, _options2.default)().theme;

	switch (iconType) {
		case 'headerIcon':
			return $('<div class="cdm-icon cdm-icon--header-image ' + iconClassName + ' ' + scheme + ' ' + theme + ' ' + widget_location + '" title="' + title + '">' + icons.headerIcon + '</div>');
		case 'pageBuilderIcon':
			return $('<div class="cdm-icon cdm-icon--page-builder ' + iconClassName + ' ' + scheme + ' ' + theme + ' ' + widget_location + '" title="' + title + '">' + icons.pageBuilderIcon + '</div>');
		default:
			return $('<div class="cdm-icon cdm-icon--text ' + iconClassName + ' ' + scheme + ' ' + theme + ' ' + widget_location + '" title="' + title + '">' + icons.editIcon + '</div>');
	}
}

function createAndAppendIcon(id, iconType, title, widget_location) {
	var $icon = createIcon(id, iconType, title, widget_location);
	$((0, _window2.default)().document.body).append($icon);
	return $icon;
}

function getElementTarget(element) {
	if (element.$target && !element.$target.parent().length) {
		// target was removed from DOM, likely by partial refresh
		element.$target = null;
	}
	return element.$target || $(element.selector);
}

},{"../helpers/click-handler":6,"../helpers/jquery":8,"../helpers/messenger":9,"../helpers/options":10,"../helpers/underscore":11,"../helpers/window":13,"debug":1}],8:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.default = getJQuery;

var _window = require('./window');

var _window2 = _interopRequireDefault(_window);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function getJQuery() {
	return (0, _window2.default)().jQuery;
}

},{"./window":13}],9:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.send = send;
exports.on = on;
exports.off = off;

var _api = require('./api');

var _api2 = _interopRequireDefault(_api);

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var debug = (0, _debug2.default)('cdm:messenger');
var api = (0, _api2.default)();

function getPreview() {
	// wp-admin is previewer, frontend is preview. why? no idea.
	return typeof api.preview !== 'undefined' ? api.preview : api.previewer;
}

function send(id, data) {
	debug('send', id, data);
	return getPreview().send(id, data);
}

function on(id, callback) {
	debug('on', id, callback);
	return getPreview().bind(id, callback);
}

function off(id) {
	var callback = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;

	debug('off', id, callback);
	if (callback) {
		return getPreview().unbind(id, callback);
	}
	// no callback? Get rid of all of 'em
	var topic = getPreview().topics[id];
	if (topic) {
		return topic.empty();
	}
}

},{"./api":5,"debug":1}],10:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.default = getOptions;

var _window = require('./window');

var _window2 = _interopRequireDefault(_window);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function getOptions() {
	return (0, _window2.default)()._Customizer_DM;
}

},{"./window":13}],11:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.default = getUnderscore;

var _window = require('./window');

var _window2 = _interopRequireDefault(_window);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function getUnderscore() {
	return (0, _window2.default)()._;
}

},{"./window":13}],12:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.getUserAgent = getUserAgent;
exports.isSafari = isSafari;
exports.isMobileSafari = isMobileSafari;

var _window = require('../helpers/window');

var _window2 = _interopRequireDefault(_window);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function getUserAgent() {
	return (0, _window2.default)().navigator.userAgent;
}

function isSafari() {
	return !!getUserAgent().match(/Version\/[\d\.]+.*Safari/);
}

function isMobileSafari() {
	return !!getUserAgent().match(/(iPod|iPhone|iPad)/);
}

},{"../helpers/window":13}],13:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.setWindow = setWindow;
exports.default = getWindow;
var windowObj = null;

function setWindow(obj) {
	windowObj = obj;
}

function getWindow() {
	if (!windowObj && !window) {
		throw new Error('No window object found.');
	}
	return windowObj || window;
}

},{}],14:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.modifyEditPostLinks = modifyEditPostLinks;
exports.disableEditPostLinks = disableEditPostLinks;

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

var _window = require('../helpers/window');

var _window2 = _interopRequireDefault(_window);

var _jquery = require('../helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _messenger = require('../helpers/messenger');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var $ = (0, _jquery2.default)();
var debug = (0, _debug2.default)('cdm:edit-post-links');

function modifyEditPostLinks(selector) {
	debug('listening for clicks on post edit links with selector', selector);
	// We use mousedown because click has been blocked by some other JS
	$('body').on('mousedown', selector, function (event) {
		(0, _window2.default)().open(event.target.href);
		(0, _messenger.send)('recordEvent', {
			name: 'wpcom_customize_direct_manipulation_click',
			props: { type: 'post-edit' }
		});
	});
}

function disableEditPostLinks(selector) {
	debug('hiding post edit links with selector', selector);
	$(selector).hide();
}

},{"../helpers/jquery":8,"../helpers/messenger":9,"../helpers/window":13,"debug":1}],15:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.default = makeFocusable;

var _window = require('../helpers/window');

var _window2 = _interopRequireDefault(_window);

var _api = require('../helpers/api');

var _api2 = _interopRequireDefault(_api);

var _jquery = require('../helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _messenger = require('../helpers/messenger');

var _iconButtons = require('../helpers/icon-buttons');

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var debug = (0, _debug2.default)('cdm:focusable');
var api = (0, _api2.default)();
var $ = (0, _jquery2.default)();

/**
 * Give DOM elements an icon button bound to click handlers
 *
 * Accepts an array of element objects of the form:
 *
 * {
 * 	id: A string to identify this element
 * 	selector: A CSS selector string to uniquely target the DOM element
 * 	type: A string to group the element, eg: 'widget'
 * 	position: (optional) A string for positioning the icon, one of 'top-left' (default), 'top-right', or 'middle' (vertically center)
 * 	icon (optional): A string specifying which icon to use. See options in icon-buttons.js
 * 	handler (optional): A callback function which will be called when the icon is clicked
 * }
 *
 * If no handler is specified, the default will be used, which will send
 * `control-focus` to the API with the element ID.
 *
 * @param {Array} elements - An array of element objects of the form above.
 */
function makeFocusable(elements) {
	var elementsWithIcons = elements.reduce(removeDuplicateReducer, []).map(_iconButtons.positionIcon).map(createHandler).map(_iconButtons.addClickHandlerToIcon);

	if (elementsWithIcons.length) {
		startIconMonitor(elementsWithIcons);
		(0, _iconButtons.enableIconToggle)();
	}
}

function makeRepositioner(elements, changeType) {
	return function () {
		debug('detected change:', changeType);
		(0, _iconButtons.repositionAfterFontsLoad)(elements);
	};
}

/**
 * Register a group of listeners to reposition icon buttons if the DOM changes.
 *
 * See `makeFocusable` for the format of the `elements` param.
 *
 * @param {Array} elements - The element objects.
 */
function startIconMonitor(elements) {
	// Reposition icons after any theme fonts load
	(0, _iconButtons.repositionAfterFontsLoad)(elements);

	// Reposition icons after a few seconds just in case (eg: infinite scroll or other scripts complete)
	setTimeout(makeRepositioner(elements, 'follow-up'), 2000);

	// Reposition icons after the window is resized
	$((0, _window2.default)()).resize(makeRepositioner(elements, 'resize'));

	// Reposition icons after the text of any element changes
	elements.filter(function (el) {
		return ['siteTitle', 'headerIcon'].indexOf(el.type) !== -1;
	}).map(function (el) {
		return api(el.id, function (value) {
			return value.bind(makeRepositioner(elements, 'title or header'));
		});
	});

	// When the widget partial refresh runs, reposition icons
	api.bind('widget-updated', makeRepositioner(elements, 'widgets'));

	// Reposition icons after any customizer setting is changed
	api.bind('change', makeRepositioner(elements, 'any setting'));

	var $document = $((0, _window2.default)().document);

	// Reposition after menus updated
	$document.on('customize-preview-menu-refreshed', makeRepositioner(elements, 'menus'));

	// Reposition after scrolling in case there are fixed position elements
	$document.on('scroll', makeRepositioner(elements, 'scroll'));

	// Reposition after page click (eg: hamburger menus)
	$document.on('click', makeRepositioner(elements, 'click'));

	// Reposition after any page changes (if the browser supports it)
	var page = (0, _window2.default)().document.querySelector('#page');
	if (page && MutationObserver) {
		var observer = new MutationObserver(makeRepositioner(elements, 'DOM mutation'));
		observer.observe(page, { attributes: true, childList: true, characterData: true });
	}
}

function createHandler(element) {
	element.handler = element.handler || makeDefaultHandler(element.id);
	return element;
}

function removeDuplicateReducer(prev, el) {
	if (prev.map(function (x) {
		return x.id;
	}).indexOf(el.id) !== -1) {
		debug('tried to add duplicate element for ' + el.id);
		return prev;
	}
	return prev.concat(el);
}

function makeDefaultHandler(id) {
	return function (event) {
		event.preventDefault();
		event.stopPropagation();
		debug('click detected on', id);
		(0, _messenger.send)('control-focus', id);
	};
}

},{"../helpers/api":5,"../helpers/icon-buttons":7,"../helpers/jquery":8,"../helpers/messenger":9,"../helpers/window":13,"debug":1}],16:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.getFooterElements = getFooterElements;
function getFooterElements() {
	return [{
		id: 'copyright_text',
		selector: '.site-info-text',
		type: 'copyright_text',
		position: 'top',
		title: _Customizer_DM.translations.footerCredit
	}];
}

},{}],17:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.getHeaderElements = getHeaderElements;

var _jquery = require('../helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var debug = (0, _debug2.default)('cdm:header-focus');
var fallbackSelector = 'header[role="banner"]';
var $ = (0, _jquery2.default)();

function getHeaderElements() {
	return [getHeaderElement()];
}

function getHeaderElement() {
	var selector = getHeaderSelector();
	var position = selector === fallbackSelector ? 'top-right' : null;
	return { id: 'header_image', selector: selector, type: 'header', icon: 'headerIcon', position: position, title: 'header image' };
}

function getHeaderSelector() {
	var selector = getModifiedSelectors();
	if ($(selector).length > 0) {
		return selector;
	}
	debug('failed to find header image selector in page; using fallback');
	return fallbackSelector;
}

function getModifiedSelectors() {
	return ['.header-image a img', '.header-image img', '.site-branding a img', '.site-header-image img', '.header-image-link img', 'img.header-image', 'img.header-img', 'img.headerimage', 'img.custom-header', '.featured-header-image a img'].map(function (selector) {
		return selector + '[src]:not(\'.site-logo\'):not(\'.wp-post-image\'):not(\'.custom-logo\')';
	}).join();
}

},{"../helpers/jquery":8,"debug":1}],18:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.getMenuElements = getMenuElements;

var _messenger = require('../helpers/messenger');

var _options = require('../helpers/options.js');

var _options2 = _interopRequireDefault(_options);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var opts = (0, _options2.default)();

function getMenuElements() {
	return opts.menus.map(function (menu) {
		return {
			id: menu.id,
			selector: '.' + menu.id + ' li:first-child',
			type: 'menu',
			handler: makeHandler(menu.location),
			title: 'menu'
		};
	});
}

function makeHandler(id) {
	return function () {
		(0, _messenger.send)('focus-menu', id);
	};
}

},{"../helpers/messenger":9,"../helpers/options.js":10}],19:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.getPageBuilderElements = getPageBuilderElements;

var _window = require('../helpers/window');

var _window2 = _interopRequireDefault(_window);

var _jquery = require('../helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

var _messenger = require('../helpers/messenger');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var debug = (0, _debug2.default)('cdm:page-builder-focus');
var $ = (0, _jquery2.default)();

function getPageBuilderElements() {
	var selector = '.site-main';
	var $el = $(selector);
	if (!$el.length) {
		debug('found no page builder for selector ' + selector);
		return [];
	}
	if (!_Customizer_DM.beaver_builder) {

		return [];
	}
	return $.makeArray($el).reduce(function (posts, post) {
		var url = getPageBuilderLink();
		return posts.concat({
			id: post.id,
			selector: selector,
			type: 'page_builder',
			position: 'top',
			handler: makeHandler(post.id, url),
			title: 'page_builder',
			icon: 'pageBuilderIcon'
		});
	}, []);
}

function getPageBuilderLink() {
	var url = _Customizer_DM.page_builder_link;
	if (!url) {
		debug('invalid edit link URL for page builder');
	}
	return url;
}

function makeHandler(id, url) {
	return function (event) {
		event.preventDefault();
		event.stopPropagation();
		debug('click detected on page builder');
		(0, _window2.default)().open(url);
		(0, _messenger.send)('recordEvent', {
			name: 'wpcom_customize_direct_manipulation_click',
			props: { type: 'page-builder-icon' }
		});
	};
}

},{"../helpers/jquery":8,"../helpers/messenger":9,"../helpers/window":13,"debug":1}],20:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, "__esModule", {
	value: true
});
exports.getWidgetElements = getWidgetElements;

var _api = require('../helpers/api');

var _api2 = _interopRequireDefault(_api);

var _messenger = require('../helpers/messenger');

var _jquery = require('../helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _debug = require('debug');

var _debug2 = _interopRequireDefault(_debug);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var debug = (0, _debug2.default)('cdm:widgets');
var api = (0, _api2.default)();
var $ = (0, _jquery2.default)();

function getWidgetElements() {
	return getWidgetSelectors().map(getWidgetsForSelector).reduce(function (widgets, id) {
		return widgets.concat(id);
	}, []) // flatten the arrays
	.map(function (id) {
		return {
			id: id,
			selector: getWidgetSelectorForId(id),
			type: 'widget',
			handler: makeHandlerForId(id),
			title: 'widget'
		};
	});
}

function getWidgetSelectors() {
	return api.WidgetCustomizerPreview.widgetSelectors;
}

function getWidgetsForSelector(selector) {
	var $el = $(selector);
	if (!$el.length) {
		debug('found no widgets for selector', selector);
		return [];
	}
	debug('found widgets for selector', selector, $el);
	return $.makeArray($el.map(function (i, w) {
		return w.id;
	}));
}

function getWidgetSelectorForId(id) {
	return '#' + id;
}

function makeHandlerForId(id) {
	return function (event) {
		event.preventDefault();
		event.stopPropagation();
		debug('click detected on', id);
		(0, _messenger.send)('focus-widget-control', id);
	};
}

},{"../helpers/api":5,"../helpers/jquery":8,"../helpers/messenger":9,"debug":1}],21:[function(require,module,exports){
'use strict';

var _window = require('./helpers/window');

var _window2 = _interopRequireDefault(_window);

var _api = require('./helpers/api');

var _api2 = _interopRequireDefault(_api);

var _jquery = require('./helpers/jquery');

var _jquery2 = _interopRequireDefault(_jquery);

var _options = require('./helpers/options');

var _options2 = _interopRequireDefault(_options);

var _userAgent = require('./helpers/user-agent');

var _focusable = require('./modules/focusable');

var _focusable2 = _interopRequireDefault(_focusable);

var _editPostLinks = require('./modules/edit-post-links');

var _headerFocus = require('./modules/header-focus');

var _widgetFocus = require('./modules/widget-focus');

var _menuFocus = require('./modules/menu-focus');

var _pageBuilderFocus = require('./modules/page-builder-focus');

var _footerFocus = require('./modules/footer-focus');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var options = (0, _options2.default)();
var api = (0, _api2.default)();
var $ = (0, _jquery2.default)();

function startDirectManipulation() {

	var basicElements = _Customizer_DM.is_wp_four_seven ? [] : [{ id: 'blogname', selector: '.site-title a, #site-title a', type: 'siteTitle', position: 'middle', title: 'site title' }];

	var widgets = _Customizer_DM.is_wp_four_seven ? [] : (0, _widgetFocus.getWidgetElements)();
	var headers = options.headerImageSupport ? (0, _headerFocus.getHeaderElements)() : [];

	var menus = (0, _menuFocus.getMenuElements)();
	var footers = (0, _footerFocus.getFooterElements)();
	var pb_elements = (0, _pageBuilderFocus.getPageBuilderElements)();

	(0, _focusable2.default)(basicElements.concat(headers, widgets, menus, footers, pb_elements));

	if (-1 === options.disabledModules.indexOf('edit-post-links')) {
		if ((0, _userAgent.isSafari)() && !(0, _userAgent.isMobileSafari)()) {
			(0, _editPostLinks.disableEditPostLinks)('.post-edit-link, [href^="https://wordpress.com/post"], [href^="https://wordpress.com/page"]');
		} else {
			(0, _editPostLinks.modifyEditPostLinks)('.post-edit-link, [href^="https://wordpress.com/post"], [href^="https://wordpress.com/page"]');
		}
	}
}

api.bind('preview-ready', function () {
	// the widget customizer doesn't run until document.ready, so let's run later
	$((0, _window2.default)().document).ready(function () {
		return setTimeout(startDirectManipulation, 100);
	});
});

},{"./helpers/api":5,"./helpers/jquery":8,"./helpers/options":10,"./helpers/user-agent":12,"./helpers/window":13,"./modules/edit-post-links":14,"./modules/focusable":15,"./modules/footer-focus":16,"./modules/header-focus":17,"./modules/menu-focus":18,"./modules/page-builder-focus":19,"./modules/widget-focus":20}]},{},[21])
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbIm5vZGVfbW9kdWxlcy9icm93c2VyLXBhY2svX3ByZWx1ZGUuanMiLCJub2RlX21vZHVsZXMvZGVidWcvc3JjL2Jyb3dzZXIuanMiLCJub2RlX21vZHVsZXMvZGVidWcvc3JjL2RlYnVnLmpzIiwibm9kZV9tb2R1bGVzL21zL2luZGV4LmpzIiwibm9kZV9tb2R1bGVzL3Byb2Nlc3MvYnJvd3Nlci5qcyIsInNyYy9oZWxwZXJzL2FwaS5qcyIsInNyYy9oZWxwZXJzL2NsaWNrLWhhbmRsZXIuanMiLCJzcmMvaGVscGVycy9pY29uLWJ1dHRvbnMuanMiLCJzcmMvaGVscGVycy9qcXVlcnkuanMiLCJzcmMvaGVscGVycy9tZXNzZW5nZXIuanMiLCJzcmMvaGVscGVycy9vcHRpb25zLmpzIiwic3JjL2hlbHBlcnMvdW5kZXJzY29yZS5qcyIsInNyYy9oZWxwZXJzL3VzZXItYWdlbnQuanMiLCJzcmMvaGVscGVycy93aW5kb3cuanMiLCJzcmMvbW9kdWxlcy9lZGl0LXBvc3QtbGlua3MuanMiLCJzcmMvbW9kdWxlcy9mb2N1c2FibGUuanMiLCJzcmMvbW9kdWxlcy9mb290ZXItZm9jdXMuanMiLCJzcmMvbW9kdWxlcy9oZWFkZXItZm9jdXMuanMiLCJzcmMvbW9kdWxlcy9tZW51LWZvY3VzLmpzIiwic3JjL21vZHVsZXMvcGFnZS1idWlsZGVyLWZvY3VzLmpzIiwic3JjL21vZHVsZXMvd2lkZ2V0LWZvY3VzLmpzIiwic3JjL3ByZXZpZXcuanMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7O0FDQUE7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBOzs7O0FDdExBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7O0FDMU1BO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTs7QUNySkE7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFD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RyxFQURnRSxDQUFqRTs7QUFJQSxLQUFNLFVBQVksZUFBZSxnQkFBakIsR0FBc0MsRUFBdEMsR0FBMkMscUNBQTNEO0FBQ0EsS0FBTSxVQUFZLFFBQVEsa0JBQVYsR0FBaUMscUNBQWpDLEdBQXVELEVBQXZFOztBQUVBLEtBQU0sUUFBUSxpQ0FBZDtBQUNBLEtBQU0sVUFBVSxxQ0FBaEI7QUFDQSxLQUFNLGNBQWMsK0NBQXBCOztBQUVBLDBCQUFlLGNBQWMsTUFBZCxDQUFzQixPQUF0QixFQUErQixPQUEvQixFQUF3QyxLQUF4QyxFQUErQyxPQUEvQyxFQUF3RCxXQUF4RCxDQUFmOztBQUVBLEtBQUssQ0FBQyxDQUFELEtBQU8sUUFBUSxlQUFSLENBQXdCLE9BQXhCLENBQWlDLGlCQUFqQyxDQUFaLEVBQW1FO0FBQ2xFLE1BQUssOEJBQWMsQ0FBRSxnQ0FBckIsRUFBd0M7QUFDdkMsNENBQXNCLDZGQUF0QjtBQUNBLEdBRkQsTUFFTztBQUNOLDJDQUFxQiw2RkFBckI7QUFDQTtBQUNEO0FBQ0Q7O0FBRUQsSUFBSSxJQUFKLENBQVUsZUFBVixFQUEyQixZQUFNO0FBQ2hDO0FBQ0EsR0FBRyx3QkFBWSxRQUFmLEVBQTBCLEtBQTFCLENBQWlDO0FBQUEsU0FBTSxXQUFZLHVCQUFaLEVBQXFDLEdBQXJDLENBQU47QUFBQSxFQUFqQztBQUNBLENBSEQiLCJmaWxlIjoiZ2VuZXJhdGVkLmpzIiwic291cmNlUm9vdCI6IiIsInNvdXJjZXNDb250ZW50IjpbIihmdW5jdGlvbiBlKHQsbixyKXtmdW5jdGlvbiBzKG8sdSl7aWYoIW5bb10pe2lmKCF0W29dKXt2YXIgYT10eXBlb2YgcmVxdWlyZT09XCJmdW5jdGlvblwiJiZyZXF1aXJlO2lmKCF1JiZhKXJldHVybiBhKG8sITApO2lmKGkpcmV0dXJuIGkobywhMCk7dmFyIGY9bmV3IEVycm9yKFwiQ2Fubm90IGZpbmQgbW9kdWxlICdcIitvK1wiJ1wiKTt0aHJvdyBmLmNvZGU9XCJNT0RVTEVfTk9UX0ZPVU5EXCIsZn12YXIgbD1uW29dPXtleHBvcnRzOnt9fTt0W29dWzBdLmNhbGwobC5leHBvcnRzLGZ1bmN0aW9uKGUpe3ZhciBuPXRbb11bMV1bZV07cmV0dXJuIHMobj9uOmUpfSxsLGwuZXhwb3J0cyxlLHQsbixyKX1yZXR1cm4gbltvXS5leHBvcnRzfXZhciBpPXR5cGVvZiByZXF1aXJlPT1cImZ1bmN0aW9uXCImJnJlcXVpcmU7Zm9yKHZhciBvPTA7bzxyLmxlbmd0aDtvKyspcyhyW29dKTtyZXR1cm4gc30pIiwiLyoqXG4gKiBUaGlzIGlzIHRoZSB3ZWIgYnJvd3NlciBpbXBsZW1lbnRhdGlvbiBvZiBgZGVidWcoKWAuXG4gKlxuICogRXhwb3NlIGBkZWJ1ZygpYCBhcyB0aGUgbW9kdWxlLlxuICovXG5cbmV4cG9ydHMgPSBtb2R1bGUuZXhwb3J0cyA9IHJlcXVpcmUoJy4vZGVidWcnKTtcbmV4cG9ydHMubG9nID0gbG9nO1xuZXhwb3J0cy5mb3JtYXRBcmdzID0gZm9ybWF0QXJncztcbmV4cG9ydHMuc2F2ZSA9IHNhdmU7XG5leHBvcnRzLmxvYWQgPSBsb2FkO1xuZXhwb3J0cy51c2VDb2xvcnMgPSB1c2VDb2xvcnM7XG5leHBvcnRzLnN0b3JhZ2UgPSAndW5kZWZpbmVkJyAhPSB0eXBlb2YgY2hyb21lXG4gICAgICAgICAgICAgICAmJiAndW5kZWZpbmVkJyAhPSB0eXBlb2YgY2hyb21lLnN0b3JhZ2VcbiAgICAgICAgICAgICAgICAgID8gY2hyb21lLnN0b3JhZ2UubG9jYWxcbiAgICAgICAgICAgICAgICAgIDogbG9jYWxzdG9yYWdlKCk7XG5cbi8qKlxuICogQ29sb3JzLlxuICovXG5cbmV4cG9ydHMuY29sb3JzID0gW1xuICAnbGlnaHRzZWFncmVlbicsXG4gICdmb3Jlc3RncmVlbicsXG4gICdnb2xkZW5yb2QnLFxuICAnZG9kZ2VyYmx1ZScsXG4gICdkYXJrb3JjaGlkJyxcbiAgJ2NyaW1zb24nXG5dO1xuXG4vKipcbiAqIEN1cnJlbnRseSBvbmx5IFdlYktpdC1iYXNlZCBXZWIgSW5zcGVjdG9ycywgRmlyZWZveCA+PSB2MzEsXG4gKiBhbmQgdGhlIEZpcmVidWcgZXh0ZW5zaW9uIChhbnkgRmlyZWZveCB2ZXJzaW9uKSBhcmUga25vd25cbiAqIHRvIHN1cHBvcnQgXCIlY1wiIENTUyBjdXN0b21pemF0aW9ucy5cbiAqXG4gKiBUT0RPOiBhZGQgYSBgbG9jYWxTdG9yYWdlYCB2YXJpYWJsZSB0byBleHBsaWNpdGx5IGVuYWJsZS9kaXNhYmxlIGNvbG9yc1xuICovXG5cbmZ1bmN0aW9uIHVzZUNvbG9ycygpIHtcbiAgLy8gTkI6IEluIGFuIEVsZWN0cm9uIHByZWxvYWQgc2NyaXB0LCBkb2N1bWVudCB3aWxsIGJlIGRlZmluZWQgYnV0IG5vdCBmdWxseVxuICAvLyBpbml0aWFsaXplZC4gU2luY2Ugd2Uga25vdyB3ZSdyZSBpbiBDaHJvbWUsIHdlJ2xsIGp1c3QgZGV0ZWN0IHRoaXMgY2FzZVxuICAvLyBleHBsaWNpdGx5XG4gIGlmICh0eXBlb2Ygd2luZG93ICE9PSAndW5kZWZpbmVkJyAmJiB3aW5kb3cgJiYgdHlwZW9mIHdpbmRvdy5wcm9jZXNzICE9PSAndW5kZWZpbmVkJyAmJiB3aW5kb3cucHJvY2Vzcy50eXBlID09PSAncmVuZGVyZXInKSB7XG4gICAgcmV0dXJuIHRydWU7XG4gIH1cblxuICAvLyBpcyB3ZWJraXQ/IGh0dHA6Ly9zdGFja292ZXJmbG93LmNvbS9hLzE2NDU5NjA2LzM3Njc3M1xuICAvLyBkb2N1bWVudCBpcyB1bmRlZmluZWQgaW4gcmVhY3QtbmF0aXZlOiBodHRwczovL2dpdGh1Yi5jb20vZmFjZWJvb2svcmVhY3QtbmF0aXZlL3B1bGwvMTYzMlxuICByZXR1cm4gKHR5cGVvZiBkb2N1bWVudCAhPT0gJ3VuZGVmaW5lZCcgJiYgZG9jdW1lbnQgJiYgJ1dlYmtpdEFwcGVhcmFuY2UnIGluIGRvY3VtZW50LmRvY3VtZW50RWxlbWVudC5zdHlsZSkgfHxcbiAgICAvLyBpcyBmaXJlYnVnPyBodHRwOi8vc3RhY2tvdmVyZmxvdy5jb20vYS8zOTgxMjAvMzc2NzczXG4gICAgKHR5cGVvZiB3aW5kb3cgIT09ICd1bmRlZmluZWQnICYmIHdpbmRvdyAmJiB3aW5kb3cuY29uc29sZSAmJiAoY29uc29sZS5maXJlYnVnIHx8IChjb25zb2xlLmV4Y2VwdGlvbiAmJiBjb25zb2xlLnRhYmxlKSkpIHx8XG4gICAgLy8gaXMgZmlyZWZveCA+PSB2MzE/XG4gICAgLy8gaHR0cHM6Ly9kZXZlbG9wZXIubW96aWxsYS5vcmcvZW4tVVMvZG9jcy9Ub29scy9XZWJfQ29uc29sZSNTdHlsaW5nX21lc3NhZ2VzXG4gICAgKHR5cGVvZiBuYXZpZ2F0b3IgIT09ICd1bmRlZmluZWQnICYmIG5hdmlnYXRvciAmJiBuYXZpZ2F0b3IudXNlckFnZW50ICYmIG5hdmlnYXRvci51c2VyQWdlbnQudG9Mb3dlckNhc2UoKS5tYXRjaCgvZmlyZWZveFxcLyhcXGQrKS8pICYmIHBhcnNlSW50KFJlZ0V4cC4kMSwgMTApID49IDMxKSB8fFxuICAgIC8vIGRvdWJsZSBjaGVjayB3ZWJraXQgaW4gdXNlckFnZW50IGp1c3QgaW4gY2FzZSB3ZSBhcmUgaW4gYSB3b3JrZXJcbiAgICAodHlwZW9mIG5hdmlnYXRvciAhPT0gJ3VuZGVmaW5lZCcgJiYgbmF2aWdhdG9yICYmIG5hdmlnYXRvci51c2VyQWdlbnQgJiYgbmF2aWdhdG9yLnVzZXJBZ2VudC50b0xvd2VyQ2FzZSgpLm1hdGNoKC9hcHBsZXdlYmtpdFxcLyhcXGQrKS8pKTtcbn1cblxuLyoqXG4gKiBNYXAgJWogdG8gYEpTT04uc3RyaW5naWZ5KClgLCBzaW5jZSBubyBXZWIgSW5zcGVjdG9ycyBkbyB0aGF0IGJ5IGRlZmF1bHQuXG4gKi9cblxuZXhwb3J0cy5mb3JtYXR0ZXJzLmogPSBmdW5jdGlvbih2KSB7XG4gIHRyeSB7XG4gICAgcmV0dXJuIEpTT04uc3RyaW5naWZ5KHYpO1xuICB9IGNhdGNoIChlcnIpIHtcbiAgICByZXR1cm4gJ1tVbmV4cGVjdGVkSlNPTlBhcnNlRXJyb3JdOiAnICsgZXJyLm1lc3NhZ2U7XG4gIH1cbn07XG5cblxuLyoqXG4gKiBDb2xvcml6ZSBsb2cgYXJndW1lbnRzIGlmIGVuYWJsZWQuXG4gKlxuICogQGFwaSBwdWJsaWNcbiAqL1xuXG5mdW5jdGlvbiBmb3JtYXRBcmdzKGFyZ3MpIHtcbiAgdmFyIHVzZUNvbG9ycyA9IHRoaXMudXNlQ29sb3JzO1xuXG4gIGFyZ3NbMF0gPSAodXNlQ29sb3JzID8gJyVjJyA6ICcnKVxuICAgICsgdGhpcy5uYW1lc3BhY2VcbiAgICArICh1c2VDb2xvcnMgPyAnICVjJyA6ICcgJylcbiAgICArIGFyZ3NbMF1cbiAgICArICh1c2VDb2xvcnMgPyAnJWMgJyA6ICcgJylcbiAgICArICcrJyArIGV4cG9ydHMuaHVtYW5pemUodGhpcy5kaWZmKTtcblxuICBpZiAoIXVzZUNvbG9ycykgcmV0dXJuO1xuXG4gIHZhciBjID0gJ2NvbG9yOiAnICsgdGhpcy5jb2xvcjtcbiAgYXJncy5zcGxpY2UoMSwgMCwgYywgJ2NvbG9yOiBpbmhlcml0JylcblxuICAvLyB0aGUgZmluYWwgXCIlY1wiIGlzIHNvbWV3aGF0IHRyaWNreSwgYmVjYXVzZSB0aGVyZSBjb3VsZCBiZSBvdGhlclxuICAvLyBhcmd1bWVudHMgcGFzc2VkIGVpdGhlciBiZWZvcmUgb3IgYWZ0ZXIgdGhlICVjLCBzbyB3ZSBuZWVkIHRvXG4gIC8vIGZpZ3VyZSBvdXQgdGhlIGNvcnJlY3QgaW5kZXggdG8gaW5zZXJ0IHRoZSBDU1MgaW50b1xuICB2YXIgaW5kZXggPSAwO1xuICB2YXIgbGFzdEMgPSAwO1xuICBhcmdzWzBdLnJlcGxhY2UoLyVbYS16QS1aJV0vZywgZnVuY3Rpb24obWF0Y2gpIHtcbiAgICBpZiAoJyUlJyA9PT0gbWF0Y2gpIHJldHVybjtcbiAgICBpbmRleCsrO1xuICAgIGlmICgnJWMnID09PSBtYXRjaCkge1xuICAgICAgLy8gd2Ugb25seSBhcmUgaW50ZXJlc3RlZCBpbiB0aGUgKmxhc3QqICVjXG4gICAgICAvLyAodGhlIHVzZXIgbWF5IGhhdmUgcHJvdmlkZWQgdGhlaXIgb3duKVxuICAgICAgbGFzdEMgPSBpbmRleDtcbiAgICB9XG4gIH0pO1xuXG4gIGFyZ3Muc3BsaWNlKGxhc3RDLCAwLCBjKTtcbn1cblxuLyoqXG4gKiBJbnZva2VzIGBjb25zb2xlLmxvZygpYCB3aGVuIGF2YWlsYWJsZS5cbiAqIE5vLW9wIHdoZW4gYGNvbnNvbGUubG9nYCBpcyBub3QgYSBcImZ1bmN0aW9uXCIuXG4gKlxuICogQGFwaSBwdWJsaWNcbiAqL1xuXG5mdW5jdGlvbiBsb2coKSB7XG4gIC8vIHRoaXMgaGFja2VyeSBpcyByZXF1aXJlZCBmb3IgSUU4LzksIHdoZXJlXG4gIC8vIHRoZSBgY29uc29sZS5sb2dgIGZ1bmN0aW9uIGRvZXNuJ3QgaGF2ZSAnYXBwbHknXG4gIHJldHVybiAnb2JqZWN0JyA9PT0gdHlwZW9mIGNvbnNvbGVcbiAgICAmJiBjb25zb2xlLmxvZ1xuICAgICYmIEZ1bmN0aW9uLnByb3RvdHlwZS5hcHBseS5jYWxsKGNvbnNvbGUubG9nLCBjb25zb2xlLCBhcmd1bWVudHMpO1xufVxuXG4vKipcbiAqIFNhdmUgYG5hbWVzcGFjZXNgLlxuICpcbiAqIEBwYXJhbSB7U3RyaW5nfSBuYW1lc3BhY2VzXG4gKiBAYXBpIHByaXZhdGVcbiAqL1xuXG5mdW5jdGlvbiBzYXZlKG5hbWVzcGFjZXMpIHtcbiAgdHJ5IHtcbiAgICBpZiAobnVsbCA9PSBuYW1lc3BhY2VzKSB7XG4gICAgICBleHBvcnRzLnN0b3JhZ2UucmVtb3ZlSXRlbSgnZGVidWcnKTtcbiAgICB9IGVsc2Uge1xuICAgICAgZXhwb3J0cy5zdG9yYWdlLmRlYnVnID0gbmFtZXNwYWNlcztcbiAgICB9XG4gIH0gY2F0Y2goZSkge31cbn1cblxuLyoqXG4gKiBMb2FkIGBuYW1lc3BhY2VzYC5cbiAqXG4gKiBAcmV0dXJuIHtTdHJpbmd9IHJldHVybnMgdGhlIHByZXZpb3VzbHkgcGVyc2lzdGVkIGRlYnVnIG1vZGVzXG4gKiBAYXBpIHByaXZhdGVcbiAqL1xuXG5mdW5jdGlvbiBsb2FkKCkge1xuICB0cnkge1xuICAgIHJldHVybiBleHBvcnRzLnN0b3JhZ2UuZGVidWc7XG4gIH0gY2F0Y2goZSkge31cblxuICAvLyBJZiBkZWJ1ZyBpc24ndCBzZXQgaW4gTFMsIGFuZCB3ZSdyZSBpbiBFbGVjdHJvbiwgdHJ5IHRvIGxvYWQgJERFQlVHXG4gIGlmICh0eXBlb2YgcHJvY2VzcyAhPT0gJ3VuZGVmaW5lZCcgJiYgJ2VudicgaW4gcHJvY2Vzcykge1xuICAgIHJldHVybiBwcm9jZXNzLmVudi5ERUJVRztcbiAgfVxufVxuXG4vKipcbiAqIEVuYWJsZSBuYW1lc3BhY2VzIGxpc3RlZCBpbiBgbG9jYWxTdG9yYWdlLmRlYnVnYCBpbml0aWFsbHkuXG4gKi9cblxuZXhwb3J0cy5lbmFibGUobG9hZCgpKTtcblxuLyoqXG4gKiBMb2NhbHN0b3JhZ2UgYXR0ZW1wdHMgdG8gcmV0dXJuIHRoZSBsb2NhbHN0b3JhZ2UuXG4gKlxuICogVGhpcyBpcyBuZWNlc3NhcnkgYmVjYXVzZSBzYWZhcmkgdGhyb3dzXG4gKiB3aGVuIGEgdXNlciBkaXNhYmxlcyBjb29raWVzL2xvY2Fsc3RvcmFnZVxuICogYW5kIHlvdSBhdHRlbXB0IHRvIGFjY2VzcyBpdC5cbiAqXG4gKiBAcmV0dXJuIHtMb2NhbFN0b3JhZ2V9XG4gKiBAYXBpIHByaXZhdGVcbiAqL1xuXG5mdW5jdGlvbiBsb2NhbHN0b3JhZ2UoKSB7XG4gIHRyeSB7XG4gICAgcmV0dXJuIHdpbmRvdy5sb2NhbFN0b3JhZ2U7XG4gIH0gY2F0Y2ggKGUpIHt9XG59XG4iLCJcbi8qKlxuICogVGhpcyBpcyB0aGUgY29tbW9uIGxvZ2ljIGZvciBib3RoIHRoZSBOb2RlLmpzIGFuZCB3ZWIgYnJvd3NlclxuICogaW1wbGVtZW50YXRpb25zIG9mIGBkZWJ1ZygpYC5cbiAqXG4gKiBFeHBvc2UgYGRlYnVnKClgIGFzIHRoZSBtb2R1bGUuXG4gKi9cblxuZXhwb3J0cyA9IG1vZHVsZS5leHBvcnRzID0gY3JlYXRlRGVidWcuZGVidWcgPSBjcmVhdGVEZWJ1Z1snZGVmYXVsdCddID0gY3JlYXRlRGVidWc7XG5leHBvcnRzLmNvZXJjZSA9IGNvZXJjZTtcbmV4cG9ydHMuZGlzYWJsZSA9IGRpc2FibGU7XG5leHBvcnRzLmVuYWJsZSA9IGVuYWJsZTtcbmV4cG9ydHMuZW5hYmxlZCA9IGVuYWJsZWQ7XG5leHBvcnRzLmh1bWFuaXplID0gcmVxdWlyZSgnbXMnKTtcblxuLyoqXG4gKiBUaGUgY3VycmVudGx5IGFjdGl2ZSBkZWJ1ZyBtb2RlIG5hbWVzLCBhbmQgbmFtZXMgdG8gc2tpcC5cbiAqL1xuXG5leHBvcnRzLm5hbWVzID0gW107XG5leHBvcnRzLnNraXBzID0gW107XG5cbi8qKlxuICogTWFwIG9mIHNwZWNpYWwgXCIlblwiIGhhbmRsaW5nIGZ1bmN0aW9ucywgZm9yIHRoZSBkZWJ1ZyBcImZvcm1hdFwiIGFyZ3VtZW50LlxuICpcbiAqIFZhbGlkIGtleSBuYW1lcyBhcmUgYSBzaW5nbGUsIGxvd2VyIG9yIHVwcGVyLWNhc2UgbGV0dGVyLCBpLmUuIFwiblwiIGFuZCBcIk5cIi5cbiAqL1xuXG5leHBvcnRzLmZvcm1hdHRlcnMgPSB7fTtcblxuLyoqXG4gKiBQcmV2aW91cyBsb2cgdGltZXN0YW1wLlxuICovXG5cbnZhciBwcmV2VGltZTtcblxuLyoqXG4gKiBTZWxlY3QgYSBjb2xvci5cbiAqIEBwYXJhbSB7U3RyaW5nfSBuYW1lc3BhY2VcbiAqIEByZXR1cm4ge051bWJlcn1cbiAqIEBhcGkgcHJpdmF0ZVxuICovXG5cbmZ1bmN0aW9uIHNlbGVjdENvbG9yKG5hbWVzcGFjZSkge1xuICB2YXIgaGFzaCA9IDAsIGk7XG5cbiAgZm9yIChpIGluIG5hbWVzcGFjZSkge1xuICAgIGhhc2ggID0gKChoYXNoIDw8IDUpIC0gaGFzaCkgKyBuYW1lc3BhY2UuY2hhckNvZGVBdChpKTtcbiAgICBoYXNoIHw9IDA7IC8vIENvbnZlcnQgdG8gMzJiaXQgaW50ZWdlclxuICB9XG5cbiAgcmV0dXJuIGV4cG9ydHMuY29sb3JzW01hdGguYWJzKGhhc2gpICUgZXhwb3J0cy5jb2xvcnMubGVuZ3RoXTtcbn1cblxuLyoqXG4gKiBDcmVhdGUgYSBkZWJ1Z2dlciB3aXRoIHRoZSBnaXZlbiBgbmFtZXNwYWNlYC5cbiAqXG4gKiBAcGFyYW0ge1N0cmluZ30gbmFtZXNwYWNlXG4gKiBAcmV0dXJuIHtGdW5jdGlvbn1cbiAqIEBhcGkgcHVibGljXG4gKi9cblxuZnVuY3Rpb24gY3JlYXRlRGVidWcobmFtZXNwYWNlKSB7XG5cbiAgZnVuY3Rpb24gZGVidWcoKSB7XG4gICAgLy8gZGlzYWJsZWQ/XG4gICAgaWYgKCFkZWJ1Zy5lbmFibGVkKSByZXR1cm47XG5cbiAgICB2YXIgc2VsZiA9IGRlYnVnO1xuXG4gICAgLy8gc2V0IGBkaWZmYCB0aW1lc3RhbXBcbiAgICB2YXIgY3VyciA9ICtuZXcgRGF0ZSgpO1xuICAgIHZhciBtcyA9IGN1cnIgLSAocHJldlRpbWUgfHwgY3Vycik7XG4gICAgc2VsZi5kaWZmID0gbXM7XG4gICAgc2VsZi5wcmV2ID0gcHJldlRpbWU7XG4gICAgc2VsZi5jdXJyID0gY3VycjtcbiAgICBwcmV2VGltZSA9IGN1cnI7XG5cbiAgICAvLyB0dXJuIHRoZSBgYXJndW1lbnRzYCBpbnRvIGEgcHJvcGVyIEFycmF5XG4gICAgdmFyIGFyZ3MgPSBuZXcgQXJyYXkoYXJndW1lbnRzLmxlbmd0aCk7XG4gICAgZm9yICh2YXIgaSA9IDA7IGkgPCBhcmdzLmxlbmd0aDsgaSsrKSB7XG4gICAgICBhcmdzW2ldID0gYXJndW1lbnRzW2ldO1xuICAgIH1cblxuICAgIGFyZ3NbMF0gPSBleHBvcnRzLmNvZXJjZShhcmdzWzBdKTtcblxuICAgIGlmICgnc3RyaW5nJyAhPT0gdHlwZW9mIGFyZ3NbMF0pIHtcbiAgICAgIC8vIGFueXRoaW5nIGVsc2UgbGV0J3MgaW5zcGVjdCB3aXRoICVPXG4gICAgICBhcmdzLnVuc2hpZnQoJyVPJyk7XG4gICAgfVxuXG4gICAgLy8gYXBwbHkgYW55IGBmb3JtYXR0ZXJzYCB0cmFuc2Zvcm1hdGlvbnNcbiAgICB2YXIgaW5kZXggPSAwO1xuICAgIGFyZ3NbMF0gPSBhcmdzWzBdLnJlcGxhY2UoLyUoW2EtekEtWiVdKS9nLCBmdW5jdGlvbihtYXRjaCwgZm9ybWF0KSB7XG4gICAgICAvLyBpZiB3ZSBlbmNvdW50ZXIgYW4gZXNjYXBlZCAlIHRoZW4gZG9uJ3QgaW5jcmVhc2UgdGhlIGFycmF5IGluZGV4XG4gICAgICBpZiAobWF0Y2ggPT09ICclJScpIHJldHVybiBtYXRjaDtcbiAgICAgIGluZGV4Kys7XG4gICAgICB2YXIgZm9ybWF0dGVyID0gZXhwb3J0cy5mb3JtYXR0ZXJzW2Zvcm1hdF07XG4gICAgICBpZiAoJ2Z1bmN0aW9uJyA9PT0gdHlwZW9mIGZvcm1hdHRlcikge1xuICAgICAgICB2YXIgdmFsID0gYXJnc1tpbmRleF07XG4gICAgICAgIG1hdGNoID0gZm9ybWF0dGVyLmNhbGwoc2VsZiwgdmFsKTtcblxuICAgICAgICAvLyBub3cgd2UgbmVlZCB0byByZW1vdmUgYGFyZ3NbaW5kZXhdYCBzaW5jZSBpdCdzIGlubGluZWQgaW4gdGhlIGBmb3JtYXRgXG4gICAgICAgIGFyZ3Muc3BsaWNlKGluZGV4LCAxKTtcbiAgICAgICAgaW5kZXgtLTtcbiAgICAgIH1cbiAgICAgIHJldHVybiBtYXRjaDtcbiAgICB9KTtcblxuICAgIC8vIGFwcGx5IGVudi1zcGVjaWZpYyBmb3JtYXR0aW5nIChjb2xvcnMsIGV0Yy4pXG4gICAgZXhwb3J0cy5mb3JtYXRBcmdzLmNhbGwoc2VsZiwgYXJncyk7XG5cbiAgICB2YXIgbG9nRm4gPSBkZWJ1Zy5sb2cgfHwgZXhwb3J0cy5sb2cgfHwgY29uc29sZS5sb2cuYmluZChjb25zb2xlKTtcbiAgICBsb2dGbi5hcHBseShzZWxmLCBhcmdzKTtcbiAgfVxuXG4gIGRlYnVnLm5hbWVzcGFjZSA9IG5hbWVzcGFjZTtcbiAgZGVidWcuZW5hYmxlZCA9IGV4cG9ydHMuZW5hYmxlZChuYW1lc3BhY2UpO1xuICBkZWJ1Zy51c2VDb2xvcnMgPSBleHBvcnRzLnVzZUNvbG9ycygpO1xuICBkZWJ1Zy5jb2xvciA9IHNlbGVjdENvbG9yKG5hbWVzcGFjZSk7XG5cbiAgLy8gZW52LXNwZWNpZmljIGluaXRpYWxpemF0aW9uIGxvZ2ljIGZvciBkZWJ1ZyBpbnN0YW5jZXNcbiAgaWYgKCdmdW5jdGlvbicgPT09IHR5cGVvZiBleHBvcnRzLmluaXQpIHtcbiAgICBleHBvcnRzLmluaXQoZGVidWcpO1xuICB9XG5cbiAgcmV0dXJuIGRlYnVnO1xufVxuXG4vKipcbiAqIEVuYWJsZXMgYSBkZWJ1ZyBtb2RlIGJ5IG5hbWVzcGFjZXMuIFRoaXMgY2FuIGluY2x1ZGUgbW9kZXNcbiAqIHNlcGFyYXRlZCBieSBhIGNvbG9uIGFuZCB3aWxkY2FyZHMuXG4gKlxuICogQHBhcmFtIHtTdHJpbmd9IG5hbWVzcGFjZXNcbiAqIEBhcGkgcHVibGljXG4gKi9cblxuZnVuY3Rpb24gZW5hYmxlKG5hbWVzcGFjZXMpIHtcbiAgZXhwb3J0cy5zYXZlKG5hbWVzcGFjZXMpO1xuXG4gIGV4cG9ydHMubmFtZXMgPSBbXTtcbiAgZXhwb3J0cy5za2lwcyA9IFtdO1xuXG4gIHZhciBzcGxpdCA9IChuYW1lc3BhY2VzIHx8ICcnKS5zcGxpdCgvW1xccyxdKy8pO1xuICB2YXIgbGVuID0gc3BsaXQubGVuZ3RoO1xuXG4gIGZvciAodmFyIGkgPSAwOyBpIDwgbGVuOyBpKyspIHtcbiAgICBpZiAoIXNwbGl0W2ldKSBjb250aW51ZTsgLy8gaWdub3JlIGVtcHR5IHN0cmluZ3NcbiAgICBuYW1lc3BhY2VzID0gc3BsaXRbaV0ucmVwbGFjZSgvXFwqL2csICcuKj8nKTtcbiAgICBpZiAobmFtZXNwYWNlc1swXSA9PT0gJy0nKSB7XG4gICAgICBleHBvcnRzLnNraXBzLnB1c2gobmV3IFJlZ0V4cCgnXicgKyBuYW1lc3BhY2VzLnN1YnN0cigxKSArICckJykpO1xuICAgIH0gZWxzZSB7XG4gICAgICBleHBvcnRzLm5hbWVzLnB1c2gobmV3IFJlZ0V4cCgnXicgKyBuYW1lc3BhY2VzICsgJyQnKSk7XG4gICAgfVxuICB9XG59XG5cbi8qKlxuICogRGlzYWJsZSBkZWJ1ZyBvdXRwdXQuXG4gKlxuICogQGFwaSBwdWJsaWNcbiAqL1xuXG5mdW5jdGlvbiBkaXNhYmxlKCkge1xuICBleHBvcnRzLmVuYWJsZSgnJyk7XG59XG5cbi8qKlxuICogUmV0dXJucyB0cnVlIGlmIHRoZSBnaXZlbiBtb2RlIG5hbWUgaXMgZW5hYmxlZCwgZmFsc2Ugb3RoZXJ3aXNlLlxuICpcbiAqIEBwYXJhbSB7U3RyaW5nfSBuYW1lXG4gKiBAcmV0dXJuIHtCb29sZWFufVxuICogQGFwaSBwdWJsaWNcbiAqL1xuXG5mdW5jdGlvbiBlbmFibGVkKG5hbWUpIHtcbiAgdmFyIGksIGxlbjtcbiAgZm9yIChpID0gMCwgbGVuID0gZXhwb3J0cy5za2lwcy5sZW5ndGg7IGkgPCBsZW47IGkrKykge1xuICAgIGlmIChleHBvcnRzLnNraXBzW2ldLnRlc3QobmFtZSkpIHtcbiAgICAgIHJldHVybiBmYWxzZTtcbiAgICB9XG4gIH1cbiAgZm9yIChpID0gMCwgbGVuID0gZXhwb3J0cy5uYW1lcy5sZW5ndGg7IGkgPCBsZW47IGkrKykge1xuICAgIGlmIChleHBvcnRzLm5hbWVzW2ldLnRlc3QobmFtZSkpIHtcbiAgICAgIHJldHVybiB0cnVlO1xuICAgIH1cbiAgfVxuICByZXR1cm4gZmFsc2U7XG59XG5cbi8qKlxuICogQ29lcmNlIGB2YWxgLlxuICpcbiAqIEBwYXJhbSB7TWl4ZWR9IHZhbFxuICogQHJldHVybiB7TWl4ZWR9XG4gKiBAYXBpIHByaXZhdGVcbiAqL1xuXG5mdW5jdGlvbiBjb2VyY2UodmFsKSB7XG4gIGlmICh2YWwgaW5zdGFuY2VvZiBFcnJvcikgcmV0dXJuIHZhbC5zdGFjayB8fCB2YWwubWVzc2FnZTtcbiAgcmV0dXJuIHZhbDtcbn1cbiIsIi8qKlxuICogSGVscGVycy5cbiAqL1xuXG52YXIgcyA9IDEwMDBcbnZhciBtID0gcyAqIDYwXG52YXIgaCA9IG0gKiA2MFxudmFyIGQgPSBoICogMjRcbnZhciB5ID0gZCAqIDM2NS4yNVxuXG4vKipcbiAqIFBhcnNlIG9yIGZvcm1hdCB0aGUgZ2l2ZW4gYHZhbGAuXG4gKlxuICogT3B0aW9uczpcbiAqXG4gKiAgLSBgbG9uZ2AgdmVyYm9zZSBmb3JtYXR0aW5nIFtmYWxzZV1cbiAqXG4gKiBAcGFyYW0ge1N0cmluZ3xOdW1iZXJ9IHZhbFxuICogQHBhcmFtIHtPYmplY3R9IG9wdGlvbnNcbiAqIEB0aHJvd3Mge0Vycm9yfSB0aHJvdyBhbiBlcnJvciBpZiB2YWwgaXMgbm90IGEgbm9uLWVtcHR5IHN0cmluZyBvciBhIG51bWJlclxuICogQHJldHVybiB7U3RyaW5nfE51bWJlcn1cbiAqIEBhcGkgcHVibGljXG4gKi9cblxubW9kdWxlLmV4cG9ydHMgPSBmdW5jdGlvbiAodmFsLCBvcHRpb25zKSB7XG4gIG9wdGlvbnMgPSBvcHRpb25zIHx8IHt9XG4gIHZhciB0eXBlID0gdHlwZW9mIHZhbFxuICBpZiAodHlwZSA9PT0gJ3N0cmluZycgJiYgdmFsLmxlbmd0aCA+IDApIHtcbiAgICByZXR1cm4gcGFyc2UodmFsKVxuICB9IGVsc2UgaWYgKHR5cGUgPT09ICdudW1iZXInICYmIGlzTmFOKHZhbCkgPT09IGZhbHNlKSB7XG4gICAgcmV0dXJuIG9wdGlvbnMubG9uZyA/XG5cdFx0XHRmbXRMb25nKHZhbCkgOlxuXHRcdFx0Zm10U2hvcnQodmFsKVxuICB9XG4gIHRocm93IG5ldyBFcnJvcigndmFsIGlzIG5vdCBhIG5vbi1lbXB0eSBzdHJpbmcgb3IgYSB2YWxpZCBudW1iZXIuIHZhbD0nICsgSlNPTi5zdHJpbmdpZnkodmFsKSlcbn1cblxuLyoqXG4gKiBQYXJzZSB0aGUgZ2l2ZW4gYHN0cmAgYW5kIHJldHVybiBtaWxsaXNlY29uZHMuXG4gKlxuICogQHBhcmFtIHtTdHJpbmd9IHN0clxuICogQHJldHVybiB7TnVtYmVyfVxuICogQGFwaSBwcml2YXRlXG4gKi9cblxuZnVuY3Rpb24gcGFyc2Uoc3RyKSB7XG4gIHN0ciA9IFN0cmluZyhzdHIpXG4gIGlmIChzdHIubGVuZ3RoID4gMTAwMDApIHtcbiAgICByZXR1cm5cbiAgfVxuICB2YXIgbWF0Y2ggPSAvXigoPzpcXGQrKT9cXC4/XFxkKykgKihtaWxsaXNlY29uZHM/fG1zZWNzP3xtc3xzZWNvbmRzP3xzZWNzP3xzfG1pbnV0ZXM/fG1pbnM/fG18aG91cnM/fGhycz98aHxkYXlzP3xkfHllYXJzP3x5cnM/fHkpPyQvaS5leGVjKHN0cilcbiAgaWYgKCFtYXRjaCkge1xuICAgIHJldHVyblxuICB9XG4gIHZhciBuID0gcGFyc2VGbG9hdChtYXRjaFsxXSlcbiAgdmFyIHR5cGUgPSAobWF0Y2hbMl0gfHwgJ21zJykudG9Mb3dlckNhc2UoKVxuICBzd2l0Y2ggKHR5cGUpIHtcbiAgICBjYXNlICd5ZWFycyc6XG4gICAgY2FzZSAneWVhcic6XG4gICAgY2FzZSAneXJzJzpcbiAgICBjYXNlICd5cic6XG4gICAgY2FzZSAneSc6XG4gICAgICByZXR1cm4gbiAqIHlcbiAgICBjYXNlICdkYXlzJzpcbiAgICBjYXNlICdkYXknOlxuICAgIGNhc2UgJ2QnOlxuICAgICAgcmV0dXJuIG4gKiBkXG4gICAgY2FzZSAnaG91cnMnOlxuICAgIGNhc2UgJ2hvdXInOlxuICAgIGNhc2UgJ2hycyc6XG4gICAgY2FzZSAnaHInOlxuICAgIGNhc2UgJ2gnOlxuICAgICAgcmV0dXJuIG4gKiBoXG4gICAgY2FzZSAnbWludXRlcyc6XG4gICAgY2FzZSAnbWludXRlJzpcbiAgICBjYXNlICdtaW5zJzpcbiAgICBjYXNlICdtaW4nOlxuICAgIGNhc2UgJ20nOlxuICAgICAgcmV0dXJuIG4gKiBtXG4gICAgY2FzZSAnc2Vjb25kcyc6XG4gICAgY2FzZSAnc2Vjb25kJzpcbiAgICBjYXNlICdzZWNzJzpcbiAgICBjYXNlICdzZWMnOlxuICAgIGNhc2UgJ3MnOlxuICAgICAgcmV0dXJuIG4gKiBzXG4gICAgY2FzZSAnbWlsbGlzZWNvbmRzJzpcbiAgICBjYXNlICdtaWxsaXNlY29uZCc6XG4gICAgY2FzZSAnbXNlY3MnOlxuICAgIGNhc2UgJ21zZWMnOlxuICAgIGNhc2UgJ21zJzpcbiAgICAgIHJldHVybiBuXG4gICAgZGVmYXVsdDpcbiAgICAgIHJldHVybiB1bmRlZmluZWRcbiAgfVxufVxuXG4vKipcbiAqIFNob3J0IGZvcm1hdCBmb3IgYG1zYC5cbiAqXG4gKiBAcGFyYW0ge051bWJlcn0gbXNcbiAqIEByZXR1cm4ge1N0cmluZ31cbiAqIEBhcGkgcHJpdmF0ZVxuICovXG5cbmZ1bmN0aW9uIGZtdFNob3J0KG1zKSB7XG4gIGlmIChtcyA+PSBkKSB7XG4gICAgcmV0dXJuIE1hdGgucm91bmQobXMgLyBkKSArICdkJ1xuICB9XG4gIGlmIChtcyA+PSBoKSB7XG4gICAgcmV0dXJuIE1hdGgucm91bmQobXMgLyBoKSArICdoJ1xuICB9XG4gIGlmIChtcyA+PSBtKSB7XG4gICAgcmV0dXJuIE1hdGgucm91bmQobXMgLyBtKSArICdtJ1xuICB9XG4gIGlmIChtcyA+PSBzKSB7XG4gICAgcmV0dXJuIE1hdGgucm91bmQobXMgLyBzKSArICdzJ1xuICB9XG4gIHJldHVybiBtcyArICdtcydcbn1cblxuLyoqXG4gKiBMb25nIGZvcm1hdCBmb3IgYG1zYC5cbiAqXG4gKiBAcGFyYW0ge051bWJlcn0gbXNcbiAqIEByZXR1cm4ge1N0cmluZ31cbiAqIEBhcGkgcHJpdmF0ZVxuICovXG5cbmZ1bmN0aW9uIGZtdExvbmcobXMpIHtcbiAgcmV0dXJuIHBsdXJhbChtcywgZCwgJ2RheScpIHx8XG4gICAgcGx1cmFsKG1zLCBoLCAnaG91cicpIHx8XG4gICAgcGx1cmFsKG1zLCBtLCAnbWludXRlJykgfHxcbiAgICBwbHVyYWwobXMsIHMsICdzZWNvbmQnKSB8fFxuICAgIG1zICsgJyBtcydcbn1cblxuLyoqXG4gKiBQbHVyYWxpemF0aW9uIGhlbHBlci5cbiAqL1xuXG5mdW5jdGlvbiBwbHVyYWwobXMsIG4sIG5hbWUpIHtcbiAgaWYgKG1zIDwgbikge1xuICAgIHJldHVyblxuICB9XG4gIGlmIChtcyA8IG4gKiAxLjUpIHtcbiAgICByZXR1cm4gTWF0aC5mbG9vcihtcyAvIG4pICsgJyAnICsgbmFtZVxuICB9XG4gIHJldHVybiBNYXRoLmNlaWwobXMgLyBuKSArICcgJyArIG5hbWUgKyAncydcbn1cbiIsIi8vIHNoaW0gZm9yIHVzaW5nIHByb2Nlc3MgaW4gYnJvd3NlclxudmFyIHByb2Nlc3MgPSBtb2R1bGUuZXhwb3J0cyA9IHt9O1xuXG4vLyBjYWNoZWQgZnJvbSB3aGF0ZXZlciBnbG9iYWwgaXMgcHJlc2VudCBzbyB0aGF0IHRlc3QgcnVubmVycyB0aGF0IHN0dWIgaXRcbi8vIGRvbid0IGJyZWFrIHRoaW5ncy4gIEJ1dCB3ZSBuZWVkIHRvIHdyYXAgaXQgaW4gYSB0cnkgY2F0Y2ggaW4gY2FzZSBpdCBpc1xuLy8gd3JhcHBlZCBpbiBzdHJpY3QgbW9kZSBjb2RlIHdoaWNoIGRvZXNuJ3QgZGVmaW5lIGFueSBnbG9iYWxzLiAgSXQncyBpbnNpZGUgYVxuLy8gZnVuY3Rpb24gYmVjYXVzZSB0cnkvY2F0Y2hlcyBkZW9wdGltaXplIGluIGNlcnRhaW4gZW5naW5lcy5cblxudmFyIGNhY2hlZFNldFRpbWVvdXQ7XG52YXIgY2FjaGVkQ2xlYXJUaW1lb3V0O1xuXG5mdW5jdGlvbiBkZWZhdWx0U2V0VGltb3V0KCkge1xuICAgIHRocm93IG5ldyBFcnJvcignc2V0VGltZW91dCBoYXMgbm90IGJlZW4gZGVmaW5lZCcpO1xufVxuZnVuY3Rpb24gZGVmYXVsdENsZWFyVGltZW91dCAoKSB7XG4gICAgdGhyb3cgbmV3IEVycm9yKCdjbGVhclRpbWVvdXQgaGFzIG5vdCBiZWVuIGRlZmluZWQnKTtcbn1cbihmdW5jdGlvbiAoKSB7XG4gICAgdHJ5IHtcbiAgICAgICAgaWYgKHR5cGVvZiBzZXRUaW1lb3V0ID09PSAnZnVuY3Rpb24nKSB7XG4gICAgICAgICAgICBjYWNoZWRTZXRUaW1lb3V0ID0gc2V0VGltZW91dDtcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIGNhY2hlZFNldFRpbWVvdXQgPSBkZWZhdWx0U2V0VGltb3V0O1xuICAgICAgICB9XG4gICAgfSBjYXRjaCAoZSkge1xuICAgICAgICBjYWNoZWRTZXRUaW1lb3V0ID0gZGVmYXVsdFNldFRpbW91dDtcbiAgICB9XG4gICAgdHJ5IHtcbiAgICAgICAgaWYgKHR5cGVvZiBjbGVhclRpbWVvdXQgPT09ICdmdW5jdGlvbicpIHtcbiAgICAgICAgICAgIGNhY2hlZENsZWFyVGltZW91dCA9IGNsZWFyVGltZW91dDtcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIGNhY2hlZENsZWFyVGltZW91dCA9IGRlZmF1bHRDbGVhclRpbWVvdXQ7XG4gICAgICAgIH1cbiAgICB9IGNhdGNoIChlKSB7XG4gICAgICAgIGNhY2hlZENsZWFyVGltZW91dCA9IGRlZmF1bHRDbGVhclRpbWVvdXQ7XG4gICAgfVxufSAoKSlcbmZ1bmN0aW9uIHJ1blRpbWVvdXQoZnVuKSB7XG4gICAgaWYgKGNhY2hlZFNldFRpbWVvdXQgPT09IHNldFRpbWVvdXQpIHtcbiAgICAgICAgLy9ub3JtYWwgZW52aXJvbWVudHMgaW4gc2FuZSBzaXR1YXRpb25zXG4gICAgICAgIHJldHVybiBzZXRUaW1lb3V0KGZ1biwgMCk7XG4gICAgfVxuICAgIC8vIGlmIHNldFRpbWVvdXQgd2Fzbid0IGF2YWlsYWJsZSBidXQgd2FzIGxhdHRlciBkZWZpbmVkXG4gICAgaWYgKChjYWNoZWRTZXRUaW1lb3V0ID09PSBkZWZhdWx0U2V0VGltb3V0IHx8ICFjYWNoZWRTZXRUaW1lb3V0KSAmJiBzZXRUaW1lb3V0KSB7XG4gICAgICAgIGNhY2hlZFNldFRpbWVvdXQgPSBzZXRUaW1lb3V0O1xuICAgICAgICByZXR1cm4gc2V0VGltZW91dChmdW4sIDApO1xuICAgIH1cbiAgICB0cnkge1xuICAgICAgICAvLyB3aGVuIHdoZW4gc29tZWJvZHkgaGFzIHNjcmV3ZWQgd2l0aCBzZXRUaW1lb3V0IGJ1dCBubyBJLkUuIG1hZGRuZXNzXG4gICAgICAgIHJldHVybiBjYWNoZWRTZXRUaW1lb3V0KGZ1biwgMCk7XG4gICAgfSBjYXRjaChlKXtcbiAgICAgICAgdHJ5IHtcbiAgICAgICAgICAgIC8vIFdoZW4gd2UgYXJlIGluIEkuRS4gYnV0IHRoZSBzY3JpcHQgaGFzIGJlZW4gZXZhbGVkIHNvIEkuRS4gZG9lc24ndCB0cnVzdCB0aGUgZ2xvYmFsIG9iamVjdCB3aGVuIGNhbGxlZCBub3JtYWxseVxuICAgICAgICAgICAgcmV0dXJuIGNhY2hlZFNldFRpbWVvdXQuY2FsbChudWxsLCBmdW4sIDApO1xuICAgICAgICB9IGNhdGNoKGUpe1xuICAgICAgICAgICAgLy8gc2FtZSBhcyBhYm92ZSBidXQgd2hlbiBpdCdzIGEgdmVyc2lvbiBvZiBJLkUuIHRoYXQgbXVzdCBoYXZlIHRoZSBnbG9iYWwgb2JqZWN0IGZvciAndGhpcycsIGhvcGZ1bGx5IG91ciBjb250ZXh0IGNvcnJlY3Qgb3RoZXJ3aXNlIGl0IHdpbGwgdGhyb3cgYSBnbG9iYWwgZXJyb3JcbiAgICAgICAgICAgIHJldHVybiBjYWNoZWRTZXRUaW1lb3V0LmNhbGwodGhpcywgZnVuLCAwKTtcbiAgICAgICAgfVxuICAgIH1cblxuXG59XG5mdW5jdGlvbiBydW5DbGVhclRpbWVvdXQobWFya2VyKSB7XG4gICAgaWYgKGNhY2hlZENsZWFyVGltZW91dCA9PT0gY2xlYXJUaW1lb3V0KSB7XG4gICAgICAgIC8vbm9ybWFsIGVudmlyb21lbnRzIGluIHNhbmUgc2l0dWF0aW9uc1xuICAgICAgICByZXR1cm4gY2xlYXJUaW1lb3V0KG1hcmtlcik7XG4gICAgfVxuICAgIC8vIGlmIGNsZWFyVGltZW91dCB3YXNuJ3QgYXZhaWxhYmxlIGJ1dCB3YXMgbGF0dGVyIGRlZmluZWRcbiAgICBpZiAoKGNhY2hlZENsZWFyVGltZW91dCA9PT0gZGVmYXVsdENsZWFyVGltZW91dCB8fCAhY2FjaGVkQ2xlYXJUaW1lb3V0KSAmJiBjbGVhclRpbWVvdXQpIHtcbiAgICAgICAgY2FjaGVkQ2xlYXJUaW1lb3V0ID0gY2xlYXJUaW1lb3V0O1xuICAgICAgICByZXR1cm4gY2xlYXJUaW1lb3V0KG1hcmtlcik7XG4gICAgfVxuICAgIHRyeSB7XG4gICAgICAgIC8vIHdoZW4gd2hlbiBzb21lYm9keSBoYXMgc2NyZXdlZCB3aXRoIHNldFRpbWVvdXQgYnV0IG5vIEkuRS4gbWFkZG5lc3NcbiAgICAgICAgcmV0dXJuIGNhY2hlZENsZWFyVGltZW91dChtYXJrZXIpO1xuICAgIH0gY2F0Y2ggKGUpe1xuICAgICAgICB0cnkge1xuICAgICAgICAgICAgLy8gV2hlbiB3ZSBhcmUgaW4gSS5FLiBidXQgdGhlIHNjcmlwdCBoYXMgYmVlbiBldmFsZWQgc28gSS5FLiBkb2Vzbid0ICB0cnVzdCB0aGUgZ2xvYmFsIG9iamVjdCB3aGVuIGNhbGxlZCBub3JtYWxseVxuICAgICAgICAgICAgcmV0dXJuIGNhY2hlZENsZWFyVGltZW91dC5jYWxsKG51bGwsIG1hcmtlcik7XG4gICAgICAgIH0gY2F0Y2ggKGUpe1xuICAgICAgICAgICAgLy8gc2FtZSBhcyBhYm92ZSBidXQgd2hlbiBpdCdzIGEgdmVyc2lvbiBvZiBJLkUuIHRoYXQgbXVzdCBoYXZlIHRoZSBnbG9iYWwgb2JqZWN0IGZvciAndGhpcycsIGhvcGZ1bGx5IG91ciBjb250ZXh0IGNvcnJlY3Qgb3RoZXJ3aXNlIGl0IHdpbGwgdGhyb3cgYSBnbG9iYWwgZXJyb3IuXG4gICAgICAgICAgICAvLyBTb21lIHZlcnNpb25zIG9mIEkuRS4gaGF2ZSBkaWZmZXJlbnQgcnVsZXMgZm9yIGNsZWFyVGltZW91dCB2cyBzZXRUaW1lb3V0XG4gICAgICAgICAgICByZXR1cm4gY2FjaGVkQ2xlYXJUaW1lb3V0LmNhbGwodGhpcywgbWFya2VyKTtcbiAgICAgICAgfVxuICAgIH1cblxuXG5cbn1cbnZhciBxdWV1ZSA9IFtdO1xudmFyIGRyYWluaW5nID0gZmFsc2U7XG52YXIgY3VycmVudFF1ZXVlO1xudmFyIHF1ZXVlSW5kZXggPSAtMTtcblxuZnVuY3Rpb24gY2xlYW5VcE5leHRUaWNrKCkge1xuICAgIGlmICghZHJhaW5pbmcgfHwgIWN1cnJlbnRRdWV1ZSkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuICAgIGRyYWluaW5nID0gZmFsc2U7XG4gICAgaWYgKGN1cnJlbnRRdWV1ZS5sZW5ndGgpIHtcbiAgICAgICAgcXVldWUgPSBjdXJyZW50UXVldWUuY29uY2F0KHF1ZXVlKTtcbiAgICB9IGVsc2Uge1xuICAgICAgICBxdWV1ZUluZGV4ID0gLTE7XG4gICAgfVxuICAgIGlmIChxdWV1ZS5sZW5ndGgpIHtcbiAgICAgICAgZHJhaW5RdWV1ZSgpO1xuICAgIH1cbn1cblxuZnVuY3Rpb24gZHJhaW5RdWV1ZSgpIHtcbiAgICBpZiAoZHJhaW5pbmcpIHtcbiAgICAgICAgcmV0dXJuO1xuICAgIH1cbiAgICB2YXIgdGltZW91dCA9IHJ1blRpbWVvdXQoY2xlYW5VcE5leHRUaWNrKTtcbiAgICBkcmFpbmluZyA9IHRydWU7XG5cbiAgICB2YXIgbGVuID0gcXVldWUubGVuZ3RoO1xuICAgIHdoaWxlKGxlbikge1xuICAgICAgICBjdXJyZW50UXVldWUgPSBxdWV1ZTtcbiAgICAgICAgcXVldWUgPSBbXTtcbiAgICAgICAgd2hpbGUgKCsrcXVldWVJbmRleCA8IGxlbikge1xuICAgICAgICAgICAgaWYgKGN1cnJlbnRRdWV1ZSkge1xuICAgICAgICAgICAgICAgIGN1cnJlbnRRdWV1ZVtxdWV1ZUluZGV4XS5ydW4oKTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgfVxuICAgICAgICBxdWV1ZUluZGV4ID0gLTE7XG4gICAgICAgIGxlbiA9IHF1ZXVlLmxlbmd0aDtcbiAgICB9XG4gICAgY3VycmVudFF1ZXVlID0gbnVsbDtcbiAgICBkcmFpbmluZyA9IGZhbHNlO1xuICAgIHJ1bkNsZWFyVGltZW91dCh0aW1lb3V0KTtcbn1cblxucHJvY2Vzcy5uZXh0VGljayA9IGZ1bmN0aW9uIChmdW4pIHtcbiAgICB2YXIgYXJncyA9IG5ldyBBcnJheShhcmd1bWVudHMubGVuZ3RoIC0gMSk7XG4gICAgaWYgKGFyZ3VtZW50cy5sZW5ndGggPiAxKSB7XG4gICAgICAgIGZvciAodmFyIGkgPSAxOyBpIDwgYXJndW1lbnRzLmxlbmd0aDsgaSsrKSB7XG4gICAgICAgICAgICBhcmdzW2kgLSAxXSA9IGFyZ3VtZW50c1tpXTtcbiAgICAgICAgfVxuICAgIH1cbiAgICBxdWV1ZS5wdXNoKG5ldyBJdGVtKGZ1biwgYXJncykpO1xuICAgIGlmIChxdWV1ZS5sZW5ndGggPT09IDEgJiYgIWRyYWluaW5nKSB7XG4gICAgICAgIHJ1blRpbWVvdXQoZHJhaW5RdWV1ZSk7XG4gICAgfVxufTtcblxuLy8gdjggbGlrZXMgcHJlZGljdGlibGUgb2JqZWN0c1xuZnVuY3Rpb24gSXRlbShmdW4sIGFycmF5KSB7XG4gICAgdGhpcy5mdW4gPSBmdW47XG4gICAgdGhpcy5hcnJheSA9IGFycmF5O1xufVxuSXRlbS5wcm90b3R5cGUucnVuID0gZnVuY3Rpb24gKCkge1xuICAgIHRoaXMuZnVuLmFwcGx5KG51bGwsIHRoaXMuYXJyYXkpO1xufTtcbnByb2Nlc3MudGl0bGUgPSAnYnJvd3Nlcic7XG5wcm9jZXNzLmJyb3dzZXIgPSB0cnVlO1xucHJvY2Vzcy5lbnYgPSB7fTtcbnByb2Nlc3MuYXJndiA9IFtdO1xucHJvY2Vzcy52ZXJzaW9uID0gJyc7IC8vIGVtcHR5IHN0cmluZyB0byBhdm9pZCByZWdleHAgaXNzdWVzXG5wcm9jZXNzLnZlcnNpb25zID0ge307XG5cbmZ1bmN0aW9uIG5vb3AoKSB7fVxuXG5wcm9jZXNzLm9uID0gbm9vcDtcbnByb2Nlc3MuYWRkTGlzdGVuZXIgPSBub29wO1xucHJvY2Vzcy5vbmNlID0gbm9vcDtcbnByb2Nlc3Mub2ZmID0gbm9vcDtcbnByb2Nlc3MucmVtb3ZlTGlzdGVuZXIgPSBub29wO1xucHJvY2Vzcy5yZW1vdmVBbGxMaXN0ZW5lcnMgPSBub29wO1xucHJvY2Vzcy5lbWl0ID0gbm9vcDtcblxucHJvY2Vzcy5iaW5kaW5nID0gZnVuY3Rpb24gKG5hbWUpIHtcbiAgICB0aHJvdyBuZXcgRXJyb3IoJ3Byb2Nlc3MuYmluZGluZyBpcyBub3Qgc3VwcG9ydGVkJyk7XG59O1xuXG5wcm9jZXNzLmN3ZCA9IGZ1bmN0aW9uICgpIHsgcmV0dXJuICcvJyB9O1xucHJvY2Vzcy5jaGRpciA9IGZ1bmN0aW9uIChkaXIpIHtcbiAgICB0aHJvdyBuZXcgRXJyb3IoJ3Byb2Nlc3MuY2hkaXIgaXMgbm90IHN1cHBvcnRlZCcpO1xufTtcbnByb2Nlc3MudW1hc2sgPSBmdW5jdGlvbigpIHsgcmV0dXJuIDA7IH07XG4iLCJpbXBvcnQgZ2V0V2luZG93IGZyb20gJy4vd2luZG93JztcblxuZXhwb3J0IGRlZmF1bHQgZnVuY3Rpb24gZ2V0QVBJKCkge1xuXHRpZiAoICEgZ2V0V2luZG93KCkud3AgfHwgISBnZXRXaW5kb3coKS53cC5jdXN0b21pemUgKSB7XG5cdFx0dGhyb3cgbmV3IEVycm9yKCAnTm8gV29yZFByZXNzIGN1c3RvbWl6ZXIgQVBJIGZvdW5kJyApO1xuXHR9XG5cdHJldHVybiBnZXRXaW5kb3coKS53cC5jdXN0b21pemU7XG59XG4iLCJpbXBvcnQgZ2V0SlF1ZXJ5IGZyb20gJy4uL2hlbHBlcnMvanF1ZXJ5JztcbmltcG9ydCBkZWJ1Z0ZhY3RvcnkgZnJvbSAnZGVidWcnO1xuXG5jb25zdCBkZWJ1ZyA9IGRlYnVnRmFjdG9yeSggJ2NkbTpjbGljay1oYW5kbGVyJyApO1xuY29uc3QgJCA9IGdldEpRdWVyeSgpO1xuXG5leHBvcnQgZGVmYXVsdCBmdW5jdGlvbiBhZGRDbGlja0hhbmRsZXIoIGNsaWNrVGFyZ2V0LCBoYW5kbGVyICkge1xuXHRkZWJ1ZyggJ2FkZGluZyBjbGljayBoYW5kbGVyIHRvIHRhcmdldCcsIGNsaWNrVGFyZ2V0ICk7XG5cdHJldHVybiAkKCAnYm9keScgKS5vbiggJ2NsaWNrJywgY2xpY2tUYXJnZXQsIGhhbmRsZXIgKTtcbn1cbiIsImltcG9ydCBnZXRXaW5kb3cgZnJvbSAnLi4vaGVscGVycy93aW5kb3cnO1xuaW1wb3J0IGdldEpRdWVyeSBmcm9tICcuLi9oZWxwZXJzL2pxdWVyeSc7XG5pbXBvcnQgeyBvbiB9IGZyb20gJy4uL2hlbHBlcnMvbWVzc2VuZ2VyJztcbmltcG9ydCBnZXRVbmRlcnNjb3JlIGZyb20gJy4uL2hlbHBlcnMvdW5kZXJzY29yZSc7XG5pbXBvcnQgYWRkQ2xpY2tIYW5kbGVyIGZyb20gJy4uL2hlbHBlcnMvY2xpY2staGFuZGxlcic7XG5pbXBvcnQgZ2V0T3B0aW9ucyBmcm9tICcuLi9oZWxwZXJzL29wdGlvbnMnO1xuaW1wb3J0IGRlYnVnRmFjdG9yeSBmcm9tICdkZWJ1Zyc7XG5cbmNvbnN0IF8gPSBnZXRVbmRlcnNjb3JlKCk7XG5jb25zdCBkZWJ1ZyA9IGRlYnVnRmFjdG9yeSggJ2NkbTppY29uLWJ1dHRvbnMnICk7XG5jb25zdCAkID0gZ2V0SlF1ZXJ5KCk7XG5cbi8vIEljb25zIGZyb206IGh0dHBzOi8vZ2l0aHViLmNvbS9Xb3JkUHJlc3MvZGFzaGljb25zL3RyZWUvbWFzdGVyL3N2Z1xuLy8gRWxlbWVudHMgd2lsbCBkZWZhdWx0IHRvIHVzaW5nIGBlZGl0SWNvbmAgYnV0IGlmIGFuIGVsZW1lbnQgaGFzIHRoZSBgaWNvbmBcbi8vIHByb3BlcnR5IHNldCwgaXQgd2lsbCB1c2UgdGhhdCBhcyB0aGUga2V5IGZvciBvbmUgb2YgdGhlc2UgaWNvbnMgaW5zdGVhZDpcbmNvbnN0IGljb25zID0ge1xuXHRoZWFkZXJJY29uOiAnPHN2ZyB2ZXJzaW9uPVwiMS4xXCIgeG1sbnM9XCJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2Z1wiIHhtbG5zOnhsaW5rPVwiaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGlua1wiIHdpZHRoPVwiMjBcIiBoZWlnaHQ9XCIyMFwiIHZpZXdCb3g9XCIwIDAgMjAgMjBcIj48cGF0aCBkPVwiTTIuMjUgMWgxNS41YzAuNjkgMCAxLjI1IDAuNTYgMS4yNSAxLjI1djE1LjVjMCAwLjY5LTAuNTYgMS4yNS0xLjI1IDEuMjVoLTE1LjVjLTAuNjkgMC0xLjI1LTAuNTYtMS4yNS0xLjI1di0xNS41YzAtMC42OSAwLjU2LTEuMjUgMS4yNS0xLjI1ek0xNyAxN3YtMTRoLTE0djE0aDE0ek0xMCA2YzAtMS4xLTAuOS0yLTItMnMtMiAwLjktMiAyIDAuOSAyIDIgMiAyLTAuOSAyLTJ6TTEzIDExYzAgMCAwLTYgMy02djEwYzAgMC41NS0wLjQ1IDEtMSAxaC0xMGMtMC41NSAwLTEtMC40NS0xLTF2LTdjMiAwIDMgNCAzIDRzMS0zIDMtMyAzIDIgMyAyelwiPjwvcGF0aD48L3N2Zz4nLFxuXHRlZGl0SWNvbjogJzxzdmcgdmVyc2lvbj1cIjEuMVwiIHhtbG5zPVwiaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmdcIiB4bWxuczp4bGluaz1cImh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmtcIiB3aWR0aD1cIjIwXCIgaGVpZ2h0PVwiMjBcIiB2aWV3Qm94PVwiMCAwIDIwIDIwXCI+PHBhdGggZD1cIk0xMy44OSAzLjM5bDIuNzEgMi43MmMwLjQ2IDAuNDYgMC40MiAxLjI0IDAuMDMwIDEuNjRsLTguMDEwIDguMDIwLTUuNTYgMS4xNiAxLjE2LTUuNThzNy42LTcuNjMgNy45OS04LjAzMGMwLjM5LTAuMzkgMS4yMi0wLjM5IDEuNjggMC4wNzB6TTExLjE2IDYuMThsLTUuNTkgNS42MSAxLjExIDEuMTEgNS41NC01LjY1ek04LjE5IDE0LjQxbDUuNTgtNS42LTEuMDcwLTEuMDgwLTUuNTkgNS42elwiPjwvcGF0aD48L3N2Zz4nLFxuXHRwYWdlQnVpbGRlckljb246ICc8c3ZnIHZlcnNpb249XCIxLjFcIiB4bWxucz1cImh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnXCIgeG1sbnM6eGxpbms9XCJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rXCIgd2lkdGg9XCIyMFwiIGhlaWdodD1cIjIwXCIgdmlld0JveD1cIjAgMCAyMCAyMFwiPjxwYXRoIGQ9XCJNMTkgMTZ2LTEzYzAtMC41NS0wLjQ1LTEtMS0xaC0xNWMtMC41NSAwLTEgMC40NS0xIDF2MTNjMCAwLjU1IDAuNDUgMSAxIDFoMTVjMC41NSAwIDEtMC40NSAxLTF6TTQgNGgxM3Y0aC0xM3YtNHpNNSA1djJoM3YtMmgtM3pNOSA1djJoM3YtMmgtM3pNMTMgNXYyaDN2LTJoLTN6TTQuNSAxMGMwLjI4IDAgMC41IDAuMjIgMC41IDAuNXMtMC4yMiAwLjUtMC41IDAuNS0wLjUtMC4yMi0wLjUtMC41IDAuMjItMC41IDAuNS0wLjV6TTYgMTBoNHYxaC00di0xek0xMiAxMGg1djVoLTV2LTV6TTQuNSAxMmMwLjI4IDAgMC41IDAuMjIgMC41IDAuNXMtMC4yMiAwLjUtMC41IDAuNS0wLjUtMC4yMi0wLjUtMC41IDAuMjItMC41IDAuNS0wLjV6TTYgMTJoNHYxaC00di0xek0xMyAxMnYyaDN2LTJoLTN6TTQuNSAxNGMwLjI4IDAgMC41IDAuMjIgMC41IDAuNXMtMC4yMiAwLjUtMC41IDAuNS0wLjUtMC4yMi0wLjUtMC41IDAuMjItMC41IDAuNS0wLjV6TTYgMTRoNHYxaC00di0xelwiPjwvcGF0aD48L3N2Zz4nXG59O1xuXG4vKipcbiAqIENyZWF0ZSAoaWYgbmVjZXNzYXJ5KSBhbmQgcG9zaXRpb24gYW4gaWNvbiBidXR0b24gcmVsYXRpdmUgdG8gaXRzIHRhcmdldC5cbiAqXG4gKiBTZWUgYG1ha2VGb2N1c2FibGVgIGZvciB0aGUgZm9ybWF0IG9mIHRoZSBgZWxlbWVudGAgcGFyYW0uXG4gKlxuICogSWYgcG9zaXRpb25pbmcgdGhlIGljb24gd2FzIHN1Y2Nlc3NmdWwsIHRoaXMgZnVuY3Rpb24gcmV0dXJucyBhIGNvcHkgb2YgdGhlXG4gKiBlbGVtZW50IGl0IHdhcyBwYXNzZWQgd2l0aCB0aGUgYWRkaXRpb25hbCBwYXJhbWV0ZXJzIGAkdGFyZ2V0YCBhbmQgYCRpY29uYFxuICogdGhhdCBhcmUgY2FjaGVkIHJlZmVyZW5jZXMgdG8gdGhlIERPTSBlbGVtZW50cy4gSWYgdGhlIHBvc2l0aW9uaW5nIGZhaWxlZCwgaXRcbiAqIGp1c3QgcmV0dXJucyB0aGUgZWxlbWVudCB1bmNoYW5nZWQuXG4gKlxuICogQHBhcmFtIHtPYmplY3R9IGVsZW1lbnQgLSBUaGUgZGF0YSB0byB1c2Ugd2hlbiBjb25zdHJ1Y3RpbmcgdGhlIGljb24uXG4gKiBAcmV0dXJuIHtPYmplY3R9IFRoZSBlbGVtZW50IHRoYXQgd2FzIHBhc3NlZCwgd2l0aCBhZGRpdGlvbmFsIGRhdGEgaW5jbHVkZWQuXG4gKi9cbmV4cG9ydCBmdW5jdGlvbiBwb3NpdGlvbkljb24oIGVsZW1lbnQgKSB7XG5cdGNvbnN0ICR0YXJnZXQgPSBnZXRFbGVtZW50VGFyZ2V0KCBlbGVtZW50ICk7XG5cdGlmICggISAkdGFyZ2V0Lmxlbmd0aCApIHtcblx0XHRkZWJ1ZyggYENvdWxkIG5vdCBmaW5kIHRhcmdldCBlbGVtZW50IGZvciBpY29uICR7ZWxlbWVudC5pZH0gd2l0aCBzZWxlY3RvciAke2VsZW1lbnQuc2VsZWN0b3J9YCApO1xuXHRcdHJldHVybiBlbGVtZW50O1xuXHR9XG5cdGNvbnN0ICRpY29uID0gZmluZE9yQ3JlYXRlSWNvbiggZWxlbWVudCApO1xuXHRjb25zdCBjc3MgPSBnZXRDYWxjdWxhdGVkQ3NzRm9ySWNvbiggZWxlbWVudCwgJHRhcmdldCwgJGljb24gKTtcblx0ZGVidWcoIGBwb3NpdGlvbmluZyBpY29uIGZvciAke2VsZW1lbnQuaWR9IHdpdGggQ1NTICR7SlNPTi5zdHJpbmdpZnkoIGNzcyApfWAgKTtcblx0JGljb24uY3NzKCBjc3MgKTtcblx0cmV0dXJuIF8uZXh0ZW5kKCB7fSwgZWxlbWVudCwgeyAkdGFyZ2V0LCAkaWNvbiB9ICk7XG59XG5cbmV4cG9ydCBmdW5jdGlvbiBhZGRDbGlja0hhbmRsZXJUb0ljb24oIGVsZW1lbnQgKSB7XG5cdGlmICggISBlbGVtZW50LiRpY29uICkge1xuXHRcdHJldHVybiBlbGVtZW50O1xuXHR9XG5cdGFkZENsaWNrSGFuZGxlciggYC4ke2dldEljb25DbGFzc05hbWUoIGVsZW1lbnQuaWQgKX1gLCBlbGVtZW50LmhhbmRsZXIgKTtcblx0cmV0dXJuIGVsZW1lbnQ7XG59XG5cbmNvbnN0IGljb25SZXBvc2l0aW9uZXIgPSBfLmRlYm91bmNlKCBlbGVtZW50cyA9PiB7XG5cdGRlYnVnKCBgcmVwb3NpdGlvbmluZyAke2VsZW1lbnRzLmxlbmd0aH0gaWNvbnNgICk7XG5cdGVsZW1lbnRzLm1hcCggcG9zaXRpb25JY29uICk7XG59LCAzNTAgKTtcblxuZXhwb3J0IGZ1bmN0aW9uIHJlcG9zaXRpb25JY29ucyggZWxlbWVudHMgKSB7XG5cdGljb25SZXBvc2l0aW9uZXIoIGVsZW1lbnRzICk7XG59XG5cbmV4cG9ydCBmdW5jdGlvbiByZXBvc2l0aW9uQWZ0ZXJGb250c0xvYWQoIGVsZW1lbnRzICkge1xuXHRpY29uUmVwb3NpdGlvbmVyKCBlbGVtZW50cyApO1xuXG5cdGlmICggZ2V0V2luZG93KCkuZG9jdW1lbnQuZm9udHMgKSB7XG5cdFx0Z2V0V2luZG93KCkuZG9jdW1lbnQuZm9udHMucmVhZHkudGhlbiggaWNvblJlcG9zaXRpb25lci5iaW5kKCBudWxsLCBlbGVtZW50cyApICk7XG5cdH1cbn1cblxuLyoqXG4gKiBUb2dnbGUgaWNvbnMgd2hlbiBjdXN0b21pemVyIHRvZ2dsZXMgcHJldmlldyBtb2RlLlxuICovXG5leHBvcnQgZnVuY3Rpb24gZW5hYmxlSWNvblRvZ2dsZSgpIHtcblx0b24oICdjZG0tdG9nZ2xlLXZpc2libGUnLCAoKSA9PiAkKCAnLmNkbS1pY29uJyApLnRvZ2dsZUNsYXNzKCAnY2RtLWljb24tLWhpZGRlbicgKSApO1xufVxuXG5mdW5jdGlvbiBmaW5kT3JDcmVhdGVJY29uKCBlbGVtZW50ICkge1xuXHRpZiAoIGVsZW1lbnQuJGljb24gKSB7XG5cdFx0cmV0dXJuIGVsZW1lbnQuJGljb247XG5cdH1cblx0Y29uc3QgJGljb24gPSAkKCBgLiR7Z2V0SWNvbkNsYXNzTmFtZSggZWxlbWVudC5pZCApfWAgKTtcblx0aWYgKCAkaWNvbi5sZW5ndGggKSB7XG5cdFx0cmV0dXJuICRpY29uO1xuXHR9XG5cblx0Y29uc3QgJHdpZGdldF9sb2NhdGlvbiA9IGdldFdpZGdldExvY2F0aW9uKCBlbGVtZW50LnNlbGVjdG9yICk7XG5cblx0Y29uc3QgdGl0bGUgPSBnZXRPcHRpb25zKCkudHJhbnNsYXRpb25zWyBlbGVtZW50LnR5cGUgXSB8fCBgQ2xpY2sgdG8gZWRpdCB0aGUgJHtlbGVtZW50LnRpdGxlfWA7XG5cblx0cmV0dXJuIGNyZWF0ZUFuZEFwcGVuZEljb24oIGVsZW1lbnQuaWQsIGVsZW1lbnQuaWNvbiwgdGl0bGUsICR3aWRnZXRfbG9jYXRpb24gKTtcbn1cblxuZnVuY3Rpb24gZ2V0V2lkZ2V0TG9jYXRpb24oIHNlbGVjdG9yICkge1xuXG5cdC8vIFNpdGUgaW5mbyB3cmFwcGVyIChiZWxvdyBmb290ZXIpXG5cdGlmICggJCggc2VsZWN0b3IgKS5wYXJlbnRzKCAnLnNpdGUtdGl0bGUtd3JhcHBlcicgKS5sZW5ndGggfHwgJCggc2VsZWN0b3IgKS5wYXJlbnRzKCAnLnNpdGUtdGl0bGUnICkubGVuZ3RoICkge1xuXG5cdFx0cmV0dXJuICdzaXRlLXRpdGxlLXdpZGdldCc7XG5cblx0fVxuXG5cdC8vIEhlcm9cblx0aWYgKCAkKCBzZWxlY3RvciApLmhhc0NsYXNzKCAnaGVybycgKSApIHtcblxuXHRcdHJldHVybiAnaGVyby13aWRnZXQnO1xuXG5cdH1cblxuXHQvLyBQYWdlIEJ1aWxkZXIgKGJlbG93IGZvb3Rlcilcblx0aWYgKCBfQ3VzdG9taXplcl9ETS5iZWF2ZXJfYnVpbGRlciApIHtcblxuXHRcdHJldHVybiAncGFnZS1idWlsZGVyLXdpZGdldCc7XG5cblx0fVxuXG5cdC8vIEZvb3RlciBXaWRnZXRcblx0aWYgKCAkKCBzZWxlY3RvciApLnBhcmVudHMoICcuZm9vdGVyLXdpZGdldCcgKS5sZW5ndGggKSB7XG5cblx0XHRyZXR1cm4gJ2Zvb3Rlci13aWRnZXQnO1xuXG5cdH1cblxuXHQvLyBTaXRlIGluZm8gd3JhcHBlciAoYmVsb3cgZm9vdGVyKVxuXHRpZiAoICQoIHNlbGVjdG9yICkucGFyZW50cyggJy5zaXRlLWluZm8td3JhcHBlcicgKS5sZW5ndGggKSB7XG5cblx0XHRyZXR1cm4gJ3NpdGUtaW5mby13cmFwcGVyLXdpZGdldCc7XG5cblx0fVxuXG5cdHJldHVybiAnZGVmYXVsdCc7XG5cbn1cblxuZnVuY3Rpb24gZ2V0SWNvbkNsYXNzTmFtZSggaWQgKSB7XG5cdHJldHVybiBgY2RtLWljb25fXyR7aWR9YDtcbn1cblxuZnVuY3Rpb24gZ2V0Q2FsY3VsYXRlZENzc0Zvckljb24oIGVsZW1lbnQsICR0YXJnZXQsICRpY29uICkge1xuXHRjb25zdCBwb3NpdGlvbiA9IGVsZW1lbnQucG9zaXRpb247XG5cdGNvbnN0IGhpZGRlbkljb25Qb3MgPSAoICdydGwnID09PSBnZXRXaW5kb3coKS5kb2N1bWVudC5kaXIgKSA/IHsgcmlnaHQ6IC0xMDAwLCBsZWZ0OiAnYXV0bycgfSA6IHsgbGVmdDogLTEwMDAsIHJpZ2h0OiAnYXV0bycgfTtcblxuXHRpZiAoICEgJHRhcmdldC5pcyggJzp2aXNpYmxlJyApICkge1xuXHRcdGRlYnVnKCBgdGFyZ2V0IGlzIG5vdCB2aXNpYmxlIHdoZW4gcG9zaXRpb25pbmcgJHtlbGVtZW50LmlkfS4gSSB3aWxsIGhpZGUgdGhlIGljb24uIHRhcmdldDpgLCAkdGFyZ2V0ICk7XG5cdFx0cmV0dXJuIGhpZGRlbkljb25Qb3M7XG5cdH1cblx0Y29uc3Qgb2Zmc2V0ID0gJHRhcmdldC5vZmZzZXQoKTtcblx0bGV0IHRvcCA9IG9mZnNldC50b3A7XG5cdGNvbnN0IGxlZnQgPSBvZmZzZXQubGVmdDtcblx0bGV0IG1pZGRsZSA9ICR0YXJnZXQuaW5uZXJIZWlnaHQoKSAvIDI7XG5cdGxldCBpY29uTWlkZGxlID0gJGljb24uaW5uZXJIZWlnaHQoKSAvIDI7XG5cdGlmICggdG9wIDwgMCApIHtcblx0XHRkZWJ1ZyggYHRhcmdldCB0b3Agb2Zmc2V0ICR7dG9wfSBpcyB1bnVzdWFsbHkgbG93IHdoZW4gcG9zaXRpb25pbmcgJHtlbGVtZW50LmlkfS4gSSB3aWxsIGhpZGUgdGhlIGljb24uIHRhcmdldDpgLCAkdGFyZ2V0ICk7XG5cdFx0cmV0dXJuIGhpZGRlbkljb25Qb3M7XG5cdH1cblx0aWYgKCBtaWRkbGUgPCAwICkge1xuXHRcdGRlYnVnKCBgdGFyZ2V0IG1pZGRsZSBvZmZzZXQgJHttaWRkbGV9IGlzIHVudXN1YWxseSBsb3cgd2hlbiBwb3NpdGlvbmluZyAke2VsZW1lbnQuaWR9LiBJIHdpbGwgaGlkZSB0aGUgaWNvbi4gdGFyZ2V0OmAsICR0YXJnZXQgKTtcblx0XHRyZXR1cm4gaGlkZGVuSWNvblBvcztcblx0fVxuXHRpZiAoIHRvcCA8IDEgKSB7XG5cdFx0ZGVidWcoIGB0YXJnZXQgdG9wIG9mZnNldCAke3RvcH0gaXMgdW51c3VhbGx5IGxvdyB3aGVuIHBvc2l0aW9uaW5nICR7ZWxlbWVudC5pZH0uIEkgd2lsbCBhZGp1c3QgdGhlIGljb24gZG93bndhcmRzLiB0YXJnZXQ6YCwgJHRhcmdldCApO1xuXHRcdHRvcCA9IDA7XG5cdH1cblx0aWYgKCBtaWRkbGUgPCAxICkge1xuXHRcdGRlYnVnKCBgdGFyZ2V0IG1pZGRsZSBvZmZzZXQgJHttaWRkbGV9IGlzIHVudXN1YWxseSBsb3cgd2hlbiBwb3NpdGlvbmluZyAke2VsZW1lbnQuaWR9LiBJIHdpbGwgYWRqdXN0IHRoZSBpY29uIGRvd253YXJkcy4gdGFyZ2V0OmAsICR0YXJnZXQgKTtcblx0XHRtaWRkbGUgPSAwO1xuXHRcdGljb25NaWRkbGUgPSAwO1xuXHR9XG5cdGlmICggcG9zaXRpb24gPT09ICdtaWRkbGUnICkge1xuXHRcdHJldHVybiBhZGp1c3RDb29yZGluYXRlcyggeyB0b3A6IHRvcCArIG1pZGRsZSAtIGljb25NaWRkbGUsIGxlZnQsIHJpZ2h0OiAnYXV0bycgfSApO1xuXHR9IGVsc2UgaWYgKCBwb3NpdGlvbiA9PT0gJ3RvcC1yaWdodCcgKSB7XG5cdFx0cmV0dXJuIGFkanVzdENvb3JkaW5hdGVzKCB7IHRvcCwgbGVmdDogbGVmdCArICR0YXJnZXQud2lkdGgoKSArIDcwLCByaWdodDogJ2F1dG8nIH0gKTtcblx0fVxuXHRyZXR1cm4gYWRqdXN0Q29vcmRpbmF0ZXMoIHsgdG9wLCBsZWZ0LCByaWdodDogJ2F1dG8nIH0gKTtcbn1cblxuZnVuY3Rpb24gYWRqdXN0Q29vcmRpbmF0ZXMoIGNvb3JkcyApIHtcblx0Y29uc3QgbWluV2lkdGggPSAzNTtcblx0Ly8gVHJ5IHRvIGF2b2lkIG92ZXJsYXBwaW5nIGhhbWJ1cmdlciBtZW51c1xuXHRjb25zdCBtYXhXaWR0aCA9IGdldFdpbmRvdygpLmlubmVyV2lkdGggLSAxMTA7XG5cdGlmICggY29vcmRzLmxlZnQgPCBtaW5XaWR0aCApIHtcblx0XHRjb29yZHMubGVmdCA9IG1pbldpZHRoO1xuXHR9XG5cdGlmICggY29vcmRzLmxlZnQgPj0gbWF4V2lkdGggKSB7XG5cdFx0Y29vcmRzLmxlZnQgPSBtYXhXaWR0aDtcblx0fVxuXHRyZXR1cm4gY29vcmRzO1xufVxuXG5mdW5jdGlvbiBjcmVhdGVJY29uKCBpZCwgaWNvblR5cGUsIHRpdGxlLCB3aWRnZXRfbG9jYXRpb24gKSB7XG5cdGNvbnN0IGljb25DbGFzc05hbWUgPSBnZXRJY29uQ2xhc3NOYW1lKCBpZCApO1xuXHRjb25zdCBzY2hlbWUgPSBnZXRPcHRpb25zKCkuaWNvbl9jb2xvcjtcblx0Y29uc3QgdGhlbWUgPSBnZXRPcHRpb25zKCkudGhlbWU7XG5cblx0c3dpdGNoICggaWNvblR5cGUgKSB7XG5cdFx0Y2FzZSAnaGVhZGVySWNvbic6XG5cdFx0XHRyZXR1cm4gJCggYDxkaXYgY2xhc3M9XCJjZG0taWNvbiBjZG0taWNvbi0taGVhZGVyLWltYWdlICR7aWNvbkNsYXNzTmFtZX0gJHtzY2hlbWV9ICR7dGhlbWV9ICR7d2lkZ2V0X2xvY2F0aW9ufVwiIHRpdGxlPVwiJHt0aXRsZX1cIj4ke2ljb25zLmhlYWRlckljb259PC9kaXY+YCApO1xuXHRcdGNhc2UgJ3BhZ2VCdWlsZGVySWNvbic6XG5cdFx0XHRyZXR1cm4gJCggYDxkaXYgY2xhc3M9XCJjZG0taWNvbiBjZG0taWNvbi0tcGFnZS1idWlsZGVyICR7aWNvbkNsYXNzTmFtZX0gJHtzY2hlbWV9ICR7dGhlbWV9ICR7d2lkZ2V0X2xvY2F0aW9ufVwiIHRpdGxlPVwiJHt0aXRsZX1cIj4ke2ljb25zLnBhZ2VCdWlsZGVySWNvbn08L2Rpdj5gICk7XG5cdFx0ZGVmYXVsdDpcblx0XHRcdHJldHVybiAkKCBgPGRpdiBjbGFzcz1cImNkbS1pY29uIGNkbS1pY29uLS10ZXh0ICR7aWNvbkNsYXNzTmFtZX0gJHtzY2hlbWV9ICR7dGhlbWV9ICR7d2lkZ2V0X2xvY2F0aW9ufVwiIHRpdGxlPVwiJHt0aXRsZX1cIj4ke2ljb25zLmVkaXRJY29ufTwvZGl2PmAgKTtcblx0fVxufVxuXG5mdW5jdGlvbiBjcmVhdGVBbmRBcHBlbmRJY29uKCBpZCwgaWNvblR5cGUsIHRpdGxlLCB3aWRnZXRfbG9jYXRpb24gKSB7XG5cdGNvbnN0ICRpY29uID0gY3JlYXRlSWNvbiggaWQsIGljb25UeXBlLCB0aXRsZSwgd2lkZ2V0X2xvY2F0aW9uICk7XG5cdCQoIGdldFdpbmRvdygpLmRvY3VtZW50LmJvZHkgKS5hcHBlbmQoICRpY29uICk7XG5cdHJldHVybiAkaWNvbjtcbn1cblxuZnVuY3Rpb24gZ2V0RWxlbWVudFRhcmdldCggZWxlbWVudCApIHtcblx0aWYgKCBlbGVtZW50LiR0YXJnZXQgJiYgISBlbGVtZW50LiR0YXJnZXQucGFyZW50KCkubGVuZ3RoICkge1xuXHRcdC8vIHRhcmdldCB3YXMgcmVtb3ZlZCBmcm9tIERPTSwgbGlrZWx5IGJ5IHBhcnRpYWwgcmVmcmVzaFxuXHRcdGVsZW1lbnQuJHRhcmdldCA9IG51bGw7XG5cdH1cblx0cmV0dXJuIGVsZW1lbnQuJHRhcmdldCB8fCAkKCBlbGVtZW50LnNlbGVjdG9yICk7XG59XG4iLCJpbXBvcnQgZ2V0V2luZG93IGZyb20gJy4vd2luZG93JztcblxuZXhwb3J0IGRlZmF1bHQgZnVuY3Rpb24gZ2V0SlF1ZXJ5KCkge1xuXHRyZXR1cm4gZ2V0V2luZG93KCkualF1ZXJ5O1xufVxuIiwiaW1wb3J0IGdldEFQSSBmcm9tICcuL2FwaSc7XG5pbXBvcnQgZGVidWdGYWN0b3J5IGZyb20gJ2RlYnVnJztcblxuY29uc3QgZGVidWcgPSBkZWJ1Z0ZhY3RvcnkoICdjZG06bWVzc2VuZ2VyJyApO1xuY29uc3QgYXBpID0gZ2V0QVBJKCk7XG5cbmZ1bmN0aW9uIGdldFByZXZpZXcoKSB7XG5cdC8vIHdwLWFkbWluIGlzIHByZXZpZXdlciwgZnJvbnRlbmQgaXMgcHJldmlldy4gd2h5PyBubyBpZGVhLlxuXHRyZXR1cm4gdHlwZW9mIGFwaS5wcmV2aWV3ICE9PSAndW5kZWZpbmVkJyA/IGFwaS5wcmV2aWV3IDogYXBpLnByZXZpZXdlcjtcbn1cblxuZXhwb3J0IGZ1bmN0aW9uIHNlbmQoIGlkLCBkYXRhICkge1xuXHRkZWJ1ZyggJ3NlbmQnLCBpZCwgZGF0YSApO1xuXHRyZXR1cm4gZ2V0UHJldmlldygpLnNlbmQoIGlkLCBkYXRhICk7XG59XG5cbmV4cG9ydCBmdW5jdGlvbiBvbiggaWQsIGNhbGxiYWNrICkge1xuXHRkZWJ1ZyggJ29uJywgaWQsIGNhbGxiYWNrICk7XG5cdHJldHVybiBnZXRQcmV2aWV3KCkuYmluZCggaWQsIGNhbGxiYWNrICk7XG59XG5cbmV4cG9ydCBmdW5jdGlvbiBvZmYoIGlkLCBjYWxsYmFjayA9IGZhbHNlICkge1xuXHRkZWJ1ZyggJ29mZicsIGlkLCBjYWxsYmFjayApO1xuXHRpZiAoIGNhbGxiYWNrICkge1xuXHRcdHJldHVybiBnZXRQcmV2aWV3KCkudW5iaW5kKCBpZCwgY2FsbGJhY2sgKTtcblx0fVxuXHQvLyBubyBjYWxsYmFjaz8gR2V0IHJpZCBvZiBhbGwgb2YgJ2VtXG5cdGNvbnN0IHRvcGljID0gZ2V0UHJldmlldygpLnRvcGljc1sgaWQgXTtcblx0aWYgKCB0b3BpYyApIHtcblx0XHRyZXR1cm4gdG9waWMuZW1wdHkoKTtcblx0fVxufVxuIiwiaW1wb3J0IGdldFdpbmRvdyBmcm9tICcuL3dpbmRvdyc7XG5cbmV4cG9ydCBkZWZhdWx0IGZ1bmN0aW9uIGdldE9wdGlvbnMoKSB7XG5cdHJldHVybiBnZXRXaW5kb3coKS5fQ3VzdG9taXplcl9ETTtcbn1cbiIsImltcG9ydCBnZXRXaW5kb3cgZnJvbSAnLi93aW5kb3cnO1xuXG5leHBvcnQgZGVmYXVsdCBmdW5jdGlvbiBnZXRVbmRlcnNjb3JlKCkge1xuXHRyZXR1cm4gZ2V0V2luZG93KCkuXztcbn1cbiIsImltcG9ydCBnZXRXaW5kb3cgZnJvbSAnLi4vaGVscGVycy93aW5kb3cnO1xuXG5leHBvcnQgZnVuY3Rpb24gZ2V0VXNlckFnZW50KCkge1xuXHRyZXR1cm4gZ2V0V2luZG93KCkubmF2aWdhdG9yLnVzZXJBZ2VudDtcbn1cblxuZXhwb3J0IGZ1bmN0aW9uIGlzU2FmYXJpKCkge1xuXHRyZXR1cm4gKCAhISBnZXRVc2VyQWdlbnQoKS5tYXRjaCggL1ZlcnNpb25cXC9bXFxkXFwuXSsuKlNhZmFyaS8gKSApO1xufVxuXG5leHBvcnQgZnVuY3Rpb24gaXNNb2JpbGVTYWZhcmkoKSB7XG5cdHJldHVybiAoICEhIGdldFVzZXJBZ2VudCgpLm1hdGNoKCAvKGlQb2R8aVBob25lfGlQYWQpLyApICk7XG59XG4iLCJsZXQgd2luZG93T2JqID0gbnVsbDtcblxuZXhwb3J0IGZ1bmN0aW9uIHNldFdpbmRvdyggb2JqICkge1xuXHR3aW5kb3dPYmogPSBvYmo7XG59XG5cbmV4cG9ydCBkZWZhdWx0IGZ1bmN0aW9uIGdldFdpbmRvdygpIHtcblx0aWYgKCAhIHdpbmRvd09iaiAmJiAhIHdpbmRvdyApIHtcblx0XHR0aHJvdyBuZXcgRXJyb3IoICdObyB3aW5kb3cgb2JqZWN0IGZvdW5kLicgKTtcblx0fVxuXHRyZXR1cm4gd2luZG93T2JqIHx8IHdpbmRvdztcbn1cbiIsImltcG9ydCBkZWJ1Z0ZhY3RvcnkgZnJvbSAnZGVidWcnO1xuaW1wb3J0IGdldFdpbmRvdyBmcm9tICcuLi9oZWxwZXJzL3dpbmRvdyc7XG5pbXBvcnQgZ2V0SlF1ZXJ5IGZyb20gJy4uL2hlbHBlcnMvanF1ZXJ5JztcbmltcG9ydCB7IHNlbmQgfSBmcm9tICcuLi9oZWxwZXJzL21lc3Nlbmdlcic7XG5cbmNvbnN0ICQgPSBnZXRKUXVlcnkoKTtcbmNvbnN0IGRlYnVnID0gZGVidWdGYWN0b3J5KCAnY2RtOmVkaXQtcG9zdC1saW5rcycgKTtcblxuZXhwb3J0IGZ1bmN0aW9uIG1vZGlmeUVkaXRQb3N0TGlua3MoIHNlbGVjdG9yICkge1xuXHRkZWJ1ZyggJ2xpc3RlbmluZyBmb3IgY2xpY2tzIG9uIHBvc3QgZWRpdCBsaW5rcyB3aXRoIHNlbGVjdG9yJywgc2VsZWN0b3IgKTtcblx0Ly8gV2UgdXNlIG1vdXNlZG93biBiZWNhdXNlIGNsaWNrIGhhcyBiZWVuIGJsb2NrZWQgYnkgc29tZSBvdGhlciBKU1xuXHQkKCAnYm9keScgKS5vbiggJ21vdXNlZG93bicsIHNlbGVjdG9yLCBldmVudCA9PiB7XG5cdFx0Z2V0V2luZG93KCkub3BlbiggZXZlbnQudGFyZ2V0LmhyZWYgKTtcblx0XHRzZW5kKCAncmVjb3JkRXZlbnQnLCB7XG5cdFx0XHRuYW1lOiAnd3Bjb21fY3VzdG9taXplX2RpcmVjdF9tYW5pcHVsYXRpb25fY2xpY2snLFxuXHRcdFx0cHJvcHM6IHsgdHlwZTogJ3Bvc3QtZWRpdCcgfVxuXHRcdH0gKTtcblx0fSApO1xufVxuXG5leHBvcnQgZnVuY3Rpb24gZGlzYWJsZUVkaXRQb3N0TGlua3MoIHNlbGVjdG9yICkge1xuXHRkZWJ1ZyggJ2hpZGluZyBwb3N0IGVkaXQgbGlua3Mgd2l0aCBzZWxlY3RvcicsIHNlbGVjdG9yICk7XG5cdCQoIHNlbGVjdG9yICkuaGlkZSgpO1xufVxuIiwiaW1wb3J0IGdldFdpbmRvdyBmcm9tICcuLi9oZWxwZXJzL3dpbmRvdyc7XG5pbXBvcnQgZ2V0QVBJIGZyb20gJy4uL2hlbHBlcnMvYXBpJztcbmltcG9ydCBnZXRKUXVlcnkgZnJvbSAnLi4vaGVscGVycy9qcXVlcnknO1xuaW1wb3J0IHsgc2VuZCB9IGZyb20gJy4uL2hlbHBlcnMvbWVzc2VuZ2VyJztcbmltcG9ydCB7IHBvc2l0aW9uSWNvbiwgYWRkQ2xpY2tIYW5kbGVyVG9JY29uLCByZXBvc2l0aW9uQWZ0ZXJGb250c0xvYWQsIGVuYWJsZUljb25Ub2dnbGUgfSBmcm9tICcuLi9oZWxwZXJzL2ljb24tYnV0dG9ucyc7XG5pbXBvcnQgZGVidWdGYWN0b3J5IGZyb20gJ2RlYnVnJztcblxuY29uc3QgZGVidWcgPSBkZWJ1Z0ZhY3RvcnkoICdjZG06Zm9jdXNhYmxlJyApO1xuY29uc3QgYXBpID0gZ2V0QVBJKCk7XG5jb25zdCAkID0gZ2V0SlF1ZXJ5KCk7XG5cbi8qKlxuICogR2l2ZSBET00gZWxlbWVudHMgYW4gaWNvbiBidXR0b24gYm91bmQgdG8gY2xpY2sgaGFuZGxlcnNcbiAqXG4gKiBBY2NlcHRzIGFuIGFycmF5IG9mIGVsZW1lbnQgb2JqZWN0cyBvZiB0aGUgZm9ybTpcbiAqXG4gKiB7XG4gKiBcdGlkOiBBIHN0cmluZyB0byBpZGVudGlmeSB0aGlzIGVsZW1lbnRcbiAqIFx0c2VsZWN0b3I6IEEgQ1NTIHNlbGVjdG9yIHN0cmluZyB0byB1bmlxdWVseSB0YXJnZXQgdGhlIERPTSBlbGVtZW50XG4gKiBcdHR5cGU6IEEgc3RyaW5nIHRvIGdyb3VwIHRoZSBlbGVtZW50LCBlZzogJ3dpZGdldCdcbiAqIFx0cG9zaXRpb246IChvcHRpb25hbCkgQSBzdHJpbmcgZm9yIHBvc2l0aW9uaW5nIHRoZSBpY29uLCBvbmUgb2YgJ3RvcC1sZWZ0JyAoZGVmYXVsdCksICd0b3AtcmlnaHQnLCBvciAnbWlkZGxlJyAodmVydGljYWxseSBjZW50ZXIpXG4gKiBcdGljb24gKG9wdGlvbmFsKTogQSBzdHJpbmcgc3BlY2lmeWluZyB3aGljaCBpY29uIHRvIHVzZS4gU2VlIG9wdGlvbnMgaW4gaWNvbi1idXR0b25zLmpzXG4gKiBcdGhhbmRsZXIgKG9wdGlvbmFsKTogQSBjYWxsYmFjayBmdW5jdGlvbiB3aGljaCB3aWxsIGJlIGNhbGxlZCB3aGVuIHRoZSBpY29uIGlzIGNsaWNrZWRcbiAqIH1cbiAqXG4gKiBJZiBubyBoYW5kbGVyIGlzIHNwZWNpZmllZCwgdGhlIGRlZmF1bHQgd2lsbCBiZSB1c2VkLCB3aGljaCB3aWxsIHNlbmRcbiAqIGBjb250cm9sLWZvY3VzYCB0byB0aGUgQVBJIHdpdGggdGhlIGVsZW1lbnQgSUQuXG4gKlxuICogQHBhcmFtIHtBcnJheX0gZWxlbWVudHMgLSBBbiBhcnJheSBvZiBlbGVtZW50IG9iamVjdHMgb2YgdGhlIGZvcm0gYWJvdmUuXG4gKi9cbmV4cG9ydCBkZWZhdWx0IGZ1bmN0aW9uIG1ha2VGb2N1c2FibGUoIGVsZW1lbnRzICkge1xuXHRjb25zdCBlbGVtZW50c1dpdGhJY29ucyA9IGVsZW1lbnRzXG5cdC5yZWR1Y2UoIHJlbW92ZUR1cGxpY2F0ZVJlZHVjZXIsIFtdIClcblx0Lm1hcCggcG9zaXRpb25JY29uIClcblx0Lm1hcCggY3JlYXRlSGFuZGxlciApXG5cdC5tYXAoIGFkZENsaWNrSGFuZGxlclRvSWNvbiApO1xuXG5cdGlmICggZWxlbWVudHNXaXRoSWNvbnMubGVuZ3RoICkge1xuXHRcdHN0YXJ0SWNvbk1vbml0b3IoIGVsZW1lbnRzV2l0aEljb25zICk7XG5cdFx0ZW5hYmxlSWNvblRvZ2dsZSgpO1xuXHR9XG59XG5cbmZ1bmN0aW9uIG1ha2VSZXBvc2l0aW9uZXIoIGVsZW1lbnRzLCBjaGFuZ2VUeXBlICkge1xuXHRyZXR1cm4gZnVuY3Rpb24oKSB7XG5cdFx0ZGVidWcoICdkZXRlY3RlZCBjaGFuZ2U6JywgY2hhbmdlVHlwZSApO1xuXHRcdHJlcG9zaXRpb25BZnRlckZvbnRzTG9hZCggZWxlbWVudHMgKTtcblx0fTtcbn1cblxuLyoqXG4gKiBSZWdpc3RlciBhIGdyb3VwIG9mIGxpc3RlbmVycyB0byByZXBvc2l0aW9uIGljb24gYnV0dG9ucyBpZiB0aGUgRE9NIGNoYW5nZXMuXG4gKlxuICogU2VlIGBtYWtlRm9jdXNhYmxlYCBmb3IgdGhlIGZvcm1hdCBvZiB0aGUgYGVsZW1lbnRzYCBwYXJhbS5cbiAqXG4gKiBAcGFyYW0ge0FycmF5fSBlbGVtZW50cyAtIFRoZSBlbGVtZW50IG9iamVjdHMuXG4gKi9cbmZ1bmN0aW9uIHN0YXJ0SWNvbk1vbml0b3IoIGVsZW1lbnRzICkge1xuXHQvLyBSZXBvc2l0aW9uIGljb25zIGFmdGVyIGFueSB0aGVtZSBmb250cyBsb2FkXG5cdHJlcG9zaXRpb25BZnRlckZvbnRzTG9hZCggZWxlbWVudHMgKTtcblxuXHQvLyBSZXBvc2l0aW9uIGljb25zIGFmdGVyIGEgZmV3IHNlY29uZHMganVzdCBpbiBjYXNlIChlZzogaW5maW5pdGUgc2Nyb2xsIG9yIG90aGVyIHNjcmlwdHMgY29tcGxldGUpXG5cdHNldFRpbWVvdXQoIG1ha2VSZXBvc2l0aW9uZXIoIGVsZW1lbnRzLCAnZm9sbG93LXVwJyApLCAyMDAwICk7XG5cblx0Ly8gUmVwb3NpdGlvbiBpY29ucyBhZnRlciB0aGUgd2luZG93IGlzIHJlc2l6ZWRcblx0JCggZ2V0V2luZG93KCkgKS5yZXNpemUoIG1ha2VSZXBvc2l0aW9uZXIoIGVsZW1lbnRzLCAncmVzaXplJyApICk7XG5cblx0Ly8gUmVwb3NpdGlvbiBpY29ucyBhZnRlciB0aGUgdGV4dCBvZiBhbnkgZWxlbWVudCBjaGFuZ2VzXG5cdGVsZW1lbnRzLmZpbHRlciggZWwgPT4gWyAnc2l0ZVRpdGxlJywgJ2hlYWRlckljb24nIF0uaW5kZXhPZiggZWwudHlwZSApICE9PSAtMSApXG5cdC5tYXAoIGVsID0+IGFwaSggZWwuaWQsIHZhbHVlID0+IHZhbHVlLmJpbmQoIG1ha2VSZXBvc2l0aW9uZXIoIGVsZW1lbnRzLCAndGl0bGUgb3IgaGVhZGVyJyApICkgKSApO1xuXG5cdC8vIFdoZW4gdGhlIHdpZGdldCBwYXJ0aWFsIHJlZnJlc2ggcnVucywgcmVwb3NpdGlvbiBpY29uc1xuXHRhcGkuYmluZCggJ3dpZGdldC11cGRhdGVkJywgbWFrZVJlcG9zaXRpb25lciggZWxlbWVudHMsICd3aWRnZXRzJyApICk7XG5cblx0Ly8gUmVwb3NpdGlvbiBpY29ucyBhZnRlciBhbnkgY3VzdG9taXplciBzZXR0aW5nIGlzIGNoYW5nZWRcblx0YXBpLmJpbmQoICdjaGFuZ2UnLCBtYWtlUmVwb3NpdGlvbmVyKCBlbGVtZW50cywgJ2FueSBzZXR0aW5nJyApICk7XG5cblx0Y29uc3QgJGRvY3VtZW50ID0gJCggZ2V0V2luZG93KCkuZG9jdW1lbnQgKTtcblxuXHQvLyBSZXBvc2l0aW9uIGFmdGVyIG1lbnVzIHVwZGF0ZWRcblx0JGRvY3VtZW50Lm9uKCAnY3VzdG9taXplLXByZXZpZXctbWVudS1yZWZyZXNoZWQnLCBtYWtlUmVwb3NpdGlvbmVyKCBlbGVtZW50cywgJ21lbnVzJyApICk7XG5cblx0Ly8gUmVwb3NpdGlvbiBhZnRlciBzY3JvbGxpbmcgaW4gY2FzZSB0aGVyZSBhcmUgZml4ZWQgcG9zaXRpb24gZWxlbWVudHNcblx0JGRvY3VtZW50Lm9uKCAnc2Nyb2xsJywgbWFrZVJlcG9zaXRpb25lciggZWxlbWVudHMsICdzY3JvbGwnICkgKTtcblxuXHQvLyBSZXBvc2l0aW9uIGFmdGVyIHBhZ2UgY2xpY2sgKGVnOiBoYW1idXJnZXIgbWVudXMpXG5cdCRkb2N1bWVudC5vbiggJ2NsaWNrJywgbWFrZVJlcG9zaXRpb25lciggZWxlbWVudHMsICdjbGljaycgKSApO1xuXG5cdC8vIFJlcG9zaXRpb24gYWZ0ZXIgYW55IHBhZ2UgY2hhbmdlcyAoaWYgdGhlIGJyb3dzZXIgc3VwcG9ydHMgaXQpXG5cdGNvbnN0IHBhZ2UgPSBnZXRXaW5kb3coKS5kb2N1bWVudC5xdWVyeVNlbGVjdG9yKCAnI3BhZ2UnICk7XG5cdGlmICggcGFnZSAmJiBNdXRhdGlvbk9ic2VydmVyICkge1xuXHRcdGNvbnN0IG9ic2VydmVyID0gbmV3IE11dGF0aW9uT2JzZXJ2ZXIoIG1ha2VSZXBvc2l0aW9uZXIoIGVsZW1lbnRzLCAnRE9NIG11dGF0aW9uJyApICk7XG5cdFx0b2JzZXJ2ZXIub2JzZXJ2ZSggcGFnZSwgeyBhdHRyaWJ1dGVzOiB0cnVlLCBjaGlsZExpc3Q6IHRydWUsIGNoYXJhY3RlckRhdGE6IHRydWUgfSApO1xuXHR9XG59XG5cbmZ1bmN0aW9uIGNyZWF0ZUhhbmRsZXIoIGVsZW1lbnQgKSB7XG5cdGVsZW1lbnQuaGFuZGxlciA9IGVsZW1lbnQuaGFuZGxlciB8fCBtYWtlRGVmYXVsdEhhbmRsZXIoIGVsZW1lbnQuaWQgKTtcblx0cmV0dXJuIGVsZW1lbnQ7XG59XG5cbmZ1bmN0aW9uIHJlbW92ZUR1cGxpY2F0ZVJlZHVjZXIoIHByZXYsIGVsICkge1xuXHRpZiAoIHByZXYubWFwKCB4ID0+IHguaWQgKS5pbmRleE9mKCBlbC5pZCApICE9PSAtMSApIHtcblx0XHRkZWJ1ZyggYHRyaWVkIHRvIGFkZCBkdXBsaWNhdGUgZWxlbWVudCBmb3IgJHtlbC5pZH1gICk7XG5cdFx0cmV0dXJuIHByZXY7XG5cdH1cblx0cmV0dXJuIHByZXYuY29uY2F0KCBlbCApO1xufVxuXG5mdW5jdGlvbiBtYWtlRGVmYXVsdEhhbmRsZXIoIGlkICkge1xuXHRyZXR1cm4gZnVuY3Rpb24oIGV2ZW50ICkge1xuXHRcdGV2ZW50LnByZXZlbnREZWZhdWx0KCk7XG5cdFx0ZXZlbnQuc3RvcFByb3BhZ2F0aW9uKCk7XG5cdFx0ZGVidWcoICdjbGljayBkZXRlY3RlZCBvbicsIGlkICk7XG5cdFx0c2VuZCggJ2NvbnRyb2wtZm9jdXMnLCBpZCApO1xuXHR9O1xufVxuIiwiZXhwb3J0IGZ1bmN0aW9uIGdldEZvb3RlckVsZW1lbnRzKCkge1xuXHRyZXR1cm4gW1xuXHRcdHtcblx0XHRcdGlkOiAnY29weXJpZ2h0X3RleHQnLFxuXHRcdFx0c2VsZWN0b3I6ICcuc2l0ZS1pbmZvLXRleHQnLFxuXHRcdFx0dHlwZTogJ2NvcHlyaWdodF90ZXh0Jyxcblx0XHRcdHBvc2l0aW9uOiAndG9wJyxcblx0XHRcdHRpdGxlOiBfQ3VzdG9taXplcl9ETS50cmFuc2xhdGlvbnMuZm9vdGVyQ3JlZGl0LFxuXHRcdH1cblx0XTtcbn1cbiIsImltcG9ydCBnZXRKUXVlcnkgZnJvbSAnLi4vaGVscGVycy9qcXVlcnknO1xuaW1wb3J0IGRlYnVnRmFjdG9yeSBmcm9tICdkZWJ1Zyc7XG5cbmNvbnN0IGRlYnVnID0gZGVidWdGYWN0b3J5KCAnY2RtOmhlYWRlci1mb2N1cycgKTtcbmNvbnN0IGZhbGxiYWNrU2VsZWN0b3IgPSAnaGVhZGVyW3JvbGU9XCJiYW5uZXJcIl0nO1xuY29uc3QgJCA9IGdldEpRdWVyeSgpO1xuXG5leHBvcnQgZnVuY3Rpb24gZ2V0SGVhZGVyRWxlbWVudHMoKSB7XG5cdHJldHVybiBbIGdldEhlYWRlckVsZW1lbnQoKSBdO1xufVxuXG5mdW5jdGlvbiBnZXRIZWFkZXJFbGVtZW50KCkge1xuXHRjb25zdCBzZWxlY3RvciA9IGdldEhlYWRlclNlbGVjdG9yKCk7XG5cdGNvbnN0IHBvc2l0aW9uID0gKCBzZWxlY3RvciA9PT0gZmFsbGJhY2tTZWxlY3RvciApID8gJ3RvcC1yaWdodCcgOiBudWxsO1xuXHRyZXR1cm4geyBpZDogJ2hlYWRlcl9pbWFnZScsIHNlbGVjdG9yLCB0eXBlOiAnaGVhZGVyJywgaWNvbjogJ2hlYWRlckljb24nLCBwb3NpdGlvbiwgdGl0bGU6ICdoZWFkZXIgaW1hZ2UnLCB9O1xufVxuXG5mdW5jdGlvbiBnZXRIZWFkZXJTZWxlY3RvcigpIHtcblx0Y29uc3Qgc2VsZWN0b3IgPSBnZXRNb2RpZmllZFNlbGVjdG9ycygpO1xuXHRpZiAoICQoIHNlbGVjdG9yICkubGVuZ3RoID4gMCApIHtcblx0XHRyZXR1cm4gc2VsZWN0b3I7XG5cdH1cblx0ZGVidWcoICdmYWlsZWQgdG8gZmluZCBoZWFkZXIgaW1hZ2Ugc2VsZWN0b3IgaW4gcGFnZTsgdXNpbmcgZmFsbGJhY2snICk7XG5cdHJldHVybiBmYWxsYmFja1NlbGVjdG9yO1xufVxuXG5mdW5jdGlvbiBnZXRNb2RpZmllZFNlbGVjdG9ycygpIHtcblx0cmV0dXJuIFtcblx0XHQnLmhlYWRlci1pbWFnZSBhIGltZycsXG5cdFx0Jy5oZWFkZXItaW1hZ2UgaW1nJyxcblx0XHQnLnNpdGUtYnJhbmRpbmcgYSBpbWcnLFxuXHRcdCcuc2l0ZS1oZWFkZXItaW1hZ2UgaW1nJyxcblx0XHQnLmhlYWRlci1pbWFnZS1saW5rIGltZycsXG5cdFx0J2ltZy5oZWFkZXItaW1hZ2UnLFxuXHRcdCdpbWcuaGVhZGVyLWltZycsXG5cdFx0J2ltZy5oZWFkZXJpbWFnZScsXG5cdFx0J2ltZy5jdXN0b20taGVhZGVyJyxcblx0XHQnLmZlYXR1cmVkLWhlYWRlci1pbWFnZSBhIGltZydcblx0XS5tYXAoIHNlbGVjdG9yID0+IHNlbGVjdG9yICsgJ1tzcmNdOm5vdChcXCcuc2l0ZS1sb2dvXFwnKTpub3QoXFwnLndwLXBvc3QtaW1hZ2VcXCcpOm5vdChcXCcuY3VzdG9tLWxvZ29cXCcpJyApLmpvaW4oKTtcbn1cbiIsImltcG9ydCB7IHNlbmQgfSBmcm9tICcuLi9oZWxwZXJzL21lc3Nlbmdlcic7XG5pbXBvcnQgZ2V0T3B0aW9ucyBmcm9tICcuLi9oZWxwZXJzL29wdGlvbnMuanMnO1xuXG5jb25zdCBvcHRzID0gZ2V0T3B0aW9ucygpO1xuXG5leHBvcnQgZnVuY3Rpb24gZ2V0TWVudUVsZW1lbnRzKCkge1xuXHRyZXR1cm4gb3B0cy5tZW51cy5tYXAoIG1lbnUgPT4ge1xuXHRcdHJldHVybiB7XG5cdFx0XHRpZDogbWVudS5pZCxcblx0XHRcdHNlbGVjdG9yOiBgLiR7bWVudS5pZH0gbGk6Zmlyc3QtY2hpbGRgLFxuXHRcdFx0dHlwZTogJ21lbnUnLFxuXHRcdFx0aGFuZGxlcjogbWFrZUhhbmRsZXIoIG1lbnUubG9jYXRpb24gKSxcblx0XHRcdHRpdGxlOiAnbWVudScsXG5cdFx0fTtcblx0fSApO1xufVxuXG5mdW5jdGlvbiBtYWtlSGFuZGxlciggaWQgKSB7XG5cdHJldHVybiBmdW5jdGlvbigpIHtcblx0XHRzZW5kKCAnZm9jdXMtbWVudScsIGlkICk7XG5cdH07XG59XG4iLCJpbXBvcnQgZ2V0V2luZG93IGZyb20gJy4uL2hlbHBlcnMvd2luZG93JztcbmltcG9ydCBnZXRKUXVlcnkgZnJvbSAnLi4vaGVscGVycy9qcXVlcnknO1xuaW1wb3J0IGRlYnVnRmFjdG9yeSBmcm9tICdkZWJ1Zyc7XG5pbXBvcnQgeyBzZW5kIH0gZnJvbSAnLi4vaGVscGVycy9tZXNzZW5nZXInO1xuXG5jb25zdCBkZWJ1ZyA9IGRlYnVnRmFjdG9yeSggJ2NkbTpwYWdlLWJ1aWxkZXItZm9jdXMnICk7XG5jb25zdCAkID0gZ2V0SlF1ZXJ5KCk7XG5cbmV4cG9ydCBmdW5jdGlvbiBnZXRQYWdlQnVpbGRlckVsZW1lbnRzKCkge1xuXHRjb25zdCBzZWxlY3RvciA9ICcuc2l0ZS1tYWluJztcblx0Y29uc3QgJGVsID0gJCggc2VsZWN0b3IgKTtcblx0aWYgKCAhICRlbC5sZW5ndGggKSB7XG5cdFx0ZGVidWcoIGBmb3VuZCBubyBwYWdlIGJ1aWxkZXIgZm9yIHNlbGVjdG9yICR7c2VsZWN0b3J9YCApO1xuXHRcdHJldHVybiBbXTtcblx0fVxuXHRpZiAoICEgX0N1c3RvbWl6ZXJfRE0uYmVhdmVyX2J1aWxkZXIgKSB7XG5cblx0XHRyZXR1cm4gW107XG5cblx0fVxuXHRyZXR1cm4gJC5tYWtlQXJyYXkoICRlbCApXG5cdC5yZWR1Y2UoICggcG9zdHMsIHBvc3QgKSA9PiB7XG5cdFx0Y29uc3QgdXJsID0gZ2V0UGFnZUJ1aWxkZXJMaW5rKCk7XG5cdFx0cmV0dXJuIHBvc3RzLmNvbmNhdCgge1xuXHRcdFx0aWQ6IHBvc3QuaWQsXG5cdFx0XHRzZWxlY3Rvcjogc2VsZWN0b3IsXG5cdFx0XHR0eXBlOiAncGFnZV9idWlsZGVyJyxcblx0XHRcdHBvc2l0aW9uOiAndG9wJyxcblx0XHRcdGhhbmRsZXI6IG1ha2VIYW5kbGVyKCBwb3N0LmlkLCB1cmwgKSxcblx0XHRcdHRpdGxlOiAncGFnZV9idWlsZGVyJyxcblx0XHRcdGljb246ICdwYWdlQnVpbGRlckljb24nLFxuXHRcdH0gKTtcblx0fSwgW10gKTtcbn1cblxuZnVuY3Rpb24gZ2V0UGFnZUJ1aWxkZXJMaW5rKCkge1xuXHRjb25zdCB1cmwgPSBfQ3VzdG9taXplcl9ETS5wYWdlX2J1aWxkZXJfbGluaztcblx0aWYgKCAhIHVybCApIHtcblx0XHRkZWJ1ZyggYGludmFsaWQgZWRpdCBsaW5rIFVSTCBmb3IgcGFnZSBidWlsZGVyYCApO1xuXHR9XG5cdHJldHVybiB1cmw7XG59XG5cbmZ1bmN0aW9uIG1ha2VIYW5kbGVyKCBpZCwgdXJsICkge1xuXHRyZXR1cm4gZnVuY3Rpb24oIGV2ZW50ICkge1xuXHRcdGV2ZW50LnByZXZlbnREZWZhdWx0KCk7XG5cdFx0ZXZlbnQuc3RvcFByb3BhZ2F0aW9uKCk7XG5cdFx0ZGVidWcoIGBjbGljayBkZXRlY3RlZCBvbiBwYWdlIGJ1aWxkZXJgICk7XG5cdFx0Z2V0V2luZG93KCkub3BlbiggdXJsICk7XG5cdFx0c2VuZCggJ3JlY29yZEV2ZW50Jywge1xuXHRcdFx0bmFtZTogJ3dwY29tX2N1c3RvbWl6ZV9kaXJlY3RfbWFuaXB1bGF0aW9uX2NsaWNrJyxcblx0XHRcdHByb3BzOiB7IHR5cGU6ICdwYWdlLWJ1aWxkZXItaWNvbicgfVxuXHRcdH0gKTtcblx0fTtcbn1cbiIsImltcG9ydCBnZXRBUEkgZnJvbSAnLi4vaGVscGVycy9hcGknO1xuaW1wb3J0IHsgc2VuZCB9IGZyb20gJy4uL2hlbHBlcnMvbWVzc2VuZ2VyJztcbmltcG9ydCBnZXRKUXVlcnkgZnJvbSAnLi4vaGVscGVycy9qcXVlcnknO1xuaW1wb3J0IGRlYnVnRmFjdG9yeSBmcm9tICdkZWJ1Zyc7XG5cbmNvbnN0IGRlYnVnID0gZGVidWdGYWN0b3J5KCAnY2RtOndpZGdldHMnICk7XG5jb25zdCBhcGkgPSBnZXRBUEkoKTtcbmNvbnN0ICQgPSBnZXRKUXVlcnkoKTtcblxuZXhwb3J0IGZ1bmN0aW9uIGdldFdpZGdldEVsZW1lbnRzKCkge1xuXHRyZXR1cm4gZ2V0V2lkZ2V0U2VsZWN0b3JzKClcblx0Lm1hcCggZ2V0V2lkZ2V0c0ZvclNlbGVjdG9yIClcblx0LnJlZHVjZSggKCB3aWRnZXRzLCBpZCApID0+IHdpZGdldHMuY29uY2F0KCBpZCApLCBbXSApIC8vIGZsYXR0ZW4gdGhlIGFycmF5c1xuXHQubWFwKCBpZCA9PiAoIHtcblx0XHRpZCxcblx0XHRzZWxlY3RvcjogZ2V0V2lkZ2V0U2VsZWN0b3JGb3JJZCggaWQgKSxcblx0XHR0eXBlOiAnd2lkZ2V0Jyxcblx0XHRoYW5kbGVyOiBtYWtlSGFuZGxlckZvcklkKCBpZCApLFxuXHRcdHRpdGxlOiAnd2lkZ2V0Jyxcblx0fSApICk7XG59XG5cbmZ1bmN0aW9uIGdldFdpZGdldFNlbGVjdG9ycygpIHtcblx0cmV0dXJuIGFwaS5XaWRnZXRDdXN0b21pemVyUHJldmlldy53aWRnZXRTZWxlY3RvcnM7XG59XG5cbmZ1bmN0aW9uIGdldFdpZGdldHNGb3JTZWxlY3Rvciggc2VsZWN0b3IgKSB7XG5cdGNvbnN0ICRlbCA9ICQoIHNlbGVjdG9yICk7XG5cdGlmICggISAkZWwubGVuZ3RoICkge1xuXHRcdGRlYnVnKCAnZm91bmQgbm8gd2lkZ2V0cyBmb3Igc2VsZWN0b3InLCBzZWxlY3RvciApO1xuXHRcdHJldHVybiBbXTtcblx0fVxuXHRkZWJ1ZyggJ2ZvdW5kIHdpZGdldHMgZm9yIHNlbGVjdG9yJywgc2VsZWN0b3IsICRlbCApO1xuXHRyZXR1cm4gJC5tYWtlQXJyYXkoICRlbC5tYXAoICggaSwgdyApID0+IHcuaWQgKSApO1xufVxuXG5mdW5jdGlvbiBnZXRXaWRnZXRTZWxlY3RvckZvcklkKCBpZCApIHtcblx0cmV0dXJuIGAjJHtpZH1gO1xufVxuXG5mdW5jdGlvbiBtYWtlSGFuZGxlckZvcklkKCBpZCApIHtcblx0cmV0dXJuIGZ1bmN0aW9uKCBldmVudCApIHtcblx0XHRldmVudC5wcmV2ZW50RGVmYXVsdCgpO1xuXHRcdGV2ZW50LnN0b3BQcm9wYWdhdGlvbigpO1xuXHRcdGRlYnVnKCAnY2xpY2sgZGV0ZWN0ZWQgb24nLCBpZCApO1xuXHRcdHNlbmQoICdmb2N1cy13aWRnZXQtY29udHJvbCcsIGlkICk7XG5cdH07XG59XG4iLCJpbXBvcnQgZ2V0V2luZG93IGZyb20gJy4vaGVscGVycy93aW5kb3cnO1xuaW1wb3J0IGdldEFQSSBmcm9tICcuL2hlbHBlcnMvYXBpJztcbmltcG9ydCBnZXRKUXVlcnkgZnJvbSAnLi9oZWxwZXJzL2pxdWVyeSc7XG5pbXBvcnQgZ2V0T3B0aW9ucyBmcm9tICcuL2hlbHBlcnMvb3B0aW9ucyc7XG5pbXBvcnQgeyBpc1NhZmFyaSwgaXNNb2JpbGVTYWZhcmkgfSBmcm9tICcuL2hlbHBlcnMvdXNlci1hZ2VudCc7XG5pbXBvcnQgbWFrZUZvY3VzYWJsZSBmcm9tICcuL21vZHVsZXMvZm9jdXNhYmxlJztcbmltcG9ydCB7IG1vZGlmeUVkaXRQb3N0TGlua3MsIGRpc2FibGVFZGl0UG9zdExpbmtzIH0gZnJvbSAnLi9tb2R1bGVzL2VkaXQtcG9zdC1saW5rcyc7XG5pbXBvcnQgeyBnZXRIZWFkZXJFbGVtZW50cyB9IGZyb20gJy4vbW9kdWxlcy9oZWFkZXItZm9jdXMnO1xuaW1wb3J0IHsgZ2V0V2lkZ2V0RWxlbWVudHMgfSBmcm9tICcuL21vZHVsZXMvd2lkZ2V0LWZvY3VzJztcbmltcG9ydCB7IGdldE1lbnVFbGVtZW50cyB9IGZyb20gJy4vbW9kdWxlcy9tZW51LWZvY3VzJztcbmltcG9ydCB7IGdldFBhZ2VCdWlsZGVyRWxlbWVudHMgfSBmcm9tICcuL21vZHVsZXMvcGFnZS1idWlsZGVyLWZvY3VzJztcbmltcG9ydCB7IGdldEZvb3RlckVsZW1lbnRzIH0gZnJvbSAnLi9tb2R1bGVzL2Zvb3Rlci1mb2N1cyc7XG5cbmNvbnN0IG9wdGlvbnMgPSBnZXRPcHRpb25zKCk7XG5jb25zdCBhcGkgPSBnZXRBUEkoKTtcbmNvbnN0ICQgPSBnZXRKUXVlcnkoKTtcblxuZnVuY3Rpb24gc3RhcnREaXJlY3RNYW5pcHVsYXRpb24oKSB7XG5cblx0Y29uc3QgYmFzaWNFbGVtZW50cyA9ICggX0N1c3RvbWl6ZXJfRE0uaXNfd3BfZm91cl9zZXZlbiApID8gW10gOiBbXG5cdFx0eyBpZDogJ2Jsb2duYW1lJywgc2VsZWN0b3I6ICcuc2l0ZS10aXRsZSBhLCAjc2l0ZS10aXRsZSBhJywgdHlwZTogJ3NpdGVUaXRsZScsIHBvc2l0aW9uOiAnbWlkZGxlJywgdGl0bGU6ICdzaXRlIHRpdGxlJyB9LFxuXHRdO1xuXG5cdGNvbnN0IHdpZGdldHMgPSAoIF9DdXN0b21pemVyX0RNLmlzX3dwX2ZvdXJfc2V2ZW4gKSA/IFtdIDogZ2V0V2lkZ2V0RWxlbWVudHMoKTtcblx0Y29uc3QgaGVhZGVycyA9ICggb3B0aW9ucy5oZWFkZXJJbWFnZVN1cHBvcnQgKSA/IGdldEhlYWRlckVsZW1lbnRzKCkgOiBbXTtcblxuXHRjb25zdCBtZW51cyA9IGdldE1lbnVFbGVtZW50cygpO1xuXHRjb25zdCBmb290ZXJzID0gZ2V0Rm9vdGVyRWxlbWVudHMoKTtcblx0Y29uc3QgcGJfZWxlbWVudHMgPSBnZXRQYWdlQnVpbGRlckVsZW1lbnRzKCk7XG5cblx0bWFrZUZvY3VzYWJsZSggYmFzaWNFbGVtZW50cy5jb25jYXQoIGhlYWRlcnMsIHdpZGdldHMsIG1lbnVzLCBmb290ZXJzLCBwYl9lbGVtZW50cyApICk7XG5cblx0aWYgKCAtMSA9PT0gb3B0aW9ucy5kaXNhYmxlZE1vZHVsZXMuaW5kZXhPZiggJ2VkaXQtcG9zdC1saW5rcycgKSApIHtcblx0XHRpZiAoIGlzU2FmYXJpKCkgJiYgISBpc01vYmlsZVNhZmFyaSgpICkge1xuXHRcdFx0ZGlzYWJsZUVkaXRQb3N0TGlua3MoICcucG9zdC1lZGl0LWxpbmssIFtocmVmXj1cImh0dHBzOi8vd29yZHByZXNzLmNvbS9wb3N0XCJdLCBbaHJlZl49XCJodHRwczovL3dvcmRwcmVzcy5jb20vcGFnZVwiXScgKTtcblx0XHR9IGVsc2Uge1xuXHRcdFx0bW9kaWZ5RWRpdFBvc3RMaW5rcyggJy5wb3N0LWVkaXQtbGluaywgW2hyZWZePVwiaHR0cHM6Ly93b3JkcHJlc3MuY29tL3Bvc3RcIl0sIFtocmVmXj1cImh0dHBzOi8vd29yZHByZXNzLmNvbS9wYWdlXCJdJyApO1xuXHRcdH1cblx0fVxufVxuXG5hcGkuYmluZCggJ3ByZXZpZXctcmVhZHknLCAoKSA9PiB7XG5cdC8vIHRoZSB3aWRnZXQgY3VzdG9taXplciBkb2Vzbid0IHJ1biB1bnRpbCBkb2N1bWVudC5yZWFkeSwgc28gbGV0J3MgcnVuIGxhdGVyXG5cdCQoIGdldFdpbmRvdygpLmRvY3VtZW50ICkucmVhZHkoICgpID0+IHNldFRpbWVvdXQoIHN0YXJ0RGlyZWN0TWFuaXB1bGF0aW9uLCAxMDAgKSApO1xufSApO1xuIl19
