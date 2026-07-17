<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class AuthorizeNet extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'wc_authorize_net_cim' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'WooCommerce Authorize.Net Gateway',
					GatewaySupport::NOT_SUPPORTED,
					'Gateway supports Express Checkout but configuration is onerous and WooCommerce Subscriptions is not supported. Consider using a different gateway. See <a class="text-blue-600 underline" target="_blank" href="https://woo.com/document/authorize-net/">Documentation</a>'
				);

				return $gateways;
			}
		);
	}
}
