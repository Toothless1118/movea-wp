<?php
/**
 * Separate Blog Theme Customizer
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 *
 * @package Separate_Blog
 */
if ( ! class_exists( 'Separate_Blog_Customizer' ) ) {
	class Separate_Blog_Customizer {
		/**
		 *  Class public __construct		 
		 */
		public function __construct() {
			add_action( 'customize_register', array( $this, 'separate_blog_customize_register' ) );
		}

		/**
		 * Register Customizer 
		 * @argument 'wp_customize'
		 */
		public function separate_blog_customize_register( $wp_customize ) {
			/**
			 * Customizer theme panel
			 */
			$wp_customize->add_panel(
				'separate_blog_panel', array(
					'priority'		=> 100,
					'capability'	=> 'edit_theme_options',
					'title'			=> __( 'Theme Option', 'separate-blog' ),
					'description'	=> __( 'This section allows you to customize the theme page of your website.', 'separate-blog' ),
				)
			);

			/**
			 * Banner section
			 */
			$wp_customize->add_section(
				'separate_blog_banner_section', array(
					'priority'	=> 5,
					'title'		=> __( 'Banner', 'separate-blog' ),
					'panel'		=> 'separate_blog_panel',
				)
			);

			/**
			 * Social media section
			 */
			$wp_customize->add_section(
				'separate_blog_social_media_section', array(
					'priority'	=> 15,
					'title'		=> __( 'Social media', 'separate-blog' ),
					'panel'		=> 'separate_blog_panel',
				)
			);

			/**
			 * Footer section
			 */
			$wp_customize->add_section(
				'separate_blog_footer_section', array(
					'priority'	=> 20,
					'title'		=> __( 'Footer', 'separate-blog' ),
					'panel'		=> 'separate_blog_panel',
				)
			);

			/**
			 * Banner setting and control
			 */
			$wp_customize->add_setting(
				'separate_blog_banner_image', array(
					'capability'		=> 'edit_theme_options',
					'sanitize_callback'	=> array( $this, 'separate_blog_sanitize_image' ),
				)
			);

			$wp_customize->add_control(
				new WP_Customize_Image_Control(
					$wp_customize, 'separate_blog_banner_image', array(
						'label'		=> __( 'Image', 'separate-blog' ),
						'section'	=> 'separate_blog_banner_section',
						'priority'	=> 5,
						'settings'	=> 'separate_blog_banner_image',
					)
				)
			);

			$wp_customize->add_setting(
				'separate_blog_banner_title', array(
					'capability'		=> 'edit_theme_options',
					'sanitize_callback' => array( $this, 'separate_blog_sanitize_string' )
				)
			);

			$wp_customize->add_control(
				'separate_blog_banner_title', array(
					'type'		=> 'text',
					'label'		=> __( 'Title', 'separate-blog' ),
					'section'	=> 'separate_blog_banner_section',
					'priority'	=> 10,
					'settings'	=> 'separate_blog_banner_title',
				)
			);

			$wp_customize->add_setting(
				'separate_blog_banner_button', array(
					'capability'		=> 'edit_theme_options',
					'sanitize_callback'	=> array( $this, 'separate_blog_sanitize_string' )
				)
			);

			$wp_customize->add_control(
				'separate_blog_banner_button', array(
					'type'		=> 'text',
					'label'		=> __( 'Button Name', 'separate-blog' ),
					'section'	=> 'separate_blog_banner_section',
					'priority'	=> 15,
					'settings'	=> 'separate_blog_banner_button',
				)
			);

			$wp_customize->add_setting(
				'separate_blog_banner_button_url', array(
					'capability'		=> 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw'
					)
				);

			$wp_customize->add_control(
				'separate_blog_banner_button_url', array(
					'type'		=> 'url',
					'label'		=> __( 'Button Url', 'separate-blog' ),
					'section'	=> 'separate_blog_banner_section',
					'priority'	=> 20,
					'settings'	=> 'separate_blog_banner_button_url',
				)
			);

			/**
			 * Social media setting and control
			 */

			$wp_customize->add_setting(
				'separate_blog_facebook', array(
					'capability' => 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw'
				)
			);

			$wp_customize->add_control(
				'separate_blog_facebook', array(
					'type'		=> 'url',
					'label'		=> __( 'Facebook', 'separate-blog' ),
					'section'	=> 'separate_blog_social_media_section',
					'priority'	=> 5,
					'settings'	=> 'separate_blog_facebook',
				)
			);

			$wp_customize->add_setting(
				'separate_blog_twitter', array(
					'capability' => 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw'
				)
			);

			$wp_customize->add_control(
				'separate_blog_twitter', array(
					'type'		=> 'url',
					'label'		=> __( 'Twitter', 'separate-blog' ),
					'section'	=> 'separate_blog_social_media_section',
					'priority'	=> 10,
					'settings'	=> 'separate_blog_twitter',
				)
			);

			$wp_customize->add_setting(
				'separate_blog_google_plus', array(
					'capability' => 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw'
				)
			);

			$wp_customize->add_control(
				'separate_blog_google_plus', array(
					'type'		=> 'url',
					'label'		=> __( 'Google Plus', 'separate-blog' ),
					'section'	=> 'separate_blog_social_media_section',
					'priority'	=> 15,
					'settings'	=> 'separate_blog_google_plus',
				)
			);

			$wp_customize->add_setting(
				'separate_blog_instagram', array(
					'capability' => 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw'
				)
			);

			$wp_customize->add_control(
				'separate_blog_instagram', array(
					'type'		=> 'url',
					'label'		=> __( 'Instagram', 'separate-blog' ),
					'section'	=> 'separate_blog_social_media_section',
					'priority'	=> 20,
					'settings'	=> 'separate_blog_instagram',
				)
			);

			$wp_customize->add_setting(
				'separate_blog_youtube', array(
					'capability' => 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw'
				)
			);

			$wp_customize->add_control(
				'separate_blog_youtube', array(
					'type'		=> 'url',
					'label'		=> __( 'Youtube', 'separate-blog' ),
					'section'	=> 'separate_blog_social_media_section',
					'priority'	=> 25,
					'settings'	=> 'separate_blog_youtube',
				)
			);

			$wp_customize->add_setting(
				'separate_blog_pinterest', array(
					'capability' => 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw'
				)
			);

			$wp_customize->add_control(
				'separate_blog_pinterest', array(
					'type'		=> 'url',
					'label'		=> __( 'Pinterest', 'separate-blog' ),
					'section'	=> 'separate_blog_social_media_section',
					'priority'	=> 30,
					'settings'	=> 'separate_blog_pinterest',
				)
			);
			/**
			 * Footer setting and control
			 */
			$wp_customize->add_setting(
				'separate_blog_address', array(
					'capability'		=> 'edit_theme_options',
					'sanitize_callback' => array( $this, 'separate_blog_sanitize_textarea' )
				)
			);

			$wp_customize->add_control(
				'separate_blog_address', array(
					'type'		=> 'textarea',
					'label'		=> __( 'Address', 'separate-blog' ),
					'section'	=> 'separate_blog_footer_section',
					'priority'	=> 5,
					'settings'	=> 'separate_blog_address',
				)
			);
		}

		/**
		 * Image sanitization callback.
		 *
		 * Checks the image's file extension and mime type against a whitelist. If they're allowed,
		 * send back the filename, otherwise, return the setting default.
		 *
		 * - Sanitization: image file extension
		 * - Control: text, WP_Customize_Image_Control
		 *
		 * @see wp_check_filetype() https://developer.wordpress.org/reference/functions/wp_check_filetype/
		 *
		 * @param string $image Image filename.
		 * @param WP_Customize_Setting $setting Setting instance.
		 * @return string The image filename if the extension is allowed; otherwise, the setting default.
		 */
		function separate_blog_sanitize_image( $image, $setting ) {

			/*
			 * Array of valid image file types.
			 *
			 * The array includes image mime types that are included in wp_get_mime_types()
			 */
			$mimes = array(
				'jpg|jpeg|jpe'	=> 'image/jpeg',
				'gif'			=> 'image/gif',
				'png'			=> 'image/png',
				'bmp'			=> 'image/bmp',
				'tif|tiff'		=> 'image/tiff',
				'ico'			=> 'image/x-icon'
			);

			// Return an array with file extension and mime_type.
			$file = wp_check_filetype( $image, $mimes );

			// If $image has a valid mime_type, return it; otherwise, return the default.
			return ( $file['ext'] ? $image : $setting->default );
		}

		/**
		 * Senitize input field
		 */
		public function separate_blog_sanitize_string( $value ) {
			return sanitize_text_field( $value );
		}

		/**
		 *Sanitize textarea field
		 */
		public function separate_blog_sanitize_textarea( $value ) {
			return sanitize_textarea_field( $value );
		}

	}
	new Separate_Blog_Customizer;
}

