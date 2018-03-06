<?php

namespace WPaaS\Admin;

use \WPaaS\Cache;
use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Bar {

	/**
	 * Admin bar object.
	 *
	 * @var WP_Admin_Bar
	 */
	private $admin_bar;

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'init', [ $this, 'init' ] );

	}

	/**
	 * Initialize the script.
	 *
	 * @action init
	 */
	public function init() {

		/**
		 * Filter the user cap required to view the admin bar menu.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$cap = (string) apply_filters( 'wpaas_admin_bar_cap', 'activate_plugins' );

		if ( ! current_user_can( $cap ) ) {

			return;

		}

		add_action( 'admin_bar_menu',        [ $this, 'admin_bar_menu' ], PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_scripts' ] );

	}

	/**
	 * Admin bar menu.
	 *
	 * @action admin_bar_menu
	 *
	 * @param \WP_Admin_Bar $admin_bar
	 */
	public function admin_bar_menu( \WP_Admin_Bar $admin_bar ) {

		$this->admin_bar = $admin_bar;

		$menus = [
			'gd' => 'gd_menu',
			'mt' => 'mt_menu',
		];

		$menu = Plugin::use_brand_value( $menus, 'reseller_menu' );

		if ( is_callable( [ $this, $menu ] ) ) {

			$this->$menu();

		}

	}

	/**
	 * Enqueue styles.
	 *
	 * @action admin_enqueue_scripts
	 * @action wp_enqueue_scripts
	 */
	public function enqueue_scripts() {

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'wpaas-admin-bar', Plugin::assets_url( "css/admin-bar{$suffix}.css" ), [], Plugin::version() );

	}

	/**
	 * GoDaddy admin menu.
	 */
	private function gd_menu() {

		$this->top_level_menu_item( __( 'GoDaddy', 'gd-system-plugin' ), 'godaddy-alt' );
		$this->account_settings_menu_item();
		$this->flush_cache_menu_item();

		global $submenu;

		if ( empty( $submenu['godaddy'] ) ) {

			return;

		}

		foreach ( $submenu['godaddy'] as $item ) {

			parse_str( $item[2] );

			$this->admin_bar->add_menu(
				[
					'parent' => 'wpaas',
					'id'     => 'wpaas-' . sanitize_title( ! empty( $tab ) ? $tab : $item[2] ),
					'href'   => $item[2],
					'title'  => $item[0],
				]
			);

		}

	}

	/**
	 * Media Temple admin menu.
	 */
	private function mt_menu() {

		$this->top_level_menu_item( __( 'Media Temple', 'gd-system-plugin' ), 'media-temple' );
		$this->account_settings_menu_item();
		$this->flush_cache_menu_item();

	}

	/**
	 * Reseller admin menu.
	 */
	private function reseller_menu() {

		$this->top_level_menu_item( __( 'Managed WordPress', 'gd-system-plugin' ), 'admin-generic' );
		$this->account_settings_menu_item();
		$this->flush_cache_menu_item();

	}

	/**
	 * Top-level menu item.
	 *
	 * @param string $label
	 * @param string $icon
	 */
	private function top_level_menu_item( $label, $icon ) {

		$this->admin_bar->add_menu(
			[
				'id'    => 'wpaas',
				'title' => sprintf(
					'<span class="ab-icon dashicons dashicons-%s"></span><span class="ab-label">%s</span>',
					esc_attr( $icon ),
					esc_html( $label )
				),
			]
		);

	}

	/**
	 * Flush Cache menu item.
	 */
	private function flush_cache_menu_item() {

		if ( ! current_user_can( Cache::$cap ) ) {

			return;

		}

		$this->admin_bar->add_menu(
			[
				'parent' => 'wpaas',
				'id'     => 'wpaas-flush-cache',
				'title'  => __( 'Flush Cache', 'gd-system-plugin' ),
				'href'   => Cache::get_flush_url(),
			]
		);

	}

	/**
	 * Account Settings menu item.
	 */
	private function account_settings_menu_item() {

		$url = Plugin::account_settings_url();

		if ( ! $url ) {

			return;

		}

		$this->admin_bar->add_menu(
			[
				'parent' => 'wpaas',
				'id'     => 'wpaas-settings',
				'href'   => esc_url( $url ),
				'title'  => sprintf(
					'%s <span class="dashicons dashicons-external"></span>',
					__( 'Account Settings', 'gd-system-plugin' )
				),
				'meta'   => [
					'target' => '_blank',
				]
			]
		);

	}

}
