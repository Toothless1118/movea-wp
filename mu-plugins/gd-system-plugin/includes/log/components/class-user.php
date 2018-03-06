<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Event;
use WPaaS\Log\Timer;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class User extends Component {

	/**
	 * A user being deleted.
	 *
	 * @var \WP_User
	 */
	private $deleted_user;

	/**
	 * Return an array of role lables for a user.
	 *
	 * @param  \WP_User|int $user
	 *
	 * @return array
	 */
	private function get_role_labels( $user ) {

		$user = is_a( $user, 'WP_User' ) ? $user : ( is_numeric( $user ) ? get_user_by( 'ID', $user ) : false );

		if ( ! $user ) {

			return;

		}

		global $wp_roles;

		$roles  = $wp_roles->get_names();
		$labels = [];

		foreach ( $roles as $role => $label ) {

			if ( in_array( $role, (array) $user->roles, true ) ) {

				$labels[] = translate_user_role( $label );

			}

		}

		return $labels;

	}

	/**
	 * Grab the object of a user being deleted.
	 *
	 * Before the user is deleted from the database we
	 * must temporarily cache their WP_User object so we
	 * can include that data the log.
	 *
	 * @action delete_user
	 *
	 * @param int $user_id
	 */
	public function callback_delete_user( $user_id ) {

		if ( $user = get_user_by( 'ID', $user_id ) ) {

			$this->deleted_user = $user;

		}

	}

	/**
	 * User > Delete
	 *
	 * @action deleted_user
	 *
	 * @param int $user_id
	 */
	public function callback_deleted_user( $user_id ) {

		if ( empty( $this->deleted_user->ID ) || $this->deleted_user->ID !== $user_id ) {

			return;

		}

		Timer::stop();

		$this->log(
			'delete',
			_x(
				'%1$s\'s user account deleted (%2$s)',
				'1. User display name, 2. User role',
				'gd-system-plugin'
			),
			[
				'display_name'    => $this->deleted_user->display_name,
				'role_labels'     => implode( ', ', $this->get_role_labels( $this->deleted_user ) ),
				'user_id'         => $this->deleted_user->ID,
				'user_login'      => $this->deleted_user->user_login,
				'user_email'      => $this->deleted_user->user_email,
				'user_registered' => Event::e_time( $this->deleted_user->user_registered ),
			]
		);

		$this->deleted_user = null; // Empty the temp user object

	}

	/**
	 * User > Update
	 *
	 * @action profile_update
	 *
	 * @param int      $user_id
	 * @param \WP_User $user
	 */
	public function callback_profile_update( $user_id, $user ) {

		if ( ! is_a( $user, 'WP_User' ) ) {

			return;

		}

		Timer::stop();

		$this->log(
			'update',
			_x(
				"%s's user profile updated",
				'User display name',
				'gd-system-plugin'
			),
			[
				'display_name'    => $user->display_name,
				'role_labels'     => implode( ', ', $this->get_role_labels( $user ) ),
				'user_id'         => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => Event::e_time( $user->user_registered ),
			]
		);

	}

	/**
	 * User > Change Role
	 *
	 * @action set_user_role
	 *
	 * @param int    $user_id
	 * @param string $new_role
	 * @param array  $old_roles
	 */
	public function callback_set_user_role( $user_id, $new_role, $old_roles ) {

		if ( ! $old_roles ) {

			return;

		}

		global $wp_roles;

		Timer::stop();

		$user = get_user_by( 'ID', $user_id );

		$this->log(
			'update',
			_x(
				'%1$s\'s role changed from %2$s to %3$s',
				'1: User display name, 2: Old role, 3: New role',
				'gd-system-plugin'
			),
			[
				'display_name'    => $user->display_name,
				'old_role'        => translate_user_role( $wp_roles->role_names[ $old_roles[0] ] ),
				'new_role'        => translate_user_role( $wp_roles->role_names[ $new_role ] ),
				'user_id'         => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => Event::e_time( $user->user_registered ),
			]
		);

	}

	/**
	 * User > Login
	 *
	 * @action set_auth_cookie
	 *
	 * @param $auth_cookie
	 * @param $expire
	 * @param $expiration
	 * @param $user_id
	 * @param $scheme
	 */
	public function callback_set_auth_cookie( $auth_cookie, $expire, $expiration, $user_id, $scheme ) {

		$user = get_user_by( 'id', $user_id );

		if ( ! is_a( $user, 'WP_User' ) ) {

			return;

		}

		Timer::stop();

		$this->log_metric( 'login', true, $user );

		$this->log(
			'login',
			_x(
				'%s logged in',
				'User display name',
				'gd-system-plugin'
			),
			[
				'display_name' => $user->display_name,
			],
			$user
		);

	}

	/**
	 * User > Logout
	 *
	 * @action clear_auth_cookie
	 */
	public function callback_clear_auth_cookie() {

		$user = wp_get_current_user();

		// Ignore incognito mode trying to clear cookies on failed login attempts
		if ( empty( $user ) || ! $user->exists() ) {

			return;

		}

		Timer::stop();

		$this->log(
			'logout',
			_x(
				'%s logged out',
				'User display name',
				'gd-system-plugin'
			),
			[
				'display_name' => $user->display_name,
			],
			$user
		);

	}

	/**
	 * User > Password Lost
	 *
	 * @action retrieve_password
	 *
	 * @param string $user_login
	 */
	public function callback_retrieve_password( $user_login ) {

		$user = is_email( $user_login ) ? get_user_by( 'email', $user_login ) : get_user_by( 'login', $user_login );

		if ( ! $user ) {

			return;

		}

		Timer::stop();

		$this->log(
			'password_lost',
			_x(
				'%s requested a password reset',
				'User display name',
				'gd-system-plugin'
			),
			[
				'display_name' => $user->display_name,
			],
			$user
		);

	}

	/**
	 * User > Password Reset
	 *
	 * @action password_reset
	 *
	 * @param \WP_User $user
	 */
	public function callback_password_reset( $user ) {

		if ( ! is_a( $user, 'WP_User' ) ) {

			return;

		}

		Timer::stop();

		$this->log(
			'password_reset',
			_x(
				"%s's password reset",
				'User display name',
				'gd-system-plugin'
			),
			[
				'display_name'    => $user->display_name,
				'user_id'         => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => Event::e_time( $user->user_registered ),
			]
		);

	}

}
