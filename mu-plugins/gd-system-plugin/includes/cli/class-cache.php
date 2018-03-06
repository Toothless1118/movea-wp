<?php

namespace WPaaS\CLI;

use \WP_CLI;
use \WP_CLI_Command;
use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Cache extends WP_CLI_Command {

	/**
	 * Flush the cache.
	 *
	 * ## OPTIONS
	 *
	 * [<type>...]
	 * : One or more cache types to flush. Accepted values: varnish, object,
	 * transient. All cache types will be flushed by default.
	 *
	 * [--urls]
	 * : Selectively purge URLs from the varnish cache (comma separated).
	 */
	public function flush( array $args, array $assoc_args ) {

		$allowed = [ 'transient', 'object', 'varnish' ];

		foreach ( array_diff( $args, $allowed ) as $type ) {

			WP_CLI::error( sprintf( '`%s` is not a supported cache type.', $type ), false );

		}

		$args = empty( $args ) ? $allowed : $args;

		foreach ( $allowed as $type ) {

			// Making sure we flush in the right sequence
			if ( ! in_array( $type, $args ) ) {

				continue;

			}

			switch ( $type ) {

				case 'transient' :

					$transient = $this->flush_transient_cache( $assoc_args );

					break;

				case 'object' :

					$object = $this->flush_object_cache();

					break;

				case 'varnish' :

					$urls = WP_CLI\Utils\get_flag_value( $assoc_args, 'urls', '' );
					$urls = array_filter( array_map( 'trim', explode( ',', $urls ) ) );

					$this->flush_varnish_cache( $urls );

					break;

			}

		}

		if ( ! empty( $varnish ) || ! empty( $object ) || ! empty( $transient ) ) {

			update_option( 'gd_system_last_cache_flush', time() );

		}

	}

	/**
	 * Date of the last cache flush.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : PHP date format. Default: c
	 *
	 * @alias last-flushed
	 *
	 * @subcommand last-flush
	 */
	public function last_flush( array $args, array $assoc_args ) {

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'c' );
		$date   = Plugin::last_cache_flush_date( $format );

		if ( ! $date ) {

			WP_CLI::warning( 'The last cache flush date is unknown.' );

			return;

		}

		WP_CLI::line( Plugin::last_cache_flush_date( $format ) );

	}

	/**
	 * Flush the Varnish cache.
	 *
	 * @param  array $urls (optional)
	 *
	 * @return bool
	 */
	private function flush_varnish_cache( array $urls = [] ) {

		if ( false === ( $vip = Plugin::vip() ) ) {

			WP_CLI::error( 'The Varnish page cache could not be flushed.', false );

			return false;

		}

		$method = ( $urls ) ? 'PURGE' : 'BAN';
		$urls   = ( $urls ) ? $urls : [ home_url() ];

		foreach ( array_unique( $urls ) as $url ) {

			$host = parse_url( $url, PHP_URL_HOST );
			$url  = esc_url_raw( $url );

			exec( "curl -is --resolve {$host}:{$vip} -X{$method} {$url} > /dev/null" );

		}

		WP_CLI::success( 'The Varnish page cache was flushed.' );

		return true;

	}

	/**
	 * Flush the object cache.
	 *
	 * @return bool
	 */
	private function flush_object_cache() {

		$result = wp_cache_flush();

		if ( false === $result ) {

			WP_CLI::error( 'The object cache could not be flushed.', false );

			return false;

		}

		WP_CLI::success( 'The object cache was flushed.' );

		return true;

	}

	/**
	 * Flush the transient cache.
	 *
	 * @param  array $assoc_args (optional)
	 *
	 * @return bool
	 */
	private function flush_transient_cache( array $assoc_args = [] ) {

		$result = \WPaaS\Cache::flush_transients();

		if ( false === $result ) {

			WP_CLI::error( 'The transient cache could not be flushed.', false );

			return false;

		}

		WP_CLI::success( sprintf( '%d transients deleted from the database.', $result ) );

		return true;

	}

	/**
	 * Return an error message for a response.
	 *
	 * @param  object $response
	 *
	 * @return string|false
	 */
	private function is_error( $response ) {

		if ( is_wp_error( $response ) ) {

			return $response->get_error_message();

		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {

			return sprintf( '%d %s', $code, wp_remote_retrieve_response_message( $response ) );

		}

		return false;

	}

}
