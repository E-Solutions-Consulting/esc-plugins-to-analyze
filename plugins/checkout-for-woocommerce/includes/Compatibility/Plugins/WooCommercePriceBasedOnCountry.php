<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Exception;
use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WooCommercePriceBasedOnCountry extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WCPBC_PLUGIN_FILE' );
	}

	public function run_immediately() {
		add_filter( 'cfw_order_bump_get_price_context', array( $this, 'change_bump_price_context' ), 10, 1 );
		add_filter( 'cfw_order_bump_captured_revenue', array( $this, 'protect_captured_revenue_from_currency_conversion' ), 10 );
	}

	public function change_bump_price_context( $context ): string {
		if ( ! $this->is_available() ) {
			return $context;
		}

		return 'view';
	}

	/**
	 * Protect captured revenue from conversion
	 *
	 * @param float $revenue The captured revenue.
	 * @throws Exception If the currency conversion fails.
	 */
	public function protect_captured_revenue_from_currency_conversion( $revenue ): float {
		if ( ! ( function_exists( 'wcpbc_the_zone' ) && wcpbc_the_zone() ) ) {
			return $revenue;
		}

		return wcpbc_the_zone()->get_base_currency_amount( $revenue );
	}
}
