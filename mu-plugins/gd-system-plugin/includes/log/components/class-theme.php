<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Timer;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Theme extends Component {

	use Theme_Helpers;

	/**
	 * {Theme} > Activate
	 *
	 * @action switch_theme
	 *
	 * @param string    $new_name
	 * @param \WP_Theme $new_theme
	 * @param \WP_Theme $old_theme
	 */
	public function callback_switch_theme( $new_name, $new_theme, $old_theme ) {

		Timer::stop();

		if ( ! is_a( $new_theme, 'WP_Theme' ) || ! is_a( $old_theme, 'WP_Theme' ) ) {

			return; // Something went wrong

		}

		$this->log_metric( 'publish' );

		$meta = $this->get_log_meta( $new_theme );

		foreach ( $this->get_log_meta( $old_theme ) as $key => $value ) {

			$meta[ 'old_' . $key ] = $value;

		}

		$this->log(
			'activate',
			_x(
				'"%s" theme activated',
				'Theme name',
				'gd-system-plugin'
			),
			$meta
		);

	}

	/**
	 * Theme > Install
	 * Theme > Update
	 *
	 * @param \Theme_Upgrader $upgrader
	 * @param array           $data
	 */
	public function callback_upgrader_process_complete( $upgrader, $data ) {

		if (
			! is_a( $upgrader, 'Theme_Upgrader' )
			||
			'theme' !== $data['type']
			||
			! in_array( $data['action'], [ 'install', 'update' ] )
		) {

			return; // Something went wrong

		}

		if ( 'install' === $data['action'] ) {

			$this->theme_install( $upgrader );

			return;

		}

		$bulk   = ( ! empty( $data['bulk'] ) && true === $data['bulk'] );
		$themes = ( $bulk ) ? $data['themes'] : [ $upgrader->result['destination_name'] ];

		foreach ( $themes as $stylesheet ) {

			$this->theme_update( $stylesheet, $bulk );

		}

	}

	/**
	 * Theme > Install
	 *
	 * @param \Theme_Upgrader $upgrader
	 */
	private function theme_install( $upgrader ) {

		Timer::stop();

		$theme = $upgrader->theme_info();

		if ( ! is_a( $theme, 'WP_Theme' ) ) {

			return; // Something went wrong

		}

		$this->log(
			'install',
			_x(
				'"%s" theme installed',
				'Theme name',
				'gd-system-plugin'
			),
			$this->get_log_meta( $theme )
		);

	}

	/**
	 * Theme > Update
	 *
	 * @param string $stylesheet
	 * @param bool   $bulk
	 */
	private function theme_update( $stylesheet, $bulk ) {

		Timer::stop();

		$theme = wp_get_theme( $stylesheet );
		$new   = get_file_data( $theme->get_stylesheet_directory() . '/style.css', [ 'Version' => 'Version' ] );

		$meta = array_merge(
			[
				'name'        => $theme->get( 'Name' ),
				'old_version' => $theme->get( 'Version' ),
				'new_version' => ! empty( $new['Version'] ) ? $new['Version'] : $theme->get( 'Version' ),
				'bulk'        => (bool) $bulk,
			],
			$this->get_log_meta( $theme )
		);

		unset( $meta['version'] ); // Named `old_version` in this log

		if ( empty( $meta['old_version'] ) || $meta['old_version'] === $meta['new_version'] ) {

			$summary = _x(
				'"%1$s" theme updated to %2$s',
				'1: theme name, 2: New theme version',
				'gd-system-plugin'
			);

		} else {

			$summary = _x(
				'"%1$s" theme updated from %2$s to %3$s',
				'1: Theme name, 2: Old theme version, 3: New theme version',
				'gd-system-plugin'
			);

		}

		$this->log( 'update', $summary, $meta );

	}

}
