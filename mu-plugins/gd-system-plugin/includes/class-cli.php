<?php

namespace WPaaS;

use \WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class CLI {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		$commands = [
			'cache' => '\WPaaS\CLI\Cache',
		];

		foreach ( $commands as $command => $class ) {

			unset( $commands[ $command ] );

			$commands[ Plugin::cli_command( $command, [], false ) ] = $class;

			if ( 'wpaas' !== Plugin::cli_base_command() ) {

				$commands["wpaas {$command}"] = $class;

			}

		}

		// Custom subcommand for a default command
		$commands['cron event wpaas'] = '\WPaaS\CLI\Cron_Event';

		/**
		 * Filter the default custom WP-CLI commands.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		$commands = (array) apply_filters( 'wpaas_cli_commands', $commands );

		$this->register( $commands );

	}

	/**
	 * Register custom WP-CLI commands.
	 *
	 * @param  array $commands
	 *
	 * @return array|bool
	 */
	private function register( array $commands ) {

		if ( ! $commands || ! is_array( $commands ) ) {

			return false;

		}

		$registered = [];

		foreach ( $commands as $command => $class ) {

			if ( ! class_exists( $class ) ) {

				continue;

			}

			WP_CLI::add_command( $command, $class );

			$registered[ $command ] = $class;

		}

		return ( $registered ) ? $registered : false;

	}

}
