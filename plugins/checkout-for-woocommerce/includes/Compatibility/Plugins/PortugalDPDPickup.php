<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class PortugalDPDPickup extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'cppw_woocommerce_review_order_before_payment' );
	}

	public function run() {
		add_action( 'cfw_checkout_after_shipping_methods', 'cppw_woocommerce_review_order_before_payment' );
	}
}
