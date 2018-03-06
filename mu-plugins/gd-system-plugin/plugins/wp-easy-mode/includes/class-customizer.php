<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Customizer {

	/**
	 * Class constructor
	 */
	public function __construct() {

		if ( ! filter_input( INPUT_GET, wpem()->page_slug ) ) {

			return;

		}

		add_action( 'customize_controls_print_styles', [ $this, 'print_styles' ] );

		// Stop here if WalkMe exists.
		if ( defined( 'GD_WALKME_ACTIVE' ) && GD_WALKME_ACTIVE ) {

			return;

		}

		$pointer          = new Pointer;
		$btn_next         = __( 'Next', 'wp-easy-mode' );
		$is_godaddy_theme = ( 'GoDaddy' === wp_get_theme()->get( 'Author' ) ) ? true : false;

		$tooltip_kses = [
			'h3'   => [],
			'p'    => [],
			'span' => [
				'class' => [],
			],
			'svg' => [
				'href'    => [],
				'version' => [],
				'width'   => [],
				'height'  => [],
			],
			'path' => [
				'd' => [],
			],
		];

		// Step 1
		$pointer->register(
			[
				'id'        => 'wpem_done_step_0',
				'screen'    => 'customize',
				'target'    => '#customize-theme-controls',
				'cap'       => 'manage_options',
				'query_var' => [ wpem()->page_slug => 1 ],
				'options'   => [
					'content'  => wp_kses(
						vsprintf(
							'<h3>%s</h3><p>%s</p>',
							$this->get_tooltip_text( 1, $is_godaddy_theme )
						),
						$tooltip_kses
					),
					'position' => [
						'edge'  => 'left',
						'align' => 'right',
					],
				],
				'btn_primary'   => $btn_next,
				'close_on_load' => true,
				'next_pointer'  => ( $is_godaddy_theme && class_exists( 'FLBuilderLoader' ) ) ? 'wpem_done_step_1' : 'wpem_done_step_2',
			]
		);

		// Step 2
		if ( $is_godaddy_theme && class_exists( 'FLBuilderLoader' ) ) {

			$pointer->register(
				[
					'id'        => 'wpem_done_step_1',
					'screen'    => 'customize',
					'target'    => '#customize-theme-controls',
					'cap'       => 'manage_options',
					'query_var' => [ wpem()->page_slug => 1 ],
					'options'   => [
						'content'  => wp_kses(
							vsprintf(
								'<h3>%s</h3><p>%s</p>',
								$this->get_tooltip_text( 2 )
							),
							$tooltip_kses
						),
						'position' => [
							'edge'  => 'left',
							'align' => 'right',
						],
					],
					'btn_primary'   => $btn_next,
					'close_on_load' => true,
					'next_pointer'  => 'wpem_done_step_2',
				]
			);

		}

		// Final Step
		$pointer->register(
			[
				'id'        => 'wpem_done_step_2',
				'screen'    => 'customize',
				'target'    => '#customize-theme-controls',
				'cap'       => 'manage_options',
				'query_var' => [ wpem()->page_slug => 1 ],
				'options'   => [
					'content'  => wp_kses_post(
						vsprintf(
							'<h3>%s</h3><p>%s</p>',
							$this->get_tooltip_text( 3 )
						)
					),
					'position' => [
						'edge'  => 'left',
						'align' => 'right',
					],
				],
				'btn_primary'       => $this->is_english() ? __( 'Watch Video', 'wp-easy-mode' ) . '<span class="dashicons dashicons-video-alt2"></span>' : __( 'Learn More', 'wp-easy-mode' ),
				'btn_primary_class' => 'show-overlay',
				'close_on_load'     => true,
				'btn_close'         => true,
			]
		);

		add_action( 'customize_controls_enqueue_scripts',      [ $this, 'enqueue_scripts' ] );
		add_action( 'customize_controls_print_footer_scripts', [ $this, 'print_script_templates' ] );

	}

	/**
	 * Print custom styles
	 *
	 * @action customize_controls_print_styles
	 */
	public function print_styles() {

		?>
		<style type="text/css">
			body.wp-customizer .change-theme {
				display: none;
			}
		</style>
		<?php

	}

	/**
	 * Enqueue scripts for the customizer
	 *
	 * @action customize_controls_enqueue_scripts
	 */
	public function enqueue_scripts() {

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'wpem-customizer', wpem()->assets_url . "js/customizer{$suffix}.js", [ 'jquery', 'backbone', 'underscore' ], wpem()->version, true );

		wp_enqueue_style( 'wpem-customizer', wpem()->assets_url . "css/customizer{$suffix}.css", [], wpem()->version );

	}

	/**
	 * Print templates needed by our scripts
	 *
	 * @action customize_controls_print_footer_scripts
	 */
	public function print_script_templates() {

		if ( $this->is_english() ) {

			$content_class = 'video';

			ob_start();

			?>
			<div class="video-wrapper">
				<iframe src="//player.vimeo.com/video/146040077"
				        webkitallowfullscreen=""
				        mozallowfullscreen=""
				        allowfullscreen=""
				        frameborder="0">
				</iframe>
			</div>
			<?php

			$content = ob_get_clean();

		} else {

			$content_class = 'text';

			ob_start();

			?>
			<h3>
				<span class="dashicons dashicons-admin-customizer"></span>
				<?php _e( 'The Customizer', 'wp-easy-mode' ); ?>
			</h3>
			<p><?php _e( 'You are now in the Customizer, a tool that enables you to make changes to your website’s appearance, and preview those changes before publishing them. We’ve created a few commonly-used pages and widgets to help you get started.', 'wp-easy-mode' ); ?></p>
			<p><?php _e( 'The top of the Customizer indicates the name of the active theme you’ve selected. You can change the theme at any time, but doing so will change these options and reset any customizations you might have made here.', 'wp-easy-mode' ); ?></p>
			<p><?php _e( 'The options available in the Customizer will vary, depending on the features supported by your current theme. But most themes include these basic Customizer controls:', 'wp-easy-mode' ); ?></p>
			<ul>
				<li><?php _e( 'Site Identity', 'wp-easy-mode' ); ?></li>
				<li><?php _e( 'Colors', 'wp-easy-mode' ); ?></li>
				<li><?php _e( 'Header and Background Images', 'wp-easy-mode' ); ?></li>
				<li><?php _e( 'Navigation Menus', 'wp-easy-mode' ); ?></li>
				<li><?php _e( 'Widgets', 'wp-easy-mode' ); ?></li>
			</ul>
			<p><?php _e( 'When you’re happy with your changes, click <strong>Save & Publish</strong> to keep these new settings. Or, simply close the Customizer by clicking the “X” in the top left-hand corner to discard your changes.', 'wp-easy-mode' ); ?></p>
			<?php

			$content = wp_kses_post( ob_get_clean() );

		}

		?>
		<script type="text/template" id="wpem-overlay-template">
			<div id="wpem-overlay" class="<?php echo $content_class; //xss ok ?>">
				<div class="wpem-overlay-background"></div>
				<div class="wpem-overlay-foreground">
					<div class="wpem-overlay-control">
						<span class="dashicons dashicons-no-alt"></span>
					</div>
					<div class="wpem-overlay-content">
						<?php echo $content; //xss ok ?>
					</div>
				</div>
			</div>
		</script>
		<?php

	}

	/**
	 * Helper function to see if we are dealing with english
	 *
	 * @return bool
	 */
	private function is_english() {

		return ( 'en' === substr( get_locale(), 0, 2 ) );

	}

	/**
	 * Helper fuction to retreive the tooltip content.
	 *
	 * @param  integer $step The tool tip step number.
	 * @param  bool $is_godaddy_theme Is this a GoDaddy theme?
	 *
	 * @return string The tooltip title/content text.
	 */
	private function get_tooltip_text( $step, $is_godaddy_theme = false ) {

		switch ( $step ) {

			default:
			case 1:

				$title   = __( 'Editing Your Site', 'wp-easy-mode' );
				$content = ( $is_godaddy_theme && class_exists( 'Jetpack_Customizer_DM' ) ) ? sprintf( __( 'Click %s to edit your header, footer, menus and more. You can also change your site style here in the Customizer area.', 'wp-easy-mode' ), '<span class="cdm-icon cdm-icon-example cdm-icon--text ' . esc_attr( get_user_option( 'admin_color' ) ) . '"><svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" viewBox="0 0 20 20"><path d="M13.89 3.39l2.71 2.72c0.46 0.46 0.42 1.24 0.030 1.64l-8.010 8.020-5.56 1.16 1.16-5.58s7.6-7.63 7.99-8.030c0.39-0.39 1.22-0.39 1.68 0.070zM11.16 6.18l-5.59 5.61 1.11 1.11 5.54-5.65zM8.19 14.41l5.58-5.6-1.070-1.080-5.59 5.6z"></path></svg></span>' ) : __( 'Update your site content, layout, color scheme and more using the window to the left.', 'wp-easy-mode' );

				break;

			case 2:

				$title   = __( 'Editing Your Content', 'wp-easy-mode' );
				$content = sprintf( __( ' Click %s to edit your page body content. This will open the page builder in a new window.', 'wp-easy-mode' ), '<span class="cdm-icon cdm-icon-example cdm-icon--text ' . esc_attr( get_user_option( 'admin_color' ) ) . '"><svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" viewBox="0 0 20 20"><path d="M19 16v-13c0-0.55-0.45-1-1-1h-15c-0.55 0-1 0.45-1 1v13c0 0.55 0.45 1 1 1h15c0.55 0 1-0.45 1-1zM4 4h13v4h-13v-4zM5 5v2h3v-2h-3zM9 5v2h3v-2h-3zM13 5v2h3v-2h-3zM4.5 10c0.28 0 0.5 0.22 0.5 0.5s-0.22 0.5-0.5 0.5-0.5-0.22-0.5-0.5 0.22-0.5 0.5-0.5zM6 10h4v1h-4v-1zM12 10h5v5h-5v-5zM4.5 12c0.28 0 0.5 0.22 0.5 0.5s-0.22 0.5-0.5 0.5-0.5-0.22-0.5-0.5 0.22-0.5 0.5-0.5zM6 12h4v1h-4v-1zM13 12v2h3v-2h-3zM4.5 14c0.28 0 0.5 0.22 0.5 0.5s-0.22 0.5-0.5 0.5-0.5-0.22-0.5-0.5 0.22-0.5 0.5-0.5zM6 14h4v1h-4v-1z"></path></svg></span>' );

				break;

			case 3:

				$title = __( 'Tutorial', 'wp-easy-mode' );

				if ( $this->is_english() ) {

					$content = __( 'Click "Watch Video" to view a quick demonstration of how to customize your site with the Customizer.', 'wp-easy-mode' );

				} else {

					$content = __( 'Click "Learn More" to view some tips on how to customize your site with the Customizer.', 'wp-easy-mode' );

				}

				break;

		}

		return [ $title, $content ];

	}

}
