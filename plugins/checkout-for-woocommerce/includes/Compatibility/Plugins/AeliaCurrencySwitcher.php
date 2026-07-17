<?php
/**
 * Aelia Currency Switcher Compatibility
 *
 * @package Objectiv\Plugins\Checkout\Compatibility
 */

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Exception;
use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class AeliaCurrencySwitcher extends CompatibilityAbstract {
	/**
	 * Indicates if this integration is available.
	 *
	 * @return boolean
	 */
	public function is_available(): bool {
		// Check if the Aelia Currency Switcher has been loaded
		return class_exists( '\Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher', false );
	}

	public function run_immediately() {
		add_filter( 'cfw_order_bump_get_price_context', array( $this, 'change_bump_price_context' ), 10, 1 );
		add_filter(
			'cfw_order_bump_captured_revenue',
			array(
				$this,
				'protect_captured_revenue_from_currency_conversion',
			),
			10
		);
	}

	/**
	 * Set the price context for bump products to "view". This will ensure that the conversion logic
	 * can run and return the correct price.
	 *
	 * @param string $context The current price context.
	 *
	 * @return string
	 */
	public function change_bump_price_context( string $context ): string {
		if ( ! $this->is_available() ) {
			return $context;
		}

		return 'view';
	}

	/**
	 * Store the captured revenue in the shop's base currency.
	 *
	 * @param float $revenue The captured revenue.
	 * @throws Exception If the currency conversion fails.
	 */
	public function protect_captured_revenue_from_currency_conversion( $revenue ): float {
		if ( is_numeric( $revenue ) ) {
			$revenue = cfw_apply_filters( 'wc_aelia_cs_convert', $revenue, get_woocommerce_currency(), get_option( 'woocommerce_currency' ) );
		}

		return (float) $revenue;
	}
}
