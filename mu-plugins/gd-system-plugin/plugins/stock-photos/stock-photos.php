<?php
/**
 * Plugin Name: Stock Photos
 * Description: Import beautiful, curated stock photos for your website
 * Version: 1.1.1
 * Author: GoDaddy
 * Author URI: https://www.godaddy.com/
 * License: GPL-2.0
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: stock-photos
 * Domain Path: /languages
 *
 * This plugin, like WordPress, is licensed under the GPL.
 * Use it to make something cool, have fun, and share what you've learned with others.
 *
 * Copyright © 2016 GoDaddy Operating Company, LLC. All Rights Reserved.
 */

namespace WPaaS\StockPhotos;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

require __DIR__ . '/includes/class-ajax.php';
require __DIR__ . '/includes/class-api.php';
require __DIR__ . '/includes/class-import.php';
require __DIR__ . '/includes/class-scripts.php';

class Plugin {

	const FILE = __FILE__;

	public static function instance() {

		if ( ! is_callable( '\WPaaS\Plugin::is_wpaas' ) || ! \WPaaS\Plugin::is_wpaas() ) {

			return;

		}

		static $instance = null;

		if ( ! is_null( $instance ) ) {

			return $instance;

		}

		$composer_autoloader = __DIR__ . '/vendor/autoload.php';

		if ( defined( 'WP_CLI' ) && WP_CLI && file_exists( $composer_autoloader ) ) {

			// This is for enabling codeception
			require_once $composer_autoloader;

		}

		load_plugin_textdomain( 'stock-photos', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		$api      = new API();
		$scripts  = new Scripts( $api );
		$import   = new Import();
		$ajax     = new Ajax( $api, $import );
		$instance = new self;

		return $instance;

	}

}

add_action( 'plugins_loaded', 'WPaaS\StockPhotos\Plugin::instance' );
