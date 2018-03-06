<?php

namespace WPaaS;

use \WPaaS\Log\Logger;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Log {

	/**
	 * Array of log components.
	 *
	 * @var array
	 */
	private static $components = [
		// Core
		'attachment'    => '\WPaaS\Log\Components\Attachment',
		'customizer'    => '\WPaaS\Log\Components\Customizer',
		'option'        => '\WPaaS\Log\Components\Option',
		'page'          => '\WPaaS\Log\Components\Page',
		'plugin'        => '\WPaaS\Log\Components\Plugin',
		'post'          => '\WPaaS\Log\Components\Post',
		'theme'         => '\WPaaS\Log\Components\Theme',
		'user'          => '\WPaaS\Log\Components\User',
		// Extra
		'beaverbuilder' => '\WPaaS\Log\Components\BeaverBuilder',
		'easymode'      => '\WPaaS\Log\Components\EasyMode',
	];

	/**
	 * Array of log metrics.
	 *
	 * @var array
	 */
	private static $metrics = [ 'login', 'publish' ];

	/**
	 * Class constructor.
	 */
	public function __construct() {

		/**
		 * Filter the array of registered log components.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		self::$components = (array) apply_filters( 'wpaas_log_components', self::$components );

		/**
		 * Filter the array of registered log metrics.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		self::$metrics = (array) apply_filters( 'wpaas_log_metrics', self::$metrics );

		add_action( 'init',      [ $this, 'register' ], -PHP_INT_MAX );
		add_action( 'wp_loaded', [ '\WPaaS\Log\Timer', 'start' ], PHP_INT_MAX );

	}

	/**
	 * Register log components.
	 */
	public function register() {

		$logger = new Logger;

		foreach ( self::$components as $class ) {

			if ( class_exists( $class ) ) {

				new $class( $logger );

			}

		}

	}

	/**
	 * Verify that a log component or metric is registered.
	 *
	 * @param  string $name
	 *
	 * @return bool
	 */
	public static function is_valid( $name ) {

		return ( isset( self::$components[ $name ] ) || in_array( $name, self::$metrics ) );

	}

}
