<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Kangu extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'kangu_shipping_method' );
	}

	public function run() {
		$instance = cfw_get_hook_instance_object( 'woocommerce_review_order_before_order_total', 'get_shippings_to_cart' );

		if ( ! $instance ) {
			return;
		}

		remove_action( 'woocommerce_review_order_before_order_total', array( $instance, 'get_shippings_to_cart' ) );
	}
}
