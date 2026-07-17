<?php

namespace Objectiv\Plugins\Checkout;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use WC_Data_Exception;
use WC_Order;

/**
 * The class responsible for controlling the address fields
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Core
 */
class AddressFieldsAugmenter extends SingletonAbstract {
	private $priorities = array(
		'first_name' => 10,
		'last_name'  => 20,
		'company'    => 30,
		'address_1'  => 40,
		'address_2'  => 50,
		'country'    => 60,
		'postcode'   => 70,
		'state'      => 80,
		'city'       => 90,
		'phone'      => 100,
	);

	/**
	 * Whether the phone fields are enabled
	 *
	 * @since 1.1.5
	 * @var string Is the phone enabled in the settings?
	 */
	private $phone_enabled;

	public function init() {
		$this->phone_enabled = cfw_is_phone_fields_enabled();

		// Setup address field defaults
		add_filter( 'woocommerce_default_address_fields', array( $this, 'get_custom_default_address_fields' ), 100000, 1 );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'enforce_field_priorities' ), 100000, 1 );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'sync_label_and_placeholder' ), 100000, 1 );

		// Fix billing email field
		add_filter( 'woocommerce_billing_fields', array( $this, 'update_billing_email_field' ), 100000 );

		// Add default value to full name fields
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_default_value_to_full_name_fields' ), 100000 );

		if ( $this->phone_enabled ) {
			add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_phone_to_address_fields' ), 10, 1 );
		}

		/**
		 * Filter address field priorities
		 *
		 * @since 2.0.0
		 *
		 * @param array $priorities The address field priorities keyed by field key
		 */
		$this->priorities = apply_filters( 'cfw_address_field_priorities', $this->priorities );
	}

	/**
	 * Add the billing phone to the address fields
	 *
	 * @since 1.1.5
	 * @param array $address_fields The address fields.
	 *
	 * @return mixed
	 */
	public function add_billing_phone_to_address_fields( $address_fields ) {
		$fields = WC()->countries->get_default_address_fields();

		if ( ! empty( $fields['phone'] ) ) {
			$address_fields['billing_phone'] = $fields['phone'];
		}

		return $address_fields;
	}

	/**
	 * Update the shipping phone on order create
	 *
	 * @param WC_Order $order The new order.
	 *
	 * @throws WC_Data_Exception If the order cannot be updated.
	 * @since 1.1.5
	 */
	public function update_shipping_phone_on_order_create( $order ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['shipping_phone'] ) ) {
			$order->set_shipping_phone( sanitize_text_field( wp_unslash( $_POST['shipping_phone'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Save shipping phone to customer meta during checkout
	 *
	 * This ensures the shipping phone is saved to user meta for logged-in customers
	 * so it can be pre-filled on subsequent checkouts.
	 *
	 * @param WC_Customer $customer The customer object
	 * @param array       $data     The checkout data
	 * @since 10.3.9
	 */
	public function save_shipping_phone_to_customer( $customer, $data ) {
		// Get shipping_phone directly from POST data since it's not in WooCommerce's default shipping fields
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['shipping_phone'] ) ) {
			$customer->set_shipping_phone( sanitize_text_field( wp_unslash( $_POST['shipping_phone'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Get custom default address fields
	 *
	 * @param array $fields The address fields.
	 * @return array
	 */
	public function get_custom_default_address_fields( $fields ): array {
		/**
		 * Filter whether to enable full name field
		 *
		 * @since 7.1.0
		 *
		 * @param array $enable_fullname_field Whether to enable full name field
		 */
		$use_fullname_field = apply_filters( 'cfw_enable_fullname_field', 'yes' === SettingsManager::instance()->get_setting( 'use_fullname_field' ) && is_cfw_page() );

		/**
		 * Filter whether to enable separate address 1 fields
		 *
		 * @since 7.1.0
		 *
		 * @param array $enable_separate_address_1_fields Whether to enable separate address 1 fields
		 */
		$enable_separate_address_1_fields = apply_filters( 'cfw_enable_separate_address_1_fields', 'yes' === SettingsManager::instance()->get_setting( 'enable_discreet_address_1_fields' ) ) && is_cfw_page();
		$enable_separate_address_1_fields = apply_filters_deprecated( 'cfw_enable_discrete_address_1_fields', array( $enable_separate_address_1_fields ), '10.0.0', 'cfw_enable_separate_address_1_fields' );
		$separate_address_1_fields_order  = SettingsManager::instance()->get_setting( 'discreet_address_1_fields_order' );

		if ( $use_fullname_field ) {
			$fields['full_name'] = array(
				'label'             => __( 'Full name', 'checkout-wc' ),
				'required'          => true,
				'input_class'       => array(),
				'priority'          => $this->priorities['first_name'] - 1,
				'autocomplete'      => 'name',
				'columns'           => 12,
				'custom_attributes' => array(
					'data-parsley-trigger'  => 'change focusout',
					'data-parsley-fullname' => 'true',
				),
			);
		}

		// First Name
		$fields['first_name']['placeholder']       = $fields['first_name']['label'];
		$fields['first_name']['class']             = array();
		$fields['first_name']['autocomplete']      = 'given-name';
		$fields['first_name']['input_class']       = array();
		$fields['first_name']['priority']          = $this->priorities['first_name'];
		$fields['first_name']['columns']           = 6;
		$fields['first_name']['custom_attributes'] = array(
			'data-parsley-trigger'       => 'change focusout',
			'data-parsley-name-not-email' => 'true',
		);

		// Last Name
		$fields['last_name']['placeholder']       = $fields['last_name']['label'];
		$fields['last_name']['class']             = array();
		$fields['last_name']['autocomplete']      = 'family-name';
		$fields['last_name']['input_class']       = array();
		$fields['last_name']['priority']          = $this->priorities['last_name'];
		$fields['last_name']['columns']           = 6;
		$fields['last_name']['custom_attributes'] = array(
			'data-parsley-trigger'       => 'change focusout',
			'data-parsley-name-not-email' => 'true',
		);

		if ( $use_fullname_field ) {
			$fields['first_name']['class'][] = 'cfw-hidden';
			$fields['last_name']['class'][]  = 'cfw-hidden';
		}

		// Address 1
		$fields['address_1']['placeholder']       = $fields['address_1']['label'];
		$fields['address_1']['class']             = array( 'address-field' );
		$fields['address_1']['autocomplete']      = 'address-line1';
		$fields['address_1']['input_class']       = array();
		$fields['address_1']['priority']          = $this->priorities['address_1'];
		$fields['address_1']['columns']           = 12;
		$fields['address_1']['custom_attributes'] = array(
			'data-parsley-trigger' => 'change focusout',
		);

		if ( $enable_separate_address_1_fields ) {
			$fields['house_number'] = array(
				'label'             => __( 'House number', 'checkout-wc' ),
				'required'          => true,
				'input_class'       => array(),
				'priority'          => $this->priorities['address_1'] - 2,
				'columns'           => 4,
				'custom_attributes' => array(
					'data-parsley-trigger' => 'change focusout',
				),
			);

			// If alternate, move street_name field before house_number field
			$fields['street_name'] = array(
				'label'             => __( 'Street name', 'checkout-wc' ),
				'required'          => true,
				'input_class'       => array(),
				'priority'          => $this->priorities['address_1'] - ( 'alternate' === $separate_address_1_fields_order ? 3 : 1 ),
				'columns'           => 8,
				'custom_attributes' => array(
					'data-parsley-trigger' => 'change focusout',
				),
			);

			$fields['address_1']['class'][] = 'cfw-hidden';
		}

		// Address 2
		if ( isset( $fields['address_2'] ) ) {
			$fields['address_2']['label']        = __( 'Apartment, suite, unit, etc.', 'woocommerce' );
			$fields['address_2']['label_class']  = '';
			$fields['address_2']['placeholder']  = $fields['address_2']['label'];
			$fields['address_2']['class']        = array( 'address-field' );
			$fields['address_2']['autocomplete'] = 'address-line2';
			$fields['address_2']['input_class']  = array();
			$fields['address_2']['priority']     = $this->priorities['address_2'];
			$fields['address_2']['columns']      = 12;
		}

		// Company
		if ( isset( $fields['company'] ) ) {
			$fields['company']['placeholder']  = $fields['company']['label'];
			$fields['company']['class']        = array();
			$fields['company']['autocomplete'] = 'organization';
			$fields['company']['input_class']  = array( 'update_totals_on_change' );
			$fields['company']['priority']     = $this->priorities['company'];
			$fields['company']['columns']      = 12;
		}

		// Country
		$fields['country']['type']         = 'country';
		$fields['country']['class']        = array( 'address-field', 'update_totals_on_change' );
		$fields['country']['input_class']  = array( 'cfw-no-select2' );
		$fields['country']['autocomplete'] = 'country';
		$fields['country']['priority']     = 60;
		$fields['country']['columns']      = 4;

		// Postcode
		$fields['postcode']['placeholder']       = $fields['postcode']['label'];
		$fields['postcode']['class']             = array( 'address-field' );
		$fields['postcode']['validate']          = array( 'postcode' );
		$fields['postcode']['autocomplete']      = 'postal-code';
		$fields['postcode']['input_class']       = array();
		$fields['postcode']['priority']          = $this->priorities['postcode'];
		$fields['postcode']['columns']           = 4;
		$fields['postcode']['custom_attributes'] = array(
			'data-parsley-length'   => '[2,12]',
			'data-parsley-trigger'  => 'change focusout',
			'data-parsley-postcode' => 'true',
			'data-parsley-debounce' => '200',
		);

		// State
		$fields['state']['type']              = 'state';
		$fields['state']['placeholder']       = $fields['state']['label'];
		$fields['state']['class']             = array( 'address-field' );
		$fields['state']['input_class']       = array( 'cfw-no-select2' );
		$fields['state']['validate']          = array( 'state' );
		$fields['state']['autocomplete']      = 'address-level1';
		$fields['state']['priority']          = $this->priorities['state'];
		$fields['state']['columns']           = 4;
		$fields['state']['custom_attributes'] = array(
			'data-parsley-trigger' => 'input change focusout',
		);

		// City
		$fields['city']['placeholder']       = $fields['city']['label'];
		$fields['city']['class']             = array( 'address-field' );
		$fields['city']['autocomplete']      = 'address-level2';
		$fields['city']['input_class']       = array();
		$fields['city']['priority']          = $this->priorities['city'];
		$fields['city']['columns']           = 12;
		$fields['city']['custom_attributes'] = array(
			'data-parsley-trigger' => 'change focusout',
		);

		// Phone
		if ( $this->phone_enabled ) {
			$fields['phone'] = array(
				'type'              => 'tel',
				'label'             => __( 'Phone', 'woocommerce' ),
				'placeholder'       => __( 'Phone', 'woocommerce' ),
				'required'          => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
				'autocomplete'      => 'tel',
				'input_class'       => array(),
				'priority'          => $this->priorities['phone'],
				'columns'           => 12,
				'validate'          => array( 'phone' ),
				'custom_attributes' => array(
					'data-parsley-trigger' => 'input change focusout',
				),
			);
		}

		foreach ( $fields as $key => $field ) {
			$type = $field['type'] ?? 'text';

			if ( isset( $field['placeholder'] ) && ! $field['required'] && 'hidden' !== $type ) {
				// Add optional to placeholder
				$fields[ $key ]['placeholder'] = sprintf( '%s (%s)', $field['placeholder'], __( 'optional', 'woocommerce' ) );
			}
		}

		return $fields;
	}

	public function update_billing_email_field( $billing_fields ): array {
		if ( ! empty( $billing_fields['billing_email'] ) ) {
			$billing_fields['billing_email']['custom_attributes']['data-parsley-email-domain'] = 'true';
			$billing_fields['billing_email']['custom_attributes']['data-parsley-type']         = 'email';
			$billing_fields['billing_email']['custom_attributes']['data-parsley-trigger']      = 'change focusout';
			$billing_fields['billing_email']['custom_attributes']['data-parsley-debounce']     = '200';
			$billing_fields['billing_email']['autocomplete']                                   = 'email';
			// Changing type from email to text ensures EmailAutocompleteInput works, but it can cause the email to need 2 taps for autofill to work on some browsers (Chrome Android observed)
			$billing_fields['billing_email']['type'] = 'text';
		}

		// If changing $billing_fields['billing_email']['type'] to email (e.g., to stop double tap issue, see above) it is strongly recommended to disable email domain autocomplete
		return apply_filters( 'cfw_update_billing_email_field', $billing_fields );
	}

	/**
	 * Enforce field priorities
	 *
	 * @param array $locales The locales.
	 * @return array
	 */
	public function enforce_field_priorities( array $locales ): array {
		foreach ( $locales as $country => $locale ) {
			foreach ( $locale as $field_key => $field_data ) {
				if ( isset( $field_data['priority'] ) ) {
					$locales[ $country ][ $field_key ]['priority'] = $this->priorities[ $field_key ];
				}
			}
		}

		return $locales;
	}

	public function sync_label_and_placeholder( array $locales ): array {
		foreach ( $locales as $country => $locale ) {
			foreach ( $locale as $field_key => $field_data ) {
				if ( isset( $field_data['label'] ) ) {
					$locales[ $country ][ $field_key ]['placeholder'] = $field_data['label'];
				}
			}
		}

		return $locales;
	}

	public function add_default_value_to_full_name_fields( $fields ): array {
		if ( $fields['billing']['full_name'] ?? false ) {
			$fields['billing']['full_name']['default'] = WC()->checkout()->get_value( 'billing_first_name' ) . ' ' . WC()->checkout()->get_value( 'billing_last_name' );
		}

		if ( $fields['shipping']['full_name'] ?? false ) {
			$fields['shipping']['full_name']['default'] = WC()->checkout()->get_value( 'shipping_first_name' ) . ' ' . WC()->checkout()->get_value( 'shipping_last_name' );
		}

		return $fields;
	}
}
