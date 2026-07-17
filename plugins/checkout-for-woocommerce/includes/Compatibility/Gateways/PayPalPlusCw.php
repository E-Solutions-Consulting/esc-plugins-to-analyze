<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class PayPalPlusCw extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\PayPalPlusCw_Util' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'PayPal Plus for WooCommerce',
					GatewaySupport::NOT_SUPPORTED,
					'Plugin no longer exists. Switch to <a class="text-blue-600 underline" target="_blank" href="https://wordpress.org/plugins/pymntpl-paypal-woocommerce/">Payment Plugins for PayPal WooCommerce</a>',
					new AlternativePlugin(
						'pymntpl-paypal-woocommerce',
						'Payment Plugins for PayPal WooCommerce'
					)
				);

				return $gateways;
			}
		);
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'PayPalPlusCw',
			'params' => array(),
		);

		return $compatibility;
	}
}
