<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Admin {

	use Data;

	/**
	 * Holds the image api instance
	 *
	 * @var object
	 */
	private $image_api;

	/**
	 * Holds the Log instance
	 *
	 * @var object
	 */
	private $log;

	/**
	 * Class constructor
	 */
	public function __construct() {

		$this->cap   = 'manage_options';
		$this->steps = [];

		add_action( 'init', [ $this, 'load' ] );

		add_action( 'wp_ajax_store_location_change', [ $this, 'wpem_store_location_change' ] );

	}

	/**
	 * Return an array of steps
	 *
	 * @return array
	 */
	public function get_steps() {

		$steps = (array) $this->steps;

		if ( ! $steps ) {

			return [];

		}

		return $steps;

	}

	/**
	 * Load admin area
	 *
	 * @action init
	 */
	public function load() {

		if ( ! current_user_can( $this->cap ) ) {

			return;

		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

			return;

		}

		$this->log       = new Log;
		$this->image_api = new Image_API;

		$this->register_steps();

		$this->maybe_force_redirect();

		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'submit' ] );
		add_action( 'admin_init', [ $this, 'screen' ] );

		/**
		 * Allow 3-letter accounts to customize the API URL via their username.
		 *
		 * Example: `wpnuxstg` will use `wpnux.stg-godaddy.com`
		 */
		add_filter( 'wpem_plugin_api_url', function( $url ) {

			$user = wp_get_current_user();

			preg_match( '/^wpnux(\w+)$/', $user->user_login, $matches );

			$prefix = ! empty( $matches[1] ) ? $matches[1] : '';

			$is_three_letter     = in_array( preg_replace( '/^.*@/', '', $user->user_email ), [ 'godaddy.com', 'mediatemple.net' ], true );
			$is_supported_prefix = in_array( $prefix, [ 'stg', 'ote', 'lt', 'test', 'dev' ], true );

			return ( $is_three_letter && $is_supported_prefix ) ? "https://wpnux.{$prefix}-godaddy.com/" : $url;

		} );

		add_filter( 'option_blogname', function( $value ) {

			return wpem_get_step_field( 'blogname', 'settings', $value );

		} );

		add_filter( 'option_blogdescription', function( $value ) {

			return wpem_get_step_field( 'blogdescription', 'settings', $value );

		} );

	}

	/**
	 * Determine if we are viewing the wizard
	 *
	 * @return bool
	 */
	public function is_wizard() {

		return ( current_user_can( $this->cap ) && wpem()->page_slug === filter_input( INPUT_GET, 'page' ) );

	}

	/**
	 * Register the steps used by the wizard
	 */
	private function register_steps() {

		// Some steps depend on the image api
		$this->steps = [
			new Step_Start( $this->log ),
			new Step_Settings( $this->log, $this->image_api ),
			new Step_Contact( $this->log ),
			new Step_Theme( $this->log, $this->image_api ),
		];

		foreach ( $this->steps as $i => $step ) {

			$step->position = $i + 1;

			$step->url = add_query_arg(
				[
					'step' => $step->name,
				],
				wpem_get_wizard_url()
			);

		}

		$this->last_viewed = $this->get_step_by( 'name', get_option( 'wpem_last_viewed', 'start' ) );

	}

	/**
	 * Force the wizard to be completed
	 */
	private function maybe_force_redirect() {

		if ( ! $this->is_wizard() ) {

			wp_safe_redirect( $this->last_viewed->url );

			exit;

		}

		$current_step = wpem_get_current_step();

		if ( $current_step->position <= $this->last_viewed->position ) {

			return;

		}

		$steps = array_slice( $this->get_steps(), $this->last_viewed->position - 1 );

		foreach ( $steps as $step ) {

			if ( $step->position === $current_step->position ) {

				break;

			}

			if ( ! $step->can_skip ) {

				wp_safe_redirect( $step->url );

				exit;

			}

		}

	}

	/**
	 * Register admin menu and assets
	 *
	 * @action admin_menu
	 */
	public function menu() {

		add_dashboard_page(
			_x( 'WP Easy Mode', 'Main plugin title', 'wp-easy-mode' ),
			_x( 'Easy Mode', 'Menu title', 'wp-easy-mode' ),
			$this->cap,
			wpem()->page_slug,
			[ $this, 'screen' ]
		);

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_register_style(
			'font-awesome',
			wpem()->assets_url . 'css/font-awesome.min.css',
			[],
			'4.5.0'
		);

		wp_register_style(
			'wpem-fullscreen',
			wpem()->assets_url . "css/fullscreen{$suffix}.css",
			[ 'dashicons', 'buttons', 'install', 'themes' ],
			wpem()->version
		);

		wp_register_script(
			'jquery-blockui',
			wpem()->assets_url . 'js/jquery.blockui.min.js',
			[ 'jquery' ],
			'2.70.0'
		);

		wp_register_script(
			'wpem',
			wpem()->assets_url . "js/common{$suffix}.js",
			[ 'jquery' ],
			wpem()->version
		);

		wp_register_script(
			'wpem-contact',
			wpem()->assets_url . "js/contact{$suffix}.js",
			[ 'wpem' ],
			wpem()->version
		);

		wp_register_script(
			'wpem-theme',
			wpem()->assets_url . "js/theme{$suffix}.js",
			[ 'wpem', 'wp-pointer', 'wpem-pointers' ],
			wpem()->version
		);

		$message = '<div class="wpem-spinner-wrapper"><div class="wpem-spinner">&nbsp;</div></div>';

		if ( 'theme' === wpem_get_current_step()->name ) {

			$message = sprintf(
				'<div><h2>%s</h2><p>%s</p>%s</div>',
				__( 'Installing your theme', 'wp-easy-mode' ),
				__( 'This could take up to 30 seconds. <strong>Do not refresh</strong> the page.', 'wp-easy-mode' ),
				$message
			);

		}

		wp_localize_script(
			'wpem',
			'wpem_vars',
			[
				'step' => wpem_get_current_step()->name,
				'i18n' => [
					'exit_confirm'    => esc_attr__( 'Are you sure you want to exit and configure WordPress on your own?', 'wp-easy-mode' ),
					'loading_message' => $message, // xss ok
				],
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'ajax_nonce'     => wp_create_nonce( 'wpem_ajax_nonce' ),
			]
		);

		// for Select2 used on settings page
		wp_register_script(
			'jquery-select2',
			wpem()->assets_url . "js/select2{$suffix}.js",
			[ 'jquery' ],
			'4.0.2'
		);

		wp_register_script(
			'jquery-select2-driver',
			wpem()->assets_url . "js/select2-driver{$suffix}.js",
			[ 'jquery-select2' ],
			wpem()->version // this JS initializes Select2 and is unique to WP Easy Mode
		);

		wp_register_style(
			'jquery-select2-css',
			wpem()->assets_url . "css/select2{$suffix}.css",
			[],
			'4.0.2'
		);

		/**
		 * Filter the list of themes to display
		 *
		 * @since 1.0.0
		 *
		 * @var array
		 */
		$themes = (array) apply_filters( 'wpem_themes', [] );

		/**
		 * Filter the terms of service URL.
		 *
		 * @since 2.2.0
		 *
		 * @var string
		 */
		$tos_url = (string) apply_filters( 'wpem_tos_url', null );

		switch ( true ) {

			case empty( $tos_url ) :

				$image_license = __( 'Images available and licensed for use are intended for our hosted customers only and are subject to the terms and conditions of third-party intellectual property rights.', 'wp-easy-mode' );

				break;

			case ( is_callable( [ '\WPaaS\Plugin', 'is_gd' ] ) && \WPaaS\Plugin::is_gd() ) :

				$image_license = sprintf(
					__( 'Images available and licensed for use are intended for GoDaddy hosted customers only and are subject to the terms and conditions of third-party intellectual property rights. <a href="%s" target="_blank">See Terms and Conditions</a> for additional details.', 'wp-easy-mode' ),
					esc_url( $tos_url )
				);

				break;

			default :

				$image_license = sprintf(
					__( 'Images available and licensed for use are intended for our hosted customers only and are subject to the terms and conditions of third-party intellectual property rights. <a href="%s" target="_blank">See Terms and Conditions</a> for additional details.', 'wp-easy-mode' ),
					esc_url( $tos_url )
				);

		}

		wp_localize_script(
			'wpem-theme',
			'wpem_theme_vars',
			[
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'ajax_nonce'     => wp_create_nonce( 'wpem_ajax_nonce' ),
				'themes'         => $themes, // xss ok
				'default_themes' => self::get_default_themes(),
				'api_url'        => static::demo_site_url(),
				'preview_url'    => static::demo_site_url(
					[
						'blogname'        => get_option( 'blogname' ),
						'blogdescription' => get_option( 'blogdescription' ),
						'email'           => wpem_get_contact_info( 'email' ),
						'phone'           => wpem_get_contact_info( 'phone' ),
						'fax'             => wpem_get_contact_info( 'fax' ),
						'address'         => wpem_get_contact_info( 'address' ),
						'social'          => implode( ',', wpem_get_social_profiles() ),
					],
					false
				),
				'customizer_url' => wpem_get_customizer_url(
					[
						'return' => admin_url(),
						'wpem'   => 1,
					]
				),
				'i18n' => [
					'expand'        => esc_attr__( 'Expand Sidebar', 'wp-easy-mode' ),
					'collapse'      => esc_attr__( 'Collapse Sidebar', 'wp-easy-mode' ),
					'select'        => esc_attr__( 'Select', 'wp-easy-mode' ),
					'image_license' => $image_license, // xss ok
				],
			]
		);

	}

	/**
	 * Return a URL for the demo API
	 *
	 * @param  array $args (optional)
	 * @param  bool  $hide_empty_args (optional)
	 *
	 * @return string
	 */
	public static function demo_site_url( $args = [], $hide_empty_args = true ) {

		$defaults = [
			'site_type'     => wpem_get_site_type(),
			'site_industry' => wpem_get_site_industry(),
			'lang'          => get_locale(),
			'gem'           => wpem_has_gem_account() ? 1 : 0,
		];

		$args = array_merge( $defaults, $args );
		$args = ( $hide_empty_args ) ? array_filter( $args ) : $args;

		return add_query_arg(
			array_map( 'urlencode', $args ),
			esc_url_raw( wpem()->api_url )
		);

	}

	/**
	 * Return an array of default theme data
	 *
	 * @return array
	 */
	public static function get_default_themes() {

		$themes = [];

		search_theme_directories( true ); // Refresh the transient

		$stylesheets = (array) get_site_transient( 'theme_roots' );

		foreach ( array_keys( $stylesheets ) as $stylesheet ) {

			$theme = wp_get_theme( $stylesheet );

			$themes[] = [
				'author'         => $theme->get( 'Author' ),
				'author_url'     => $theme->get( 'AuthorURI' ),
				'description'    => $theme->get( 'Description' ),
				'name'           => $theme->get( 'Name' ),
				'screenshot_url' => sprintf( '%s/%s/screenshot.png', get_theme_root_uri(), $stylesheet ),
				'slug'           => $stylesheet,
				'template'       => $theme->get( 'Template' ),
				'theme_url'      => $theme->get( 'ThemeURI' ),
				'version'        => $theme->get( 'Version' ),
			];

		}

		return $themes;

	}

	/**
	 * Listen for POST requests and process them
	 *
	 * @action admin_init
	 */
	public function submit() {

		$nonce = filter_input( INPUT_POST, 'wpem_step_nonce' );
		$name  = filter_input( INPUT_POST, 'wpem_step_name' );

		if ( false === wp_verify_nonce( $nonce, sprintf( 'wpem_step_nonce-%s-%d', $name, get_current_user_id() ) ) ) {

			return;

		}

		$step = $this->get_step_by( 'name', $name );

		if ( ! $step ) {

			return;

		}

		$took = filter_input( INPUT_POST, 'wpem_step_took' );

		if ( $took ) {

			$this->log->add_step_time( $took );

		}

		$step->callback();

		$next_step = wpem_get_next_step();

		if ( $next_step ) {

			update_option( 'wpem_last_viewed', $next_step->name );

			wp_safe_redirect( $next_step->url );

			exit;

		}

		new Done( $this->log );

	}

	/**
	 * Register admin menu screen
	 *
	 * @action admin_init
	 */
	public function screen() {

		$template = wpem()->base_dir . 'templates/fullscreen.php';

		if ( is_readable( $template ) ) {

			require_once $template;

			exit;

		}

	}

	/**
	 * Get a step by name or actual position
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 *
	 * @return object
	 */
	public function get_step_by( $field, $value ) {

		$steps = (array) $this->steps;

		if ( empty( $steps ) || empty( $value ) ) {

			return;

		}

		if ( 'name' === $field ) {

			foreach ( $steps as $step ) {

				if ( $step->name !== $value ) {

					continue;

				}

				return $step;

			}

		}

		if ( 'position' === $field && is_numeric( $value ) ) {

			foreach ( $steps as $step ) {

				if ( $step->position !== $value ) {

					continue;

				}

				return $step;

			}

		}

	}

	/**
	 * Get the new tax rates, based on the set country/state
	 *
	 * @return mixed
	 */
	public function wpem_store_location_change() {

		check_ajax_referer( 'wpem_ajax_nonce', 'location_nonce' );

		$location = filter_input( INPUT_POST, 'location', FILTER_SANITIZE_STRING );

		if ( ! $location ) {

			return;

		}

		$store_locale = new Store_Settings();

		wp_send_json_success(
			[
				'tax_table' => $store_locale->woocommerce_tax_table( $location ),
			]
		);

	}

}
