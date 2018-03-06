<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Change_Domain {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

		add_filter( 'sanitize_option_home',    [ $this, 'block_domain_changes' ], PHP_INT_MAX, 2 );
		add_filter( 'sanitize_option_siteurl', [ $this, 'block_domain_changes' ], PHP_INT_MAX, 2 );

		if ( Plugin::is_multi_domain_mode() ) {

			add_action( 'template_redirect', [ $this, 'rewrite_output' ], -PHP_INT_MAX );

			$this->domain();
			$this->ssl();

		}

	}

	/**
	 * Enqueue small JS to disable siteurl and home fields.
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param string $hook
	 */
	public function admin_enqueue_scripts( $hook ) {

		if ( 'options-general.php' !== $hook ) {

			return;

		}

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'wpaas-options-general',
			Plugin::assets_url( "js/options-general{$suffix}.js" ),
			[ 'jquery' ]
		);

		$notice = sprintf(
			_x( '%s Your domain cannot be changed here.', 'Title of alert in bold', 'gd-system-plugin' ),
			sprintf( '<strong>%s</strong>', __( 'Note:', 'gd-system-plugin' ) )
		);

		// Append a link where the domain can be changed
		if ( $url = Plugin::account_settings_url( 'cname_link' ) ) {

			$notice .= sprintf(
				' <a href="%s">%s</a>',
				esc_url( $url ),
				__( 'Change domain', 'gd-system-plugin' )
			);

		}

		// Use a different message entirely on staging sites
		if ( Plugin::is_staging_site() ) {

			$notice = sprintf(
				_x( '%s This is your staging site and the domain cannot be changed.', 'Title of alert in bold', 'gd-system-plugin' ),
				sprintf( '<strong>%s</strong>', __( 'Note:', 'gd-system-plugin' ) )
			);

		}

		wp_localize_script(
			'wpaas-options-general',
			'wpaas_options_general_vars',
			[
				'urls_notice_text' => esc_js( $notice ),
			]
		);

	}

	/**
	 * Don't allow domain options to be changed manually.
	 *
	 * @filter sanitize_option_home
	 * @filter sanitize_option_siteurl
	 *
	 * @param  string $value
	 * @param  string $option
	 *
	 * @return string
	 */
	public function block_domain_changes( $value, $option ) {

		if ( Plugin::is_doing_wpem() ) {

			return;

		}

		global $wp_settings_errors;

		foreach ( (array) $wp_settings_errors as $key => $error ) {

			if ( $option === $error['setting'] ) {

				unset( $wp_settings_errors[ $key ] );

			}

		}

		if (
			$value === ( $old = get_option( $option ) ) // No change
			||
			! function_exists( 'add_settings_error' ) // WP-CLI mode
		) {

			return $old;

		}

		if ( Plugin::is_staging_site() ) {

			add_settings_error(
				$option,
				'wpaas_invalid_' . $option,
				sprintf(
					__( '%s This is your staging site and the domain cannot be changed.', 'Title of alert in bold', 'gd-system-plugin' ),
					sprintf( '<strong>%s</strong>', __( 'Note:', 'gd-system-plugin' ) )
				)
			);

		} else {

			add_settings_error(
				$option,
				'wpaas_invalid_' . $option,
				sprintf(
					_x( '%s Your domain cannot be changed here.', 'Title of alert in bold', 'gd-system-plugin' ),
					sprintf( '<strong>%s</strong>', __( 'Note:', 'gd-system-plugin' ) )
				)
			);

		}

		return $old;

	}

	/**
	 * Rewrite output.
	 *
	 * @action template_redirect
	 */
	public function rewrite_output() {

		ob_start( function( $content ) { return apply_filters( 'wpaas_output_rewrite', $content ); } );

	}

	/**
	 * Change the domain.
	 */
	private function domain() {

		$old = parse_url( site_url(), PHP_URL_HOST );
		$new = $this->get_current_domain();

		add_filter( 'option_home', function( $value ) use ( $old, $new ) {

			return str_replace( "://{$old}", "://{$new}", $value );

		}, PHP_INT_MAX );

		add_filter( 'option_siteurl', function( $value ) use ( $old, $new ) {

			return str_replace( "://{$old}", "://{$new}", $value );

		}, PHP_INT_MAX );

		add_filter( 'wpaas_output_rewrite', function( $content ) use ( $old, $new ) {

			return preg_replace( "#(https?)://{$old}(/?)#", "$1://{$new}$2", $content );

		}, PHP_INT_MAX );

	}

	/**
	 * Switch to SSL.
	 */
	private function ssl() {

		if ( ! is_ssl() && ! Plugin::is_ssl_admin() && ! Plugin::is_ssl_login() ) {

			return;

		}

		add_filter( 'wpaas_output_rewrite', function( $content ) {

			$domain = parse_url( site_url(), PHP_URL_HOST );
			$domain = ( $this->get_current_domain() === $domain ) ? $domain : $this->get_current_domain();

			return preg_replace( "#http://{$domain}(/?)#", "https://{$domain}$1", $content );

		}, PHP_INT_MAX );

	}

	/**
	 * Get the current domain.
	 *
	 * @return string
	 */
	private function get_current_domain() {

		return preg_replace( '/(:[0-9]+)/', '', $_SERVER['HTTP_HOST'] ); // Without port number

	}

}
