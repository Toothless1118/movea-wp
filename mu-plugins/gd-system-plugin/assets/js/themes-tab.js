/* global jQuery, gdThemesTab, ajaxurl, */

jQuery( document ).ready( function( $ ) {

	var themesTab = {

		init: function() {

			var link_markup = '<li><a href="" data-sort="gd-themes" class="js-gd-themes">' + gdThemesTab.tab + '</a></li>';

			$( '.wp-filter > .filter-links' ).append( link_markup );

			$( '.page-title-action' ).attr( 'aria-expanded', true );

		},

		shiftAddNewTheme: function() {

			var $add_new_container = $( '.add-new-theme' );

			$add_new_container.remove();

			$( '.theme-browser .themes' ).prepend( $add_new_container );

		}

	};

	var coreThemeInstaller = wp.themes.view.Installer.prototype,
	    $body              = $( 'body' );

	wp.themes.view.Installer = wp.themes.view.Installer.extend( {

		events: function() {

			var events = _.extend( {}, coreThemeInstaller.events, {
				'click .filter-links li > a.js-gd-themes': 'gdThemes'
			} );

			delete events['click .filter-links li > a'];

			events['click .filter-links li > a:not( .js-gd-themes )'] = 'onSort';

			return events;

		},

		browse: function( section ) {

			if ( 'gd-themes' === section ) {

				this.loadThemes();

				return;

			}

			this.collection.query( { browse: section } );

		},


		gdThemes: function( event ) {

			event.preventDefault();

			this.clearSearch();

			var $target = $( event.target ),
			    sort    = $target.data( 'sort' );

			if ( $target.hasClass( this.activeClass ) ) {

				return;

			}

			$body.removeClass( 'filters-applied show-filters' );
			$( '.drawer-toggle' ).attr( 'aria-expanded', 'false' );
			$body.removeClass( 'show-favorites-form' );
			$( '.filter-links li > a, .theme-filter' ).removeClass( this.activeClass );
			$( '[data-sort="' + sort + '"]' ).addClass( this.activeClass );

			this.loadThemes();

			wp.themes.router.navigate( wp.themes.router.baseUrl( wp.themes.router.browsePath + sort ) );

		},

		loadThemes: function() {

			$body.addClass( 'loading-content' ).removeClass( 'no-results' );

			$.post( ajaxurl, { action: 'gd_render_themes' }, function( response ) {

				if ( ! response.success ) {

					return;

				}

				$body.removeClass( 'loading-content' );

				$( '.theme-browser .themes' ).empty().append( response.data );

				$( '.theme-count' ).text( $( '.theme-browser .themes > div.theme' ).length );

			} );

			if ( $( '#gd-theme-install' ).length ) {

				return;

			}

			$( '.theme-browser .themes' ).after( '<div id="gd-theme-install" style="display:none;"></div>' );

		},

		/**
		 * Overrides wp.themes.view.Appearance.scroller
		 */
		scroller: function() {

			if ( $( '.js-gd-themes' ).hasClass( 'current' ) ) {

				return;

			}

			coreThemeInstaller.scroller.apply( this, arguments );

		}

	} );

	var installTheme = {

		init: function( button ) {

			var data = {
				action: 'gd_install_theme',
				slug: button.data( 'slug' ),
				gd_package: button.data( 'package' ),
				_ajax_nonce: button.data( 'nonce' )
			};

			$.post( ajaxurl, data, function( response ) {

				if ( ! response.success ) {

					$( 'div[data-slug="' + button.data( 'slug' ) + '"]' )
						.append( '<div class="notice notice-error notice-alt update-message"><p>' + gdThemesTab.error + '</p></div>' );

					button.removeClass( 'updating-message' );

					return;

				}

				$( 'div[data-slug="' + button.data( 'slug' ) + '"]' )
					.append( '<div class="notice notice-success notice-alt"><p>' + gdThemesTab.installed + '</p></div>' )
					.removeClass( 'focus' )
					.find( '.theme-actions > .theme-install' )
					.removeClass( 'updating-message' )
					.text( gdThemesTab.installed )
					.addClass( 'disabled' )
					.delay( 1500 )
					.replaceWith(
						'<a class="button button-primary activate-theme" href="' + response.data.activateUrl + '">' + gdThemesTab.activate + '</a>' +
						'<a class="button button-secondary live-preview-theme" href="' + response.data.customizeUrl + '">' + gdThemesTab.live_preview + '</a>'
					);

			} );

		}

	};

	themesTab.init();

	$( window ).on( 'load', themesTab.shiftAddNewTheme );

	$( document ).on( 'click', '.theme.godaddy .theme-screenshot, .theme.godaddy .more-details', function() {

		var url = $( this ).closest( '.theme.godaddy' ).attr( 'data-demo-url' );

		window.open( url, '_blank' );

	} );

	$( document ).on( 'click', '.theme.godaddy .button.theme-install', function( e ) {

		e.preventDefault();
		e.stopPropagation();

		$( this ).addClass( 'updating-message' ).parents( 'div.theme' ).addClass( 'focus' );

		installTheme.init( $( this ) );

	} );

} );
