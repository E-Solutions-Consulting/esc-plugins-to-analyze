<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Acuva extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'ac_setup' );
	}

	public function run() {
		$instance = cfw_get_hook_instance_object( 'woocommerce_cart_item_quantity', 'ac_display_cart_quantity' );

		if ( ! $instance ) {
			return;
		}

		remove_filter( 'woocommerce_cart_item_quantity', array( $instance, 'ac_display_cart_quantity' ), 10 );
	}
}
