<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Singleton {

	/**
	 * The plugin instance.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Return the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function load() {

		if ( ! self::$instance ) {

			self::$instance = new self();

		}

		return self::$instance;

	}

	/**
	 * Reset the plugin instance.
	 */
	public static function reset() {

		self::$instance = null;

	}

}
