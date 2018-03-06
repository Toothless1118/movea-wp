<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Timer;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Customizer extends Component {

	/**
	 * Return an array of supported theme mods and their labels.
	 *
	 * @return array
	 */
	private function get_theme_mods() {

		$textdomain = wp_get_theme()->get( 'TextDomain' );
		$theme_mods = [
			'background_attachment'   => __( 'Background Attachment' ),
			'background_color'        => __( 'Background Color' ),
			'background_image'        => __( 'Background Image' ),
			'background_position_x'   => __( 'Background Position' ),
			'background_repeat'       => __( 'Background Repeat' ),
			'color_scheme'            => __( 'Base Color Scheme', $textdomain ), // Twenty Sixteen
			'custom_logo'             => __( 'Logo' ),
			'header_background_color' => __( 'Header and Sidebar Background Color', $textdomain ), // Twenty Fifteen
			'header_image'            => __( 'Header Image' ),
			'header_text'             => __( 'Display Site Title and Tagline' ),
			'header_textcolor'        => __( 'Header Text Color' ),
			'link_color'              => __( 'Link Color', $textdomain ), // Twenty Sixteen
			'main_text_color'         => __( 'Main Text Color', $textdomain ), // Twenty Sixteen
			'page_background_color'   => __( 'Page Background Color', $textdomain ), // Twenty Sixteen
			'secondary_text_color'    => __( 'Secondary Text Color', $textdomain ), // Twenty Sixteen
			'sidebar_textcolor'       => __( 'Header and Sidebar Text Color', $textdomain ), // Twenty Fifteen
		];

		return $theme_mods;

	}

	/**
	 * Customizer > Update
	 *
	 * @action updated_option
	 *
	 * @param  string $option
	 * @param  mixed  $old_value
	 * @param  mixed  $value
	 */
	public function callback_updated_option( $option, $old_value, $value ) {

		if ( $old_value === $value || $option !== 'theme_mods_' . get_stylesheet() ) {

			return;

		}

		Timer::stop();

		$theme_mods = $this->get_theme_mods();
		$value      = array_intersect_key( $value, $theme_mods );

		if ( ! $theme_mods ) {

			return;

		}

		$theme = wp_get_theme();

		foreach ( $value as $mod => $mod_value ) {

			$mod_old_value = isset( $old_value[ $mod ] ) ? $old_value[ $mod ] : '';

			if ( $mod_old_value === $mod_value ) {

				continue;

			}

			$this->log_metric( 'publish' );

			$this->log(
				'update',
				_x(
					'"%1$s" updated for the %2$s theme',
					'1. Option name, 2. Theme name',
					'gd-system-plugin'
				),
				[
					'theme_mod_label' => $theme_mods[ $mod ],
					'theme_name'      => $theme->get( 'Name' ),
					'stylesheet'      => $theme->get_stylesheet(),
					'template'        => $theme->get_template(),
					'theme_mod'       => $mod,
					'new_value'       => $mod_value,
					'old_value'       => $mod_old_value,
				]
			);

		}

	}

}
