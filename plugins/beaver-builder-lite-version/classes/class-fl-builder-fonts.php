<?php

/**
 * Helper class for font settings.
 *
 * @class   FLBuilderFonts
 * @since   1.6.3
 */
final class FLBuilderFonts {

	/**
	 * An array of fonts / weights.
	 * @var array
	 */
	static private $fonts = array();

	/**
	 * @since 1.9.5
	 * @return void
	 */
	static public function init() {
		add_action( 'wp_enqueue_scripts', __CLASS__ . '::combine_google_fonts', 99 );
	}

	/**
	 * Renders the JavasCript variable for font settings dropdowns.
	 *
	 * @since  1.6.3
	 * @return void
	 */
	static public function js() {
		$default = json_encode( apply_filters( 'fl_builder_font_families_default', FLBuilderFontFamilies::$default ) );
		$system  = json_encode( apply_filters( 'fl_builder_font_families_system', FLBuilderFontFamilies::$system ) );
		$google  = json_encode( apply_filters( 'fl_builder_font_families_google', FLBuilderFontFamilies::google() ) );

		echo 'var FLBuilderFontFamilies = { default: ' . $default . ', system: ' . $system . ', google: ' . $google . ' };';
	}

	/**
	 * Renders a list of all available fonts.
	 *
	 * @since  1.6.3
	 * @param  string $font The current selected font.
	 * @return void
	 */
	static public function display_select_font( $font ) {
		$system_fonts = apply_filters( 'fl_builder_font_families_system', FLBuilderFontFamilies::$system );
		$google_fonts = apply_filters( 'fl_builder_font_families_google', FLBuilderFontFamilies::google() );

		echo '<option value="Default" ' . selected( 'Default', $font, false ) . '>' . __( 'Default', 'fl-builder' ) . '</option>';
		echo '<optgroup label="System">';

		foreach ( $system_fonts as $name => $variants ) {
			echo '<option value="' . $name . '" ' . selected( $name, $font, false ) . '>' . $name . '</option>';
		}

		echo '<optgroup label="Google">';

		foreach ( $google_fonts as $name => $variants ) {
			echo '<option value="' . $name . '" ' . selected( $name, $font, false ) . '>' . $name . '</option>';
		}
	}

	/**
	 * Renders a list of all available weights for a selected font.
	 *
	 * @since  1.6.3
	 * @param  string $font   The current selected font.
	 * @param  string $weight The current selected weight.
	 * @return void
	 */
	static public function display_select_weight( $font, $weight ) {
		if ( 'Default' == $font ) {
			echo '<option value="default" selected="selected">' . __( 'Default', 'fl-builder' ) . '</option>';
		} else {
			$system_fonts = apply_filters( 'fl_builder_font_families_system', FLBuilderFontFamilies::$system );
			$google_fonts = apply_filters( 'fl_builder_font_families_google', FLBuilderFontFamilies::google() );

			if ( array_key_exists( $font, $system_fonts ) ) {
				foreach ( $system_fonts[ $font ]['weights'] as $variant ) {
					echo '<option value="' . $variant . '" ' . selected( $variant, $weight, false ) . '>' . FLBuilderFonts::get_weight_string( $variant ) . '</option>';
				}
			} else {
				foreach ( $google_fonts[ $font ] as $variant ) {
					echo '<option value="' . $variant . '" ' . selected( $variant, $weight, false ) . '>' . FLBuilderFonts::get_weight_string( $variant ) . '</option>';
				}
			}
		}

	}

	/**
	 * Returns a font weight name for a respective weight.
	 *
	 * @since  1.6.3
	 * @param  string $weight The selected weight.
	 * @return string         The weight name.
	 */
	static public function get_weight_string( $weight ) {

		$weight_string = apply_filters( 'fl_builder_font_weight_strings', array(
			'default' => __( 'Default', 'fl-builder' ),
			'regular' => __( 'Regular', 'fl-builder' ),
			'100' => 'Thin 100',
			'200' => 'Extra-Light 200',
			'300' => 'Light 300',
			'400' => 'Normal 400',
			'500' => 'Medium 500',
			'600' => 'Semi-Bold 600',
			'700' => 'Bold 700',
			'800' => 'Extra-Bold 800',
			'900' => 'Ultra-Bold 900',
		) );

		return $weight_string[ $weight ];
	}

