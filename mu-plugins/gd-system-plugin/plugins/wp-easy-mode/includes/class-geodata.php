<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Geodata {

	/**
	 * GeoIP API URL
	 *
	 * @var string
	 */
	const API_URL = 'https://freegeoip.net/json/%s';

	/**
	 * Alternate GeoIP API URL
	 *
	 * @var string
	 */
	const ALT_API_URL = 'http://geoip.nekudo.com/api/%s/full';

	/**
	 * Array of geodata
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Max seconds for API requests
	 *
	 * @var int
	 */
	private $request_timeout = 5;

	/**
	 * Class constructor
	 *
	 * @param Log $log
	 */
	public function __construct( Log $log ) {

		if ( ! empty( $this->data ) ) {

			return;

		}

		if ( isset( $log->geodata ) ) {

			$this->data = $log->geodata;

			return;

		}

		$ip = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );

		if ( $this->is_public_ip( $ip ) ) {

			$this->data = $this->get_geodata( $ip );

		}

		if ( $log ) {

			$log->add( 'geodata', $this->data );

		}

	}

	/**
	 * Magic getter method
	 *
	 * @throws Exception
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {

		if ( property_exists( $this, $key ) ) {

			return $this->{$key};

		}

		if ( isset( $this->data[ $key ] ) ) {

			return $this->data[ $key ];

		}

		throw new Exception( "Unrecognized property: '{$key}'" );

	}

	/**
	 * Check if an IP address is valid and public
	 *
	 * @param  string $ip
	 *
	 * @return bool
	 */
	private function is_public_ip( $ip ) {

		// IPv4
		$ip = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );

		if ( $ip ) {

			return true;

		}

		// IPv6
		$ip = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );

		if ( $ip ) {

			return true;

		}

		return false;

	}

	/**
	 * Normalize geodata between multiple API sources
	 *
	 * @param  array $geodata
	 *
	 * @return array
	 */
	private function normalize( $geodata ) {

		if ( isset( $geodata['city']['names']['en'] ) ) {

			$geodata['city'] = $geodata['city']['names']['en'];

		}

		if ( isset( $geodata['country']['iso_code'] ) ) {

			$geodata['country_code'] = $geodata['country']['iso_code'];

		}

		if ( isset( $geodata['country'] ) ) {

			$geodata['country_name'] = $geodata['country'];

		}

		if ( isset( $geodata['country']['names']['en'] ) ) {

			$geodata['country_name'] = $geodata['country']['names']['en'];

		}

		if ( isset( $geodata['latitude'] ) ) {

			$geodata['latitude'] = wpem_round( $geodata['latitude'] );

		}

		if ( isset( $geodata['location']['latitude'] ) ) {

			$geodata['latitude'] = wpem_round( $geodata['location']['latitude'] );

		}

		if ( isset( $geodata['longitude'] ) ) {

			$geodata['longitude'] = wpem_round( $geodata['longitude'] );

		}

		if ( isset( $geodata['location']['longitude'] ) ) {

			$geodata['longitude'] = wpem_round( $geodata['location']['longitude'] );

		}

		if ( isset( $geodata['zip_code'] ) ) {

			$geodata['postal_code'] = $geodata['zip_code'];

		}

		if ( isset( $geodata['postal']['code'] ) ) {

			$geodata['postal_code'] = $geodata['postal']['code'];

		}

		if ( isset( $geodata['subdivisions'][0]['iso_code'] ) ) {

			$geodata['region_code'] = $geodata['subdivisions'][0]['iso_code'];

		}

		if ( isset( $geodata['region'] ) ) {

			$geodata['region_name'] = $geodata['region'];

		}

		if ( isset( $geodata['subdivisions'][0]['names']['en'] ) ) {

			$geodata['region_name'] = $geodata['subdivisions'][0]['names']['en'];

		}

		if ( isset( $geodata['time_zone'] ) ) {

			$geodata['timezone'] = $geodata['time_zone'];

		}

		if ( isset( $geodata['location']['time_zone'] ) ) {

			$geodata['timezone'] = $geodata['location']['time_zone'];

		}

		$whitelist = [
			'city',
			'country_code',
			'country_name',
			'ip',
			'latitude',
			'longitude',
			'postal_code',
			'region_code',
			'region_name',
			'timezone',
		];

		$geodata = array_intersect_key( $geodata, array_flip( $whitelist ) );

		ksort( $geodata );

		return $geodata;

	}

	/**
	 * Return the geodata of a given IP address
	 *
	 * @param  string $ip
	 *
	 * @return array
	 */
	private function get_geodata( $ip ) {

		$response = $this->request( static::API_URL, $ip );

		if ( ! $response ) {

			$response = $this->request( static::ALT_API_URL, $ip );

		}

		if ( ! $response ) {

			return [];

		}

		return $this->normalize( $response );

	}

	/**
	 * Request geodata from the API
	 *
	 * @param  string $url
	 * @param  string $ip
	 *
	 * @return array|bool
	 */
	private function request( $url, $ip ) {

		$url      = esc_url_raw( sprintf( $url, $ip ) );
		$response = wp_remote_get( $url, [ 'timeout' => $this->request_timeout ] );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) || empty( $response ) ) {

			return false;

		}

		return json_decode( wp_remote_retrieve_body( $response ), true );

	}

}
