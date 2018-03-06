/* globals wpem_vars */
window.WPEM = window.WPEM || {};

jQuery( document ).ready( function( $ ) {

	var start = new Date().getTime() / 1000;

	window.WPEM.Form = {

		beforeSubmit: function( $form ) {

			var now = new Date().getTime() / 1000;

			$( '#wpem_step_took' ).val( parseFloat( now - start ).toFixed( 3 ) );

			$form.find( 'input, select' ).blur();

			$form.find( 'input[type="submit"]' ).prop( 'disabled', true ).addClass( 'disabled' );

			this.displaySpinner();

			var $links = $( '.wpem-steps-list-item a');

			$links.css( 'cursor', 'default' );

			// Disable links to go back while page is loading
			$links.on( 'click', function( e ) {

				e.preventDefault();

			} );

		},

		displaySpinner: function() {

			$( '#wpbody-content' ).block( {
				css: {
					position: 'fixed',
					width: '100%'
				},
				message: wpem_vars.i18n.loading_message,
				overlayCSS: {
					backgroundColor: '#fff',
					opacity: '0.9'
				}
			} );

		}

	};

	$( '.wpem-screen form' ).on( 'click', '#wpem_no_thanks', function( e ) {

		e.preventDefault();

		if ( ! window.confirm( wpem_vars.i18n.exit_confirm ) ) {

			return;

		}

		window.WPEM.Form.displaySpinner();

		$( '#wpem_continue' ).val( 'no' );

		$( '.wpem-screen form' ).submit();

	} );

	var validated = false;

	$( '.wpem-screen' ).on( 'submit', 'form', function( e ) {

		// Submit now if validated
		if ( validated ) {

			return true;

		}

		var $form = $( this );

		if ( ! $form[0].checkValidity() ) {

			return false;

		}

		e.preventDefault();

		window.WPEM.Form.beforeSubmit( $form );

		validated = true;

		if ( -1 === navigator.userAgent.indexOf( 'Safari' ) ) {

			$form.submit();

			return false;

		}

		// Workaround for Safari not repainting the DOM on submit
		setTimeout( function() {

			$form.submit();

		}, 250 );

	} );

	$( 'input[name="wpem_site_type"]' ).on( 'click', function() {

		var excluded_fields = $( 'input[name="wpem_woocommerce[calc_shipping]"], input[name="wpem_woocommerce[calc_taxes]"], *[name^="wpem_woocommerce[payment_methods]"], input[name="wpem_woocommerce[prices_include_tax]"]' );

		if ( 'store' !== $( this ).val() ) {

			$( '.wpem-ecommerce-option-group' ).find( 'input[type="checkbox"], input[type="radio"], select' ).not( excluded_fields ).removeAttr( 'required' );

			$( '.wpem-ecommerce-option-group' ).slideUp();

			return;

		}

		$( '.wpem-ecommerce-option-group' ).find( 'input[type="checkbox"], input[type="radio"], select' ).not( excluded_fields ).attr( 'required', 'required' );

		$( '.wpem-ecommerce-option-group' ).slideDown();

	} );

	$( 'input[name="wpem_woocommerce[calc_taxes]"]' ).on( 'click', function() {

		var targets = '.wpem-woocommerce-prices-include-tax';

		if ( $( this ).is( ':checked' ) ) {

			$( targets ).slideDown();

			return;

		}

		$( targets ).slideUp();

	} );

	$( '#wpem_woocommerce\\[store_location\\]' ).on( 'change', function( e ) {

		$.post(
			wpem_vars.ajax_url,
			{
				'action'         : 'store_location_change',
				'location_nonce' : wpem_vars.ajax_nonce,
				'location'       : $( e.currentTarget ).val()
			},
			function( response ) {

				if ( ! response.success ) {

					return;

				}

				// Update tax table
				if ( $.isEmptyObject( response.data.tax_table ) ) {

					$( '.wpem-woocommerce-tax-details' ).html( '' );

					return;

				}

				$( '.wpem-woocommerce-tax-details' ).html( response.data.tax_table );

			}
		);

	});

} );
