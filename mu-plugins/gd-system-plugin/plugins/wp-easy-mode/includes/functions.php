<?php

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

/**
 * Return the current step
 *
 * @return object
 */
function wpem_get_current_step() {

	if ( ! \WPEM\wpem()->admin->is_wizard() ) {

		return;

	}

	$step = wpem_get_step_by( 'name', filter_input( INPUT_GET, 'step' ) );

	return ! empty( $step ) ? $step : wpem_get_step_by( 'position', 1 ); // Default to first step

}

/**
 * Return the next step
 *
 * @return object
 */
function wpem_get_next_step() {

	return wpem_get_step_by( 'position', wpem_get_current_step()->position + 1 );

}

/**
 * Get a step by name or actual position
 *
 * @param  string $field
 * @param  mixed  $value
 *
 * @return object
 */
function wpem_get_step_by( $field, $value ) {

	return \WPEM\wpem()->admin->get_step_by( $field, $value );

}

/**
 * Return a step field value from the log
 *
 * @param  string $field
 * @param  string $step (optional)
 * @param  mixed  $default (optional)
 *
 * @return mixed
 */
function wpem_get_step_field( $field, $step = null, $default = false ) {

	$step = ! empty( $step ) ? $step : wpem_get_current_step()->name;
	$log  = new \WPEM\Log;

	return ! empty( $log->steps[ $step ]['fields'][ $field ] ) ? $log->steps[ $step ]['fields'][ $field ] : $default;

}

/**
 * Return the URL for the setup wizard
 *
 * @return string
 */
function wpem_get_wizard_url() {

	$url = add_query_arg(
		[
			'page' => \WPEM\wpem()->page_slug,
		],
		admin_url()
	);

	return $url;

}

/**
 * Return the customizer version of a given URL
 *
 * @param  array $args (optional)
 *
 * @return string
 */
function wpem_get_customizer_url( $args = [] ) {

	$url = admin_url( 'customize.php' );

	if ( ! $args || ! is_array( $args ) ) {

		return $url;

	}

	return add_query_arg( array_map( 'urlencode', $args ), $url );

}

/**
 * Return the site type
 *
 * @param  string $default
 *
 * @return string
 */
function wpem_get_site_type( $default = 'standard' ) {

	return (string) get_option( 'wpem_site_type', $default );

}

/**
 * Return the site industry
 *
 * @param  string $default
 *
 * @return string
 */
function wpem_get_site_industry( $default = '' ) {

	return (string) get_option( 'wpem_site_industry', $default );

}

/**
 * Return site contact information
 *
 * @param  string $key
 * @param  mixed  $default (optional)
 *
 * @return mixed
 */
function wpem_get_contact_info( $key, $default = false ) {

	$array = (array) get_option( 'wpem_contact_info', [] );

	return isset( $array[ $key ] ) ? $array[ $key ] : $default;

}

/**
 * Return a social network URL
 *
 * @param  string $key
 * @param  mixed  $default (optional)
 *
 * @return mixed
 */
function wpem_get_social_profile_url( $key, $default = false ) {

	$array = (array) get_option( 'wpem_social_profiles', [] );

	return isset( $array[ $key ] ) ? $array[ $key ] : $default;

}

/**
 * Return an array of social profile names
 *
 * @return array
 */
function wpem_get_social_profiles() {

	return array_keys( (array) get_option( 'wpem_social_profiles', [] ) );

}

/**
 * Return a woocommerce option
 *
 * @param bool|string $name
 *
 * @return string
 */
function wpem_get_woocommerce_options( $name = false ) {

	$defaults = [
		'store_location'     => 'US:AL',
		'currency_code'      => 'USD',
		'weight_unit'        => 'lbs',
		'dimension_unit'     => 'in',
		'payment_methods'    => [],
		'calc_shipping'      => true,
		'calc_taxes'         => false,
		'prices_include_tax' => 'no',
	];

	if ( ! $name ) {

		return (array) get_option( 'wpem_woocommerce', $defaults );

	}

	$options = get_option( 'wpem_woocommerce', $defaults );

	return isset( $options[ $name ] ) ? $options[ $name ] : null;

}

/**
 * Return an array of color schemes
 *
 * @param bool $color_scheme
 *
 * @param null $stylesheet
 *
 * @return array
 */
function wpem_get_theme_color_schemes( $color_scheme = false, $stylesheet = null ) {

	$args = [
		'action' => 'get_color_schemes',
	];

	if ( ! is_null( $stylesheet ) ) {

		$args['theme'] = $stylesheet;

	}

	$response = wp_remote_get( \WPEM\Admin::demo_site_url( $args ) );

	if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {

		return false;

	}

	$color_schemes = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( is_null( $color_schemes ) ) {

		return false;

	}

	if ( $color_scheme ) {

		return apply_filters( 'wpem_theme_color_scheme', (array) $color_schemes[ $color_scheme ] );

	}

	ksort( $color_schemes );

	// Keep default at the top of the array
	$color_schemes = array( 'default' => $color_schemes['default'] ) + $color_schemes;

	return apply_filters( 'wpem_theme_color_schemes', (array) $color_schemes );

}

/**
 * Return a Woocommerce file(s)
 *
 * @return array
 *
 */
function wpem_get_store_data() {

	$args = [
		'site_type' => 'store',
		'action'    => 'get_woocommerce_store_data',
	];

	$response = wp_remote_get( \WPEM\Admin::demo_site_url( $args ) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

		return false;

	}

	$body = wp_remote_retrieve_body( $response );

	return $body;

}

