( function( $ ) {

	/**
	 * Helper for handling responsive editing logic.
	 *
	 * @since 1.9
	 * @class FLBuilderResponsiveEditing
	 */
	FLBuilderResponsiveEditing = {

		/**
		 * The current editing mode we're in.
		 *
		 * @since 1.9
		 * @private
		 * @property {String} _mode
		 */
		_mode: 'default',

		/**
		 * Refreshes the media queries for the responsive preview
		 * if necessary.
		 *
		 * @since 1.9
		 * @method refreshPreview
		 */
		refreshPreview: function()
		{
			var width;

			if ( ! $( '.fl-responsive-preview' ).length || 'default' == this._mode ) {
				return;
			}
			else if ( 'responsive' == this._mode ) {
				width = FLBuilderConfig.global.responsive_breakpoint >= 320 ? 320 : FLBuilderConfig.global.responsive_breakpoint;
				FLBuilderSimulateMediaQuery.update( width );
			}
			else if ( 'medium' == this._mode ) {
				width = FLBuilderConfig.global.medium_breakpoint >= 769 ? 769 : FLBuilderConfig.global.medium_breakpoint;
				FLBuilderSimulateMediaQuery.update( width );
			}

			FLBuilder._resizeLayout();
		},

		/**
		 * Initializes responsive editing.
		 *
		 * @since 1.9
		 * @access private
		 * @method _init
		 */
		_init: function()
		{
			this._bind();
			this._initMediaQueries();
		},

		/**
		 * Bind events.
		 *
		 * @since 1.9
		 * @access private
		 * @method _bind
		 */
		_bind: function()
		{
			FLBuilder.addHook( 'responsive-editing-switched', this._previewSpacingFields );
			FLBuilder.addHook( 'settings-form-init', this._initSettingsForms );
			FLBuilder.addHook( 'settings-lightbox-closed', this._clearPreview );

			$( 'body' ).delegate( '.fl-field-responsive-toggle', 'click', this._settingToggleClicked );
		},

		/**
		 * Initializes faux media queries.
		 *
		 * @since 1.10
		 * @access private
		 * @method _initMediaQueries
		 */
		_initMediaQueries: function()
		{
			// Don't simulate media queries for stylesheets that match these paths.
			FLBuilderSimulateMediaQuery.ignore( [
				FLBuilderConfig.pluginUrl,
				'fl-theme-builder',
				'/wp-includes/',
				'/wp-admin/'
			] );

			// Reparse stylesheets that match these paths on each update.
			FLBuilderSimulateMediaQuery.reparse( [
				FLBuilderConfig.postId + '-layout-draft.css?',
				FLBuilderConfig.postId + '-layout-draft-partial.css?',
				FLBuilderConfig.postId + '-layout-preview.css?',
				FLBuilderConfig.postId + '-layout-preview-partial.css?'
			] );
		},

		/**
		 * Switches to either mobile, tablet or desktop editing.
		 *
		 * @since 1.9
		 * @access private
		 * @method _switchTo
		 */
		_switchTo: function( mode, callback )
		{
			var body        = $( 'body' ),
				content     = $( FLBuilder._contentClass ),
				preview     = $( '.fl-responsive-preview' ),
				mask        = $( '.fl-responsive-preview-mask' ),
				placeholder = $( '.fl-content-placeholder' ),
				width       = null;

			// Save the new mode.
			FLBuilderResponsiveEditing._mode = mode;

			// Setup the preview.
			if ( 'default' == mode ) {

				if ( 0 === placeholder.length ) {
					return;
				}

				placeholder.after( content );
				placeholder.remove();
				preview.remove();
				mask.remove();
			}
			else if ( 0 === preview.length ) {
				content.after( '<div class="fl-content-placeholder"></div>' );
				body.prepend( wp.template( 'fl-responsive-preview' )() );
				$( '.fl-responsive-preview' ).addClass( 'fl-preview-' + mode );
				$( '.fl-responsive-preview-content' ).append( content );
			}
			else {
				preview.removeClass( 'fl-preview-responsive fl-preview-medium' );
				preview.addClass( 'fl-preview-' + mode  );
			}

			// Set the content width and apply media queries.
			if ( 'responsive' == mode ) {
				width = FLBuilderConfig.global.responsive_breakpoint >= 320 ? 320 : FLBuilderConfig.global.responsive_breakpoint;
				content.width( width );
				FLBuilderSimulateMediaQuery.update( width, callback );
			}
			else if ( 'medium' == mode ) {
				width = FLBuilderConfig.global.medium_breakpoint >= 769 ? 769 : FLBuilderConfig.global.medium_breakpoint;
				content.width( width );
				FLBuilderSimulateMediaQuery.update( width, callback );
			}
			else {
				content.width( '' );
				FLBuilderSimulateMediaQuery.update( null, callback );
			}

			// Set the content background color.
			this._setContentBackgroundColor();

			// Resize the layout.
			FLBuilder._resizeLayout();

			// Broadcast the switch.
			FLBuilder.triggerHook( 'responsive-editing-switched', mode );
		},

		/**
		 * Sets the background color for the builder content
		 * in a responsive preview.
		 *
		 * @since 1.9
		 * @access private
		 * @method _setContentBackgroundColor
		 */
		_setContentBackgroundColor: function()
		{
			var content     = $( FLBuilder._contentClass ),
				preview     = $( '.fl-responsive-preview' ),
				placeholder = $( '.fl-content-placeholder' ),
				parents     = placeholder.parents(),
				parent      = null,
				color       = '#fff',
				i           = 0;

			if ( 0 === preview.length ) {
				content.css( 'background-color', '' );
			}
			else {

				for( ; i < parents.length; i++ ) {

					color = parents.eq( i ).css( 'background-color' );

					if ( color != 'rgba(0, 0, 0, 0)' ) {
						break;
					}
				}

				content.css( 'background-color', color );
			}
		},

		/**
		 * Switches to the given mode and scrolls to an
		 * active node if one is present.
		 *
		 * @since 1.9
		 * @access private
		 * @method _switchToAndScroll
		 */
		_switchToAndScroll: function( mode )
		{
			var nodeId  = $( '.fl-builder-settings' ).data( 'node' ),
				element = undefined === nodeId ? undefined : $( '.fl-node-' + nodeId );

			FLBuilderResponsiveEditing._switchTo( mode, function() {

				if ( undefined !== element && element ) {

					setTimeout( function(){

						var win     = $( window ),
							content = $( '.fl-responsive-preview-content' );

						if ( ! content.length || win.height() < content.height() ) {
							$( 'html, body' ).animate( {
								scrollTop: element.offset().top - 100
							}, 250 );
						}
						else {
							scrollTo( 0, 0 );
						}

					}, 250 );
				}
			} );
		},

		/**
		 * Clears the responsive editing preview and reverts
		 * to the default view.
		 *
		 * @since 1.9
		 * @access private
		 * @method _clearPreview
		 */
		_clearPreview: function()
		{
			FLBuilderResponsiveEditing._switchToAndScroll( 'default' );
		},

		/**
		 * Callback for when the responsive toggle of a setting
		 * is clicked.
		 *
		 * @since 1.9
		 * @access private
		 * @method _settingToggleClicked
		 */
		_settingToggleClicked: function()
		{
			var toggle  = $( this ),
				mode    = toggle.data( 'mode' );

			if ( 'default' == mode ) {
				mode  = 'medium';
			}
			else if ( 'medium' == mode ) {
				mode  = 'responsive';
			}
			else {
				mode  = 'default';
			}

			FLBuilderResponsiveEditing._switchAllSettingsTo( mode );

			toggle.siblings( '.fl-field-responsive-setting:visible' ).find( 'input' ).focus();
		},

		/**
		 * Switches all responsive settings in a settings form
		 * to the given mode.
		 *
		 * @since 1.9
		 * @access private
		 * @method _switchAllSettingsTo
		 * @param {String} mode
		 */
		_switchAllSettingsTo: function( mode )
		{
			var className = 'dashicons-desktop dashicons-tablet dashicons-smartphone';

			$( '.fl-field-responsive-toggle' ).removeClass( className );
			$( '.fl-field-responsive-setting' ).hide();

			if ( 'default' == mode ) {
				className = 'dashicons-desktop';
			}
			else if ( 'medium' == mode ) {
				className = 'dashicons-tablet';
			}
			else {
				className = 'dashicons-smartphone';
			}

			$( '.fl-field-responsive-toggle' ).addClass( className ).data( 'mode', mode );
			$( '.fl-field-responsive-setting-' + mode ).css( 'display', 'inline-block' );

			FLBuilderResponsiveEditing._switchToAndScroll( mode );
		},

		/**
		 * Initializes responsive settings in settings forms.
		 *
		 * @since 1.9
		 * @access private
		 * @method _initSettingsForms
		 */
		_initSettingsForms: function()
		{
			var self = FLBuilderResponsiveEditing;

			if ( Number( FLBuilderConfig.global.responsive_enabled ) ) {

				self._initFields( 'dimension', 'input', 'keyup', self._spacingFieldKeyup, [
					'margin',
					'padding'
				] );

				self._initFields( 'dimension', 'input', 'keyup', self._textFieldKeyup );
				self._initFields( 'unit', 'input', 'keyup', self._textFieldKeyup );
			}

			self._switchAllSettingsTo( self._mode );
		},

		/**
		 * Initializes responsive logic for fields when
		 * an event occurs.
		 *
		 * @since 1.9
		 * @access private
		 * @method _initFields
		 * @param {String} type
		 * @param {String} selector
		 * @param {String} event
		 * @param {Function} callback
		 * @param {Array} names
		 */
		_initFields: function( type, selector, event, callback, names )
		{
			var form   = $( '.fl-builder-settings' ),
				fields = form.find( '.fl-field' ).has( '.fl-field-responsive-setting' ),
				field  = null,
				name   = null,
				i      = 0;

			for ( ; i < fields.length; i++ ) {

				field = fields.eq( i );
				name  = field.attr( 'id' ).replace( 'fl-field-', '' );

				if ( 'object' == typeof names && $.inArray( name, names ) < 0 ) {
					continue;
				}
				if ( type != field.attr( 'data-type' ) ) {
					continue;
				}
				if ( undefined !== field.attr( 'data-responsive-init' ) ) {
					continue;
				}

				field.find( '.fl-field-responsive-setting-default ' + selector ).on( event, callback );
				field.find( '.fl-field-responsive-setting-medium ' + selector ).on( event, callback );
				field.find( '.fl-field-responsive-setting-responsive ' + selector ).on( event, callback );
				field.find( '.fl-field-responsive-setting-default ' + selector ).trigger( event );
				field.attr( 'data-responsive-init', 1 );
			}
		},

		/**
		 * Returns the fields object for an element.
		 *
		 * @since 1.9
		 * @access private
		 * @method _getFields
		 * @param {Object} element
		 * @param {String} selector
		 * @return {Object}
		 */
		_getFields: function( element, selector )
		{
			var field = $( element ).closest( '.fl-field' );

			return {
				'default'    : field.find( '.fl-field-responsive-setting-default ' + selector ),
				'medium'     : field.find( '.fl-field-responsive-setting-medium ' + selector ),
				'responsive' : field.find( '.fl-field-responsive-setting-responsive ' + selector )
			};
		},

		/**
		 * Callback for the keyup event on a text field.
		 *
		 * @since 1.9
		 * @access private
		 * @method _textFieldKeyup
		 */
		_textFieldKeyup: function()
		{
			var fields = FLBuilderResponsiveEditing._getFields( this, 'input' );

			fields.default.each( function( i ) {

				var defaultPlaceholder = fields.default.eq( i ).attr( 'placeholder' ),
					defaultValue       = fields.default.eq( i ).val(),
					mediumValue        = fields.medium.eq( i ).val();

				// Medium placeholder
				if ( '' == defaultValue ) {
					fields.medium.eq( i ).attr( 'placeholder', ( undefined === defaultPlaceholder ? '' : defaultPlaceholder ) );
				}
				else {
					fields.medium.eq( i ).attr( 'placeholder', defaultValue );
				}

				// Responsive placeholder
				if ( '' == mediumValue ) {
					fields.responsive.eq( i ).attr( 'placeholder', fields.medium.eq( i ).attr( 'placeholder' ) );
				}
				else {
					fields.responsive.eq( i ).attr( 'placeholder', mediumValue );
				}
			} );
		},

		/**
		 * Callback for the keyup event on a spacing field.
		 *
		 * @since 1.9
		 * @access private
		 * @method _spacingFieldKeyup
		 */
		_spacingFieldKeyup: function()
		{
			var form                 = $( '.fl-builder-settings' ),
				type                 = 'row',
				name                 = $( this ).closest( '.fl-field' ).attr( 'id' ).replace( 'fl-field-', '' ),
				fields               = FLBuilderResponsiveEditing._getFields( this, 'input' ),
				config               = FLBuilderConfig.global,
				configPrefix         = null,
				defaultGlobalVal     = null,
				mediumGlobalVal      = null,
				responsiveGlobalVal  = null;

			// Get the node type
			if ( form.hasClass( 'fl-builder-row-settings' ) ) {
				type = 'row';
			}
			else if ( form.hasClass( 'fl-builder-col-settings' ) ) {
				type = 'col';
			}
			else if ( form.hasClass( 'fl-builder-module-settings' ) ) {
				type = 'module';
			}

			// Spoof global column defaults since we don't have a setting for those.
			$.extend( config, {
				col_margins            : 0,
				col_margins_medium     : '',
				col_margins_responsive : '',
				col_padding            : 0,
				col_padding_medium     : '',
				col_padding_responsive : ''
			} );

			// Set the global values
			configPrefix         = type + '_' + name + ( 'margin' == name ? 's' : '' );
			defaultGlobalVal     = config[ configPrefix ];
			mediumGlobalVal      = config[ configPrefix + '_medium' ];
			responsiveGlobalVal  = config[ configPrefix + '_responsive' ];

			// Loop through the fields.
			fields.default.each( function( i ) {

				var dimension            = fields.default.eq( i ).attr( 'name' ).split( '_' ).pop(),
					defaultVal           = fields.default.eq( i ).val(),
					mediumVal            = fields.medium.eq( i ).val(),
					responsiveVal        = fields.responsive.eq( i ).val(),
					moduleGlobalVal      = null,
					moduleResponsiveVal  = null;

				// Medium value
				if ( '' == mediumGlobalVal ) {

					if ( '' != defaultVal ) {
						fields.medium.eq( i ).attr( 'placeholder', defaultVal );
					}
					else if ( '' != defaultGlobalVal ) {
						fields.medium.eq( i ).attr( 'placeholder', defaultGlobalVal );
					}
				}

				// Responsive value
				if ( '' == responsiveGlobalVal ) {

					if ( 'module' == type && Number( config.auto_spacing ) ) {

						moduleGlobalVal     = '' == mediumGlobalVal ? Number( defaultGlobalVal ) : mediumGlobalVal;
						moduleResponsiveVal = '' == mediumVal ? Number( defaultVal ) : mediumVal;

						if ( '' != moduleResponsiveVal && ( moduleResponsiveVal > moduleGlobalVal || moduleResponsiveVal < 0 ) ) {
							fields.responsive.eq( i ).attr( 'placeholder', moduleGlobalVal );
						}
						else if ( '' != moduleResponsiveVal ) {
							fields.responsive.eq( i ).attr( 'placeholder', moduleResponsiveVal );
						}
						else {
							fields.responsive.eq( i ).attr( 'placeholder', moduleGlobalVal );
						}
					}
					else if ( ! Number( config.auto_spacing ) || ( 'padding' == name && 'top|bottom'.indexOf( dimension ) > -1 ) ) {

						if ( '' != mediumVal ) {
							fields.responsive.eq( i ).attr( 'placeholder', mediumVal );
						}
						else if ( '' != mediumGlobalVal ) {
							fields.responsive.eq( i ).attr( 'placeholder', mediumGlobalVal );
						}
						else if ( '' != defaultVal ) {
							fields.responsive.eq( i ).attr( 'placeholder', defaultVal );
						}
						else if ( '' != defaultGlobalVal ) {
							fields.responsive.eq( i ).attr( 'placeholder', defaultGlobalVal );
						}
					}
				}
			} );
		},

		/**
		 * Callback for when the responsive preview changes
		 * to live preview CSS for spacing fields.
		 *
		 * @since 1.9
		 * @access private
		 * @method _previewSpacingFields
		 */
		_previewSpacingFields: function()
		{
			var mode = FLBuilderResponsiveEditing._mode,
				form = $( '.fl-builder-settings' );

			if ( 0 === form.length || undefined === form.attr( 'data-node' ) ) {
				return;
			}

			form.find( '.fl-field' ).has( '.fl-field-responsive-setting' ).each( function() {

				var fields  = FLBuilderResponsiveEditing._getFields( this, 'input' ),
					preview = fields.responsive.closest( '.fl-field' ).data( 'preview' );

				if ( 'refresh' == preview.type ) {
					return;
				}

				fields[ mode ].trigger( 'keyup' );
			} );
		},
	};

	$( function() { FLBuilderResponsiveEditing._init() } );

} )( jQuery );
