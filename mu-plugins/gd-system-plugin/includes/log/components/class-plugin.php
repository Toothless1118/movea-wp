<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Timer;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Plugin extends Component {

	use Plugin_Helpers;

	/**
	 * Plugin > Activate
	 *
	 * @action activate_plugin
	 *
	 * @param string $plugin
	 * @param bool   $network_wide
	 */
	public function callback_activate_plugin( $plugin, $network_wide ) {

		Timer::stop();

		$summary = _x(
			'"%s" plugin activated',
			'Plugin name',
			'gd-system-plugin'
		);

		if ( $network_wide ) {

			$summary = _x(
				'"%s" plugin activated network wide',
				'Plugin name',
				'gd-system-plugin'
			);

		}

		$this->log_metric( 'publish' );

		$this->log( 'activate', $summary, $this->get_log_meta( $plugin, $network_wide ) );

	}

	/**
	 * Plugin > Deactivate
	 *
	 * @action deactivate_plugin
	 *
	 * @param string $plugin
	 * @param bool   $network_wide
	 */
	public function callback_deactivate_plugin( $plugin, $network_wide ) {

		Timer::stop();

		$summary = _x(
			'"%s" plugin deactivated',
			'Plugin name',
			'gd-system-plugin'
		);

		if ( $network_wide ) {

			$summary = _x(
				'"%s" plugin deactivated network wide',
				'Plugin name',
				'gd-system-plugin'
			);

		}

		$this->log_metric( 'publish' );

		$this->log( 'deactivate', $summary, $this->get_log_meta( $plugin, $network_wide ) );

	}

	/**
	 * Plugin > Delete
	 *
	 * @action delete_plugin
	 *
	 * @param string $plugin
	 */
	public function callback_delete_plugin( $plugin ) {

		Timer::stop();

		$this->log(
			'delete',
			_x(
				'"%s" plugin deleted',
				'Plugin name',
				'gd-system-plugin'
			),
			$this->get_log_meta( $plugin )
		);

	}

	/**
	 * Before plugin upgrades.
	 *
	 * @param  array $options
	 *
	 * @return array
	 */
	public function callback_upgrader_package_options( $options ) {

		if ( ! $this->plugins && isset( $options['hook_extra']['plugin'] ) ) {

			$this->plugins = $this->get_plugins();

		}

		return $options;

	}

	/**
	 * Plugin > Install
	 * Plugin > Update
	 *
	 * @param \Plugin_Upgrader $upgrader
	 * @param array            $data
	 */
	public function callback_upgrader_process_complete( $upgrader, $data ) {

		if (
			! is_a( $upgrader, 'Plugin_Upgrader' )
			||
			'plugin' !== $data['type']
			||
			! in_array( $data['action'], [ 'install', 'update' ] )
		) {

			return;

		}

		if ( 'install' === $data['action'] ) {

			$this->plugin_install( $upgrader );

			return;

		}

		wp_clean_plugins_cache();

		$bulk    = ( ! empty( $data['bulk'] ) && true === $data['bulk'] );
		$plugins = ( $bulk ) ? $data['plugins'] : [ $upgrader->result['destination_name'] ];

		foreach ( $plugins as $plugin ) {

			$this->plugin_update( $plugin, $bulk );

		}

	}

	/**
	 * Plugin > Install
	 *
	 * @param \Plugin_Upgrader $upgrader
	 */
	private function plugin_install( $upgrader ) {

		Timer::stop();

		unset( $this->plugins );

		$plugin      = $upgrader->plugin_info();
		$plugin_data = get_plugin_data( trailingslashit( $upgrader->result['local_destination'] ) . $plugin );

		$meta = array_merge(
			$this->get_log_meta( $plugin ),
			[
				'name'     => $plugin_data['Name'],
				'version'  => $plugin_data['Version'],
				'slug'     => $upgrader->result['destination_name'],
			]
		);

		$this->log(
			'install',
			_x(
				'"%s" plugin installed',
				'Plugin name',
				'gd-system-plugin'
			),
			$meta
		);

	}

	/**
	 * Plugin > Update
	 *
	 * @param string $plugin
	 * @param bool   $bulk
	 */
	private function plugin_update( $plugin, $bulk ) {

		Timer::stop();

		$version = $this->get_plugin_data( $plugin, 'Version' );

		$meta = array_merge(
			[
				'name'        => $this->get_plugin_data( $plugin, 'Name' ),
				'old_version' => ! empty( $this->plugins[ $plugin ]['Version'] ) ? $this->plugins[ $plugin ]['Version'] : $version,
				'new_version' => $version,
				'bulk'        => (bool) $bulk,
			],
			$this->get_log_meta( $plugin )
		);

		unset( $meta['version'] ); // Named `new_version` in this log

		if ( empty( $meta['old_version'] ) || $meta['old_version'] === $meta['new_version'] ) {

			$summary = _x(
				'"%1$s" plugin updated to %2$s',
				'1: Plugin name, 2: New plugin version',
				'gd-system-plugin'
			);

		} else {

			$summary = _x(
				'"%1$s" plugin updated from %2$s to %3$s',
				'1: Plugin name, 2: Old plugin version, 3: New plugin version',
				'gd-system-plugin'
			);

		}

		$this->log( 'update', $summary, $meta );

	}

}
