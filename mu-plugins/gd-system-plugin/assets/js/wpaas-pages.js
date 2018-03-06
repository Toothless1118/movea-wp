/* globals jQuery, wpaas_pages */

jQuery( document ).ready( function( $ ) {

	$( '.wpaas_hidden_tabs' ).on( 'click', function() {

		if ( ! window.confirm( wpaas_pages.confirm ) ) {

			$( this ).prop( 'checked', false );

			return false;

		}

		window.location.href = $( this ).data( 'url' );

	} );

} );
