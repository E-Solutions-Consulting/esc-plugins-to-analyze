<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class OnePageCheckout extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'is_wcopc_checkout' );
	}

	public function run() {
		add_filter( 'cfw_is_checkout', array( $this, 'maybe_disable_checkout_template' ), 10, 1 );
	}

	public function maybe_disable_checkout_template( $is_checkout ) {
		if ( is_wcopc_checkout() ) {
			$is_checkout = false;
		}

		return $is_checkout;
	}
}
