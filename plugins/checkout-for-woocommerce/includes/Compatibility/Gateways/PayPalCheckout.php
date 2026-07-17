<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class PayPalCheckout extends CompatibilityAbstract {
	public function is_available(): bool {
		return ( function_exists( 'wc_gateway_ppec' ) && ! empty( wc_gateway_ppec()->settings ) && method_exists( wc_gateway_ppec()->settings, 'is_enabled' ) && wc_gateway_ppec()->settings->is_enabled() );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'WooCommerce PayPal Checkout Payment Gateway',
					GatewaySupport::NOT_SUPPORTED,
					'No longer supported by WooCommerce. Switch to <a class="text-blue-600 underline" target="_blank" href="https://wordpress.org/plugins/pymntpl-paypal-woocommerce/">Payment Plugins for PayPal WooCommerce</a>',
					new AlternativePlugin(
						'pymntpl-paypal-woocommerce',
						'Payment Plugins for PayPal WooCommerce'
					)
				);

				return $gateways;
			}
		);
	}

	public function run() {
		add_filter( 'cfw_is_checkout', array( $this, 'is_checkout' ), 10, 1 );
	}

	public function is_checkout( $is_checkout ) {
		if ( wc_gateway_ppec()->checkout->has_active_session() ) {
			return false;
		}

		return $is_checkout;
	}
}