	/**
	 * Helper function to render css styles for a selected font.
	 *
	 * @since  1.6.3
	 * @param  array $font An array with font-family and weight.
	 * @return void
	 */
	static public function font_css( $font ) {

		$system_fonts = apply_filters( 'fl_builder_font_families_system', FLBuilderFontFamilies::$system );

		$css = '';

		if ( array_key_exists( $font['family'], $system_fonts ) ) {

			$css .= 'font-family: "' . $font['family'] . '",' . $system_fonts[ $font['family'] ]['fallback'] . ';';

		} else {
			$css .= 'font-family: "' . $font['family'] . '", sans-serif;';
		}

		if ( 'regular' == $font['weight'] ) {
			$css .= 'font-weight: normal;';
		} else {
			if ( 'i' == substr( $font['weight'], -1 ) ) {
				$css .= 'font-weight: ' . substr( $font['weight'], 0, -1 ) . ';';
				$css .= 'font-style: italic;';
			} else {
				$css .= 'font-weight: ' . $font['weight'] . ';';
			}
		}

		echo $css;
	}

	/**
	 * Add fonts to the $font array for a module.
	 *
	 * @since  1.6.3
	 * @param  object $module The respective module.
	 * @return void
	 */
	static public function add_fonts_for_module( $module ) {
		$fields = FLBuilderModel::get_settings_form_fields( $module->form );

		foreach ( $fields as $name => $field ) {
			if ( 'font' == $field['type'] && isset( $module->settings->$name ) ) {
				self::add_font( $module->settings->$name );
			} elseif ( isset( $field['form'] ) ) {
				$form = FLBuilderModel::$settings_forms[ $field['form'] ];
				self::add_fonts_for_nested_module_form( $module, $form['tabs'], $name );
			}
		}
	}

	/**
	 * Add fonts to the $font array for a nested module form.
	 *
	 * @since 1.8.6
	 * @access private
	 * @param object $module The module to add for.
	 * @param array $form The nested form.
	 * @param string $setting The nested form setting key.
	 * @return void
	 */
	static private function add_fonts_for_nested_module_form( $module, $form, $setting ) {
		$fields = FLBuilderModel::get_settings_form_fields( $form );

		foreach ( $fields as $name => $field ) {
			if ( 'font' == $field['type'] && isset( $module->settings->$setting ) ) {
				foreach ( $module->settings->$setting as $key => $val ) {
					if ( isset( $val->$name ) ) {
						self::add_font( (array) $val->$name );
					} elseif ( $name == $key && ! empty( $val ) ) {
						self::add_font( (array) $val );
					}
				}
			}
		}
	}

	/**
	 * Enqueue the stylesheet for fonts.
	 *
	 * @since  1.6.3
	 * @return void
	 */
	static public function enqueue_styles() {
		$google_fonts_domain = apply_filters( 'fl_builder_google_fonts_domain', '//fonts.googleapis.com/' );
		$google_url = $google_fonts_domain . 'css?family=';

		if ( count( self::$fonts ) > 0 ) {

			foreach ( self::$fonts as $family => $weights ) {
				$google_url .= $family . ':' . implode( ',', $weights ) . '|';
			}

			$google_url = substr( $google_url, 0, -1 );

			wp_enqueue_style( 'fl-builder-google-fonts-' . md5( $google_url ), $google_url, array() );

			self::$fonts = array();
		}
	}

	/**
	 * Adds data to the $fonts array for a font to be rendered.
	 *
	 * @since  1.6.3
	 * @param  array $font an array with the font family and weight to add.
	 * @return void
	 */
	static public function add_font( $font ) {

		if ( 'Default' != $font['family'] ) {

			$system_fonts = apply_filters( 'fl_builder_font_families_system', FLBuilderFontFamilies::$system );

			// check if is a Google Font
			if ( ! array_key_exists( $font['family'], $system_fonts ) ) {

				// check if font family is already added
				if ( array_key_exists( $font['family'], self::$fonts ) ) {

					// check if the weight is already added
					if ( ! in_array( $font['weight'], self::$fonts[ $font['family'] ] ) ) {
						self::$fonts[ $font['family'] ][] = $font['weight'];
					}
				} else {
					// adds a new font and weight
					self::$fonts[ $font['family'] ] = array( $font['weight'] );

				}
			}
		}
	}

