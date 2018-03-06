<?php

namespace WPaaS\Log;

use WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

class Logger {

	/**
	 * @var bool
	 */
	public $metric_logged = false;

	/**
	 * Log a new event and store it.
	 *
	 * @param string   $name
	 * @param string   $action
	 * @param string   $summary
	 * @param array    $meta
	 * @param \WP_User $user (optional)
	 *
	 * @return bool
	 */
	public function __invoke( $name, $action, $summary, array $meta, \WP_User $user = null ) {

		$user  = is_null( $user ) ? wp_get_current_user() : $user;
		$event = new Event( $name, $action, $summary, $meta, $user );

		if (
			! $event->is_valid()
			||
			! ( $asap_key = Plugin::asap_key() )
			||
			! ( $xid = Plugin::xid() )
		) {

			return false;

		}

		$env  = Plugin::is_wp_cli() ? 'wpcli' : 'wpadmin';
		$e_id = [ 'hosting', 'wpaas', 'account', $env, $event->component, $event->action ];
		$data = [
			'asapkey' => $asap_key,
			'xid'     => $xid,
			'e_id'    => implode( '.' , array_unique( $e_id ) ),
			'e_time'  => $event->time,
		];

		$data = array_merge( $data, $event->data );

		if ( WP_DEBUG && defined( 'WPAAS_EVENTS_LOG' ) && WPAAS_EVENTS_LOG ) {

			file_put_contents( WP_CONTENT_DIR . '/events.log', wp_json_encode( $data, JSON_PRETTY_PRINT ) . PHP_EOL, FILE_APPEND | LOCK_EX );

		}

		$this->syslog( $data );

		if ( $event->is_metric ) {

			/**
			 * Fire after a metric is logged.
			 *
			 * @since 2.0.0
			 *
			 * @param string $name
			 * @param array  $data
			 */
			do_action( 'wpaas_log_metric', $event->action, $data );

			return;

		}

		/**
		 * Fire after an event is logged.
		 *
		 * @since 2.0.0
		 *
		 * @param string $component
		 * @param string $action
		 * @param array  $data
		 */
		do_action( 'wpaas_log_event', $event->component, $event->action, $data );

		Timer::start(); // Restart the timer for bulk actions

	}

	/**
	 * Save log data to the syslog.
	 *
	 * @param  array $data
	 *
	 * @return bool
	 */
	private function syslog( array $data ) {

		/**
		 * Filter to enable saving to the syslog.
		 *
		 * @var bool
		 */
		$enabled = (bool) apply_filters( 'wpaas_log_syslog_enabled', true );

		if ( ! $enabled || false === openlog( 'wpaas-event', LOG_NDELAY | LOG_PID, LOG_LOCAL1 ) ) {

			return false;

		}

		syslog( LOG_INFO, wp_json_encode( $data ) );

		closelog();

		return true;

	}

}
