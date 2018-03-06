<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class SSO {

	/**
	 * Query arg to identify sso problems
	 */
	const INVALID_SSO_QARG = 'wpaas_invalid_sso';

	/**
	 * Class constructor.
	 *
	 * @param API|API_Interface $api
	 */
	public function __construct( API_Interface $api ) {

		$this->api = $api;

		/**
		 * We must + 1 the minimum integer when hooking into SSO to ensure
		 * that the Log class captures these events properly.
		 *
		 * Note: In WordPress 4.7 the SORT_NUMERIC flag was added to ksort()
		 * for sorting filter priorities. There is also a bug in PHP 5 that
		 * treats ~PHP_INT_MAX as greater than -PHP_INT_MAX. For this reason,
		 * ~PHP_INT_MAX should never be used as a filter priority in WP.
		 *
		 * @link https://gist.github.com/fjarrett/d2d1d60930d2ca4e67d35cf672ac9b13
		 */
		add_action( 'init', [ $this, 'init' ], -PHP_INT_MAX + 1 );

		add_action( 'shake_error_codes', [ $this, 'shake_error_codes' ] );
		add_filter( 'wp_login_errors',   [ $this, 'check_sso_problems' ] );

	}

	/**
	 * Initialize script.
	 *
	 * @action init
	 */
	public function init() {

		$action = ! empty( $_REQUEST['GD_COMMAND'] ) ? strtolower( $_REQUEST['GD_COMMAND'] ) : filter_input( INPUT_GET, 'wpaas_action' ); // Backwards compat
		$hash   = ! empty( $_REQUEST['SSO_HASH'] ) ? $_REQUEST['SSO_HASH'] : filter_input( INPUT_GET, 'wpaas_sso_hash' ); // Backwards compat

		if ( 'sso_login' !== $action || ! $hash ) {

			return;

		}

		if ( is_user_logged_in() ) {

			wp_safe_redirect( self_admin_url() );

			exit;

		}

		$user_id = $this->user_id();

		if ( is_int( $user_id ) && $this->api->is_valid_sso_hash( $hash ) ) {

			@wp_set_auth_cookie( $user_id );

			wp_safe_redirect( self_admin_url() );

			exit;

		}

		wp_safe_redirect( add_query_arg( static::INVALID_SSO_QARG, '', wp_login_url( self_admin_url() ) ) );

		exit;

	}

	/**
	 * Return the SSO user ID.
	 *
	 * @return int|false
	 */
	private function user_id() {

		$user_id = ! empty( $_REQUEST['SSO_USER_ID'] ) ? $_REQUEST['SSO_USER_ID'] : filter_input( INPUT_GET, 'wpaas_sso_user_id', FILTER_VALIDATE_INT ); // Backwards compat

		if ( $user_id ) {

			return absint( $user_id );

		}

		$user = get_users(
			[
				'role'   => 'administrator',
				'number' => 1,
			]
		);

		return isset( $user[0]->ID ) ? $user[0]->ID : false;

	}

	/**
	 * Check if there was any sso problems
	 *
	 * @param $errors
	 *
	 * @return mixed
	 */
	public function check_sso_problems( $errors ) {

		if ( ! isset( $_GET[ static::INVALID_SSO_QARG ] ) ) {

			return $errors;

		}

		$errors->add( static::INVALID_SSO_QARG, __( 'We were unable to log you in automatically. Please enter your WordPress username and password.', 'gd-system-plugin' ), 'error' );

		return $errors;

	}

	/**
	 * Add our custom error message to the shaking messages
	 *
	 * @param $shake_error_codes
	 *
	 * @return array
	 */
	public function shake_error_codes( $shake_error_codes ) {

		$shake_error_codes[] = static::INVALID_SSO_QARG;

		return $shake_error_codes;

	}

}
