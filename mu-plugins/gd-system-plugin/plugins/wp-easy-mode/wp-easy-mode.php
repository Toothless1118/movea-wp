<?php
/**
 * Plugin Name: WP Easy Mode
 * Description: Helping users launch their new WordPress site in just a few clicks.
 * Version: 2.3.5
 * Author: GoDaddy
 * Author URI: https://www.godaddy.com/
 * License: GPL-2.0
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-easy-mode
 * Domain Path: /languages
 *
 * This plugin, like WordPress, is licensed under the GPL.
 * Use it to make something cool, have fun, and share what you've learned with others.
 *
 * Copyright © 2017 GoDaddy Operating Company, LLC. All Rights Reserved.
 */

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

$autoload = __DIR__ . '/includes/autoload.php';

if ( is_readable( $autoload ) ) {

	require_once $autoload;

	/**
	 * WP Easy Mode
	 *
	 * Helping users launch their new WordPress site in just a few clicks.
	 *
	 * @author Frankie Jarrett <fjarrett@godaddy.com>
	 * @author Jonathan Bardo <jbardo@godaddy.com>
	 */
	final class Plugin {

		use Singleton, Data;

		/**
		 * Admin object
		 *
		 * @var Admin
		 */
		public $admin;

		/**
		 * Class constructor
		 */
		private function __construct() {

			$this->version    = '2.3.5';
			$this->basename   = plugin_basename( __FILE__ );
			$this->base_dir   = plugin_dir_path( __FILE__ );
			$this->assets_url = plugin_dir_url( __FILE__ ) . 'assets/';
			$this->page_slug  = 'wpem';
			$this->api_url    = 'https://wpnux.godaddy.com/';

			if ( defined( 'WP_CLI' ) && WP_CLI ) {

				$composer_autoloader = __DIR__ . '/vendor/autoload.php';

				if ( file_exists( $composer_autoloader ) ) {

					// This is for enabling codeception
					require_once $composer_autoloader;

				}

				\WP_CLI::add_command( 'easy-mode', sprintf( '\%s\CLI', __NAMESPACE__ ) );

				return;

			}

			if ( ! is_admin() ) {

				return;

			}

			add_action( 'plugins_loaded', [ $this, 'i18n' ] );
			add_action( 'shutdown',       [ $this, 'shutdown' ] );

			/**
			 * Always allow external HTTP requests to our API
			 *
			 * @filter http_request_host_is_external
			 */
			add_filter( 'http_request_host_is_external', function( $allow, $host, $url ) {

				return ( parse_url( $this->api_url, PHP_URL_HOST ) === $host ) ? true : $allow;

			}, 10, 3 );

			/**
			 * Instantiate the Customizer class
			 *
			 * @action load-customize.php
			 */
			add_action( 'load-customize.php', function() {

				new Customizer;

			} );

			if ( ! wpem_is_fresh_wp() ) {

				if ( ! wpem_is_done() ) {

					add_filter( 'wpem_deactivate_plugins_on_quit', '__return_false' );

					wpem_quit();

				}

				return;

			}

			if ( wpem_is_done() ) {

				add_action( 'init', 'wpem_maybe_redirect' );

				return;

			}

			define( 'WPEM_DOING_STEPS', true );

			$this->admin = new Admin;

		}

		/**
		 * Load languages
		 *
		 * @action plugins_loaded
		 */
		public function i18n() {

			load_plugin_textdomain( 'wp-easy-mode', false, dirname( $this->basename ) . '/languages' );

		}

		/**
		 * Maybe self-destruct on shutdown
		 *
		 * @action shutdown
		 */
		public function shutdown() {

			global $wp_customize;

			if (
				wpem_is_done()
				&&
				$this->page_slug !== filter_input( INPUT_GET, 'page' )
				&&
				! isset( $wp_customize )
			) {

				wpem_self_destruct();

			}

		}

	}

	wpem();

}
