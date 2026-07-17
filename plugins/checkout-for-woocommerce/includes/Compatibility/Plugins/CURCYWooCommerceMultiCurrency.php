<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class CURCYWooCommerceMultiCurrency extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'wmc_get_price' );
	}

	public function pre_init() {
		add_filter( 'cfw_order_bump_captured_revenue', array( $this, 'protect_captured_revenue_from_currency_conversion' ), 10 );
	}

	public function protect_captured_revenue_from_currency_conversion( $revenue ) {
		if ( ! $this->is_available() ) {
			return $revenue;
		}

		return wmc_revert_price( $revenue );
	}
}
