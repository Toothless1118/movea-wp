<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Logger;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

abstract class Component {

	/**
	 * Component name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Array of registered hooks.
	 *
	 * @var array
	 */
	protected $hooks = [];

	/**
	 * Access to the Logger object.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class constructor.
	 *
	 * @param Logger $logger
	 */
	public function __construct( Logger $logger ) {

		$this->logger = $logger;

		$this->set_default_name();

		if ( method_exists( $this, 'load' ) ) {

			$this->load();

		}

		$this->register_hooks();

		$this->do_callbacks_on_hooks();

	}

	/**
	 * Set the component's default name.
	 */
	protected function set_default_name() {

		$parts      = explode( '\\', get_called_class() );
		$class      = array_pop( $parts );
		$this->name = strtolower( $class );

	}

	/**
	 * Register hooks from callback methods.
	 */
	protected function register_hooks() {

		foreach ( (array) get_class_methods( $this ) as $method ) {

			if ( 0 === strpos( $method, 'callback_' ) ) {

				$this->hooks[] = str_replace( 'callback_', '', $method );

			}

		}

	}

	/**
	 * Add a callback to each hook.
	 */
	protected function do_callbacks_on_hooks() {

		if ( ! $this->hooks ) {

			return;

		}

		foreach ( $this->hooks as $hook ) {

			$callback = [ $this, 'callback_' . $hook ];

			if ( is_callable( $callback ) ) {

				add_action( $hook, $callback, -PHP_INT_MAX, 99 );

			}

		}

	}

	/**
	 * Proxy the request to the logger class.
	 *
	 * @param string   $action
	 * @param string   $summary
	 * @param array    $meta
	 * @param \WP_User $user
	 */
	final protected function log( $action, $summary, array $meta, \WP_User $user = null ) {

		$logger = $this->logger; // https://bugs.php.net/bug.php?id=50029

		$logger( $this->name, $action, $summary, $meta, $user );

	}

	/**
	 * Log a basic metric.
	 *
	 * @param string   $name
	 * @param bool     $options (optional)
	 * @param \WP_User $user    (optional)
	 */
	final protected function log_metric( $name, $options = true, \WP_User $user = null ) {

		$logger = $this->logger; // https://bugs.php.net/bug.php?id=50029

		/**
		 * We will not log a metric when:
		 *
		 * 1. Another metric was logged in this process.
		 * 2. WP Easy Mode is running.
		 * 3. WP-CLI is being used.
		 * 4. The user is not an Administrator.
		 */
		if (
			$logger->metric_logged
			||
			\WPaaS\Plugin::is_doing_wpem()
			||
			\WPaaS\Plugin::is_wp_cli()
			||
			is_a( $user, 'WP_User' ) ? ! user_can( $user, 'activate_plugins' ) : ! current_user_can( 'activate_plugins' )
		) {

			return;

		}

		$name = sanitize_key( $name );
		$time = time();

		if ( $options ) {

			update_option( "gd_system_last_{$name}", $time );

			if ( ! get_option( "gd_system_first_{$name}" ) ) {

				update_option( "gd_system_first_{$name}", $time );

			}

		}

		/**
		 * Component name and action will be the same in
		 * the simple logs.
		 *
		 * Duplicates are removed from the e_id so that
		 * `wpadmin.publish.publish` will actually be saved
		 * as `wpadmin.publish`.
		 */
		$logger( $name, $name, null, [] );

		$logger->metric_logged = true;

	}

}
