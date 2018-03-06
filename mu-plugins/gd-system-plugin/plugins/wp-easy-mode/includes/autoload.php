<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

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
 * Returns the plugin instance
 *
 * @return Plugin
 */
function wpem() {

	return Plugin::load();

}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/template-tags.php';
