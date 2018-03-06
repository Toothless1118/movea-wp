<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Hotfixes {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		/**
		 * Define the WP101 API key from the WPaaS config.
		 */
		if ( ! defined( 'GD_WP101_API_KEY' ) ) {

			define( 'GD_WP101_API_KEY', Plugin::config( 'wp101_key' ) );

		}

		/**
		 * WP Easy Mode.
		 *
		 * Deactivate all plugins on quit, except WP101.
		 */
		add_filter( 'wpem_deactivate_plugins_on_quit', function( $plugins ) {

			if ( ! function_exists( 'get_plugins' ) ) {

				require_once ABSPATH . 'wp-admin/includes/plugin.php';

			}

			$plugins = get_plugins();

			unset( $plugins['wp101-video-tutorial/wp101-video-tutorial.php'] );
			unset( $plugins['search-engine-visibility/sev.php'] );

			return array_keys( $plugins );

		} );

		/**
		 * WP Popular Posts.
		 *
		 * This makes it perform much better especially on high traffic sites.
		 */
		add_filter( 'wpp_data_sampling', '__return_true' );

		/**
		 * Limit Login Attempts.
		 */
		add_filter( 'pre_update_option_limit_login_lockouts',      [ $this, 'clean_limit_login_attempts' ], PHP_INT_MAX );
		add_filter( 'pre_update_option_limit_login_retries_valid', [ $this, 'clean_limit_login_attempts' ], PHP_INT_MAX );
		add_filter( 'pre_update_option_limit_login_retries',       [ $this, 'clean_limit_login_attempts' ], PHP_INT_MAX );
		add_filter( 'pre_update_option_limit_login_logged',        [ $this, 'clean_limit_login_attempts' ], PHP_INT_MAX );

		/**
		 * Jetpack.
		 */
		if ( Plugin::is_staging_site() ) {

			// Prevent identity crisis from triggering on staging sites.
			add_filter( 'jetpack_has_identity_crisis', '__return_false', PHP_INT_MAX );

		}

		// Hide the Jetpack updates screen nag.
		add_filter( 'option_jetpack_options', [ $this, 'remove_jetpack_nag' ], PHP_INT_MAX );

		/**
		 * Disable sslverify for remote requests on non-production environments.
		 */
		add_filter( 'http_request_args', function( array $args ) {

			if ( ! Plugin::is_env( 'prod' ) ) {

				$args['sslverify'] = false;

			}

			return $args;

		}, PHP_INT_MAX );

		/**
		 * Override the GEM API base URL on non-production environments.
		 */
		if ( ! Plugin::is_env( 'prod' ) ) {

			add_filter( 'gem_api_base_url', function( $url ) {

				return sprintf( 'https://gem.%s-godaddy.com/', Plugin::get_env() );

			}, PHP_INT_MAX );

		}

		/**
		 * WP-Cron.
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {

			$this->blacklist_cron_event_hooks();

		}

		$this->add_cron_restrictions();

		/**
		 * Remove the author credit from GoDaddy themes for other brands.
		 */
		if ( ! Plugin::is_gd() ) {

			add_filter( 'primer_author_credit',               '__return_false' );
			add_filter( 'primer_show_site_identity_settings', '__return_false' );
			add_filter( 'wpem_show_theme_byline',             '__return_false' );

		}

		/**
		 * Change the terms of service URL depending on the brand.
		 */
		$tos_urls = [
			'gd'       => 'https://www.godaddy.com/agreements/showdoc.aspx?pageid=Hosting_SA',
			'mt'       => 'https://mediatemple.net/legal/terms-of-service/',
			'reseller' => sprintf(
				'https://www.secureserver.net/agreements/showdoc.aspx?pageid=Hosting_SA&prog_id=%d',
				Plugin::reseller_id()
			),
		];

		if ( $tos_url = Plugin::use_brand_value( $tos_urls ) ) {

			$return_tos_url = function() use ( $tos_url ) { return $tos_url; };

			add_filter( 'wpem_tos_url',         $return_tos_url );
			add_filter( 'stock_photos_tos_url', $return_tos_url );

		}

	}

	/**
	 * Hide the Jetpack updates screen nag.
	 *
	 * @filter option_jetpack_options
	 *
	 * @param  array $options
	 *
	 * @return array
	 */
	public function remove_jetpack_nag( $options ) {

		if ( $options && empty( $options['hide_jitm']['manage'] ) || 'hide' !== $options['hide_jitm']['manage'] ) {

			$options['hide_jitm']['manage'] = 'hide';

		}

		return $options;

	}

	/**
	 * Clean up options for Limit Login Attempts.
	 *
	 * On very active sites these can become massive
	 * arrays that turn into massive strings and break
	 * MySQL because of packet size limitations.
	 *
	 * @filter pre_update_option_limit_login_lockouts
	 * @filter pre_update_option_limit_login_retries_valid
	 * @filter pre_update_option_limit_login_retries
	 * @filter pre_update_option_limit_login_logged
	 *
	 * @param  array $value
	 *
	 * @return array
	 */
	public function clean_limit_login_attempts( array $value ) {

		if ( count( $value ) < 250 ) {

			return $value;

		}

		$sorting_func = function( $a, $b ) {

			if ( is_array( $b ) ) {

				if ( count( $a ) == count( $b ) ) {

					return 0;

				}

				return ( count( $a ) < count( $b ) ) ? - 1 : 1;

			}

			if ( $a == $b ) {

				return 0;

			}

			return ( $a < $b ) ? -1 : 1;

		};

		uasort( $value, $sorting_func );

		return array_slice( $value, -200 );

	}

	/**
	 * Blacklist cron event hooks.
	 *
	 * Note: Should only run when using WP-CLI.
	 */
	private function blacklist_cron_event_hooks() {

		global $wpdb, $wpaas_cron_event_temp_blacklist;

		$blacklist = [
			'wp_version_check',
		];

		// Get temporary blacklist transients
		$transients = (array) $wpdb->get_results(
			"SELECT
			option_name,
			option_value
			FROM {$wpdb->options}
			WHERE option_name
			LIKE '_transient_wpaas_skip_cron_%';"
		);

		// Scrub array of key prefixes and expired transients
		foreach ( $transients as $key => $transient ) {

			$transients[ $key ]->option_name = preg_filter( '/^_transient_/', '', $transient->option_name );

			if ( false === get_transient( $transient->option_name ) ) {

				unset( $transients[ $key ] );

			}

		}

		// Store in global, used by 'cron event wpaas reset' subcommand
		$wpaas_cron_event_temp_blacklist = array_combine(
			wp_list_pluck( $transients, 'option_name' ),
			wp_list_pluck( $transients, 'option_value' )
		);

		// Merge temporary blacklist into core blacklist
		$blacklist = array_merge( $blacklist, array_values( $wpaas_cron_event_temp_blacklist ) );

		// Remove blacklisted events from the crons array
		add_filter( 'option_cron', function( $crons ) use ( $blacklist ) {

			if ( ! $crons ) {

				return $crons;

			}

			foreach ( (array) $crons as $timestamp => $events ) {

				foreach ( (array) $events as $hook => $event ) {

					if ( in_array( $hook, $blacklist ) ) {

						unset( $crons[ $timestamp ][ $hook ] );

					}

				}

				if ( ! $events ) {

					unset( $crons[ $timestamp ] );

				}

			}

			return (array) $crons;

		}, PHP_INT_MAX );

	}

	/**
	 * Implement WPaaS restrictions on cron events
	 *
	 * since @NEXT
	 */
	private function add_cron_restrictions() {

		/**
		 * If the wp_version_check cron was ever scheduled before
		 * we unschedule it since we are on a managed platform.
		 */
		if ( false !== wp_next_scheduled( 'wp_version_check' ) ) {

			wp_clear_scheduled_hook( 'wp_version_check' );

		}

		add_filter( 'schedule_event', function ( $event ) {

			$blacklisted_hooks = [
				'wp_version_check',
				'wp_maybe_auto_update',
			];

			if ( ! isset( $event->hook ) || in_array( $event->hook, $blacklisted_hooks ) ) {

				return false;

			}

			return $event;

		} );

	}

}
