<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class WooCommercePayPalPayments extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'PAYPAL_API_URL' );
	}

	public function pre_init() {
		add_filter( 'cfw_is_checkout', array( $this, 'is_checkout' ) );

		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'WooCommerce PayPal Payments',
					GatewaySupport::NOT_SUPPORTED,
					'Gateway does not support Express Checkout button at checkout. Switch to <a class="text-blue-600 underline" target="_blank" href="https://wordpress.org/plugins/pymntpl-paypal-woocommerce/">Payment Plugins for PayPal WooCommerce</a>',
					new AlternativePlugin(
						'pymntpl-paypal-woocommerce',
						'Payment Plugins for PayPal WooCommerce'
					)
				);

				return $gateways;
			}
		);
	}

	public function is_checkout( $is_checkout ): bool {
		if ( isset( $_GET['wc-ajax'] ) && 'ppc-create-order' === $_GET['wc-ajax'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return (bool) $is_checkout;
	}
}
