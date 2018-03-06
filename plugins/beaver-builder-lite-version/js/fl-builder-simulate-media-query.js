( function( $ ) {

	/**
	 * Helper for simulating media queries without resizing
	 * the viewport.
	 *
	 * Parts based on Respond.js by Scott Jehl (https://github.com/scottjehl/Respond)
	 *
	 * @since 1.10
	 * @class SimulateMediaQuery
	 */
	var SimulateMediaQuery = {

		/**
		 * Strings to look for in stylesheet URLs that are
		 * going to be parsed. If a string matches, that
		 * stylesheet won't be parsed.
		 *
		 * @since 1.10
		 * @property {Array} ignored
		 */
		ignored: [],

		/**
		 * Strings to look for in stylesheet URLs. If a
		 * string matches, that stylesheet will be reparsed
		 * on each updated.
		 *
		 * @since 1.10
		 * @property {Array} reparsed
		 */
		reparsed: [],

		/**
		 * The current viewport width to simulate.
		 *
		 * @since 1.10
		 * @property {Number} width
		 */
		width: null,

		/**
		 * A callback to run when an update completes.
		 *
		 * @since 1.10
		 * @property {Function} callback
		 */
		callback: null,

		/**
		 * Cache of original stylesheets.
		 *
		 * @since 1.10
		 * @property {Object} sheets
		 */
		sheets: {},

		/**
		 * Style tags used for rendering simulated
		 * media query styles.
		 *
		 * @since 1.10
		 * @property {Array} styles
		 */
		styles: [],

		/**
		 * AJAX queue for retrieving rules from a sheet.
		 *
		 * @since 1.10
		 * @property {Array} queue
		 */
		queue: [],

		/**
		 * The value of 1em in pixels.
		 *
		 * @since 1.10
		 * @access private
		 * @property {Number} emPxValue
		 */
		emPxValue: null,

		/**
		 * Regex for parsing styles.
		 *
		 * @since 1.10
		 * @property {Object} _regex
		 */
		regex: {
			media: /@media[^\{]+\{([^\{\}]*\{[^\}\{]*\})+[^\}]+\}/ig,
			keyframes: /@(?:\-(?:o|moz|webkit)\-)?keyframes[^\{]+\{(?:[^\{\}]*\{[^\}\{]*\})+[^\}]*\}/gi,
			comments: /\/\*[^*]*\*+([^/][^*]*\*+)*\//gi,
			urls: /(url\()['"]?([^\/\)'"][^:\)'"]+)['"]?(\))/g,
			findStyles: /@media *([^\{]+)\{([\S\s]+?)\}$/,
			only: /(only\s+)?([a-zA-Z]+)\s?/,
			minw: /\(\s*min\-width\s*:\s*(\s*[0-9\.]+)(px|em)\s*\)/,
			maxw: /\(\s*max\-width\s*:\s*(\s*[0-9\.]+)(px|em)\s*\)/,
			minmaxwh: /\(\s*m(in|ax)\-(height|width)\s*:\s*(\s*[0-9\.]+)(px|em)\s*\)/gi,
			other: /\([^\)]*\)/g
		},

		/**
		 * Adds strings to look for in stylesheet URLs
		 * that are going to be parsed. If a string matches,
		 * that stylesheet won't be parsed.
		 *
		 * @since 1.10
		 * @method ignore
		 * @param {Array} strings
		 */
		ignore: function( strings )
		{
			Array.prototype.push.apply( this.ignored, strings );
		},

		/**
		 * Adds strings to look for in stylesheet URLs. If a
		 * string matches, that stylesheet will be reparsed.
		 *
		 * @since 1.10
		 * @method reparse
		 * @param {Array} strings
		 */
		reparse: function( strings )
		{
			Array.prototype.push.apply( this.reparsed, strings );
		},

		/**
		 * Updates all simulated media query rules.
		 *
		 * @since 1.10
		 * @method update
		 * @param {Number} width The viewport width to simulate.
		 * @param {Function) callback
		 */
		update: function( width, callback )
		{
			this.width    = undefined === width ? null : width;
			this.callback = undefined === callback ? null : callback;

			ForceJQueryValues.update();

			if ( this.queueSheets() ) {
				this.runQueue();
			}
			else {
				this.applyStyles();
			}
		},

		/**
		 * Adds all sheets that aren't already cached
		 * to the AJAX queue.
		 *
		 * @since 1.10
		 * @method queueSheets
		 * @return {Boolean}
		 */
		queueSheets: function()
		{
			var links  = $( 'link' ),
				sheet  = null,
				href   = null,
				media  = null,
				isCSS  = null,
				ignore = false,
				i      = 0,
				k      = 0;

			for ( ; i < links.length; i++ ) {

				sheet  = links[ i ];
				href   = sheet.href;
				media  = sheet.media;
				isCSS  = sheet.rel && sheet.rel.toLowerCase() === 'stylesheet';
				ignore = false;

				if ( !! href && isCSS ) {

					for ( k = 0; k < this.ignored.length; k++ ) {
						if ( href.indexOf( this.ignored[ k ] ) > -1 ) {
							ignore = true;
							break;
						}
					}

					if ( ignore ) {
						continue;
					}

					for ( k = 0; k < this.reparsed.length; k++ ) {
						if ( href.indexOf( this.reparsed[ k ] ) > -1 ) {
							this.sheets[ href ] = null;
							break;
						}
					}

					if ( undefined === this.sheets[ href ] || ! this.sheets[ href ] ) {
						this.queue.push( {
							link  : links.eq( i ),
							href  : href,
							media : media
						} );
					}
				}
			}

			return this.queue.length;
		},

		/**
		 * Send AJAX requests to get styles from all
		 * stylesheets in the queue.
		 *
		 * @since 1.10
		 * @method runQueue
		 */
		runQueue: function()
		{
			var item;

			if ( this.queue.length ) {

				item = this.queue.shift();

				$.get( item.href, $.proxy( function( response ) {
					this.parse( response, item );
					this.runQueue();
				}, this ) );
			}
			else {
				this.applyStyles();
			}
		},

		/**
		 * Parse a stylesheet that has been returned
		 * from an AJAX request.
		 *
		 * @since 1.10
		 * @method parse
		 * @param {String} styles
		 * @param {Array} item
		 */
		parse: function( styles, item )
		{
			var re         = this.regex,
				allQueries = styles.replace( re.comments, '' ).replace( re.keyframes, '' ).match( re.media ),
				length     = allQueries && allQueries.length || 0,
				useMedia   = ! length && item.media,
				query      = null,
				queries    = null,
				media      = null,
				all        = '',
				i          = 0,
				k          = 0;

			if ( allQueries ) {
				all = styles.replace( re.media, '' );
			}
			else if ( useMedia && 'all' != item.media ) {
				length = 1;
			}
			else {
				all = styles;
			}

			this.sheets[ item.href ] = {
				link    : item.link,
				href    : item.href,
				link    : item.link,
				all     : all,
				queries : []
			};

			for ( i = 0; i < length; i++ ) {

				if ( useMedia ) {
					query  = item.media;
					styles = this.convertURLs( styles, item.href );
				}
				else{
					query  = allQueries[ i ].match( re.findStyles ) && RegExp.$1;
					styles = RegExp.$2 && this.convertURLs( RegExp.$2, item.href );
				}

				queries = query.split( ',' );

				for ( k = 0; k < queries.length; k++ ) {

					query = queries[ k ];
					media = query.split( '(' )[ 0 ].match( re.only ) && RegExp.$2;

					if ( 'print' == media ) {
						continue;
					}
					if ( query.replace( re.minmaxwh, '' ).match( re.other ) ) {
						continue;
					}

					this.sheets[ item.href ].queries.push( {
						minw     : query.match( re.minw ) && parseFloat( RegExp.$1 ) + ( RegExp.$2 || '' ),
						maxw     : query.match( re.maxw ) && parseFloat( RegExp.$1 ) + ( RegExp.$2 || '' ),
						styles   : styles
					} );
				}
			}
		},

		/**
		 * Applies simulated media queries to the page.
		 *
		 * @since 1.10
		 * @method applyStyles
		 */
		applyStyles: function()
		{
			var head    = $( 'head' ),
				styles  = null,
				style   = null,
				sheet   = null,
				href    = null,
				query   = null,
				i       = null,
				min     = null,
				max     = null,
				added   = false;

			this.clearStyles();

			for ( href in this.sheets ) {

				styles = '';
				style  = $( '<style></style>' );
				sheet  = this.sheets[ href ];

				if ( ! sheet.queries.length || ! this.width ) {
					continue;
				}

				styles += sheet.all;

				for ( i = 0; i < sheet.queries.length; i++ ) {

					query = sheet.queries[ i ];
					min   = query.minw;
					max   = query.maxw;
					added = false;

					if ( min ) {

						min = parseFloat( min ) * ( min.indexOf( 'em' ) > -1 ? this.getEmPxValue() : 1 );

						if ( this.width >= min ) {
							styles += query.styles;
							added   = true;
						}
					}

					if ( max && ! added ) {

						max = parseFloat( max ) * ( max.indexOf( 'em' ) > -1 ? this.getEmPxValue() : 1 );

						if ( this.width <= max ) {
							styles += query.styles;
						}
					}
				}

				this.styles.push( style );
				head.append( style );
				style.html( styles );
				sheet.link.remove();
			}
		},

		/**
		 * Clears all style tags used to render
		 * simulated queries.
		 *
		 * @since 1.10
		 * @method applyStyles
		 */
		clearStyles: function()
		{
			var head   = $( 'head' ),
				href   = null,
				styles = this.styles.slice( 0 );

			this.styles = [];

			for ( href in this.sheets ) {
				if ( ! this.sheets[ href ].link.parent().length ) {
					head.append( this.sheets[ href ].link );
				}
			}

			setTimeout( function() {
				for ( var i = 0; i < styles.length; i++ ) {
					styles[ i ].empty();
					styles[ i ].remove();
				}
			}, 50 );
		},

		/**
		 * Converts relative URLs to absolute URLs since the
		 * styles will be added to a <style> tag.
		 *
		 * @since 1.10
		 * @method convertURLs
		 * @param {String} styles
		 * @param {String} href
		 */
		convertURLs: function( styles, href )
		{
			href = href.substring( 0, href.lastIndexOf( '/' ) );

			if ( href.length ) {
				href += '/';
			}

			return styles.replace( this.regex.urls, "$1" + href + "$2$3" );
		},

		/**
		 * Returns the value of 1em in pixels.
		 *
		 * @since 1.10
		 * @method getEmPixelValue
		 * @return {Number}
		 */
		getEmPxValue: function()
		{
			if ( this.emPxValue ) {
				return this.emPxValue;
			}

			var value                = null,
				doc                  = window.document,
				docElem              = doc.documentElement,
				body                 = doc.body,
				div                  = doc.createElement( 'div' ),
				originalHTMLFontSize = docElem.style.fontSize,
				originalBodyFontSize = body && body.style.fontSize,
				fakeUsed             = false;

			div.style.cssText = 'position:absolute;font-size:1em;width:1em';

			if ( ! body ) {
				body = fakeUsed = doc.createElement( 'body' );
				body.style.background = 'none';
			}

			// 1em in a media query is the value of the default font size of the browser.
			// Reset docElem and body to ensure the correct value is returned.
			docElem.style.fontSize = '100%';
			body.style.fontSize = '100%';

			body.appendChild( div );

			if ( fakeUsed ) {
				docElem.insertBefore( body, docElem.firstChild );
			}

			// Get the em px value.
			value = parseFloat( div.offsetWidth );

			// Remove test elements.
			if ( fakeUsed ) {
				docElem.removeChild( body );
			}
			else {
				body.removeChild( div );
			}

			// Restore the original values.
			docElem.style.fontSize = originalHTMLFontSize;

			if ( originalBodyFontSize ) {
				body.style.fontSize = originalBodyFontSize;
			}
			else {
				body.style.fontSize = '';
			}

			this.emPxValue = value;

			return value;
		}
	};

	/**
	 * Force jQuery functions to return certain values
	 * based on the current simulated media query.
	 *
	 * @since 1.10
	 * @class ForceJQueryValues
	 */
	var ForceJQueryValues = {

		/**
		 * jQuery functions that have been overwritten. Saved for
		 * restoring them later.
		 *
		 * @since 1.10
		 * @access private
		 * @property {Object} _functions
		 */
		_functions: null,

		/**
		 * Updates forced jQuery methods.
		 *
		 * @since 1.10
		 * @method update
		 */
		update: function()
		{
			var fn;

			// Cache the original jQuery functions.
			if ( ! this._functions ) {

				this._functions = {};

				for ( fn in ForceJQueryFunctions ) {
					this._functions[ fn ] = jQuery.fn[ fn ];
				}
			}

			// Reset the jQuery functions if no width, otherwise, override them.
			if ( ! SimulateMediaQuery.width ) {
				for ( fn in this._functions ) {
					jQuery.fn[ fn ] = this._functions[ fn ];
				}
			}
			else {
				for ( fn in ForceJQueryFunctions ) {
					jQuery.fn[ fn ] = ForceJQueryFunctions[ fn ];
				}
			}
		}
	};

	/**
	 * jQuery functions that get overwritten by
	 * the ForceJQueryValues class.
	 *
	 * @since 1.10
	 * @class ForceJQueryFunctions
	 */
	var ForceJQueryFunctions = {

		/**
		 * @since 1.10
		 * @method width
		 */
		width: function( val )
		{
			if ( undefined != val ) {
				return ForceJQueryValues._functions['width'].call( this, val );
			}

			if ( $.isWindow( this[0] ) ) {
				return SimulateMediaQuery.width;
			}

			return ForceJQueryValues._functions['width'].call( this );
		}
	};

	/**
	 * Public API
	 */
	FLBuilderSimulateMediaQuery = {
		ignore: function( strings ) {
			SimulateMediaQuery.ignore( strings );
		},
		reparse: function( strings ) {
			SimulateMediaQuery.reparse( strings );
		},
		update: function( width, callback ) {
			SimulateMediaQuery.update( width, callback );
		}
	};

} )( jQuery );
