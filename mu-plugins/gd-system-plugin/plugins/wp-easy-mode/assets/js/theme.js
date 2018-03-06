/* globals wpem_theme_vars */

window.WPEM = window.WPEM || {};

jQuery( document ).ready( function( $ ) {

	$( document ).on( 'click', '.image-license', function( e ) {

		e.preventDefault();

		$( this ).parent().replaceWith( '<p class="description">' + wpem_theme_vars.i18n.image_license + '</p>' );

	} );

	$( document ).on( 'mouseenter', '.theme', function() {

		$( '.theme' ).removeClass( 'hover' );

	} );

	$( document ).on( 'keyup', function( e ) {

		// Tab
		if ( 9 === e.keyCode ) {

			$( '.theme' ).removeClass( 'hover' );

			$( 'a.preview-theme:focus' ).closest( '.theme' ).addClass( 'hover' );

		}

	} );

	$( document ).on( 'keydown', function( e ) {

		switch ( e.keyCode ) {

			case 27 : // Esc

				$( 'a.close-full-overlay' ).click();

				break;

			case 37 : // Left arrow

				$( 'a.previous-theme' ).click();

				break;

			case 39 : // Right arrow

				$( 'a.next-theme' ).click();

				break;

		}

	} );

	$( document ).on( 'click', '#sections .section-title', function( e ) {

		e.preventDefault();

		var section_title = $( this );

		if ( section_title.hasClass( 'active' ) ) {

			return;

		}

		var section  = section_title.data( 'section' ),
		    sections = $( '#hidden-sections' ).find( '.section' );

		$( '.section-title' ).removeClass( 'active' );

		section_title.addClass( 'active' );

		sections.hide();

		$( '#' + section ).fadeIn();

	} );

	var use_fallbacks = false;

	if ( 0 === wpem_theme_vars.themes.length ) {

		wpem_theme_vars.themes = wpem_theme_vars.default_themes;

		use_fallbacks = true;

	}

	$.each( wpem_theme_vars.themes, function( index, theme ) {

		fetchTheme( index, theme );

	} );

	function fetchTheme( index, theme ) {

		if ( 'undefined' !== typeof theme.slug ) {

			renderTheme( theme, index );

			return;

		}

		$.ajax( {
			type: 'GET',
			url: wpem_theme_vars.api_url,
			dataType: 'jsonp',
			data: {
				action: 'get_theme',
				theme: theme
			},
			success: function( response ) {

				wpem_theme_vars.themes[ index ] = response;

				renderTheme( response, index );

			}

		} );

	}

	function renderTheme( data, index ) {

		var $template  = $( '#wpem-template-theme' ),
		    $container = $( '.theme-browser .themes'),
		    $clone     = $( $.trim( $template.clone().html() ) );

		var slug           = ( 'undefined' !== typeof data.slug ) ? data.slug : '',
		    name           = ( 'undefined' !== typeof data.name ) ? data.name : '',
		    author         = ( 'undefined' !== typeof data.author ) ? data.author : '',
		    screenshot_url = ( 'undefined' !== typeof data.screenshot_url ) ? data.screenshot_url : '';

		$( $clone ).appendTo( $container );

		$clone.addClass( slug );

		$clone.attr( 'data-theme', slug );

		if ( use_fallbacks ) {

			$( '.theme .more-details' ).text( wpem_theme_vars.i18n.select );

		}

		if ( screenshot_url ) {

			$clone.find( '.theme-screenshot img' ).attr( 'src', screenshot_url );

		} else {

			$clone.find( '.theme-screenshot' ).addClass( 'blank' );
			$clone.find( '.theme-screenshot img' ).remove();

		}

		$clone.find( '.theme-author span' ).text( author );

		$clone.find( '.theme-name' ).text( name );

		$clone.on( 'click', '.theme-actions a', function( e ) {

			e.preventDefault();

			var $theme = $( this ).closest( '.theme' );

			selectTheme( $theme );

			return false;

		} );


		$clone.on( 'click', function() {

			$( 'body' ).addClass( 'wpem-theme-preview' );

			renderThemePreview( data, index );

		} );

	}

	function selectTheme( $theme ) {

		$( '#wpem_selected_theme' ).val( $theme.data( 'theme' ) );

		$theme.removeClass( 'hover' ).addClass( 'active' );

		var $screen = $( '.wpem-screen' );

		$screen.find( 'form' ).submit();

	}

	function renderThemePreview( data, index ) {

		// Add body class to prevent double scroll bars
		$( 'body' ).addClass( 'wpem-theme-preview' );

		var $template                   = $( '#wpem-template-theme-preview' ),
		    $container                  = $( '.wrap' ),
		    $tpl_customizations         = $( '#wpem-template-customizations' ),
		    $clone                      = $( $.trim( $template.clone().html() ) ),
		    $tpl_customizations_wrapper = $clone.find( '#wpem-template-customizations-wrapper' );

		var slug           = ( 'undefined' !== typeof data.slug ) ? data.slug : '',
		    name           = ( 'undefined' !== typeof data.name ) ? data.name : '',
		    author         = ( 'undefined' !== typeof data.author ) ? data.author : '',
		    version        = ( 'undefined' !== typeof data.version ) ? data.version : '',
		    preview_url    = wpem_theme_vars.preview_url + '&theme=' + data.slug,
		    screenshot_url = ( 'undefined' !== typeof data.screenshot_url ) ? data.screenshot_url : '',
		    description    = ( 'undefined' !== typeof data.description ) ? data.description : '';

		if ( use_fallbacks ) {

			$.ajax( {
				type: 'POST',
				url: wpem_theme_vars.ajax_url,
				data: {
					action: 'wpem_switch_theme',
					theme: slug,
					nonce: wpem_theme_vars.ajax_nonce
				},
				complete: function() {

					window.location.href = wpem_theme_vars.customizer_url;

				}

			} );

			return;

		}

		// Add template customizations
		$tpl_customizations_wrapper.html( $tpl_customizations.html() );

		// Check if we have a selected header image
		var $header_image       = $tpl_customizations_wrapper.find( 'li.selected a' ),
		    $header_image_input = $( '#wpem_selected_header_image_url' );

		if ( 'undefined' !== typeof $header_image.data( 'image-preview-url' ) ) {

			preview_url += '&header_image=' + encodeURIComponent( $header_image.data( 'image-preview-url' ) );

			$header_image_input.val( $header_image.data( 'image-url' ) );

		}

		// Check if we have a color selected color scheme
		var $color_scheme       = $tpl_customizations_wrapper.find( '.color-option.selected input[name="wpem_color_scheme"]' ),
		    $color_scheme_input = $( '#wpem_selected_color_scheme' );

		if ( 'undefined' !== typeof $color_scheme ) {

			preview_url += '&color_scheme=' + encodeURIComponent( $color_scheme.val() );

			$color_scheme_input.val( $color_scheme.val() );

		}

		$( $clone ).appendTo( $container ).show();

		$clone.find( '.theme-name' ).text( name );
		$clone.find( '.theme-by span' ).text( author );
		$clone.find( '.theme-screenshot' ).attr( 'src', screenshot_url );
		$clone.find( '.theme-version span' ).text( version );

		if ( 'WooThemes' === author && data.premium_theme ) {

			$clone.find( '.premium-theme-notice' ).html( '<div class="woo-theme-help-link"><h4>' + data.premium_theme_label + '</h4><p class="woo-theme-help-text">' + data.premium_theme_disclaimer + '</p></div>' );

		}

		var $description = ( $tpl_customizations_wrapper.is( ':empty' ) ) ? description : truncateText( description, 140, '&nbsp;&hellip;' );

		$clone.find( '.theme-description' ).html( $description );

		// Open first pointer if he exist
		openPointers();

		var $main = $clone.find( '.wp-full-overlay-main' );

		$main.append( '<iframe>' );

		$main.find( 'iframe' ).attr( 'src', preview_url );

		$clone.on( 'click', 'a.previous-theme', function( e ) {

			e.preventDefault();

			index = ( 0 === index ) ? wpem_theme_vars.themes.length - 1 : index - 1;

			var theme_data     = wpem_theme_vars.themes[ index ];

			$clone.remove();

			renderThemePreview( theme_data, index );

		} );

		$clone.on( 'click', 'a.next-theme', function( e ) {

			e.preventDefault();

			index = ( wpem_theme_vars.themes.length - 1 === index ) ? 0 : index + 1;

			var theme_data     = wpem_theme_vars.themes[ index ];

			$clone.remove();

			renderThemePreview( theme_data, index );

		} );

		$clone.on( 'click', '.color-option', function( e ) {

			e.preventDefault();

			// if user clicks selected option, do not re-render
			if ( $( this ).hasClass( 'selected' ) ) {

				return;

			}

			var $iframe         = $( '.wp-full-overlay-main iframe' ),
			    $clicked        = $( this ),
			    original_color = $( '.color-option.selected input[name="wpem_color_scheme"]' ).val(),
			    color_scheme   = $clicked.find( 'input[type="radio"]' ).val(),
			    old_href        = '',
			    iframe_url      = $iframe.attr( 'src' );

			if ( ! $clicked.hasClass( 'selected' ) ) {

				iframe_url = ( '' === old_href ) ? iframe_url + '&color_scheme=' + color_scheme : iframe_url.replace( original_color, color_scheme );

				$( '.color-option' ).removeClass( 'selected' );

			} else {

				iframe_url = iframe_url.replace( '&color_scheme=' + color_scheme, '' );

			}

			$clicked.toggleClass( 'selected' );

			$tpl_customizations.html( $tpl_customizations_wrapper.html() );

			$color_scheme_input.val( color_scheme );

			$iframe.on( 'load', function() {

				$iframe.css( 'opacity', 1 );

				$iframe.off( 'load' );

			} );

			$iframe.css( 'opacity', 0 );

			$iframe.attr( 'src', iframe_url );

		} );

		$clone.on( 'click', 'a.theme-install', function( e ) {

			e.preventDefault();

			var $theme = $( '.theme.' + slug );

			$clone.find( 'a.close-full-overlay' ).click();

			selectTheme( $theme );

		} );

		$clone.on( 'click', 'a.close-full-overlay', function( e ) {

			e.preventDefault();

			$( 'body' ).removeClass( 'wpem-theme-preview' );

			$clone.remove();

			// Remove the body class
			$( 'body' ).removeClass( 'wpem-theme-preview' );

		} );

		$clone.on( 'click', '.wpem-header-images-list li:not(.load-more) a', function( e ) {

			e.preventDefault();

			var $t       = $( this ),
			    $li      = $t.parent(),
			    cur_href = encodeURIComponent( $t.data( 'image-preview-url' ) ),
			    old_href = '',
			    $iframe  = $( '.wp-full-overlay-main iframe' );

			if ( ! $li.hasClass( 'selected' ) && $li.siblings( '.selected' ).length > 0 ) {

				old_href = encodeURIComponent( $li.siblings( '.selected' ).find( 'a' ).data( 'image-preview-url' ) );

				$li.siblings().removeClass( 'selected' );

			}

			$li.toggleClass( 'selected' );

			$tpl_customizations.html( $tpl_customizations_wrapper.html() );

			// Update iframe URL
			var iframe_url = $iframe.attr( 'src' );

			if ( ! $li.hasClass( 'selected' ) ) {

				iframe_url = iframe_url.replace( '&header_image=' + cur_href, '' );
				cur_href   = '';

			} else {

				iframe_url = ( '' === old_href ) ? iframe_url + '&header_image=' + cur_href : iframe_url.replace( old_href, cur_href );

			}

			// Update reference for input
			$header_image_input.val( cur_href ? $t.data( 'image-url' ) : '' );

			$iframe.on( 'load', function() {

				$iframe.css( 'opacity', 1 );

				$iframe.off( 'load' );

			} );

			$iframe.css( 'opacity', 0 );

			$iframe.attr( 'src', iframe_url );

		} );

		$clone.on( 'click', '.wpem-header-images-list li.load-more a', function( e ) {

			e.preventDefault();

			var $this = $(this),
			    $parent = $this.parent();

			$parent.empty().addClass('spinner');

			$.get( $this.attr( 'href' ), function( data ) {

				$parent.replaceWith( data );

				$tpl_customizations.html( $tpl_customizations_wrapper.html() );

			} );

		} );

		var $theme_overlay   = $( '.theme-install-overlay' ),
		    $devices_preview = $theme_overlay.find( '.devices button' );

		$( 'button.collapse-sidebar' ).on( 'click', function() {

			if ( 'true' === $( this ).attr( 'aria-expanded' ) ) {

				window.WPEM.Pointers.hideAll();

				$( this ).attr( { 'aria-expanded': 'false', 'aria-label': wpem_theme_vars.i18n.expand } );

				$devices_preview.hide();

			} else {

				setTimeout( function() {

					openPointers();

					$devices_preview.show();

				}, 200 );

				$( this ).attr( { 'aria-expanded': 'true', 'aria-label': wpem_theme_vars.i18n.collapse } );

			}

			$( this ).toggleClass( 'collapsed' ).toggleClass( 'expanded' );

			$( '.wp-full-overlay' ).toggleClass( 'collapsed' ).toggleClass( 'expanded' );

			return false;

		} );

		$devices_preview.on( 'click', function( event ) {

			var device = $( event.currentTarget ).data( 'device' );

			$theme_overlay.removeClass( 'preview-desktop preview-tablet preview-mobile' )
			              .addClass( 'preview-' + device )
			              .data( 'current-preview-device', device );

			var $devices = $( '.wp-full-overlay-footer .devices' );

			$devices.find( 'button' )
			        .removeClass( 'active' )
			        .attr( 'aria-pressed', false );

			$devices.find( 'button.preview-' + device )
			        .addClass( 'active' )
			        .attr( 'aria-pressed', true );

		} );

	}

	function openPointers() {

		window.WPEM.Pointers.openFirstInRange( 'wpem_theme_preview_', [ 1, 2, 3 ] );

	}

	function truncateText( value, length, suffix ) {

		if ( value.length <= length ) {

			return value;

		}

		var strAry = value.split( ' ' ),
		    retLen = strAry[0].length;

		for ( var i = 1; i < strAry.length; i++ ) {

			if ( retLen === length || retLen + strAry[ i ].length + 1 > length ) {

				break;

			}

			retLen += strAry[ i ].length + 1;

		}

		return strAry.slice( 0, i ).join( ' ' ) + ( suffix || '' );

	}

} );
