<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class PostFinance extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\WooCommerce_PostFinanceCheckout' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'PostFinance Checkout',
					GatewaySupport::NOT_SUPPORTED
				);

				return $gateways;
			}
		);
	}
}