	/**
	 * Combines all enqueued google font HTTP calls into one URL.
	 *
	 * @since  1.9.5
	 * @return void
	 */
	static public function combine_google_fonts() {
		global $wp_styles;

		// Check for any enqueued `fonts.googleapis.com` from BB theme or plugin
		if ( isset( $wp_styles->queue ) ) {

			$google_fonts_domain = 'https://fonts.googleapis.com/css';
			$enqueued_google_fonts = array();
			$families = array();
			$subsets = array();
			$font_args = array();

			// Collect all enqueued google fonts
			foreach ( $wp_styles->queue as $key => $handle ) {

				if ( ! isset( $wp_styles->registered[ $handle ] ) || strpos( $handle, 'fl-builder-google-fonts-' ) === false ) {
					continue;
				}

				$style_src = $wp_styles->registered[ $handle ]->src;

				if ( strpos( $style_src, 'fonts.googleapis.com/css' ) !== false ) {
					$url = wp_parse_url( $style_src );

					if ( is_string( $url['query'] ) ) {
						parse_str( $url['query'], $parsed_url );

						if ( isset( $parsed_url['family'] ) ) {

							// Collect all subsets
							if ( isset( $parsed_url['subset'] ) ) {
								$subsets[] = urlencode( trim( $parsed_url['subset'] ) );
							}

							$font_families = explode( '|', $parsed_url['family'] );
							foreach ( $font_families as $parsed_font ) {

								$get_font = explode( ':', $parsed_font );

								// Extract the font data
								if ( isset( $get_font[0] ) && ! empty( $get_font[0] ) ) {
									$family = $get_font[0];
									$weights = isset( $get_font[1] ) && ! empty( $get_font[1] ) ? explode( ',', $get_font[1] ) : array();

									// Combine weights if family has been enqueued
									if ( isset( $enqueued_google_fonts[ $family ] ) && $weights != $enqueued_google_fonts[ $family ]['weights'] ) {
										$combined_weights = array_merge( $weights, $enqueued_google_fonts[ $family ]['weights'] );
										$enqueued_google_fonts[ $family ]['weights'] = array_unique( $combined_weights );
									} else {
										$enqueued_google_fonts[ $family ] = array(
											'handle'	=> $handle,
											'family'	=> $family,
											'weights'	=> $weights,
										);

										// Remove enqueued google font style, so we would only have one HTTP request.
										wp_dequeue_style( $handle );
									}
								}
							}
						}
					}// End if().
				}// End if().
			}// End foreach().

			// Start combining all enqueued google fonts
			if ( count( $enqueued_google_fonts ) > 0 ) {

				foreach ( $enqueued_google_fonts as $family => $data ) {
					// Collect all family and weights
					if ( ! empty( $data['weights'] ) ) {
						$families[] = $family . ':' . implode( ',', $data['weights'] );
					} else {
						$families[] = $family;
					}
				}

				if ( ! empty( $families ) ) {
					$font_args['family'] = implode( '|', $families );

					if ( ! empty( $subsets ) ) {
						$font_args['subset'] = implode( ',', $subsets );
					}

					$src = add_query_arg( $font_args, $google_fonts_domain );

					// Enqueue google fonts into one URL request
					wp_enqueue_style(
						'fl-builder-google-fonts-' . md5( $src ),
						$src,
						array()
					);

					// Clears data
					$enqueued_google_fonts = array();
				}
			}
		}// End if().
	}

}

FLBuilderFonts::init();

/**
 * Font info class for system and Google fonts.
 *
 * @class FLFontFamilies
 * @since 1.6.3
 */
final class FLBuilderFontFamilies {

	/**
	 * Parse fonts.json to get all possible Google fonts.
	 * @since 1.10.7
	 * @return array
	 */
	static function google() {

		$fonts = array();
		$json = (array) json_decode( file_get_contents( FL_BUILDER_DIR . 'json/fonts.json' ), true );

		foreach ( $json as $k => $font ) {

			$name = key( $font );

			foreach ( $font[ $name ] as $key => $variant ) {
				if ( stristr( $variant, 'italic' ) ) {
							unset( $font[ $name ][ $key ] );
				}
				if ( 'regular' == $variant ) {
					$font[ $name ][ $key ] = '400';
				}
			}

			$fonts[ $name ] = $font[ $name ];
		}
		return $fonts;
	}

	static public $default = array(
		'Default' => array(
			'default'
		),
	);

	/**
	 * Array with a list of system fonts.
	 * @var array
	 */
	static public $system = array(
		'Helvetica' => array(
			'fallback' => 'Verdana, Arial, sans-serif',
			'weights'  => array(
				'300',
				'400',
				'700',
			),
		),
		'Verdana' => array(
			'fallback' => 'Helvetica, Arial, sans-serif',
			'weights'  => array(
				'300',
				'400',
				'700',
			),
		),
		'Arial' => array(
			'fallback' => 'Helvetica, Verdana, sans-serif',
			'weights'  => array(
				'300',
				'400',
				'700',
			),
		),
		'Times' => array(
			'fallback' => 'Georgia, serif',
			'weights'  => array(
				'300',
				'400',
				'700',
			),
		),
		'Georgia' => array(
			'fallback' => 'Times, serif',
			'weights'  => array(
				'300',
				'400',
				'700',
			),
		),
		'Courier' => array(
			'fallback' => 'monospace',
			'weights'  => array(
				'300',
				'400',
				'700',
			),
		),
	);
}
