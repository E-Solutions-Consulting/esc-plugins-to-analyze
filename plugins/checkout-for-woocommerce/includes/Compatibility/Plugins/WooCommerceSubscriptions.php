<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class WooCommerceSubscriptions extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\WC_Subscriptions_Cart' );
	}

	public function pre_init() {
		add_filter( 'cfw_is_checkout_pay_page', array( $this, 'disable_cfw_for_change_payment_request' ), 10, 1 );
		add_filter( 'cfw_available_variations', array( $this, 'allow_variation_changes_for_subscription_limits' ), 10, 3 );
	}

	public function run_immediately() {
		add_filter( 'cfw_show_shipping_tab', array( $this, 'maybe_hide_shipping_tab' ) );
	}

	public function run() {
		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'override_registration_required' ), 10, 1 );
	}

	public function maybe_hide_shipping_tab( $show_shipping_tab ) {
		if ( ! $show_shipping_tab ) {
			return $show_shipping_tab;
		}

		$cart_contents = WC()->cart->get_cart_contents();

		// Remove any items from the cart array that are non-qualifying subscriptions
		foreach ( $cart_contents as $i => $cart_item ) {
			if ( \WC_Subscriptions_Product::get_trial_length( $cart_item['data'] ) > 0 ) {
				unset( $cart_contents[ $i ] );
			}
		}

		// If the cart is now empty, we should hide the shipping tab
		if ( count( $cart_contents ) === 0 ) {
			return false;
		}

		return $show_shipping_tab;
	}

	public function disable_cfw_for_change_payment_request( $result ) {
		if ( ! empty( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		return $result;
	}

	public function override_registration_required( $result ) {
		if ( \WC_Subscriptions_Cart::cart_contains_subscription() && ! is_user_logged_in() ) {
			$result = true;
		}

		return $result;
	}

	/**
	 * Allow changes when editing variable subscription cart items, even when subscription limits would normally prevent it.
	 *
	 * For subscription products with "Limit subscription" set to "Limit to one active subscription"
	 * or "Limit to one of any status", WooCommerce Subscriptions marks variations as not purchasable
	 * when there's already a subscription product in the cart for the same parent product. This
	 * prevents customers from adding multiple subscriptions, which is correct behavior.
	 *
	 * However, when editing an existing cart item variation, we're not adding a new subscription -
	 * we're changing which variation of the existing subscription cart item should be used. In this
	 * context, the subscription limit check incorrectly blocks valid variation changes.
	 *
	 * This fix only applies to subscription products with subscription limits set (not "no").
	 * It overrides the purchasable status for all variations to allow variation changes when
	 * editing existing cart items. Other restrictions (out of stock, etc.) are still enforced
	 * by WooCommerce's validation.
	 *
	 * @param array $available_variations Array of available variations.
	 * @param \WC_Product_Variable $variable_product The variable product object.
	 * @param array $cart_item The cart item being edited, if any. Empty array if not editing.
	 * @return array Modified array of available variations.
	 */
	public function allow_variation_changes_for_subscription_limits( $available_variations, $variable_product, $cart_item ) {
		// Only filter if cart editing and variation changes are enabled in CheckoutWC settings.
		if ( SettingsManager::instance()->get_setting( 'enable_cart_editing' ) !== 'yes'
			|| SettingsManager::instance()->get_setting( 'allow_checkout_cart_item_variation_changes' ) !== 'yes' ) {
			return $available_variations;
		}

		// Check cart item has required data set.
		if ( ! isset( $_GET['key'] ) || empty( $cart_item ) || ! is_array( $cart_item ) || ! isset( $cart_item['variation_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $available_variations;
		}

		// Check if this is a subscription product with subscription limits set.
		$has_subscription_limits = false;
		if ( class_exists( '\WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $variable_product ) ) {
			$subscription_limit = $variable_product->get_meta( '_subscription_limit' );
			// Only apply if subscription limit is set (not "no" or empty).
			$has_subscription_limits = ! empty( $subscription_limit ) && 'no' !== $subscription_limit;
		}

		// Only override purchasable status for subscription products with limits set.
		if ( ! $has_subscription_limits ) {
			return $available_variations;
		}

		// Override purchasable status for all variations to allow variation changes.
		// This fixes the issue where subscription limits incorrectly block valid variation changes
		// when editing existing cart items. Other restrictions (out of stock, etc.) are still
		// enforced by WooCommerce's validation. If the customer has an existing subscription for this
		// product, they can't add it to cart before getting to checkout for this override to take effect.
		foreach ( $available_variations as $key => $variation ) {
			$available_variations[ $key ]['is_purchasable'] = true;
			$available_variations[ $key ]['purchasable']    = true;
		}

		return $available_variations;
	}
}
