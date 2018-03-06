<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Helpers {

	/**
	 * Return the plugin version.
	 *
	 * @return string|false
	 */
	public static function version() {

		return Plugin::$data['version'];

	}

	/**
	 * Return the plugin basename.
	 *
	 * @return string|false
	 */
	public static function basename() {

		return Plugin::$data['basename'];

	}

	/**
	 * Return the plugin base directory path (with trailing slash).
	 *
	 * @return string|false
	 */
	public static function base_dir() {

		return Plugin::$data['base_dir'];

	}

	/**
	 * Return the plugin assets URL (with trailing slash).
	 *
	 * @param  string $path (optional)
	 *
	 * @return string|false
	 */
	public static function assets_url( $path = '' ) {

		$path = ( 0 === strpos( $path, '/' ) ) ? $path : '/' . $path;

		return ( Plugin::$data['assets_url'] ) ? untrailingslashit( Plugin::$data['assets_url'] ) . $path : false;

	}

	/**
	 * Return the plugin assets directory path (with trailing slash).
	 *
	 * @param  string $path (optional)
	 *
	 * @return string|false
	 */
	public static function assets_dir( $path = '' ) {

		$path = ( 0 === strpos( $path, '/' ) ) ? $path : '/' . $path;

		return ( Plugin::$data['assets_url'] ) ? untrailingslashit( Plugin::$data['assets_dir'] ) . $path : false;

	}

	/**
	 * Return a plugin config.
	 *
	 * @param  string $config
	 *
	 * @return mixed|false
	 */
	public static function config( $config ) {

		return self::$configs->get( $config );

	}

	/**
	 * Check if the site locale is English.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_english() {

		$result = ( 'en' === substr( get_locale(), 0, 2 ) );

		/**
		 * Filter if the site locale is English.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_english', $result );

	}

	/**
	 * Return an array of supported brands.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public static function brands() {

		$brands = [ 'gd', 'mt', 'reseller' ];

		/**
		 * Filter the array of supported brands.
		 *
		 * @since 3.1.0
		 *
		 * @var array
		 */
		return (array) apply_filters( 'wpaas_brands', $brands );

	}

	/**
	 * Return the current brand.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	public static function brand() {

		$brand  = ( self::reseller_id() ) ? 'reseller' : null; // Default
		$brands = array_diff( self::brands(), [ 'reseller' ] ); // Non-default

		foreach ( $brands as $brandname ) {

			$callback = 'is_' . trim( $brandname );

			if ( is_callable( [ __CLASS__, $callback ] ) && self::$callback() ) {

				$brand = $brandname;

				break;

			}

		}

		/**
		 * Filter the current brand.
		 *
		 * @since 3.1.0
		 *
		 * @var string
		 */
		return (string) apply_filters( 'wpaas_brand', $brand );

	}

	/**
	 * Return the value whose array key matches the current brand.
	 *
	 * @since 3.1.0
	 *
	 * @param  array $values
	 * @param  mixed $default (optional)
	 *
	 * @return mixed
	 */
	public static function use_brand_value( $values, $default = null ) {

		return isset( $values[ self::brand() ] ) ? $values[ self::brand() ] : $default;

	}

	/**
	 * Check if this is a reseller site.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_reseller() {

		$result = ( 'reseller' === self::brand() );

		/**
		 * Filter if this is a reseller site.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_reseller', $result );

	}

	/**
	 * Check if this is a GD site.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_gd() {

		$result = ( 1 === self::reseller_id() );

		/**
		 * Filter if this is a GD site.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_gd', $result );

	}

	/**
	 * Check if this is a MT site.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_mt() {

		$result = ( 495469 === self::reseller_id() );

		/**
		 * Filter if this is a MT site.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_mt', $result );

	}

	/**
	 * Check if a given URL is using www.
	 *
	 * @param  string $url (optional)
	 *
	 * @return bool
	 */
	public static function is_www_url( $url = '' ) {

		$url = ( $url ) ? $url : ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );

		return ( 0 === strpos( parse_url( $url, PHP_URL_HOST ), 'www.' ) );

	}

	/**
	 * Check if this site is using HTTPS.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_ssl_site() {

		$result = self::is_ssl_url( get_home_url() );

		/**
		 * Filter if this site is using HTTPS.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_ssl_site', $result );

	}

	/**
	 * Check if a given URL is using HTTPS.
	 *
	 * @param  string $url (optional)
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_ssl_url( $url = '' ) {

		$url = ( $url ) ? $url : ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );

		return ( 0 === strpos( $url, 'https://' ) );

	}

	/**
	 * Check if the WP Admin should be forced SSL.
	 *
	 * @return bool
	 */
	public static function is_ssl_admin() {

		return ( defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN );

	}

	/**
	 * Check if the login should be forced SSL.
	 *
	 * @return bool
	 */
	public static function is_ssl_login() {

		return ( defined( 'FORCE_SSL_LOGIN' ) && FORCE_SSL_LOGIN );

	}

	/**
	 * Check if this is a staging site.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_staging_site() {

		$result = defined( 'GD_STAGING_SITE' ) ? GD_STAGING_SITE : false;

		/**
		 * Filter if this is a staging site.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_staging_site', $result );

	}

	/**
	 * Get the current environment type.
	 *
	 * @return string
	 */
	public static function get_env() {

		preg_match( '/\.(.*?)\-/', parse_url( self::config( 'cname_link' ), PHP_URL_HOST ), $matches );

		$result = empty( $matches[1] ) ? 'prod' : $matches[1];

		/**
		 * Filter the current environment type.
		 *
		 * @since 2.0.1
		 *
		 * @var string
		 */
		return (string) apply_filters( 'wpaas_get_env', $result );

	}

	/**
	 * Check for a specific environment.
	 *
	 * @param  string|array $env
	 *
	 * @return bool
	 */
	public static function is_env( $env ) {

		$current = self::get_env();
		$result  = is_array( $env ) ? in_array( $current, $env ) : ( $env === $current );

		/**
		 * Filter the check for a specific environment.
		 *
		 * @since 2.0.1
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_env', $result );

	}

	/**
	 * Check if this is a temporary domain.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_temp_domain() {

		$result = false;

		if ( self::is_staging_site() ) {

			$result = true;

		}

		foreach ( (array) self::config( 'cname_domains' ) as $domain ) {

			if ( 0 === strcasecmp( substr( self::domain(), 0 - strlen( $domain ) ), $domain ) ) {

				$result = true;

			}

		}

		/**
		 * Filter if this is a temporary domain.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_temp_domain', $result );

	}

	/**
	 * Check if this site is in multiple domain mode.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_multi_domain_mode() {

		$result = get_option( 'gd_system_multi_domain' );

		/**
		 * Filter if this site is in multiple domain mode.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_multi_domain_mode', ( false !== $result ) );

	}

	/**
	 * Check if this site is hosted on WPaaS.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_wpaas() {

		$result = ( self::$configs->exist() || false !== strpos( gethostname(), '.secureserver.net' ) );

		/**
		 * Filter if this site is hosted on WPaaS.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'is_wpaas', $result );

	}

	/**
	 * Check if the log is enabled.
	 *
	 * @return bool
	 */
	public static function is_log_enabled() {

		/**
		 * Filter if the log is enabled.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_log_enabled', true );

	}

	/**
	 * Check if the file editor has been enabled.
	 *
	 * @return bool
	 */
	public static function is_file_editor_enabled() {

		return ( 1 === (int) get_site_option( 'wpaas_file_editor_enabled' ) );

	}

	/**
	 * Return the date this site was created.
	 *
	 * @param string $format (optional)
	 *
	 * @since 2.0.0
	 *
	 * @return int|string
	 */
	public static function site_created_date( $format = 'U' ) {

		// Use when this constant was introduced as default (Tue, 22 Dec 2015 00:00:00 GMT)
		$time   = defined( 'GD_SITE_CREATED' ) ? (int) GD_SITE_CREATED : 1450742400;
		$format = empty( $format ) ? 'U' : $format;
		$date   = ( 'U' === $format ) ? $time : gmdate( $format, $time );

		/**
		 * Filter the date this site was created.
		 *
		 * @since 2.0.0
		 *
		 * @var int|string
		 */
		return apply_filters( 'wpaas_site_created_date', $date );

	}

	/**
	 * Return the date of the first Administrator login.
	 *
	 * @param string $format (optional)
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public static function first_login_date( $format = 'U' ) {

		$time   = (int) get_option( 'gd_system_first_login' );
		$format = empty( $format ) ? 'U' : $format;
		$date   = ( $time && 'U' === $format ) ? $time : ( $time ? gmdate( $format, $time ) : false );

		return $date;

	}

	/**
	 * Return the date of the last Administrator login.
	 *
	 * @param string $format (optional)
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public static function last_login_date( $format = 'U' ) {

		$time   = (int) get_option( 'gd_system_last_login' );
		$format = empty( $format ) ? 'U' : $format;
		$date   = ( $time && 'U' === $format ) ? $time : ( $time ? gmdate( $format, $time ) : false );

		return $date;

	}

	/**
	 * Return the date of the first publish activity.
	 *
	 * @param string $format (optional)
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public static function first_publish_date( $format = 'U' ) {

		$time   = (int) get_option( 'gd_system_first_publish' );
		$format = empty( $format ) ? 'U' : $format;
		$date   = ( $time && 'U' === $format ) ? $time : ( $time ? gmdate( $format, $time ) : false );

		return $date;

	}

	/**
	 * Return the date of the last publish activity.
	 *
	 * @param string $format (optional)
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public static function last_publish_date( $format = 'U' ) {

		$time   = (int) get_option( 'gd_system_last_publish' );
		$format = empty( $format ) ? 'U' : $format;
		$date   = ( $time && 'U' === $format ) ? $time : ( $time ? gmdate( $format, $time ) : false );

		return $date;

	}

	/**
	 * Return the last cache flush date.
	 *
	 * @param string $format (optional)
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public static function last_cache_flush_date( $format = 'U' ) {

		$time   = (int) get_option( 'gd_system_last_cache_flush' );
		$format = empty( $format ) ? 'U' : $format;
		$date   = ( $time && 'U' === $format ) ? $time : ( $time ? gmdate( $format, $time ) : false );

		/**
		 * Filter the last cache flush date.
		 *
		 * @since 2.0.0
		 *
		 * @var mixed
		 */
		return apply_filters( 'wpaas_last_cache_flush_date', $date );

	}

	/**
	 * Check if this site has WPEM enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_wpem_enabled() {

		$result = defined( 'GD_EASY_MODE' ) ? GD_EASY_MODE : false;

		/**
		 * Filter if this site has WPEM enabled.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_wpem_enabled', $result );

	}

	/**
	 * Check if this site is doing WPEM steps.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_doing_wpem() {

		$result = ( self::is_wpem_enabled() && defined( 'WPEM_DOING_STEPS' ) && WPEM_DOING_STEPS );

		/**
		 * Filter if this site is doing WPEM steps.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_is_doing_wpem', $result );

	}

	/**
	 * Check if this site has used WPEM (not opted-out).
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function has_used_wpem() {

		$result = ( self::is_wpem_enabled() && get_option( 'wpem_done' ) && ! get_option( 'wpem_opt_out' ) );

		/**
		 * Filter if this site has used WPEM (not opted-out).
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		return (bool) apply_filters( 'wpaas_has_used_wpem', $result );

	}

	/**
	 * Return the country code determined during WPEM.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false
	 */
	public static function wpem_country_code() {

		$wpem_log     = json_decode( get_option( 'wpem_log' ) );
		$country_code = ! empty( $wpem_log->geodata->country_code ) ? $wpem_log->geodata->country_code : false;

		/**
		 * Filter the WPEM country code.
		 *
		 * @since 2.0.0
		 *
		 * @var string|false
		 */
		return apply_filters( 'wpaas_wpem_country_code', $country_code );

	}

	/**
	 * Return true if using a gd theme
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public static function is_using_gd_theme() {

		$theme = wp_get_theme();

		return 'GoDaddy' === $theme->get( 'Author' ) ? true : false;

	}

	/**
	 * Return the reseller ID.
	 *
	 * @since 2.0.0
	 *
	 * @return int|false
	 */
	public static function reseller_id() {

		return defined( 'GD_RESELLER' ) ? (int) GD_RESELLER : false;

	}

	/**
	 * Return the site domain.
	 *
	 * @return string
	 */
	public static function domain() {

		return parse_url( home_url(), PHP_URL_HOST );

	}

	/**
	 * Return the external URL to manage account settings.
	 *
	 * @param string $config
	 *
	 * @return string|null
	 */
	public static function account_settings_url( $config = 'gateway_url' ) {

		$url = self::config( $config );

		if ( ! $url || ! self::reseller_id() ) {

			return null;

		}

		return str_replace(
			[
				'%domain%',
				'%pl_id%',
			],
			[
				self::domain(),
				self::reseller_id(),
			],
			self::config( $config )
		);

	}

	/**
	 * Return the VIP.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false
	 */
	public static function vip() {

		return defined( 'GD_VIP' ) ? (string) GD_VIP : false;

	}

	/**
	 * Return the account ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function account_id() {

		$account_id = self::is_wp_cli() ? basename( dirname( ABSPATH ) ) : ( ! empty( $_SERVER['REAL_USERNAME'] ) ? $_SERVER['REAL_USERNAME'] : false );

		/**
		 * Filter the account ID.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		return (string) apply_filters( 'wpaas_account_id', $account_id );

	}

	/**
	 * Return the ASAP key.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false
	 */
	public static function asap_key() {

		$asap_key = defined( 'GD_ASAP_KEY' ) ? (string) GD_ASAP_KEY : false;

		/**
		 * Filter the ASAP key.
		 *
		 * @since 2.0.0
		 *
		 * @var string|false
		 */
		return apply_filters( 'wpaas_asap_key', $asap_key );

	}

	/**
	 * Return the XID.
	 *
	 * @since 2.0.0
	 *
	 * @return int|false
	 */
	public static function xid() {

		$xid = self::is_wp_cli() ? (int) substr( substr( self::account_id(), 4 ), 0, -3 ) : (int) basename( dirname( ABSPATH ) );
		$xid = ( $xid > 1000000 ) ? $xid : false;

		/**
		 * Filter the XID.
		 *
		 * @since 2.0.0
		 *
		 * @var int|false
		 */
		return apply_filters( 'wpaas_xid', $xid );

	}

	/**
	 * Check if the current process is using WP-CLI.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_wp_cli() {

		return ( defined( 'WP_CLI' ) && WP_CLI );

	}

	/**
	 * Base WP-CLI command.
	 *
	 * @return string
	 */
	public static function cli_base_command() {

		$commands = [
			'gd' => 'godaddy',
			'mt' => 'mt',
		];

		$command = self::use_brand_value( $commands, 'wpaas' );

		/**
		 * Filter the base WP-CLI command.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		return (string) apply_filters( 'wpaas_cli_base_command', $command );

	}

	/**
	 * Return a WP-CLI command.
	 *
	 * @since 2.0.0
	 *
	 * @param  string $subcommand
	 * @param  array  $options (optional)
	 * @param  bool   $wp (optional)
	 *
	 * @return string
	 */
	public static function cli_command( $subcommand, array $options = [], $wp = true ) {

		foreach ( $options as $key => &$value ) {

			$value = is_bool( $value ) ? sprintf( '--%s', $key ) : sprintf( '--%s=%s', $key, is_int( $value ) ? $value : escapeshellarg( $value ) );

		}

		return trim(
			sprintf(
				'%s %s %s %s',
				( $wp ) ? 'wp' : null,
				escapeshellcmd( self::cli_base_command() ),
				escapeshellcmd( $subcommand ),
				implode( ' ', $options )
			)
		);

	}

	/**
	 * Return an asyncronous WP-CLI command.
	 *
	 * @since 2.0.0
	 *
	 * @param  string $subcommand
	 * @param  array  $options (optional)
	 * @param  bool   $wp (optional)
	 *
	 * @return string
	 */
	public static function async_cli_command( $subcommand, array $options = [], $wp = true ) {

		return self::cli_command( $subcommand, $options, $wp ) . ' > /dev/null 2>/dev/null &'; // Non-blocking

	}

	/**
	 * Set/update the value of a site transient using a persistent manner. Uses options API.
	 *
	 * You do not need to serialize values, if the value needs to be serialize, then
	 * it will be serialized before it is set.
	 *
	 * @since 2.0.2
	 *
	 * @see set_site_transient()
	 *
	 * @param string $transient  Transient name. Expected to not be SQL-escaped. Must be
	 *                           40 characters or fewer in length.
	 * @param mixed  $value      Transient value. Expected to not be SQL-escaped.
	 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
	 * @return bool False if value was not set and true if value was set.
	 */
	public static function set_persistent_site_transient( $transient, $value, $expiration = 0 ) {

		$transient_timeout = '_site_transient_timeout_' . $transient;
		$option            = '_site_transient_' . $transient;

		if ( false === get_site_option( $option ) ) {

			if ( $expiration ) {

				add_site_option( $transient_timeout, time() + $expiration );

			}

			return add_site_option( $option, $value );

		}

		if ( $expiration ) {

			update_site_option( $transient_timeout, time() + $expiration );

		}

		return update_site_option( $option, $value );

	}

	/**
	 * Transient function that skips object cache check and fallback to db instead.
	 *
	 * @param string $transient Transient name. Expected to not be SQL-escaped.
	 *
	 * @since 2.0.2
	 *
	 * @see get_site_transient()
	 *
	 * @return bool|mixed
	 */
	public static function get_persistent_site_transient( $transient ) {

		$transient_option  = '_site_transient_' . $transient;
		$transient_timeout = '_site_transient_timeout_' . $transient;
		$timeout           = get_site_option( $transient_timeout );

		if ( false !== $timeout && $timeout < time() ) {

			delete_site_option( $transient_option );
			delete_site_option( $transient_timeout );

			$value = false;

		}

		if ( ! isset( $value ) ) {

			$value = get_site_option( $transient_option );

		}

		return $value;

	}

}
