<?php

final class FL_Debug {

	static private $tests = array();

	public static function init() {
		if ( isset( $_GET['fldebug'] ) && get_option( 'fl_debug_mode', false ) === $_GET['fldebug'] ) {
			self::prepare_tests();
			self::display_tests();
		}
	}
	private static function display_tests() {

		header( 'Content-Type:text/plain' );

		foreach ( (array) self::$tests as $test ) {
			echo self::display( $test );
		}
		die();
	}

	private static function display( $test ) {

		if ( is_array( $test['data'] ) ) {
			$test['data'] = implode( "\n", $test['data'] );
		}
		return sprintf( "%s\n%s\n\n", $test['name'], $test['data'] );
	}

	private static function register( $slug, $args ) {
		self::$tests[ $slug ] = $args;
	}

	private static function formatbytes( $size, $precision = 2 ) {
		$base = log( $size, 1024 );
		$suffixes = array( '', 'K', 'M', 'G', 'T' );

		return round( pow( 1024, $base - floor( $base ) ), $precision ) . $suffixes[ floor( $base ) ];
	}

	private static function get_plugins() {

		$plugins = array();
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		require_once( ABSPATH . 'wp-admin/includes/update.php' );

		$plugins_data = get_plugins();

		foreach ( $plugins_data as $plugin_path => $plugin ) {
			$plugins[] = sprintf( '%s - version %s by %s. [%s]', $plugin['Name'], $plugin['Version'], $plugin['Author'], ( is_plugin_active( $plugin_path ) ? __( 'Enabled' ) : __( 'Disabled' ) ) );
		}
		return $plugins;
	}

	private static function get_mu_plugins() {
		$plugins_data = get_mu_plugins();
		$plugins = array();

		foreach ( $plugins_data as $plugin_path => $plugin ) {
			$plugins[] = sprintf( '%s version %s by %s', $plugin['Name'], $plugin['Version'], $plugin['Author'] );
		}
		return $plugins;
	}

	private static function safe_ini_get( $ini ) {
		return @ini_get( $ini ); // @codingStandardsIgnoreLine
	}

	private static function divider() {
		return '----------------------------------------------';
	}

