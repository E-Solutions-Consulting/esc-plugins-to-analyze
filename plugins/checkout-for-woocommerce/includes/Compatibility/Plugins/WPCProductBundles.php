<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use WC_Cart;

class WPCProductBundles extends CompatibilityAbstract {

	private $bundle_bump_data = array();

	public function is_available(): bool {
		return function_exists( 'woosb_init' );
	}

	public function run_immediately() {
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'hide_quantity_dropdown' ), 100, 2 );

		// Add filter to determine if cart items should be skipped in sync_bump_cart_prices
		add_filter( 'cfw_skip_bump_cart_item_pricing', array( $this, 'skip_wpc_bundle_pricing' ), 10, 2 );

		// Skip discount HTML for WPC bundles to prevent double discount display
		add_filter( 'cfw_skip_bump_cart_item_discount_html', array( $this, 'skip_wpc_bundle_pricing' ), 10, 2 );

		// Handle WPC bundle pricing for order bumps
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'store_bundle_bump_data' ), 50000 );
		add_filter( 'woosb_bundles_price', array( $this, 'apply_bundle_bump_discount' ), 10, 2 );
	}

	public function hide_quantity_dropdown( $quantity, $cart_item ) {
		if ( isset( $cart_item['woosb_parent_id'] ) ) {
			return $cart_item['quantity'];
		}

		return $quantity;
	}

	/**
	 * Determine whether to skip bump pricing for WPC bundles
	 *
	 * @param bool  $skip Whether to skip this cart item.
	 * @param array $cart_item The cart item data.
	 * @return bool Whether to skip pricing for this item
	 */
	public function skip_wpc_bundle_pricing( $skip, $cart_item ) {
		// Skip WPC bundles - they'll be handled by the woosb_bundles_price filter
		if ( ! empty( $cart_item['woosb_ids'] ) ) {
			return true;
		}

		return $skip;
	}

	/**
	 * Store bundle bump discount data before WPC calculates pricing
	 *
	 * @param WC_Cart $cart The woo cart.
	 */
	public function store_bundle_bump_data( $cart ) {
		foreach ( $cart->get_cart_contents() as $cart_item_key => $cart_item ) {
			// Only process WPC bundles with order bumps
			if ( empty( $cart_item['woosb_ids'] ) || empty( $cart_item['_cfw_order_bump_id'] ) ) {
				continue;
			}

			$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] );

			if ( ! $bump->is_cart_bump_valid() ) {
				continue;
			}

			$original_price = $cart_item['data']->get_price();
			$bump_price     = $bump->get_price(
				apply_filters( 'cfw_order_bump_get_price_context', 'cart', $cart_item, $bump ),
				$cart_item['variation_id'] ?? 0
			);

			// Store the discount amount for this bundle
			if ( $original_price > 0 ) {
				$discount_amount                          = $original_price - $bump_price;
				$this->bundle_bump_data[ $cart_item_key ] = $discount_amount;
			}
		}
	}

	/**
	 * Apply bundle bump discount using WPC's pricing filter
	 *
	 * @param float $bundles_price The calculated bundle price.
	 * @param array $cart_item The cart item data.
	 * @return float The modified bundle price
	 */
	public function apply_bundle_bump_discount( $bundles_price, $cart_item ) {
		$cart_item_key = $cart_item['key'] ?? '';

		if ( empty( $this->bundle_bump_data[ $cart_item_key ] ) ) {
			return $bundles_price;
		}

		$discount_amount = $this->bundle_bump_data[ $cart_item_key ];
		return max( 0, $bundles_price - $discount_amount );
	}
}
