<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Timer;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Option extends Component {

	/**
	 * Array of options to log.
	 *
	 * @var array
	 */
	private $options = [];

	/**
	 * Run on load.
	 */
	protected function load() {

		$this->options = [
			'blogname'        => __( 'Site Title' ),
			'blogdescription' => __( 'Tagline' ),
			'page_for_posts'  => __( 'Posts page' ),
			'page_on_front'   => __( 'Front page' ),
			'show_on_front'   => __( 'Front page displays' ),
			'site_icon'       => __( 'Site Icon' ),
			'WPLANG'          => __( 'Site Language' ),
		];

	}

	/**
	 * Option > Update
	 *
	 * @action updated_option
	 *
	 * @param  string $option
	 * @param  mixed  $old_value
	 * @param  mixed  $value
	 */
	public function callback_updated_option( $option, $old_value, $value ) {

		if ( 0 === strpos( $option, 'widget_' ) ) {

			$this->log_metric( 'publish' );

		}

		if ( ! isset( $this->options[ $option ] ) ) {

			return;

		}

		Timer::stop();

		/**
		 * Change the component name when options are
		 * being updated via the Customizer.
		 */
		if ( did_action( 'customize_save' ) ) {

			$this->name = 'customizer';

		}

		$this->log_metric( 'publish' );

		$this->log(
			'update',
			_x(
				'"%s" option updated',
				'Option name',
				'gd-system-plugin'
			),
			[
				'option_label' => $this->options[ $option ],
				'option_name'  => $option,
				'new_value'    => $value,
				'old_value'    => $old_value,
			]
		);

	}

}
