<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Auto_Updates {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_filter( 'auto_update_core',                    '__return_false', PHP_INT_MAX );
		add_filter( 'automatic_updates_send_email',        '__return_false', PHP_INT_MAX );
		add_filter( 'enable_auto_upgrade_email',           '__return_false', PHP_INT_MAX );
		add_filter( 'automatic_updates_send_debug_email',  '__return_false', PHP_INT_MAX );
		add_filter( 'auto_core_update_send_email',         '__return_false', PHP_INT_MAX );
		add_filter( 'send_core_update_notification_email', '__return_false', PHP_INT_MAX );

		add_filter( 'user_has_cap',                   [ $this, 'spoof_update_core_cap' ], PHP_INT_MAX );
		add_filter( 'pre_site_transient_update_core', [ $this, 'spoof_update_core_object' ], PHP_INT_MAX );

		$this->unhook_core_update_nags();

	}

	/**
	 * Prevent users from having the `update_core` capability.
	 *
	 * @filter user_has_cap
	 *
	 * @param  array $allcaps
	 *
	 * @return array
	 */
	public function spoof_update_core_cap( array $allcaps ) {

		$allcaps['update_core'] = false;

		return $allcaps;

	}

	/**
	 * Prevent update core nags and notifications.
	 *
	 * @filter pre_site_transient_update_core
	 *
	 * @return object
	 */
	public function spoof_update_core_object() {

		return (object) [
			'last_checked'    => time(),
			'version_checked' => get_bloginfo( 'version' ),
		];

	}

	/**
	 * Prevent all nags related to core updates.
	 *
	 * 1. Loop through every possible nag on every possible admin notice hook.
	 * 2. Dynamically add a hook that unhooks a nag from itself (hookception).
	 * 3. Unhook the dynamically-added hook.
	 * 4. Close the closure pointer reference after each iteration.
	 */
	private function unhook_core_update_nags() {

		$hooks = [
			'network_admin_notices', // Multisite
			'user_admin_notices',
			'admin_notices',
			'all_admin_notices',
		];

		$callbacks = [
			'update_nag',
			'maintenance_nag',
			'site_admin_notice', // Multisite
		];

		foreach ( $hooks as $hook ) {

			foreach ( $callbacks as $callback ) {

				$closure = function () use ( $hook, $callback, &$closure ) {

					if ( false !== ( $priority = has_action( $hook, $callback ) ) ) {

						remove_action( $hook, $callback, $priority );

					}

					remove_action( $hook, $closure, -PHP_INT_MAX );

				};

				add_action( $hook, $closure, -PHP_INT_MAX );

				unset( $closure ); // Close pointer reference

			}

		}

	}

}
