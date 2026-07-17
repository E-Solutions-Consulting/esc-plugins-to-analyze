<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;
use WC_Product;
use WOOMC\Product\Info;

/**
 * WOOMC Multi-Currency compatibility for order bumps
 *
 * PROBLEM: WOOMC hardcoded custom prices conflict with order bump discounts.
 * When a product has custom prices (e.g., £19 GBP sale price) and a 10% order bump discount,
 * the system should show £17.10 (£19 - 10%) but shows £19 instead.
 *
 * WHY THIS APPROACH IS REQUIRED:
 * 1. Display layer makes direct $product->get_price() calls that bypass CheckoutWC's pricing flow
 * 2. WOOMC only converts prices in 'view' context, but some calls use 'cart' context
 * 3. Standard filter approaches fail because they don't catch direct product price calls
 * 4. High priority interception (9999) is necessary to catch these bypass calls
 *
 * HOW THIS WORKS:
 * 1. Intercept direct product price calls at very high priority (9999)
 * 2. Check if the product is an order bump offer in the cart
 * 3. Use CheckoutWC's get_price('view') which applies WOOMC conversion + order bump discount
 * 4. Temporarily remove our own filter during calculation to prevent infinite loops
 * 5. Return the properly converted and discounted price
 *
 * @link https://woocommerce.com/products/multi-currency/
 * @since 10.2.8
 */
class WOOMCMultiCurrency extends CompatibilityAbstract {

	public function is_available(): bool {
		return defined( 'WOOCOMMERCE_MULTICURRENCY_VERSION' );
	}

	public function run_immediately(): void {
		// Add our price interception filters
		$this->add_price_filters();

		// Hook into CheckoutWC's display price calculation actions to temporarily disable our filters
		// This prevents double-discounting when CheckoutWC calculates cart item display prices
		add_action( 'cfw_before_bump_display_price_calculation', array( $this, 'remove_price_filters' ) );
		add_action( 'cfw_after_bump_display_price_calculation', array( $this, 'add_price_filters' ) );
	}

	/**
	 * Intercept direct product price calls for order bump products
	 *
	 * This is the core fix: when the frontend displays prices by calling $product->get_price() directly,
	 * we catch those calls and route them through CheckoutWC's pricing system which properly handles
	 * WOOMC currency conversion AND applies order bump discounts.
	 *
	 * @param float      $price The original product price.
	 * @param WC_Product $product The product object.
	 * @return float The properly converted and discounted price.
	 */
	public function intercept_display_price_calls( $price, $product ) {
		// Only intercept for products that are order bump offers in the cart
		$bump = $this->find_cart_bump_for_product( $product->get_id() );

		if ( ! $bump ) {
			return $price; // Not an order bump product, let WOOMC handle normally
		}

		// CRITICAL: Only intercept if the product actually has custom WOOMC pricing
		// Products without custom prices should use normal WOOMC conversion + CheckoutWC discounts
		// This prevents double-discounting when WOOMC handles conversion automatically
		if ( ! $this->product_has_custom_pricing( $product ) ) {
			return $price; // No custom pricing, let WOOMC + CheckoutWC handle normally
		}

		// CRITICAL: Prevent infinite loop by temporarily removing our filter
		// This is necessary because $bump->get_price() internally calls $product->get_price()
		// which would trigger our filter again, creating recursion
		$this->remove_price_filters();

		// Use CheckoutWC's pricing system with 'view' context
		// This allows WOOMC to automatically convert the price AND applies the order bump discount
		$calculated_price = $bump->get_price( 'view', $product->get_id() );

		// Re-add our filters for future calls
		$this->add_price_filters();

		return $calculated_price;
	}

	/**
	 * Find order bump for a given product ID by searching cart contents
	 *
	 * This is necessary because we need to determine if a product price call is for
	 * an order bump product that should have converted pricing + discounts applied.
	 *
	 * @param int $product_id The product ID to search for.
	 * @return BumpInterface|false The bump object or false if not found.
	 */
	private function find_cart_bump_for_product( $product_id ) {
		if ( ! WC()->cart || empty( WC()->cart->get_cart_contents() ) ) {
			return false;
		}

		// Search through cart items to find any with this product ID that are order bumps
		foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
			if ( ! isset( $cart_item['_cfw_order_bump_id'] ) ) {
				continue; // Not an order bump item
			}

			$cart_product_id   = $cart_item['product_id'] ?? 0;
			$cart_variation_id = $cart_item['variation_id'] ?? 0;

			// Check if this cart item matches the product we're pricing
			if ( $cart_product_id !== $product_id && $cart_variation_id !== $product_id ) {
				continue;
			}

			// Found a matching order bump - return the bump object
			return BumpFactory::get( $cart_item['_cfw_order_bump_id'] );
		}

		return false;
	}

	/**
	 * Check if a product has custom WOOMC pricing
	 *
	 * Uses WOOMC's own method to determine if a product has custom prices set.
	 * This prevents double-discounting by only intercepting products that actually
	 * need the custom price + discount calculation.
	 *
	 * @param WC_Product $product The product to check.
	 * @return bool True if product has custom WOOMC pricing, false otherwise.
	 */
	private function product_has_custom_pricing( $product ) {
		if ( ! class_exists( '\\WOOMC\\Product\\Info' ) ) {
			return false;
		}

		$product_info = new Info( $product );
		return $product_info->is_custom_priced();
	}

	/**
	 * Add our high priority price filters
	 *
	 * These catch display price calls that completely bypass the order bump pricing system.
	 * Without these, WOOMC custom prices override order bump discounts in the UI.
	 */
	public function add_price_filters() {
		add_filter( 'woocommerce_product_get_price', array( $this, 'intercept_display_price_calls' ), 9999, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'intercept_display_price_calls' ), 9999, 2 );
	}

	/**
	 * Remove our high priority price filters
	 *
	 * Used to prevent infinite loops and double-discounting during CheckoutWC calculations.
	 */
	public function remove_price_filters() {
		remove_filter( 'woocommerce_product_get_price', array( $this, 'intercept_display_price_calls' ), 9999 );
		remove_filter( 'woocommerce_product_variation_get_price', array( $this, 'intercept_display_price_calls' ), 9999 );
	}
}