/**
 * Mark the wizard as started
 */
function wpem_mark_as_started() {

	update_option( 'wpem_started', 1 );

	update_option( 'wpem_done', 0 );

	/**
	 * Fires when the wizard has started
	 *
	 * @since 2.0.5
	 */
	do_action( 'wpem_started' );

}

/**
 * Mark the wizard as done
 */
function wpem_mark_as_done() {

	delete_option( 'wpem_last_viewed' );

	update_option( 'wpem_done', 1 );

	/**
	 * Fires when the wizard has completed
	 *
	 * @since 2.0.5
	 */
	do_action( 'wpem_done' );

}

/**
 * Quit the wizard
 */
function wpem_quit() {

	update_option( 'wpem_opt_out', 1 );

	wpem_mark_as_done();

	/**
	 * Filter plugins to be deactivated on quit (user opt-out)
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	$plugins = (array) apply_filters( 'wpem_deactivate_plugins_on_quit', [] );

	if ( $plugins && ( ! defined( 'WPEM_DOING_TESTS' ) || ! WPEM_DOING_TESTS ) ) {

		if ( ! function_exists( 'deactivate_plugins' ) ) {

			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		}

		deactivate_plugins( $plugins );

	}

	/**
	 * Fires when the wizard quits (user opt-out)
	 *
	 * @since 2.0.5
	 */
	do_action( 'wpem_quit' );

	if ( function_exists( 'wp_safe_redirect' ) ) {

		wp_safe_redirect( admin_url() );

		exit;

	}

}

/**
 * Round a float and preserve trailing zeros
 *
 * @param  float $value
 * @param  int   $precision (optional)
 *
 * @return float
 */
function wpem_round( $value, $precision = 3 ) {

	$precision = absint( $precision );

	return sprintf( "%.{$precision}f", round( $value, $precision ) );

}

/**
 * Check if a GEM account already exists
 *
 * @return bool
 */
function wpem_has_gem_account() {

	$settings = (array) get_option( 'gem-settings', [] );

	return ( ! empty( $settings['username'] ) && ! empty( $settings['api-key'] ) );

}

/**
 * Is this a fresh WordPress install?
 *
 * @return bool
 */
function wpem_is_fresh_wp() {

	$log      = new \WPEM\Log;
	$is_fresh = $log->is_fresh_wp;

	if ( ! isset( $is_fresh ) ) {

		$is_fresh = wpem_check_is_fresh_wp();

		$log->add( 'is_fresh_wp', $is_fresh );

	}

	return $is_fresh;

}

/**
 * Check the WordPress database for freshness
 *
 * @return bool
 */
function wpem_check_is_fresh_wp() {

	global $wpdb;

	$posts = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM `{$wpdb->posts}` WHERE post_status != 'auto-draft';" );
	$users = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM `{$wpdb->users}`;" );

	$is_fresh = ( $posts <= 2 && 1 === $users );

	/**
	 * Filter whether the WordPress database is fresh
	 *
	 * @since 1.0.3
	 *
	 * @var bool
	 */
	return (bool) apply_filters( 'wpem_check_is_fresh_wp', $is_fresh );

}

/**
 * Has the wizard already been done?
 *
 * @return bool
 */
function wpem_is_done() {

	$status = get_option( 'wpem_done' );

	return ! empty( $status );

}

/**
 * Is WPEM running as a standalone plugin?
 *
 * @return bool
 */
function wpem_is_standalone_plugin() {

	if ( ! function_exists( 'is_plugin_active' ) ) {

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

	}

	return is_plugin_active( \WPEM\wpem()->basename );

}

/**
 * Redirect away from the wizard screen
 *
 * @action init
 */
function wpem_maybe_redirect() {

	if ( \WPEM\wpem()->page_slug !== filter_input( INPUT_GET, 'page' ) ) {

		return;

	}

	wp_safe_redirect( admin_url() );

	exit;

}

/**
 * Deactivate the plugin silently
 */
function wpem_deactivate() {

	if ( ! wpem_is_standalone_plugin() ) {

		return;

	}

	/**
	 * Filter to deactivate when done
	 *
	 * @since 1.0.3
	 *
	 * @var bool
	 */
	if ( ! (bool) apply_filters( 'wpem_deactivate', true ) || defined( 'WPEM_DOING_TESTS' ) && WPEM_DOING_TESTS ) {

		return;

	}

	deactivate_plugins( \WPEM\wpem()->basename, true );

}

/**
 * Self-destruct the plugin
 */
function wpem_self_destruct() {

	if ( ! wpem_is_standalone_plugin() ) {

		return;

	}

	/**
	 * Filter to self-destruct when done
	 *
	 * @since 1.0.3
	 *
	 * @var bool
	 */
	if ( ! (bool) apply_filters( 'wpem_self_destruct', true ) || defined( 'WPEM_DOING_TESTS' ) && WPEM_DOING_TESTS ) {

		return;

	}

	if ( ! class_exists( 'WP_Filesystem' ) ) {

		require_once ABSPATH . 'wp-admin/includes/file.php';

	}

	WP_Filesystem();

	global $wp_filesystem;

	$wp_filesystem->rmdir( \WPEM\wpem()->base_dir, true );

	wpem_deactivate();

}
