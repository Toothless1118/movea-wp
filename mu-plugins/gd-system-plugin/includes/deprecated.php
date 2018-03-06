<?php

use WPaaS\Cache;
use WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class WPaaS_Deprecated {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		$this->wp_cli();

		$this->actions();

		$this->filters();

		$this->migrations();

	}

	/**
	 * Deprecated WP-CLI commands.
	 */
	private function wp_cli() {

		if ( ! Plugin::is_wp_cli() ) {

			return;

		}

		/**
		 * wp purge
		 */
		WP_CLI::add_command( 'purge', function() {

			WP_CLI::warning(
				sprintf(
					'`wp purge` is deprecated. Use `%s` instead.',
					Plugin::cli_command( 'cache flush' )
				)
			);

			WP_CLI::run_command( [ Plugin::cli_base_command(), 'cache', 'flush' ] );

		} );

	}

	/**
	 * Deprecated actions.
	 */
	private function actions() {

		add_action( 'flush_cache', function( array $args = [] ) {

			if ( ( ! isset( $args['ban'] ) || 0 !== $args['ban'] ) && ! Cache::has_ban() ) {

				add_action( 'shutdown', [ '\WPaaS\Cache', 'ban' ], PHP_INT_MAX );

			}

			if ( isset( $args['ban'] ) && 0 === isset( $args['ban'] ) && ! empty( $args['urls'] ) && ! Cache::has_ban() && ! Cache::has_purge() ) {

				Cache::$purge_urls = $args['urls'];

				add_action( 'shutdown', [ '\WPaaS\Cache', 'purge' ], PHP_INT_MAX );

			}

		}, PHP_INT_MAX );

	}

	/**
	 * Deprecated filters.
	 */
	private function filters() {

		add_filter( 'wpaas_api_retry_delay', function( $seconds ) {

			return (int) apply_filters( 'gd_system_api_retry_delay', $seconds );

		} );

	}

	/**
	 * Perform any necessary migrations.
	 *
	 * Find all migration methods in this class that have
	 * a version number greater than the previous version
	 * and run them in order.
	 */
	private function migrations() {

		$previous = get_option( 'gd_system_version', '2.0.0' );

		if ( ! version_compare( Plugin::version(), $previous, '>' ) ) {

			return;

		}

		$migrations = [];

		foreach ( get_class_methods( __CLASS__ ) as $method ) {

			if ( 0 !== strpos( $method, 'migration_' ) ) {

				continue;

			}

			$version = str_replace( [ 'migration_', '_' ], [ '', '.' ], $method );

			if ( version_compare( $previous, $version, '<' ) ) {

				$migrations[ $version ] = $method;

			}

		}

		natsort( $migrations );

		foreach ( $migrations as $migration ) {

			if ( is_callable( [ $this, $migration ] ) ) {

				$this->$migration();

			}

		}

		update_option( 'gd_system_version', Plugin::version() );

	}

}

/**
 * Deprecated class to flush cache on app servers
 *
 * Class GD_System_Plugin_Purge_Command
 */
class GD_System_Plugin_Purge_Command {

	function __invoke( $args, $assoc_args ) {

		WP_CLI::run_command( [ Plugin::cli_base_command(), 'cache', 'flush' ] );

	}

}
