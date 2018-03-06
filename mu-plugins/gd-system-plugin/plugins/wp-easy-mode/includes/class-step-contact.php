<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Step_Contact extends Step {

	/**
	 * Fields object
	 *
	 * @var object
	 */
	private $fields;

	/**
	 * Class constructor
	 *
	 * @param Log $log
	 */
	public function __construct( Log $log ) {

		parent::__construct( $log );

		$this->args = [
			'name'       => 'contact',
			'title'      => __( 'Contact', 'wp-easy-mode' ),
			'page_title' => __( 'Contact', 'wp-easy-mode' ),
			'can_skip'   => true,
		];

		add_action( 'wpem_print_header_scripts_' . $this->args['name'], [ $this, 'print_header_scripts' ] );
		add_action( 'wpem_print_footer_scripts_' . $this->args['name'], [ $this, 'print_footer_scripts' ] );

	}

	/**
	 * Add font awesome script
	 *
	 * @action wpem_print_header_scripts_contact
	 */
	public function print_header_scripts() {

		wp_print_styles( 'font-awesome' );

	}

	/**
	 * Add js script
	 *
	 * @action wpem_print_footer_scripts_contact
	 */
	public function print_footer_scripts() {

		wp_print_scripts( 'wpem-contact' );

	}

	/**
	 * Step init
	 */
	protected function init() {

		$fields = [
			[
				'name'        => 'wpem_contact_info[email]',
				'id'          => 'wpem_contact_email',
				'label'       => __( 'Email', 'wp-easy-mode' ),
				'type'        => 'email',
				'sanitizer'   => 'sanitize_email',
				'description' => __( 'An email address where website vistors can contact you.', 'wp-easy-mode' ),
				'value'       => wpem_get_contact_info( 'email', wp_get_current_user()->user_email ),
				'default'     => '',
				'atts'        => [
					'placeholder' => __( 'Enter your email address here', 'wp-easy-mode' ),
				],
			],
			[
				'name'        => 'wpem_contact_info[phone]',
				'id'          => 'wpem_contact_phone',
				'label'       => __( 'Phone Number', 'wp-easy-mode' ),
				'type'        => 'text',
				'sanitizer'   => 'sanitize_text_field',
				'description' => __( 'A phone number that website vistors can call if they have questions.', 'wp-easy-mode' ),
				'value'       => wpem_get_contact_info( 'phone' ),
				'default'     => '',
				'atts'        => [
					'placeholder' => __( 'Enter your phone number here', 'wp-easy-mode' ),
				],
			],
			[
				'name'        => 'wpem_contact_info[fax]',
				'id'          => 'wpem_contact_fax',
				'label'       => __( 'Fax Number', 'wp-easy-mode' ),
				'type'        => 'text',
				'sanitizer'   => 'sanitize_text_field',
				'description' => __( 'A fax number that website vistors can use to send important documents.', 'wp-easy-mode' ),
				'value'       => wpem_get_contact_info( 'fax' ),
				'default'     => '',
				'atts'        => [
					'placeholder' => __( 'Enter your fax number here', 'wp-easy-mode' ),
				],
			],
			[
				'name'        => 'wpem_contact_info[address]',
				'id'          => 'wpem_contact_address',
				'label'       => __( 'Address', 'wp-easy-mode' ),
				'sanitizer'   => function( $value ) {
					return nl2br( wp_kses_post( stripslashes( $value ) ) );
				},
				'type'        => 'textarea',
				'description' => __( 'A physical address where website vistors can go to visit you in person.', 'wp-easy-mode' ),
				'value'       => strip_tags( wpem_get_contact_info( 'address' ) ), // Hide <br /> tags
				'default'     => '',
				'atts'        => [
					'placeholder' => __( 'Enter your street address here', 'wp-easy-mode' ),
				],
			],
			[
				'name'      => 'wpem_social_profiles',
				'sanitizer' => 'sanitize_text_field', // Values are not always URLs (e.g. Skype)
				'default'   => [],
				'skip_log'  => true,
			],
		];

		$this->fields = new Fields( $fields );

	}

	/**
	 * Step content
	 */
	public function content() {

		printf(
			'<p class="lead-text align-center">%s</p>',
			__( 'Please provide the contact details for your website', 'wp-easy-mode' )
		);

		$this->fields->display();

		// Save fields now on first load in case this step is skipped
		if ( ! get_option( 'wpem_contact_info' ) && ! get_option( 'wpem_social_profiles' ) ) {

			$this->fields->save();

		}

		include_once wpem()->base_dir . 'includes/social-networks.php';

		if ( ! empty( $social_networks ) ) :

			ksort( $social_networks );

			?>
			<section>
				<label><?php esc_html_e( 'Add Your Social Media Profiles', 'wp-easy-mode' ) ?></label>
				<br>
				<span class="description"><?php esc_html_e( 'If you have existing social media profiles, select them below. Otherwise, skip this section.', 'wp-easy-mode' ) ?></span>
				<br>
				<span class="wpem-contact-social-grid">
					<?php foreach ( $social_networks as $network => $data ) : ?>
						<a
						href="#"
						title="<?php echo esc_attr( $data['label'] ) ?>"
						data-key="<?php echo esc_attr( $network ) ?>"
						data-url="<?php echo esc_attr( $data['url'] ) ?>"
						data-select="<?php echo ! empty( $data['select'] ) ? esc_attr( $data['select'] ) : null ?>"
						data-placeholder="<?php esc_attr_e( sprintf( 'Enter your %s URL here', $data['label'] ), 'wp-easy-mode' ) ?>"
						class="<?php echo wpem_get_social_profile_url( $network ) ? 'active' : '' ?>">
								<i class="fa fa-<?php echo ! empty( $data['icon'] ) ? esc_attr( $data['icon'] ) : esc_attr( $network ) ?>"></i>
						</a>
					<?php endforeach; ?>
				</span>
			</section>

			<div id="wpem-contact-social-fields"></div>

			<script type="text/html" id="wpem-social-link-template">
				<p>
					<label><i></i></label>
					<input type="text" required>
				</p>
			</script>
			<?php

		endif;

		/**
		 * Fires after the Contact content
		 *
		 * @since 2.0.0
		 */
		do_action( 'wpem_step_contact_after_content' );

	}

	/**
	 * Step actions
	 */
	public function actions() {

		?>
		<a href="<?php echo esc_url( wpem_get_next_step()->url ) ?>" type="submit" class="button button-secondary"><?php _e( 'Skip', 'wp-easy-mode' ) ?></a>
		<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Continue', 'wp-easy-mode' ) ?>">
		<?php

	}

	/**
	 * Step callback
	 */
	public function callback() {

		$saved = $this->fields->save();

		// Only log which networks were used
		if ( ! empty( $saved['wpem_social_profiles'] ) ) {

			$this->log->add_step_field( 'wpem_social_profiles', array_keys( $saved['wpem_social_profiles'] ) );

		}

	}

}
