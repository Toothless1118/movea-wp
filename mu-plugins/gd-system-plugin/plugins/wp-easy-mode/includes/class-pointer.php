<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Pointer {

	/**
	 * Array of pointers
	 *
	 * @var array
	 */
	private $pointers = [];

	/**
	 * Class constructor
	 */
	public function __construct() {

		if ( get_option( 'wpem_opt_out' ) ) {

			return;

		}

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

	}

	/**
	 * Register a pointer
	 *
	 * @param array $pointer
	 */
	public function register( $pointer ) {

		if (
			empty( $pointer['id'] )
			||
			empty( $pointer['target'] )
			||
			empty( $pointer['options']['content'] )
		) {

			return;

		}

		/**
		 * Example:
		 *
		 * [
		 *   'id'        => 'wpem_name',      // Unique name
		 *   'screen'    => 'edit',           // Admin screen ID to display pointer
		 *   'target'    => '#some-element',  // Page element to point at
		 *   'user_id'   => 1,                // Only this user will see the pointer (optional)
		 *   'cap'       => 'manage_options', // Only users with this capability will see the pointer (optional)
		 *   'query_var' => [
		 *     'wpem' => 1,                   // Only show pointer when this $_GET key/value is present (optional)
		 *   ],
		 *   'options'   => [
		 *     'content'  => '<h3>Heading</h3><p>Hello world</p>',
		 *     'position' => [
		 *       'edge'  => 'left',
		 *       'align' => 'right',
		 *     ],
		 *   ],
		 * ];
		 *
		 * @var array
		 */
		$this->pointers[] = $pointer;

	}

	public function register_scripts() {

		if ( ! $this->pointers ) {

			return;

		}

		$pointers = [];

		foreach ( $this->pointers as $pointer ) {

			if ( $this->is_viewable( $pointer ) ) {

				// Add ajax url in case we don't have access to ajaxurl var
				$pointer['ajaxurl'] = admin_url( 'admin-ajax.php', 'relative' );

				$pointers[] = $pointer;

			}

		}

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'wpem-pointers', wpem()->assets_url . "js/pointers{$suffix}.js", [ 'jquery', 'wp-pointer' ], wpem()->version, true );

		wp_localize_script( 'wpem-pointers', 'wpem_pointers', $pointers );

	}

	/**
	 * Enqueue scripts and styles in the admin
	 *
	 * @action admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts() {

		$this->register_scripts();

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script( 'wpem-pointers' );

	}

	/**
	 * Check if a pointer is viewable
	 *
	 * @param  array $pointer
	 *
	 * @return bool
	 */
	private function is_viewable( $pointer ) {

		$query_var = ! empty( $pointer['query_var'] ) ? $pointer['query_var'] : [];
		$user_id   = ! empty( $pointer['user_id'] ) ? absint( $pointer['user_id'] ) : get_current_user_id();
		$cap       = ! empty( $pointer['cap'] ) ? $pointer['cap'] : 'read';
		$screen    = ( empty( $pointer['screen'] ) || $pointer['screen'] === get_current_screen()->id );

		return (
			$screen
			&&
			(string) current( $query_var ) === (string) filter_input( INPUT_GET, key( $query_var ) )
			&&
			get_current_user_id() === $user_id
			&&
			current_user_can( $cap )
			&&
			! $this->is_dismissed( $pointer['id'] )
		);

	}

	/**
	 * Check if a pointer has been dismissed by the current user
	 *
	 * @param  string $pointer_id
	 *
	 * @return bool
	 */
	private function is_dismissed( $pointer_id ) {

		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

		return in_array( $pointer_id, $dismissed );

	}

}
