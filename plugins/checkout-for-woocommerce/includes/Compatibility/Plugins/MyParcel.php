<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class MyParcel extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'WCMYPA' );
	}

	public function run() {
		if ( WCMYPA()->setting_collection->isEnabled( 'use_split_address_fields' ) ) {
			$this->disable_nl_hooks();

			add_filter( 'woocommerce_default_address_fields', array( $this, 'add_new_fields' ), 100001, 1 ); // run after our normal hook
			add_filter( 'woocommerce_get_country_locale', array( $this, 'prevent_postcode_sort_change' ), 100001 );

			// Fix shipping preview
			add_filter( 'cfw_get_shipping_details_address', array( $this, 'fix_shipping_preview' ), 10, 2 );
		}

		add_filter( 'cfw_enable_zip_autocomplete', '__return_false' );

		// Move delivery options
		add_filter( 'wc_wcmp_delivery_options_location', array( $this, 'move_delivery_options' ), 20 );
	}

	public function disable_nl_hooks() {
		// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
		$priority = apply_filters( 'wcpn_checkout_fields_priority', 10, 'billing' );
		// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
		$instance = cfw_get_hook_instance_object( 'woocommerce_billing_fields', 'modifyBillingFields', $priority );

		if ( ! $instance ) {
			return;
		}

		remove_filter( 'woocommerce_billing_fields', array( $instance, 'modifyBillingFields' ), $priority );
		remove_filter( 'woocommerce_shipping_fields', array( $instance, 'modifyShippingFields' ), $priority );
		remove_filter( 'woocommerce_default_address_fields', array( $instance, 'default_address_fields' ) );
	}

	public function add_new_fields( $fields ) {
		// Add street name
		$fields['street_name'] = array(
			'label'             => __( 'street_name', 'woocommerce-myparcel' ),
			'placeholder'       => esc_attr__( 'Street name', 'woocommerce-postnl' ),
			'required'          => true,
			'class'             => array(),
			'autocomplete'      => '',
			'input_class'       => array(),
			'priority'          => 31, // after company
			'columns'           => 6,
			'custom_attributes' => array(
				'data-parsley-trigger' => 'change focusout',
			),
		);

		// Then add house number
		$fields['house_number'] = array(
			'label'             => __( 'abbreviation_house_number', 'woocommerce-myparcel' ),
			'placeholder'       => esc_attr__( 'Nr.', 'woocommerce-postnl' ),
			'required'          => true,
			'class'             => array(),
			'autocomplete'      => '',
			'input_class'       => array(),
			'priority'          => 32,
			'custom_attributes' => array(
				'data-parsley-trigger' => 'change focusout',
			),
			'columns'           => 3,
		);

		// Then house number suffix
		$fields['house_number_suffix'] = array(
			'label'             => __( 'suffix', 'woocommerce-myparcel' ),
			'placeholder'       => esc_attr__( 'Suffix', 'woocommerce-postnl' ),
			'required'          => false,
			'class'             => array(),
			'autocomplete'      => '',
			'input_class'       => array(),
			'priority'          => 33,
			'columns'           => 3,
			'custom_attributes' => array(
				'data-parsley-trigger' => 'change focusout',
			),
		);

		// Adjust postcode field
		$fields['postcode']['priority'] = 85; // defaults to 70
		$fields['postcode']['columns']  = 6;
		$fields['city']['columns']      = 6; // priority 90
		$fields['state']['priority']    = 91;
		$fields['state']['columns']     = 12;
		$fields['country']['columns']   = 12;
		$fields['country']['priority']  = 95;

		// Set address 1 / address 2 to hidden
		$fields['address_1']['type']  = 'hidden';
		$fields['address_1']['start'] = false;
		$fields['address_1']['end']   = false;
		unset( $fields['address_1']['custom_attributes'] );
		unset( $fields['address_1']['input_class'] );
		$fields['address_2']['type']  = 'hidden';
		$fields['address_2']['start'] = false;
		$fields['address_2']['end']   = false;
		unset( $fields['address_2']['custom_attributes'] );
		unset( $fields['address_2']['input_class'] );

		return $fields;
	}

	public function prevent_postcode_sort_change( array $locales ): array {
		foreach ( $locales as $key => $value ) {
			if ( ! empty( $value['postcode'] ) && ! empty( $value['postcode']['priority'] ) ) {
				$locales[ $key ]['postcode']['priority'] = 85;
			}
		}

		return $locales;
	}

	public function fix_shipping_preview( $address, $checkout ) {
		$address['address_1'] = $checkout->get_value( 'shipping_street_name' ) . ' ' . $checkout->get_value( 'shipping_house_number' );

		if ( ! empty( $checkout->get_value( 'shipping_house_number_suffix' ) ) ) {
			$address['address_1'] = $address['address_1'] . '-' . $checkout->get_value( 'shipping_house_number_suffix' );
		}

		return $address;
	}

	/**
	 * Change the delivery option output hook
	 *
	 * @return string
	 */
	public function move_delivery_options(): string {
		return 'cfw_checkout_after_shipping_methods';
	}
}
