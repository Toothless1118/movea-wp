<?php

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

/**
 * Activate a local theme if API is not available
 */
add_action( 'wp_ajax_wpem_switch_theme', function() {

	$nonce      = filter_input( INPUT_POST, 'nonce' );
	$stylesheet = filter_input( INPUT_POST, 'theme' );
	$theme      = wp_get_theme( $stylesheet );

	if ( false !== wp_verify_nonce( $nonce, 'wpem_ajax_nonce' ) && $theme->exists() ) {

		switch_theme( $stylesheet );

		wpem_mark_as_done();

	}

	wp_die();

} );
