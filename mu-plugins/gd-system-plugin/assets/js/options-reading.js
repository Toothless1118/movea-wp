/* global jQuery, wpaas_options_reading_vars */

jQuery( document ).ready( function( $ ) {

	$( '#blog_public').prop( 'disabled', true ).css( 'cursor', 'not-allowed' );

	var $notice = $( '<div class="wpaas-inline-notice"></div>' );

	$notice.html( wpaas_options_reading_vars.blog_public_notice_text );

	$( '.option-site-visibility p.description' )
		.after( $notice )
		.after( '<div class="clear"></div>' )
		.hide();

} );
