<?php

namespace WPaaS\Log;

use DateTime;
use WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Event {

	/**
	 * Event component.
	 *
	 * @var string
	 */
	private $component;

	/**
	 * Event action.
	 *
	 * @var string
	 */
	private $action;

	/**
	 * Event timestamp.
	 *
	 * @var string
	 */
	private $time;

	/**
	 * Array of event data.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * The event is a basic metric.
	 *
	 * @var bool
	 */
	private $is_metric = false;

	/**
	 * Class constructor.
	 *
	 * @param  string   $component
	 * @param  string   $action
	 * @param  string   $summary
	 * @param  array    $meta
	 * @param  \WP_User $user
	 */
	public function __construct( $component, $action, $summary, array $meta, \WP_User $user ) {

		$this->component = (string) sanitize_key( $component );
		$this->action    = (string) sanitize_key( $action );
		$this->time      = (string) self::e_time();

		// All type of event need the reseller id
		$this->data['account.reseller'] = (int) Plugin::reseller_id(); // false will become 0

		/**
		 * Skip the rest if we are just logging a basic metric.
		 */
		if ( $component === $action || empty( $summary ) || empty( $meta ) ) {

			$this->is_metric = true;

			return;

		}

		$this->data = array_merge(
			[
				'summary'       => (string) vsprintf( $summary, $meta ),
				'took'          => (string) Timer::took(),
				'site_url'      => (string) site_url(),
				'temp_domain'   => (bool) Plugin::is_temp_domain(),
				'used_wpem'     => (bool) Plugin::has_used_wpem(),
				'multisite'     => (bool) is_multisite(),
				'site_id'       => is_multisite() ? (int) get_current_site()->id : 1,
				'blog_id'       => (int) get_current_blog_id(),
				'locale'        => (string) get_locale(),
				'version'       => (string) $GLOBALS['wp_version'],
				'php.version'   => (string) PHP_VERSION,
				'wpaas.version' => (string) Plugin::version(),
				'account.id'    => (string) Plugin::account_id(),
				'account.fqdn'  => (string) gethostname(),
			],
			$this->data
		);

		if ( ! Plugin::is_wp_cli() && ! empty( $user->ID ) ) {

			$user_roles = empty( $user->roles ) ? [] : $user->roles;

			$this->data = array_merge(
				$this->data,
				[
					'user.id'           => (int) $user->ID,
					'user.role'         => isset( $user_roles[0] ) ? (string) $user_roles[0] : '',
					'user.roles'        => (array) $user_roles,
					'user.email'        => (string) $user->user_email,
					'user.login'        => (string) $user->user_login,
					'user.display_name' => (string) $user->display_name,
					'user.ip_address'   => (string) filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
					'user.http_agent'   => (string) filter_input( INPUT_SERVER, 'HTTP_USER_AGENT' ),
					'user.locale'       => (string) ( function_exists( 'get_user_locale' ) ? get_user_locale( $user ) : get_locale() ),
				]
			);

		}

		$meta       = self::prefix_array_keys( $meta, 'meta.' );
		$this->data = array_merge( $this->data, $meta );
		$this->data = self::prefix_array_keys( $this->data, 'wp.' );

	}

	/**
	 * Magic data getta.
	 *
	 * @param  string $key
	 *
	 * @return mixed|false
	 */
	public function __get( $key ) {

		if ( in_array( $key, [ 'component', 'action', 'time', 'data', 'is_metric' ] ) ) {

			return $this->{$key};

		}

		if ( isset( $this->data[ $key ] ) ) {

			return $this->data[ $key ];

		}

		return false;

	}

	/**
	 * Verify that the Event is valid.
	 *
	 * @return bool
	 */
	public function is_valid() {

		if (
			! empty( $this->component )
			&&
			! empty( $this->action )
			&&
			! empty( $this->time )
			&&
			false !== strtotime( $this->time )
		) {

			return true;

		}

		return false;

	}

	/**
	 * Add a prefix to all keys in an array.
	 *
	 * @param  array  $array
	 * @param  string $prefix
	 *
	 * @return array
	 */
	private static function prefix_array_keys( array $array, $prefix ) {

		$output = [];

		foreach ( $array as $key => $value ) {

			$output[ "{$prefix}{$key}" ] = $value;

		}

		return $output;

	}

	/**
	 * Return the time in ISO 8601 extended format.
	 *
	 * e.g. 2016-03-31T14:17:47.67Z
	 *
	 * @param  string $time (optional)
	 *
	 * @return string
	 */
	public static function e_time( $time = '' ) {

		$time     = ( ! $time ) ? microtime( true ) : strtotime( $time );
		$micro    = sprintf( '%06d', ( $time - floor( $time ) ) * 1000000 );
		$datetime = new DateTime( gmdate( 'Y-m-d H:i:s.' . $micro, $time ) );

		return sprintf(
			'%s%02dZ',
			$datetime->format( 'Y-m-d\TH:i:s.' ),
			floor( $datetime->format( 'u' ) / 10000 )
		);

	}

}
