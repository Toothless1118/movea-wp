/* globals ajaxurl, wpaas_pointers */

jQuery( document ).ready( function( $ ) {

	$.each( wpaas_pointers, function( i, pointer ) {

		render( pointer );

	} );

	function render( pointer ) {

		var options = $.extend( pointer.options, {

			pointerClass: 'wp-pointer wpem-pointer',

			close: function() {
				$.post( ajaxurl, {
					pointer: pointer.id,
					action: 'dismiss-wp-pointer'
				} );
			}

		} );

		$( pointer.target ).pointer( options ).pointer( 'open' );

	}

	$( '.wp-pointer' ).css( { 'z-index': 999999 } );

} );
