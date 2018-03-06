<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Theme_Updates {

	/**
	 * URL for fetching JSON theme data.
	 *
	 * @var string
	 */
	const URL = 'https://raw.githubusercontent.com/godaddy/wp-themes/master/manifest.min.json';

	/**
	 * Array of themes to check.
	 *
	 * @var array
	 */
	private static $themes = [
		'activation',
		'ascension',
		'escapade',
		'lyrical',
		'mins',
		'primer',
		'scribbles',
		'stout',
		'uptown-style',
		'velux',
	];

	/**
	 * Class constructor.
	 */
	public function __construct() {

		/**
		 * Filter the array of themes to check.
		 *
		 * @var array
		 */
		self::$themes = (array) apply_filters( 'wpaas_theme_updates_list', self::$themes );

		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'update_themes' ], PHP_INT_MAX, 2 );

	}

	/**
	 * Intercept the transient that holds available theme updates.
	 *
	 * @add_filter pre_set_site_transient_update_themes
	 *
	 * @param stdClass $value
	 * @param string   $transient
	 */
	public function update_themes( $value, $transient ) {

		if ( ! is_a( $value, 'stdClass' ) || ! property_exists( $value, 'checked' ) || ! is_array( $value->checked ) ) {

			return $value;

		}

		// We only care about checking themes if they are installed
		$installed = array_intersect( self::$themes, array_keys( $value->checked ) );

		if ( ! $installed ) {

			return $value;

		}

		static $theme_data;

		if ( ! $theme_data ) {

			// Ensure data is only fetched once per page load
			$theme_data = $this->fetch_theme_data( $installed );

		}

		foreach ( $theme_data as $data ) {

			list( $theme, $new_version ) = array_values( $data );

			$wp_org_new_version = $this->check_wp_org_version( $value->response, $theme );

			// If a dot org update is the same or newer than ours, skip and use that
			if ( version_compare( $wp_org_new_version, $new_version, '>=' ) ) {

				continue;

			}

			if ( version_compare( $new_version, $value->checked[ $theme ], '>' ) ) {

				$value->response[ $theme ] = $data;

			}

		}

		return $value;

	}

	/**
	 * Return an array of fetched theme data for specific themes.
	 *
	 * @param  array $themes
	 *
	 * @return array
	 */
	private function fetch_theme_data( array $themes ) {

		$response = wp_remote_get( add_query_arg( 'ver', time(), esc_url_raw( self::URL ) ) );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {

			return [];

		}

		$response = (array) json_decode( trim( wp_remote_retrieve_body( $response ) ), true );

		return array_filter( $response, function ( $data ) use ( $themes ) {

			return ( $this->is_valid_theme_data( $data ) && in_array( $data['theme'], $themes, true ) );

		} );

	}

	/**
	 * Check if theme data is valid.
	 *
	 * @param  array $data
	 *
	 * @return bool
	 */
	private function is_valid_theme_data( $data ) {

		return ( ! empty( $data['theme'] ) && ! empty( $data['new_version'] ) && ! empty( $data['url'] ) && ! empty( $data['package'] ) );

	}

	/**
	 * Check if wordpress.org has already reported an update available.
	 *
	 * @param  array  $response
	 * @param  string $theme
	 *
	 * @return string
	 */
	private function check_wp_org_version( $response, $theme ) {

		if (
			! empty( $response[ $theme ]['new_version'] )
			&&
			! empty( $response[ $theme ]['package'] )
			&&
			false !== strpos( $response[ $theme ]['package'], 'wordpress.org' )
		) {

			return $response[ $theme ]['new_version'];

		}

		return '0';

	}

}
