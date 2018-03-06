<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

if ( defined( 'WP_CLI' ) && WP_CLI && defined( 'WP_DEBUG' ) && WP_DEBUG ) {

	$composer_autoloader = __DIR__ . '/../../vendor/autoload.php';

	if ( file_exists( $composer_autoloader ) ) {

		// This is for enabling codeception
		require_once $composer_autoloader;

	}

}

spl_autoload_register( function( $resource ) {

	if ( 0 !== strpos( $resource, __NAMESPACE__ ) ) {

		return;

	}

	$resource = strtolower(
		str_replace(
			[ __NAMESPACE__ . '\\', '_' ],
			[ '',                   '-' ],
			$resource
		)
	);

	$parts = explode( '\\', $resource );
	$name  = array_pop( $parts );
	$files = str_replace( '//', '/', glob( sprintf( '%s/%s/*-%s.php', __DIR__, implode( '/', $parts ), $name ) ) );

	if ( isset( $files[0] ) && is_readable( $files[0] ) ) {

		require_once $files[0];

	}

} );

/**
 * Returns the plugin instance.
 *
 * @return Plugin
 */
function plugin() {

	return Plugin::load();

}
