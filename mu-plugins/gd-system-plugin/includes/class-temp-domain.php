<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Temp_Domain {

	/**
	 * Class constructor.
	 *
	 * @param API_Interface $api
	 */
	public function __construct( API_Interface $api ) {

		add_filter( 'option_blog_public', [ $this, 'option_blog_public' ], PHP_INT_MAX );

		/**
		 * Bail early if:
		 *
		 * 1. This is a front-end request.
		 * 2. The domain known to be custom.
		 * 3. The user has recently changed their domain.
		 *
		 * Checking the API should be the last conditional
		 * so we can keep those calls to a minimum.
		 */
		if ( ! is_admin() || ! Plugin::is_temp_domain() || $api->user_changed_domain() ) {

			return;

		}

		add_filter( 'pre_update_option_blog_public', [ $this, 'pre_update_option_blog_public' ], PHP_INT_MAX, 2 );
		add_action( 'admin_enqueue_scripts',         [ $this, 'admin_enqueue_scripts' ] );

		if ( ! Plugin::is_staging_site() ) {

			$message = sprintf(
				__( '<strong>Note:</strong> You\'re using the temporary domain <strong>%s</strong>. <a href="%s" target="_blank">Change domain</a>', 'gd-system-plugin' ),
				esc_html( Plugin::domain() ),
				esc_url( Plugin::account_settings_url( 'cname_link' ) )
			);

			new Admin\Notice( $message, [ 'updated', 'error' ] );

		}

	}

	/**
	 * Always disallow indexing on temp domains.
	 *
	 * @filter option_blog_public
	 *
	 * @param  string $value
	 *
	 * @return string
	 */
	public function option_blog_public( $value ) {

		return ( $value && Plugin::is_temp_domain() ) ? '0' : $value;

	}

	/**
	 * Prevent updating the value on temp domains.
	 *
	 * @filter pre_update_option_blog_public
	 *
	 * @param  string $new_value
	 * @param  string $old_value
	 *
	 * @return string
	 */
	public function pre_update_option_blog_public( $new_value, $old_value ) {

		return $old_value;

	}

	/**
	 * Enqueue small JS to disable blog_public checkbox.
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param string $hook
	 */
	public function admin_enqueue_scripts( $hook ) {

		if ( 'options-reading.php' !== $hook ) {

			return;

		}

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'wpaas-options-reading',
			Plugin::assets_url( "js/options-reading{$suffix}.js" ),
			[ 'jquery' ]
		);

		if ( Plugin::is_staging_site() ) {

			$notice = sprintf(
				__( '%s This is your staging site and it cannot be indexed by search engines.', 'gd-system-plugin' ),
				sprintf( '<strong>%s</strong>', __( 'Note:', 'gd-system-plugin' ) )
			);

		} else {

			$notice = sprintf(
				__( '%s Your site is using a temporary domain that cannot be indexed by search engines.', 'gd-system-plugin' ),
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

		}

		wp_localize_script(
			'wpaas-options-reading',
			'wpaas_options_reading_vars',
			[
				'blog_public_notice_text' => esc_js( $notice ),
			]
		);

	}

}
