<?php

namespace WPaaS\Log\Components;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Plugin_Helpers {

	/**
	 * Array of cached plugin data (before actions).
	 *
	 * @var array
	 */
	protected $plugins = [];

	/**
	 * Wrapper for get_plugins().
	 *
	 * @return array
	 */
	protected function get_plugins() {

		if ( ! function_exists( 'get_plugins' ) ) {

			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		}

		$plugins = get_plugins();

		foreach ( $plugins as $plugin => &$data ) {

			$data['Slug'] = ( false === strpos( $plugin, '/' ) ) ? basename( $plugin, '.php' ) : dirname( $plugin );

		}

		return $plugins;

	}

	/**
	 * Return data for a specified plugin.
	 *
	 * @param  string $plugin
	 * @param  string $data    (optional)
	 *
	 * @return mixed
	 */
	protected function get_plugin_data( $plugin, $data = '' ) {

		$plugins = $this->get_plugins();

		if ( ! isset( $plugins[ $plugin ] ) ) {

			return;

		}

		if ( ! $data ) {

			return (array) $plugins[ $plugin ];

		}

		if ( ! isset( $plugins[ $plugin ][ $data ] ) ) {

			return;

		}

		return $plugins[ $plugin ][ $data ];

	}

	/**
	 * Return an array of meta for a plugin log.
	 *
	 * @param string $plugin
	 * @param bool   $network_wide (optional)
	 *
	 * @return array
	 */
	protected function get_log_meta( $plugin, $network_wide = null ) {

		$meta = [
			'name'     => $this->get_plugin_data( $plugin, 'Name' ),
			'version'  => $this->get_plugin_data( $plugin, 'Version' ),
			'slug'     => $this->get_plugin_data( $plugin, 'Slug' ),
			'basename' => $plugin,
		];

		// Whether the plugin originated from WPEM
		if ( \WPaaS\Plugin::has_used_wpem() ) {

			$meta['wpem_plugin'] = in_array( $plugin, (array) get_option( 'wpem_plugins', [] ), true ) ? $meta['slug'] : false;

		}

		// Include network-wide meta, if available
		if ( null !== $network_wide ) {

			$meta['network_wide'] = (bool) $network_wide;

		}

		return $meta;

	}

}
