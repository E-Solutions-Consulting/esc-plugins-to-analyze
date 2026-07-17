<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Greenmart extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'greenmart_woocommerce_cart_item_name' );
	}

	public function run() {
		remove_filter( 'woocommerce_cart_item_name', 'greenmart_woocommerce_cart_item_name', 10 );
		remove_action( 'woocommerce_checkout_after_order_review', 'woocommerce_checkout_payment', 20 );
	}
}
