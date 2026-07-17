<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WCFieldFactory extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'wcff' );
	}

	public function run() {
		$this->remove_filter();
	}

	public function remove_filter() {
		$Wcff_CheckoutFields = cfw_get_hook_instance_object( 'woocommerce_checkout_fields', 'wcccf_filter_checkout_fields', 9 );

		if ( empty( $Wcff_CheckoutFields ) ) {
			return;
		}

		remove_filter( 'woocommerce_checkout_fields', array( $Wcff_CheckoutFields, 'wcccf_filter_checkout_fields' ), $priority );
	}
}
