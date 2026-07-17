<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class Braintree extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WC_PAYPAL_BRAINTREE_FILE' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Braintree for WooCommerce Payment Gateway',
					GatewaySupport::NOT_SUPPORTED,
					'Gateway does not support Express Checkout at checkout. Switch to <a class="text-blue-600 underline" target="_blank" href="https://wordpress.org/plugins/woo-payment-gateway/">Payment Plugins Braintree.</a>',
					new AlternativePlugin(
						'woo-payment-gateway',
						'Payment Plugins Braintree For WooCommerce'
					)
				);

				return $gateways;
			}
		);
	}

	public function run() {
		remove_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_methods', 10 );
		add_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_methods', 25 );
	}

	/**
	 * Safely get Braintree gateway ID constant from either WC_Braintree or WC_Braintree\WC_Braintree class
	 *
	 * @param string $constant_name The constant name to retrieve (without class prefix)
	 * @return string|false The constant value or false if not found
	 */
	private function get_braintree_gateway_id( string $constant_name ) {
		// Try the older class structure first: \WC_Braintree::CONSTANT
		if ( class_exists( '\WC_Braintree' ) && defined( '\WC_Braintree::' . $constant_name ) ) {
			return constant( '\WC_Braintree::' . $constant_name );
		}

		// Try the newer namespaced class structure: \WC_Braintree\WC_Braintree::CONSTANT
		if ( class_exists( '\WC_Braintree\WC_Braintree' ) && defined( '\WC_Braintree\WC_Braintree::' . $constant_name ) ) {
			return constant( '\WC_Braintree\WC_Braintree::' . $constant_name );
		}

		return false;
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		// Safely get credit card gateway ID
		$cc_gateway_id = $this->get_braintree_gateway_id( 'CREDIT_CARD_GATEWAY_ID' );
		if ( false === $cc_gateway_id ) {
			cfw_debug_log( 'Braintree compatibility: Unable to load credit card gateway ID constants' );
			return $compatibility;
		}

		// Safely get PayPal gateway ID
		$paypal_gateway_id = $this->get_braintree_gateway_id( 'PAYPAL_GATEWAY_ID' );
		if ( false === $paypal_gateway_id ) {
			cfw_debug_log( 'Braintree compatibility: Unable to load PayPal gateway ID constants' );
			return $compatibility;
		}

		$cc_gateway_available     = isset( $payment_gateways[ $cc_gateway_id ] ) ? $payment_gateways[ $cc_gateway_id ]->is_available() : false;
		$paypal_gateway_available = isset( $payment_gateways[ $paypal_gateway_id ] ) ? $payment_gateways[ $paypal_gateway_id ]->is_available() : false;

		$compatibility[] = array(
			'class'  => 'Braintree',
			'params' => array(
				'cc_gateway_available'     => $cc_gateway_available,
				'paypal_gateway_available' => $paypal_gateway_available,
			),
		);

		return $compatibility;
	}
}
