<?php

namespace WPaaS\Admin;

use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Pointers {

	/**
	 * Array of pointer arrays.
	 *
	 * @var array
	 */
	private $pointers = [];

	/**
	 * Class constructor.
	 */
	public function __construct( ) {

		add_action( 'init',                  [ $this, 'register_pointer' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

	}

	/**
	 * Register all pointers on init for i18n.
	 *
	 * @action init
	 */
	public function register_pointer() {

		$this->pointers = [
			[
				'id'               => 'wpaas_admin_bar_buttons',
				'target'           => '#wp-admin-bar-wpaas .ab-icon',
				'cap'              => 'activate_plugins',
				'site_created_max' => '2016-01-14', // Date this feature was deployed
				'options'          => [
					'content'  => wp_kses_post(
						sprintf(
							'<h3>%s</h3><p>%s</p>',
							__( 'Good news!', 'gd-system-plugin' ),
							__( 'You can now access <strong>Flush Cache</strong> and other links directly from the admin bar using your desktop or mobile device.', 'gd-system-plugin' )
						)
					),
					'position' => [
						'edge'  => 'top',
						'align' => 'left',
					],
				],
			]
		];

		/**
		 * Filter admin pointers.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		$this->pointers = (array) apply_filters( 'wpaas_admin_pointers', $this->pointers );

	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @action admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts() {

		if ( ! $this->pointers ) {

			return;

		}

		$pointers = [];

		foreach ( $this->pointers as $pointer ) {

			if ( $this->is_viewable( $pointer ) ) {

				$pointers[] = $pointer;

			}

		}

		if ( ! $pointers ) {

			return;

		}

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'wp-pointer' );

		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script(
			'wpaas-pointers',
			Plugin::assets_url( "js/pointers{$suffix}.js" ),
			[ 'jquery', 'wp-pointer' ],
			Plugin::version(),
			true
		);

		wp_localize_script( 'wpaas-pointers', 'wpaas_pointers', $pointers );

	}

	/**
	 * Check if a pointer is viewable.
	 *
	 * @param  array $pointer
	 *
	 * @return bool
	 */
	private function is_viewable( array $pointer ) {

		// Checking screen
		$should_appear_on_screen = true;

		if ( isset( $pointer['screen'] ) && $pointer['screen'] !== get_current_screen()->id ) {

			$should_appear_on_screen = false;

		}

		// Checking cap
		$user_can_see = current_user_can( ! empty( $pointer['cap'] ) ? $pointer['cap'] : 'read' );

		// Checking date
		$is_before_site_created_max = ! empty( $pointer['site_created_max'] ) ? ( Plugin::site_created_date() <= strtotime( $pointer['site_created_max'] ) ) : true;

		return (
			$user_can_see
			&& ! $this->is_dismissed( $pointer['id'] )
			&& $should_appear_on_screen
			&& $is_before_site_created_max
		);

	}

	/**
	 * Check if a pointer has been dismissed by the current user
	 *
	 * @param  string $pointer_id
	 *
	 * @return bool
	 */
	private function is_dismissed( $pointer_id ) {

		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

		return in_array( $pointer_id, $dismissed );

	}

}
