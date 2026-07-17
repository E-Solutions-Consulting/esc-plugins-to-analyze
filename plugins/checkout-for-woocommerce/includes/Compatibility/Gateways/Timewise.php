<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

/**
 * Timewise Payment Gateway Compatibility
 *
 * Handles compatibility with Timewise payment gateway which uses
 * a redirect flow with tw-pg-id parameter to display custom payment UI
 */
class Timewise extends CompatibilityAbstract {

	/**
	 * Check if Timewise gateway is active
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		// Check if Timewise payment gateway class exists
		return class_exists( 'Timewise\Payment\Gateway\Plugin' );
	}

	/**
	 * Run compatibility early on wp_loaded hook
	 */
	public function pre_init() {
		// Check if this is a Timewise payment redirect
		if ( ! $this->is_timewise_payment_page() ) {
			return;
		}

		// Disable CheckoutWC templates to allow Timewise to take over
		add_filter( 'cfw_load_checkout_template', '__return_false', 5 );
		add_filter( 'cfw_is_checkout', '__return_false', 5 );
	}

	/**
	 * Check if current page is a Timewise payment page
	 *
	 * @return bool
	 */
	private function is_timewise_payment_page(): bool {
		// Check for tw-pg-id parameter
		if ( ! isset( $_GET['tw-pg-id'] ) ) {
			return false;
		}

		return true;
	}
}
