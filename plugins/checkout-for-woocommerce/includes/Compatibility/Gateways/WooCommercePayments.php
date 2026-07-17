<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class WooCommercePayments extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WCPAY_PLUGIN_FILE' );
	}

	public function pre_init() {
		/**
		 * Filters whether to override Stripe payment request button heights
		 *
		 * @since 5.3.3
		 *
		 * @param bool $allow Whether to ignore shipping phone requirement during payment requests
		 */
		if ( apply_filters( 'cfw_wcpay_payment_requests_ignore_shipping_phone', true ) ) {
			add_action( 'wc_ajax_wcpay_create_order', array( $this, 'process_payment_request_ajax_checkout' ), 1 );
		}

		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'WooPayments',
					GatewaySupport::FULLY_SUPPORTED,
					'WooCommerce Payments is a whitelabel Stripe provider. Switch to <a class="text-blue-600 underline" target="_blank" href="https://wordpress.org/plugins/woo-stripe-payment/">Payment Plugins for Stripe WooCommerce</a>',
					new AlternativePlugin(
						'woo-stripe-payment',
						'Payment Plugins for Stripe WooCommerce'
					)
				);

				return $gateways;
			}
		);
	}

	public function run() {
		add_action( 'wp_enqueue_scripts', array( $this, 'modify_localized_data' ), 100000 );

		$this->add_payment_request_buttons();
	}

	public function add_payment_request_buttons() {
		// Setup Apple Pay
		if ( class_exists( '\\WC_Payments' ) && cfw_is_checkout() ) {
			/**
			 * The WC_Payments_Payment_Request_Button_Handler instance
			 *
			 * @var \WC_Payments_Payment_Request_Button_Handler $wc_payments_payment_request_button_handler
			 */
			$wc_payments_payment_request_button_handler = cfw_get_hook_instance_object( 'woocommerce_checkout_before_customer_details', 'display_express_checkout_buttons', 1 );

			if ( ! $wc_payments_payment_request_button_handler ) {
				return;
			}

			// Remove default stripe request placement
			remove_action( 'woocommerce_checkout_before_customer_details', array( $wc_payments_payment_request_button_handler, 'display_express_checkout_buttons' ), 1 );

			// Add our own stripe requests
			add_action( 'cfw_payment_request_buttons', array( $wc_payments_payment_request_button_handler, 'display_express_checkout_buttons' ), 1 );
		}
	}

	public function process_payment_request_ajax_checkout() {
		$payment_request_type = isset( $_POST['payment_request_type'] ) ? wc_clean( wp_unslash( $_POST['payment_request_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Disable shipping phone validation when using payment request
		if ( ! empty( $payment_request_type ) ) {
			add_filter(
				'woocommerce_checkout_fields',
				function ( $fields ) {
					if ( isset( $fields['shipping']['shipping_phone'] ) ) {
						$fields['shipping']['shipping_phone']['required'] = false;
						$fields['shipping']['shipping_phone']['validate'] = array();
					}

					if ( 'yes' === SettingsManager::instance()->get_setting( 'use_fullname_field' ) ) {
						unset( $fields['shipping']['shipping_full_name'] );
						unset( $fields['billing']['billing_full_name'] );
					}

					if ( 'yes' === SettingsManager::instance()->get_setting( 'enable_discreet_address_1_fields' ) ) {
						unset( $fields['shipping']['shipping_house_number'] );
						unset( $fields['billing']['billing_house_number'] );
						unset( $fields['shipping']['shipping_street_name'] );
						unset( $fields['billing']['billing_street_name'] );
					}

					return $fields;
				},
				1
			);
		}
	}

	public function modify_localized_data() {
		if ( ! is_cfw_page() ) {
			return;
		}

		global $wp_scripts;

		if ( ! isset( $wp_scripts->registered['WCPAY_PAYMENT_REQUEST'] ) ) {
			return;
		}

		$data = $wp_scripts->registered['WCPAY_PAYMENT_REQUEST']->extra['data'];

		$data = str_replace( '"height":"40"', '"height":"42"', $data );
		$data = str_replace( '"height":"48"', '"height":"42"', $data );
		$data = str_replace( '"height":"56"', '"height":"42"', $data );

		$wp_scripts->registered['WCPAY_PAYMENT_REQUEST']->extra['data'] = $data;
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'WooCommercePayments',
			'params' => array(),
		);

		return $compatibility;
	}
}
