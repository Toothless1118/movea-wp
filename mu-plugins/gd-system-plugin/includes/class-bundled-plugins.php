<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Bundled_Plugins {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		$bundled_plugins = [
			'customize-direct-manipulation/customize-direct-manipulation.php' => Plugin::is_using_gd_theme(),
			'limit-login-attempts/limit-login-attempts.php'                   => ( false !== Plugin::first_login_date() ),
			'stock-photos/stock-photos.php'                                   => true,
			'wp-easy-mode/wp-easy-mode.php'                                   => Plugin::is_wpem_enabled(),
		];

		/**
		 * Filter the list of bundled plugins.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		$bundled_plugins = (array) apply_filters( 'wpaas_bundled_plugins', $bundled_plugins );

		foreach ( $bundled_plugins as $basename => $enabled ) {

			if ( $enabled ) {

				$this->maybe_load_plugin( $basename );

			}

		}

	}

	/**
	 * Maybe load a bundled plugin.
	 *
	 * @param  string $basename
	 *
	 * @return bool
	 */
	private function maybe_load_plugin( $basename ) {

		$path = Plugin::base_dir() . "plugins/{$basename}";

		if (
			$this->is_plugin_active( $basename )
			||
			$this->is_plugin_activating( $basename )
			||
			! is_readable( $path )
		) {

			return false;

		}

		add_filter( 'load_textdomain_mofile', [ $this, 'load_textdomain_mofile' ], 10, 2 );

		require_once $path;

		return true;

	}

	/**
	 * Check if a plugin is currently active.
	 *
	 * @param  string $basename
	 *
	 * @return bool
	 */
	private function is_plugin_active( $basename ) {

		if ( ! function_exists( '\is_plugin_active' ) ) {

			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		}

		return is_plugin_active( $basename );

	}

	/**
	 * Check if a plugin is currently activating.
	 *
	 * @param  string $basename
	 *
	 * @return bool
	 */
	private function is_plugin_activating( $basename ) {

		return ( is_admin() && $basename === filter_input( INPUT_GET, 'plugin' ) && in_array( filter_input( INPUT_GET, 'action' ), [ 'error_scrape', 'activate' ] ) );

	}

	/**
	 * Fix textdomain paths for bundled plugins.
	 *
	 * @filter load_textdomain_mofile
	 *
	 * @param  string $mofile
	 * @param  string $domain
	 *
	 * @return string
	 */
	public function load_textdomain_mofile( $mofile, $domain ) {

		$path = Plugin::base_dir() . sprintf( 'plugins/%1$s/languages/%1$s-%2$s.mo', $domain, get_locale() );

		return is_readable( $path ) ? $path : $mofile;

	}

}