	private static function prepare_tests() {

		global $wpdb, $wp_version;

		$args = array(
			'name' => 'WordPress',
			'data' => self::divider(),
		);
		self::register( 'wp', $args );

		$args = array(
			'name' => 'WordPress Address',
			'data' => get_option( 'siteurl' ),
		);
		self::register( 'wp_url', $args );

		$args = array(
			'name' => 'Site Address',
			'data' => get_option( 'home' ),
		);
		self::register( 'site_url', $args );

		$args = array(
			'name' => 'WP Version',
			'data' => $wp_version,
		);
		self::register( 'wp_version', $args );

		$args = array(
			'name' => 'WP Debug',
			'data' => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Yes' : 'No',
		);
		self::register( 'wp_debug', $args );

		$args = array(
			'name' => 'FL Debug',
			'data' => FLBuilder::is_debug() ? 'Yes' : 'No',
		);
		self::register( 'fl_debug', $args );

		$args = array(
			'name' => 'SSL Enabled',
			'data' => is_ssl() ? 'Yes' : 'No',
		);
		self::register( 'wp_ssl', $args );

		$args = array(
			'name' => 'Language',
			'data' => get_locale(),
		);
		self::register( 'lang', $args );

		$args = array(
			'name' => 'Multisite',
			'data' => is_multisite() ? 'Yes' : 'No',
		);
		self::register( 'is_multi', $args );

		$theme = wp_get_theme();
		$args = array(
			'name' => 'Active Theme',
			'data' => array(
				sprintf( '%s - v%s', $theme->get( 'Name' ), $theme->get( 'Version' ) ),
				sprintf( 'Parent Theme: %s', ( $theme->get( 'Template' ) ) ? $theme->get( 'Template' ) : 'Not a child theme' ),
			),
		);
		self::register( 'active_theme', $args );

		$args = array(
			'name' => 'WordPress memory limit',
			'data' => WP_MAX_MEMORY_LIMIT,
		);
		self::register( 'wp_max_mem', $args );

		$args = array(
			'name' => 'Plugins',
			'data' => self::divider(),
		);
		self::register( 'wp_plugins', $args );

		$args = array(
			'name' => 'Must-Use Plugins',
			'data' => self::get_mu_plugins(),
		);
		self::register( 'mu_plugins', $args );

		$args = array(
			'name' => 'Plugins',
			'data' => self::get_plugins(),
		);
		self::register( 'wp_plugins', $args );

		$args = array(
			'name' => 'PHP',
			'data' => self::divider(),
		);
		self::register( 'php', $args );

		$args = array(
			'name' => 'PHP SAPI',
			'data' => php_sapi_name(),
		);
		self::register( 'php_sapi', $args );

		$args = array(
			'name' => 'PHP Memory Limit',
			'data' => self::safe_ini_get( 'memory_limit' ),
		);
		self::register( 'php_mem_limit', $args );

		$args = array(
			'name' => 'PHP Version',
			'data' => phpversion(),
		);
		self::register( 'php_ver', $args );

		$args = array(
			'name' => 'Post Max Size',
			'data' => self::safe_ini_get( 'post_max_size' ),
		);
		self::register( 'post_max', $args );

		$args = array(
			'name' => 'PHP Max Input Vars',
			'data' => self::safe_ini_get( 'max_input_vars' ),
		);
		self::register( 'post_max_input', $args );

		$args = array(
			'name' => 'PHP Max Execution Time',
			'data' => self::safe_ini_get( 'max_execution_time' ),
		);
		self::register( 'post_max_time', $args );

		$args = array(
			'name' => 'Max Upload Size',
			'data' => self::formatbytes( wp_max_upload_size() ),
		);
		self::register( 'post_max_upload', $args );

		$curl = ( function_exists( 'curl_version' ) ) ? curl_version() : false;
		$args = array(
			'name' => 'Curl',
			'data' => ( $curl ) ? sprintf( '%s - %s', $curl['version'], $curl['ssl_version'] ) : 'Not Enabled.',
		);
		self::register( 'curl', $args );

		$args = array(
			'name' => 'PCRE Backtrack Limit ( default 1000000 )',
			'data' => self::safe_ini_get( 'pcre.backtrack_limit' ),
		);
		self::register( 'backtrack', $args );

		$args = array(
			'name' => 'PCRE Recursion Limit ( default 100000 )',
			'data' => self::safe_ini_get( 'pcre.recursion_limit' ),
		);
		self::register( 'recursion', $args );

		$args = array(
			'name' => 'BB Products',
			'data' => self::divider(),
		);
		self::register( 'bb', $args );

		$args = array(
			'name' => 'Beaver Builder',
			'data' => FL_BUILDER_VERSION,
		);
		self::register( 'bb_version', $args );

		$args = array(
			'name' => 'Beaver Themer',
			'data' => ( defined( 'FL_THEME_BUILDER_VERSION' ) ) ? FL_THEME_BUILDER_VERSION : 'Not active/installed.',
		);
		self::register( 'themer_version', $args );

		$args = array(
			'name' => 'Beaver Theme',
			'data' => ( defined( 'FL_THEME_VERSION' ) ) ? FL_THEME_VERSION : 'Not active/installed.',
		);
		self::register( 'theme_version', $args );

		$args = array(
			'name' => 'Cache Folders',
			'data' => self::divider(),
		);
		self::register( 'cache_folders', $args );

		$cache = FLBuilderModel::get_cache_dir();

		$args = array(
			'name' => 'Beaver Builder Path',
			'data' => $cache['path'],
		);
		self::register( 'bb_cache_path', $args );

		$args = array(
			'name' => 'Beaver Builder Path writable',
			'data' => ( is_writable( $cache['path'] ) ) ? 'Yes' : 'No',
		);
		self::register( 'bb_cache_path_writable', $args );

		if ( class_exists( 'FLCustomizer' ) ) {
			$cache = FLCustomizer::get_cache_dir();

			$args = array(
				'name' => 'Beaver Theme Path',
				'data' => $cache['path'],
			);
			self::register( 'bb_theme_cache_path', $args );

			$args = array(
				'name' => 'Beaver Theme Path writable',
				'data' => ( is_writable( $cache['path'] ) ) ? 'Yes' : 'No',
			);
			self::register( 'bb_theme_cache_path_writable', $args );
		}

		$args = array(
			'name' => 'License',
			'data' => self::divider(),
		);
		self::register( 'license', $args );

		if ( true === FL_BUILDER_LITE ) {
			$args = array(
				'name' => 'Beaver Builder License',
				'data' => 'Lite version detected',
			);
			self::register( 'bb_sub_lite', $args );

		} else {
			$subscription = FLUpdater::get_subscription_info();
			$args = array(
				'name' => 'Beaver Builder License',
				'data' => ( $subscription->active ) ? 'Active' : 'Not Active',
			);
			self::register( 'bb_sub', $args );

			if ( isset( $subscription->error ) ) {
				$args = array(
					'name' => 'License Error',
					'data' => $subscription->error,
				);
				self::register( 'bb_sub_err', $args );
			}

			if ( isset( $subscription->domain ) ) {
				$args = array(
					'name' => 'Domain Active',
					'data' => ( '1' == $subscription->domain->active ) ? 'Yes' : 'No',
				);
				self::register( 'bb_sub_domain', $args );
			}
		}

		$args = array(
			'name' => 'Server',
			'data' => self::divider(),
		);
		self::register( 'serv', $args );

		$args = array(
			'name' => 'MySQL Version',
			'data' => ( ! empty( $wpdb->is_mysql ) ? $wpdb->db_version() : 'Unknown' ),
		);
		self::register( 'mysql_version', $args );

		$args = array(
			'name' => 'Server Info',
			'data' => $_SERVER['SERVER_SOFTWARE'],
		);
		self::register( 'server', $args );
	}
}
add_action( 'init', array( 'FL_Debug', 'init' ) );
