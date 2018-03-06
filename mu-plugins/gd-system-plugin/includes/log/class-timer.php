<?php

namespace WPaaS\Log;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Timer {

	/**
	 * Start time (in microseconds).
	 *
	 * @var float
	 */
	private static $start;

	/**
	 * Time it took from start to stop (in seconds).
	 *
	 * @var string
	 */
	private static $took = 0;

	/**
	 * Start the timer.
	 *
	 * @action wp_loaded
	 *
	 * @return mixed|void
	 */
	public static function start() {

		self::$took = 0;

		return self::$start = microtime( true );

	}

	/**
	 * Stop the timer and return the time diff (in seconds).
	 *
	 * @param  int $precision (optional)
	 *
	 * @return string
	 */
	public static function stop( $precision = 3 ) {

		if ( empty( self::$start ) ) {

			return new WP_Error( 'Please call start function first' );

		}

		$precision = absint( $precision );

		return self::$took = sprintf( "%.{$precision}f", round( microtime( true ) - self::$start, $precision ) );

	}

	/**
	 * Return the time it took from start to stop (in seconds).
	 *
	 * @return string
	 */
	public static function took() {

		return self::$took;

	}

}
