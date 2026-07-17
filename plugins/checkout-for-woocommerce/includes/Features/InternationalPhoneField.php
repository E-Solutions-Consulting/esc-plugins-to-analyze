<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 */
class InternationalPhoneField extends FeaturesAbstract {
	protected function run_if_cfw_is_enabled() {
		if ( 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ) ) {
			add_filter( 'cfw_get_billing_checkout_fields', array( $this, 'add_billing_phone_custom_validator' ) );
			add_filter( 'cfw_get_shipping_checkout_fields', array( $this, 'add_shipping_phone_custom_validator' ) );
		}

		add_filter( 'woocommerce_default_address_fields', array( $this, 'shim_hidden_phone_formatted_phone_field' ) );
		add_filter( 'cfw_event_object', array( $this, 'add_localized_settings' ) );
		add_action( 'cfw_before_process_checkout', array( $this, 'override_phone_numbers' ) );
	}

	public function shim_hidden_phone_formatted_phone_field( $fields ): array {
		$fields['phone_formatted'] = array(
			'type'     => 'hidden',
			'priority' => 1000,
			'required' => false,
		);

		return $fields;
	}

	/**
	 * @param array $event_data The event data.
	 * @return array
	 */
	public function add_localized_settings( array $event_data ): array {
		$format = $this->settings_getter->get_setting( 'international_phone_field_standard' );

		$event_data['settings']['enable_international_phone_field']   = true;
		$event_data['settings']['international_phone_field_standard'] = $format ? $format : 'raw';

		/**
		 * Filter to allow the country dropdown to be disabled
		 *
		 * @since 5.3.5
		 * @param bool $allow
		 */
		$event_data['settings']['allow_international_phone_field_country_dropdown'] = apply_filters( 'cfw_allow_international_phone_field_country_dropdown', true );

		/**
		 * Filter international phone field placeholder mode
		 *
		 * @since 8.2.19
		 * @param string $mode
		 */
		$event_data['settings']['international_phone_field_placeholder_mode'] = apply_filters( 'cfw_international_phone_field_placeholder_mode', 'aggressive' );

		return $event_data;
	}

	public function override_phone_numbers() {
		if ( ! empty( $_POST['shipping_phone_formatted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['shipping_phone'] = wp_unslash( $_POST['shipping_phone_formatted'] ); // phpcs:ignore
		}

		if ( ! empty( $_POST['billing_phone_formatted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['billing_phone'] = wp_unslash( $_POST['billing_phone_formatted'] ); // phpcs:ignore
		}
	}

	public function add_billing_phone_custom_validator( $fields ): array {
		if ( isset( $fields['billing_phone'] ) ) {
			$fields['billing_phone']['custom_attributes']['data-parsley-valid-international-phone'] = 'billing';
		}

		return $fields;
	}

	public function add_shipping_phone_custom_validator( $fields ): array {
		if ( isset( $fields['shipping_phone'] ) ) {
			$fields['shipping_phone']['custom_attributes']['data-parsley-valid-international-phone'] = 'shipping';
		}

		return $fields;
	}
}
