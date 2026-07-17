<?php
namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class AllProductsForSubscriptions extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'WCS_ATT' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		/**
		 * Filter whether to load our customizations for All Products for Subscriptions on Side Cart
		 *
		 * @since 10.1.0
		 * @param bool $load_on_side_cart Whether to load our customizations for All Products for Subscriptions on Side Cart
		 * @return bool
		 */
		if ( ! apply_filters( 'cfw_compatibility_all_products_for_subscriptions_run_on_side_cart', false ) ) {
			return;
		}

		add_filter( 'wcsatt_enqueue_cart_script', '__return_true' );
		add_filter( 'cfw_enable_side_cart_woocommerce_after_cart_totals_hook', '__return_true' );
	}

	public function setup( bool $side_cart_feature_active ): AllProductsForSubscriptions {
		$this->side_cart_feature_active = $side_cart_feature_active;

		return $this;
	}
}
