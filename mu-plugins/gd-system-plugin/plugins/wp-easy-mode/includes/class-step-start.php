<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Step_Start extends Step {

	/**
	 * Class constructor
	 *
	 * @param Log $log
	 */
	public function __construct( Log $log ) {

		parent::__construct( $log );

		$this->args = [
			'name'       => 'start',
			'title'      => __( 'Start', 'wp-easy-mode' ),
			'page_title' => __( 'Get Started Quickly with WordPress', 'wp-easy-mode' ),
			'can_skip'   => false,
		];

	}

	/**
	 * Step init
	 */
	protected function init() {}

	/**
	 * Step content
	 */
	public function content() {

		update_option( 'wpem_last_viewed', $this->name );

		?>
		<p><?php _e( 'Our Quick Start Wizard is the fastest way to build your website because it:', 'wp-easy-mode' ) ?></p>

		<ul>
			<li><?php _e( 'Features pre-built websites for you to customize and publish', 'wp-easy-mode' ) ?></li>
			<li><?php _e( 'Includes a drag-and-drop editor to make customization easy', 'wp-easy-mode' ) ?></li>
			<li><?php _e( 'Has 1000s of categorized, searchable images in its image library', 'wp-easy-mode' ) ?></li>
		</ul>
		<?php

	}

	/**
	 * Step actions
	 */
	public function actions() {

		?>
		<input type="hidden" id="wpem_continue" name="wpem_continue" value="yes">
		<input type="submit" id="wpem_no_thanks" class="button button-secondary" value="<?php esc_attr_e( 'No thanks', 'wp-easy-mode' ) ?>">
		<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Start Wizard', 'wp-easy-mode' ) ?>">
		<?php

	}

	/**
	 * Step callback
	 */
	public function callback() {

		$continue = filter_input( INPUT_POST, 'wpem_continue' );

		$continue = in_array( $continue, [ 'yes', 'no' ] ) ? $continue : 'no';

		$this->log->add_step_field( 'wpem_continue', $continue );

		if ( 'no' === $continue ) {

			wpem_quit();

			return;

		}

		if ( isset( $this->log->geodata ) ) {

			new Smart_Defaults( $this->log->geodata );

		}

		wpem_mark_as_started();

	}

}
