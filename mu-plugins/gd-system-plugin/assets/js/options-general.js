/* global jQuery, wpaas_options_general_vars */

jQuery( document ).ready( function( $ ) {

	$( '#siteurl').prop( 'disabled', true ).css( 'cursor', 'not-allowed' );
	$( '#home').prop( 'disabled', true ).css( 'cursor', 'not-allowed' );

	var $notice = $( '<div class="wpaas-inline-notice"></div>' );

	$notice.html( wpaas_options_general_vars.urls_notice_text );

	$( '#home-description' )
		.after( $notice )
		.after( '<div class="clear"></div>' )
		.hide();

} );
