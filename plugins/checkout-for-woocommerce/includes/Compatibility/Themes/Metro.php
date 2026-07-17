<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Metro extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\Metro_Main' );
	}

	public function run() {
		remove_action( 'woocommerce_checkout_after_order_review', 'woocommerce_checkout_payment' );
	}
}
