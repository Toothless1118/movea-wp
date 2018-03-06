<?php

namespace WPaaS;

use \WP_Error;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

interface API_Interface {

	public function get_blacklist();

	public function is_valid_sso_hash( $hash );

	public function user_changed_domain( $domain = '' );

}

final class API implements API_Interface {

	/**
	 * Return an array of blacklisted plugins.
	 *
	 * Note: The transient used here is persistent, meaning it
	 * will not be short-circuited by object cache and it will
	 * always be set to a non-false value regardless of the API
	 * response.
	 *
	 * @return array
	 */
	public function get_blacklist() {

		if ( false !== ( $transient = Plugin::get_persistent_site_transient( 'gd_system_blacklist' ) ) ) {

			return (array) $transient;

		}

		$response  = $this->call( 'blacklistapi/' );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$blacklist = ! empty( $body['data'] ) ? (array) $body['data'] : [];

		Plugin::set_persistent_site_transient( 'gd_system_blacklist', $blacklist, HOUR_IN_SECONDS );

		return $blacklist;

	}

	/**
	 * Validate an SSO hash.
	 *
	 * @param  string $hash
	 *
	 * @return bool
	 */
	public function is_valid_sso_hash( $hash ) {

		$response = $this->call(
			sprintf( 'ssoauthenticationapi/%s?AllowSsoLogin', $hash ),
			sprintf( '"%s"', DB_NAME ),
			'POST'
		);

		$body = wp_remote_retrieve_body( $response );

		return ( $body ) ? ( 'true' === strtolower( $body ) ) : false;

	}

	/**
	 * Check if a user has changed their domain.
	 *
	 * It isn't reflected here yet because we're waiting on the
	 * DNS TTL to take effect.
	 *
	 * Note: The transient used here is persistent, meaning it
	 * will not be short-circuited by object cache and it will
	 * always be set to a non-false value regardless of the API
	 * response.
	 *
	 * @param  string $cname (optional)
	 *
	 * @return bool
	 */
	public function user_changed_domain( $cname = '' ) {

		if ( false !== ( $transient = Plugin::get_persistent_site_transient( 'gd_system_domain_changed' ) ) ) {

			return (
				1 === (int) $transient
				||
				'Y' === $transient // Back compat
			);

		}

		$cname    = ( $cname ) ? $cname : Plugin::domain();
		$response = $this->call( 'domains/' . $cname );
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );
		$changed  = ! empty( $body['domainChanged'] ) ? 1 : 0;
		$timeout  = Plugin::config( 'cname_timeout' ) ? Plugin::config( 'cname_timeout' ) : 300;

		Plugin::set_persistent_site_transient( 'gd_system_domain_changed', $changed, absint( $timeout ) );

		return ( 1 === $changed );

	}

	/**
	 * Make an API call.
	 *
	 * @param  string        $method
	 * @param  array|string  $method_args (optional)
	 * @param  string        $http_verb   (optional)
	 *
	 * @return array|WP_Error
	 */
	private function call( $method, $method_args = [], $http_verb = 'GET' ) {

		$api_url = apply_filters( 'wpaas_api_url', Plugin::config( 'api_url' ) );

		if ( ! $api_url ) {

			return new WP_Error( 'wpaas_api_url_not_found' );

		}

		$http_args = [
			'headers' => [
				'Content-type' => 'application/json',
			]
		];

		$url = trailingslashit( $api_url ) . $method;

		$retries     = 0;
		$max_retries = 1;

		add_filter( 'https_ssl_verify', '__return_false' );

		while ( $retries <= $max_retries ) {

			$retries++;

			switch ( $http_verb ) {

				case 'GET' :

					if ( ! empty( $method_args ) ) {

						$url .= '?' . build_query( $method_args );

					}

					$response = wp_remote_get( $url, $http_args );

					break;

				case 'POST' :

					$http_args['body'] = $method_args;

					$response = wp_remote_post( $url, $http_args );

					break;

				default:

					return new WP_Error( 'wpaas_api_invalid_http_verb' );

			}

			$response_code = wp_remote_retrieve_response_code( $response );

			// Check if we aren't on the last iteration and we can try the request again
			if (
				$retries <= $max_retries
				&&
				$this->is_retryable( $response, $response_code )
			) {

				// Give some time for the API to recover
				sleep( (int) apply_filters( 'wpaas_api_retry_delay', 1 ) );

				continue;

			}

			break;

		}

		remove_filter( 'https_ssl_verify', '__return_false' );

		if ( 200 !== $response_code ) {

			return new WP_Error( 'wpaas_api_bad_status' );

		}

		return $response;

	}

	/**
	 * Check if a response is an error and retryable.
	 *
	 * @param  array|WP_Error $response
	 * @param  int   $response_code
	 *
	 * @return bool
	 */
	private function is_retryable( $response, $response_code ) {

		if ( 200 === $response_code ) {

			return false;

		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if (
			isset( $body['status'], $body['type'], $body['code'] )
			&&
			503 === absint( $body['status'] )
			&&
			'error' === $body['type']
			&&
			'RetryRequest' === $body['code']
		) {

			return true;

		}

		return false;

	}

}
