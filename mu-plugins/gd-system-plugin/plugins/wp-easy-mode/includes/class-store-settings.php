<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Store_Settings {

	/**
	 * Store data
	 *
	 * @var object
	 */
	private static $store_data;

	public function __construct() {

		if ( false === ( $wpem_store_data = get_transient( 'wpem_store_data' ) ) ) {

			$wpem_store_data = json_decode( wpem_get_store_data(), true );

			set_transient( 'wpem_store_data', $wpem_store_data );

		}

		if ( empty( $wpem_store_data ) ) {

			return;

		}

		self::$store_data = new \StdClass;

		foreach ( $wpem_store_data as $key => $value ) {

			self::$store_data->$key = $value;

		}

	}

	/**
	 * Render the WooCommerce store option sections
	 *
	 * @return array
	 */
	public function wpem_ecommerce_fields() {

		if ( empty( self::$store_data ) ) {

			return;

		}

		$visible_required   = ( 'store' === wpem_get_site_type() ) ? true : false;
		$woocommerce_options = wpem_get_woocommerce_options();

		$fields = [
			'name'     => 'wpem-ecommerce-option-group',
			'type'     => 'group',
			'visible'  => $visible_required,
			'sections' => [
				[
					'name'        => 'wpem_woocommerce[section_title]',
					'type'        => 'html',
					'content'     => wp_kses_post( '<h1>' . __( 'Store Settings', 'wp-easy-mode' ) . '</h1><p class="lead-text align-center">' . __( 'Please tell us more about your online store.', 'wp-easy-mode' ) . '</p>' ),
					'skip_option' => true,
				],
				[
					'name'        => 'wpem_woocommerce[store_location]',
					'label'       => __( 'Store Location', 'wp-easy-mode' ),
					'type'        => 'jq_select',
					'choices'     => self::$store_data->locations,
					'description' => __( 'Where is your store based?', 'wp-easy-mode' ),
					'value'       => $woocommerce_options['store_location'],
					'required'    => $visible_required,
					'atts'        => [
						'data-select2-opts' => wp_json_encode(
							[
								'placeholder' => __( '- Select Store Location -', 'wp-easy-mode' ),
							]
						),
					],
				],
				[
					'name'        => 'wpem_woocommerce[currency_code]',
					'label'       => __( 'Store Currency', 'wp-easy-mode' ),
					'type'        => 'jq_select',
					'choices'     => self::$store_data->currencies,
					'description' => __( 'Which currency will your store use? (If your currency is not listed you can add it later.)', 'wp-easy-mode' ),
					'value'       => $woocommerce_options['currency_code'],
					'required'    => $visible_required,
					'atts'        => [
						'data-select2-opts' => wp_json_encode(
							[
								'placeholder' => __( '- Select Store Currency -', 'wp-easy-mode' ),
							]
						),
					],
				],
				[
					'name'        => 'wpem_woocommerce[weight_unit]',
					'label'       => __( 'Product Weight Units', 'wp-easy-mode' ),
					'type'        => 'select',
					'choices'     => [
						'kg'  => __( 'kg', 'wp-easy-mode' ),
						'g'   => __( 'g', 'wp-easy-mode' ),
						'lbs' => __( 'lbs', 'wp-easy-mode' ),
						'oz'  => __( 'oz', 'wp-easy-mode' ),
					],
					'description' => __( 'Which unit should be used for product weights?', 'wp-easy-mode' ),
					'value'       => $woocommerce_options['weight_unit'],
					'required'    => $visible_required,
					'atts'        => [
						'data-select2-opts' => wp_json_encode(
							[
								'placeholder' => __( '- Select Product Weight Units -', 'wp-easy-mode' ),
							]
						),
					],
				],
				[
					'name'        => 'wpem_woocommerce[dimension_unit]',
					'label'       => __( 'Product Dimension Units', 'wp-easy-mode' ),
					'type'        => 'select',
					'choices'     => [
						'm'  => __( 'm', 'wp-easy-mode' ),
						'cm' => __( 'cm', 'wp-easy-mode' ),
						'mm' => __( 'mm', 'wp-easy-mode' ),
						'in' => __( 'in', 'wp-easy-mode' ),
						'yd' => __( 'yd', 'wp-easy-mode' ),
					],
					'description' => __( 'Which unit should be used for product dimensions?', 'wp-easy-mode' ),
					'value'       => $woocommerce_options['dimension_unit'],
					'required'    => $visible_required,
					'atts'        => [
						'data-select2-opts' => wp_json_encode(
							[
								'placeholder' => __( '- Select Product Dimension Units -', 'wp-easy-mode' ),
							]
						),
					],
				],
				[
					'name'        => 'wpem_woocommerce[calc_shipping]',
					'label'       => __( 'Will you be shipping products?', 'wp-easy-mode' ),
					'type'        => 'checkbox',
					'choices'     => [
						'true' => __( 'Yes, I will be shipping physical goods to customers.', 'wp-easy-mode' ),
					],
					'value'       => true,
				],
				[
					'name'        => 'wpem_woocommerce[calc_taxes]',
					'label'       => __( 'Will you be charging sales tax?', 'wp-easy-mode' ),
					'type'        => 'checkbox',
					'choices'     => [
						'true' => __( 'Yes, I will be charging sales tax.', 'wp-easy-mode' ),
					],
					'value'       => true,
					'default'     => false,
				],
				[
					'name'        => 'wpem_woocommerce[prices_include_tax]',
					'label'       => __( 'How will you enter product prices?', 'wp-easy-mode' ),
					'type'        => 'radio',
					'choices'     => [
						'yes' => __( 'I will enter prices inclusive of tax', 'wp-easy-mode' ),
						'no'  => __( 'I will enter prices exclusive of tax', 'wp-easy-mode' ),
					],
					'value'       => $woocommerce_options['prices_include_tax'],
					'visible'     => $woocommerce_options['calc_taxes'],
					'after'       => $this->woocommerce_tax_table(),
				],
				[
					'name'        => 'wpem_woocommerce[payment_methods]',
					'label'       => __( 'Payments', 'wp-easy-mode' ),
					'description' => __( 'WooCommerce can accept both online and offline payments. Additional payment methods can be installed later and managed from the checkout settings screen.', 'wp-easy-mode' ),
					'type'        => 'checkbox',
					'choices'     => [
						'woocommerce_paypal-braintree_settings' => __( 'PayPal', 'wp-easy-mode' ),
						'woocommerce_stripe_settings'           => __( 'Stripe', 'wp-easy-mode' ),
						'woocommerce_paypal_settings'           => __( 'PayPal Standard', 'wp-easy-mode' ),
						'woocommerce_cheque_settings'           => __( 'Check Payments', 'wp-easy-mode' ),
						'woocommerce_bacs_settings'             => __( 'Bank Transfer (BACS) Payments', 'wp-easy-mode' ),
						'woocommerce_cod_settings'              => __( 'Cash on Delivery', 'wp-easy-mode' ),
					],
					'atts'        => [
						'class' => 'wpem_store_payment_methods',
					],
					'value'       => 'yes',
					'default'     => false,
					'required'    => false,
					'visible'     => true,
				],
			],
		];

		return apply_filters( 'wpem_ecommerce_fields', $fields );

	}

	/**
	 * Generate the tax details based on the user location
	 *
	 * note: $country and $state are undefined on initial load,
	 * and defined in the ajax request when the store location changes
	 *
	 * @param bool $location
	 *
	 * @return mixed
	 */
	public function woocommerce_tax_table( $location = false ) {

		$location = ( $location ) ? $location : wpem_get_woocommerce_options( 'store_location' );

		$tax_rates = self::get_woocommerce_tax_rates( self::$store_data, $location );

		ob_start();

		if ( ! $tax_rates ) {

			return;

		}

		?>
		<section class="wpem-woocommerce-tax-details">
		<table>

			<tr class="tax-rates">

				<td colspan="2">
					<p><?php printf( esc_html__( 'The following tax rates will be imported automatically for you. You can read more about taxes in the %1$sWooCommerce documentation%2$s.', 'wp-easy-mode' ), '<a href="https://docs.woocommerce.com/document/setting-up-taxes-in-woocommerce/" target="_blank">', '</a>' ); ?></p>
					<div class="importing-tax-rates">
						<table class="tax-rates">
							<thead>
								<tr>
									<th><?php _e( 'Country', 'wp-easy-mode' ); ?></th>
									<th><?php _e( 'State', 'wp-easy-mode' ); ?></th>
									<th><?php _e( 'Rate (%)', 'wp-easy-mode' ); ?></th>
									<th><?php _e( 'Name', 'wp-easy-mode' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
									foreach ( $tax_rates as $rate ) {
										?>
										<tr>
											<td class="readonly"><?php echo esc_attr( $rate['country'] ); ?></td>
											<td class="readonly"><?php echo esc_attr( $rate['state'] ? $rate['state'] : '*' ); ?></td>
											<td class="readonly"><?php echo esc_attr( $rate['rate'] ); ?></td>
											<td class="readonly"><?php echo esc_attr( $rate['name'] ); ?></td>
										</tr>
										<?php
									}
								?>
							</tbody>
						</table>
					</div>

					<span class="description"><?php esc_html_e( 'You may need to add/edit rates based on your products or business location which can be done from the tax settings screen. If in doubt, speak to an accountant.', 'wp-easy-mode' ); ?></span>

				</td>

			</tr>

		</table>
		</section>

		<?php

		return ob_get_clean();

	}

	/**
	 * Get tax rates
	 *
	 * @param  array $store_data Data returned about the current store.
	 * @param  string $location
	 *
	 * @return array
	 */
	public static function get_woocommerce_tax_rates( $store_data, $location ) {

		$split_location = explode( ':', $location );

		$country = isset( $split_location[0] ) ? $split_location[0] : '';
		$state   = isset( $split_location[1] ) ? $split_location[1] : '';

		if ( ! isset( $store_data->locale_info[ $country ] ) ) {

			return [];

		}

		$tax_rates = $store_data->locale_info[ $country ]['tax_rates'];

		if ( isset( $tax_rates[ $state ] ) ) {

			return $tax_rates[ $state ];

		}

		if ( isset( $tax_rates[''] ) ) {

			return $tax_rates[''];

		}

		if ( isset( $tax_rates['*'] ) ) {

			return $tax_rates['*'];

		}

		return [];

	}

}
