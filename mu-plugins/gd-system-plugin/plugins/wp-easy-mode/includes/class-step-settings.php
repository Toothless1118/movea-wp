<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Step_Settings extends Step {

	/**
	 * Fields object
	 *
	 * @var object
	 */
	private $fields;

	/**
	 * Hold the image api we got by dependency injection
	 *
	 * @var object
	 */
	private $image_api;

	/**
	 * Class constructor
	 *
	 * @param Log       $log
	 * @param Image_API $image_api
	 */
	public function __construct( Log $log, Image_API $image_api ) {

		$this->image_api = $image_api;

		parent::__construct( $log );

		$this->args = [
			'name'       => 'settings',
			'title'      => __( 'Settings', 'wp-easy-mode' ),
			'page_title' => __( 'Settings', 'wp-easy-mode' ),
			'can_skip'   => false,
		];

		if ( $this->image_api->is_d3_locale() ) {

			add_action( 'wpem_print_header_scripts_' . $this->args['name'], [ $this, 'print_header_scripts' ] );
			add_action( 'wpem_print_footer_scripts_' . $this->args['name'], [ $this, 'print_footer_scripts' ] );

		}

	}

	/**
	 * Add select2 css
	 *
	 * @action wpem_print_header_scripts_settings
	 */
	public function print_header_scripts() {

		wp_print_styles( 'jquery-select2-css' );

	}

	/**
	 * Add select2 js
	 *
	 * @action wpem_print_footer_scripts_settings
	 */
	public function print_footer_scripts() {

		wp_print_scripts( 'jquery-select2' );
		wp_print_scripts( 'jquery-select2-driver' );

	}

	/**
	 * Step init
	 */
	protected function init() {

		$store_locale = new Store_Settings();

		$fields = [
			[
				'name'        => 'wpem_site_type',
				'label'       => __( 'Type', 'wp-easy-mode' ),
				'type'        => 'radio',
				'sanitizer'   => 'sanitize_key',
				'description' => __( 'What type of website would you like to create?', 'wp-easy-mode' ),
				'value'       => wpem_get_site_type(),
				'required'    => true,
				'choices'     => [
					'standard' => __( 'Website + Blog', 'wp-easy-mode' ),
					'blog'     => __( 'Blog only', 'wp-easy-mode' ),
					'store'    => __( 'Online Store', 'wp-easy-mode' ),
				],
			],
			$this->industry_field(),
			[
				'name'        => 'blogname',
				'label'       => __( 'Title', 'wp-easy-mode' ),
				'type'        => 'text',
				'sanitizer'   => function( $value ) {
					return stripcslashes( sanitize_option( 'blogname', $value ) );
				},
				'description' => __( 'The title of your website appears at the top of all pages and in search results.', 'wp-easy-mode' ),
				'value'       => ( __( 'A WordPress Site', 'wp-easy-mode' ) !== get_option( 'blogname' ) ) ? get_option( 'blogname' ) : __( 'A WordPress Site', 'wp-easy-mode' ),
				'required'    => true,
				'atts'        => [
					'placeholder' => __( 'Enter your website title here', 'wp-easy-mode' ),
				],
			],
			[
				'name'        => 'blogdescription',
				'label'       => __( 'Tagline', 'wp-easy-mode' ),
				'type'        => 'text',
				'sanitizer'   => function( $value ) {
					return stripcslashes( sanitize_option( 'blogdescription', $value ) );
				},
				'description' => __( 'Think of the tagline as a slogan that describes what makes your website special. It will also appear in search results.', 'wp-easy-mode' ),
				'value'       => ( __( 'Just another WordPress site', 'wp-easy-mode' ) !== get_option( 'blogdescription' ) ) ? get_option( 'blogdescription' ) : __( 'Just another WordPress site', 'wp-easy-mode' ),
				'required'    => true,
				'atts'        => [
					'placeholder' => __( 'Enter your website tagline here', 'wp-easy-mode' ),
				],
			],
			$store_locale->wpem_ecommerce_fields(),
		];

		$this->fields = new Fields( $fields );

		add_action( 'wpem_template_notices', [ $this->fields, 'error_notice' ] );

	}

	/**
	 * Step content
	 */
	public function content() {

		printf(
			'<p class="lead-text align-center">%s</p>',
			__( 'Please tell us more about your website (all fields are required)', 'wp-easy-mode' )
		);

		$this->fields->display();

		/**
		 * Fires after the Settings content
		 *
		 * @since 1.0.0
		 */
		do_action( 'wpem_step_settings_after_content' );

	}

	/**
	 * Step actions
	 */
	public function actions() {

		?>
		<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Continue', 'wp-easy-mode' ) ?>">
		<?php

	}

	/**
	 * Step callback
	 */
	public function callback() {

		$saved = $this->fields->save();

		// No need to fetch API again if fields updated

		// Once all the fields are saved, let's query our Image API with the saved category
		$site_industry = isset( $saved['wpem_site_industry'] ) ? $saved['wpem_site_industry'] : false;
		$site_type     = isset( $saved['wpem_site_type'] ) ? $saved['wpem_site_type'] : false;

		if ( $site_industry && 'store' !== $site_type ) {

			$this->image_api->get_images_by_cat( $site_industry );

		}

	}

	/**
	 * Due to i18n limitations of the D3 categories,
	 * conditionally show d3 categories in a new fancy schmancy select field for en_US and en_CA users
	 * and show the original basic industry select field for everyone else
	 */
	private function industry_field() {

		$field = [
			'name'        => 'wpem_site_industry',
			'label'       => __( 'Industry', 'wp-easy-mode' ),
			'type'        => 'select',
			'sanitizer'   => 'sanitize_key',
			'description' => __( 'What will your website be about?', 'wp-easy-mode' ),
			'value'       => wpem_get_site_industry(),
			'required'    => true,
		];

		$choices = $this->image_api->get_d3_choices();

		if ( ! $this->image_api->is_d3_locale() || ! $choices ) {

			$field['choices'] = [ '' => __( '- Select an industry -', 'wp-easy-mode' ) ]
			                    + $this->image_api->get_d3_categories_fallback();

			return $field;

		}

		$field['type']    = 'jq_select';
		$field['choices'] = $choices;
		$field['atts']    = [
			'data-select2-opts' => wp_json_encode(
				[
					'placeholder' => __( '- Select an industry -', 'wp-easy-mode' ),
				]
			),
		];

		return $field;

	}

}
