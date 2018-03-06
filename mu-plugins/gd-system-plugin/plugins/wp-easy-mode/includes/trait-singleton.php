<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Singleton {

	/**
	 * The plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Return the plugin instance
	 *
	 * @return Plugin
	 */
	public static function load() {

		if ( ! static::$instance ) {

			static::$instance = new self();

		}

		return static::$instance;

	}

	/**
	 * Reset the plugin instance
	 */
	public static function reset() {

		static::$instance = null;

	}

}
