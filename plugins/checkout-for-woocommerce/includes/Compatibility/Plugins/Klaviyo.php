<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Klaviyo extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\WooCommerceKlaviyo' )
			&& version_compare( \WooCommerceKlaviyo::$version, '2.4.1', '>=' )
			&& (
				function_exists( 'kl_checkbox_custom_checkout_field' )
				|| function_exists( 'kl_mobile_consent_checkout_field' )
			);
	}

	public function run() {
		remove_filter( 'woocommerce_checkout_fields', 'kl_sms_consent_checkout_field', 11 );
		remove_filter( 'woocommerce_checkout_fields', 'kl_mobile_consent_checkout_field', 11 );
		remove_filter( 'woocommerce_checkout_fields', 'kl_checkbox_custom_checkout_field', 11 );
		remove_filter( 'woocommerce_checkout_fields', 'kl_sms_consent_checkout_field', 10 );
		remove_filter( 'woocommerce_checkout_fields', 'kl_mobile_consent_checkout_field', 10 );
		remove_filter( 'woocommerce_checkout_fields', 'kl_checkbox_custom_checkout_field', 10 );
		remove_filter( 'woocommerce_after_checkout_billing_form', 'kl_sms_compliance_text' );
		remove_filter( 'woocommerce_after_checkout_billing_form', 'kl_mobile_compliance_text' );

		add_filter(
			'cfw_unique_billing_fields',
			function ( array $fields ): array {
				// Klaviyo may have already injected these into checkout fields before our remove_filter() runs,
				// so they can remain in WooCommerce's fieldset cache and render a duplicate copy.
				unset( $fields['kl_newsletter_checkbox'] );
				unset( $fields['kl_sms_consent_checkbox'] );

				return $fields;
			}
		);

		/**
		 * Where to output Klaviyo checkboxes
		 *
		 * @since 5.1.2
		 * @param string $location Where to output the checkbox.
		 */
		$hook = apply_filters( 'cfw_klaviyo_output_hook', 'cfw_checkout_before_payment_method_tab_nav' );

		add_action( $hook, [ $this, 'output_checkbox' ], 11 );
	}

	public function output_checkbox() {
		$settings = get_option( 'klaviyo_settings' );

		echo '<div style="margin-top: 0.8em;">';

		if ( ! empty( $settings['klaviyo_newsletter_list_id'] ) ) {
			$newsletter_field = kl_checkbox_custom_checkout_field( [] );

			woocommerce_form_field( 'kl_newsletter_checkbox', $newsletter_field['billing']['kl_newsletter_checkbox'] );
		}

		$mobile_consent_enabled = function_exists( 'kl_any_mobile_channel_enabled' )
			? kl_any_mobile_channel_enabled( $settings )
			: (
				isset( $settings['klaviyo_sms_subscribe_checkbox'] )
				&& $settings['klaviyo_sms_subscribe_checkbox']
				&& ! empty( $settings['klaviyo_sms_list_id'] )
			);

		if ( $mobile_consent_enabled ) {
			if ( function_exists( 'kl_mobile_consent_checkout_field' ) ) {
				$mobile_field = kl_mobile_consent_checkout_field( [] );
			} else {
				$mobile_field = kl_sms_consent_checkout_field( [] );
			}

			woocommerce_form_field( 'kl_sms_consent_checkbox', $mobile_field['billing']['kl_sms_consent_checkbox'] );

			if ( function_exists( 'kl_mobile_compliance_text' ) ) {
				kl_mobile_compliance_text();
			} else {
				kl_sms_compliance_text();
			}
		}

		echo '</div>';
	}
}
