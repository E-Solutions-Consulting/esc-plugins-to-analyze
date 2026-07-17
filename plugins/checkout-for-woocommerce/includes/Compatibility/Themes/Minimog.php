<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Minimog extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'MINIMOG_THEME_NAME' );
	}

	public function run() {
		$instance = cfw_get_hook_instance_object( 'woocommerce_checkout_after_order_review', 'template_checkout_payment_title' );
		remove_action( 'woocommerce_checkout_after_order_review', array( $instance, 'template_checkout_payment_title' ), 10 );
		remove_action( 'woocommerce_checkout_after_order_review', 'woocommerce_checkout_payment', 20 );
	}
}
