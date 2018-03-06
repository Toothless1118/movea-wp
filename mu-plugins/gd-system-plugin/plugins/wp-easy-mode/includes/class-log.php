<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Log {

	/**
	 * Option key
	 */
	const OPTION_KEY = 'wpem_log';

	/**
	 * Log data
	 *
	 * @var array
	 */
	private static $log = [];

	/**
	 * Current step
	 *
	 * @var object
	 */
	private $step;

	/**
	 * Class constructor
	 */
	public function __construct() {

		if ( empty( static::$log ) ) {

			$log = get_option( static::OPTION_KEY );

			if ( $log ) {

				static::$log = json_decode( $log, true );

				return;

			}

			add_action( 'init', [ $this, 'maybe_set_defaults' ] );

		}

	}

	/**
	 * Magic getter method
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {

		if ( property_exists( $this, $key ) ) {

			return $this->{$key};

		}

		if ( isset( static::$log[ $key ] ) ) {

			return static::$log[ $key ];

		}

	}

	/**
	 * Magic isset method
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public function __isset( $key ) {

		return ( property_exists( $this, $key ) || isset( static::$log[ $key ] ) );

	}

	/**
	 * Add a new log entry
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	public function add( $key, $value ) {

		static::$log[ $key ] = $value;

		return $this->save();

	}

	/**
	 * Get current step for functions who needs it
	 */
	private function get_step() {

		if ( ! isset( $this->step ) ) {

			$this->step = wpem_get_current_step();

		}

	}

	/**
	 * Add a new log entry for a step field
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	public function add_step_field( $key, $value ) {

		$this->get_step();

		static::$log['steps'][ $this->step->name ]['fields'][ $key ] = $value;

		return $this->save();

	}

	/**
	 * Add a new log entry for step time
	 *
	 * @param  float $value
	 *
	 * @return bool
	 */
	public function add_step_time( $value ) {

		$this->get_step();

		static::$log['steps'][ $this->step->name ]['took'] = $total = wpem_round( $value );

		if ( $this->save() ) {

			$this->recalculate_total_time();

			return true;

		}

		return false;

	}

	/**
	 * Recalculate the total for all time logs
	 */
	public function recalculate_total_time() {

		$total = 0.000;

		foreach ( static::$log['steps'] as $step => $data ) {

			if ( ! isset( $data['took'] ) ) {

				continue;

			}

			$total = wpem_round( $total + $data['took'] );

		}

		$this->add( 'took', $total );

	}

	/**
	 * Set log defaults if not yet present
	 *
	 * @action init
	 */
	public function maybe_set_defaults() {

		$defaults = [
			'datetime',
			'fqdn',
			'site_url',
			'account_id',
			'user_email',
			'locale',
			'wp_version',
		];

		if ( ! array_diff_key( $defaults, static::$log ) ) {

			return;

		}

		$defaults = [
			'datetime'     => gmdate( 'c' ),
			'fqdn'         => gethostname(),
			'site_url'     => get_option( 'siteurl' ),
			'account_id'   => exec( 'whoami' ),
			'user_email'   => get_userdata( 1 )->user_email,
			'locale'       => ( $locale = get_option( 'WPLANG' ) ) ? $locale : 'en_US',
			'wp_version'   => get_bloginfo( 'version' ),
			'wpem_version' => wpem()->version,
		];

		/**
		 * Filter default log fields
		 *
		 * @since 2.0.5
		 *
		 * @var array
		 */
		static::$log = (array) apply_filters( 'wpem_log_defaults', $defaults );

		$this->save();

		new Geodata( $this ); // Saves to log

	}

	/**
	 * Save log to the database
	 *
	 * @return bool
	 */
	private function save() {

		$updated = update_option( static::OPTION_KEY, wp_json_encode( static::$log ) );

		if ( $updated ) {

			/**
			 * Fires when the log is updated
			 *
			 * @since 2.0.5
			 *
			 * @param array $log
			 */
			do_action( 'wpem_log_updated', static::$log );

		}

		return $updated;

	}

}
