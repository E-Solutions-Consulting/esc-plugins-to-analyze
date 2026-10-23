<?php
/**
 * Shipping-state product replacement at checkout.
 *
 * When the order is placed, cart variations are swapped for the hidden variation
 * configured for the shipping state (and back when the state does not match),
 * after every checkout validation passed and before the order is created and charged.
 * See common/state-product-replacement-helpers.php.
 */

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bh_State_Product_Replacement' ) ) {

	class Bh_State_Product_Replacement {

		const ERROR_CODE = 'bh_state_product_replacement_failed';

		public function __construct() {
			// Classic checkout: last callback of the validation step, before the order is created from the cart.
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'apply_classic' ), PHP_INT_MAX, 2 );

			// Store API checkout (express checkout): after the shared validators (priority 5), before the draft order.
			add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'apply_store_api' ), 30, 2 );

			// Replacement variations are never offered on the storefront.
			add_filter( 'woocommerce_variation_is_visible', array( $this, 'hide_replacement_variation' ), 10, 2 );

			// A coupon for (or excluding) a variation also covers its replacements.
			add_filter( 'woocommerce_coupon_get_product_ids', array( $this, 'add_replacements_to_coupon' ), 10, 1 );
			add_filter( 'woocommerce_coupon_get_excluded_product_ids', array( $this, 'add_replacements_to_coupon' ), 10, 1 );
		}

		/**
		 * @param array    $data   Posted checkout data (shipping_* already falls back to billing_*).
		 * @param WP_Error $errors Validation errors.
		 */
		public function apply_classic( $data, $errors ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout verified the nonce.
			if ( ! empty( $_POST['woocommerce_checkout_update_totals'] ) || ! WC()->cart ) {
				return;
			}
			if ( ( $errors instanceof WP_Error && $errors->has_errors() ) || wc_notice_count( 'error' ) > 0 ) {
				return;
			}

			$result = bh_apply_state_product_replacement( WC()->cart, $this->state_from( $data['shipping_state'] ?? '', $data['billing_state'] ?? '' ) );
			foreach ( $result['errors'] as $message ) {
				$errors->add( self::ERROR_CODE, $message );
			}
			// The failed submission refreshes the order review, so the new total is shown.
			if ( $result['total_changed'] && WC()->session ) {
				WC()->session->set( 'refresh_totals', true );
			}
		}

		/**
		 * @param WC_Customer     $customer Customer filled from the request.
		 * @param WP_REST_Request $request  Checkout request.
		 * @throws RouteException When a cart item cannot be swapped.
		 */
		public function apply_store_api( $customer, $request ) {
			if ( ! $customer instanceof WC_Customer || ! WC()->cart ) {
				return;
			}
			// Paying an existing order (/checkout/{id}) does not use the cart: leave the cart alone.
			if ( $request instanceof WP_REST_Request && preg_match( '#/checkout/\d+/?$#', $request->get_route() ) ) {
				return;
			}

			$result = bh_apply_state_product_replacement( WC()->cart, $this->state_from( $customer->get_shipping_state(), $customer->get_billing_state() ) );
			if ( $result['errors'] ) {
				throw new RouteException( self::ERROR_CODE, implode( ' ', $result['errors'] ), 400 );
			}
		}

		/**
		 * @param bool $visible      Whether the variation is visible.
		 * @param int  $variation_id Variation ID.
		 * @return bool
		 */
		public function hide_replacement_variation( $visible, $variation_id ) {
			if ( ! $visible || ( is_admin() && ! wp_doing_ajax() ) ) {
				return $visible;
			}

			return ! bh_is_state_replacement_variation( (int) $variation_id );
		}

		/**
		 * Adds the replacement variations to a coupon's product restriction.
		 * Admin screens keep showing the restriction as saved.
		 *
		 * @param int[] $product_ids Product IDs of a coupon restriction.
		 * @return int[]
		 */
		public function add_replacements_to_coupon( $product_ids ) {
			if ( ! is_array( $product_ids ) || ( is_admin() && ! wp_doing_ajax() ) ) {
				return $product_ids;
			}

			return bh_add_state_replacements_to_product_ids( $product_ids );
		}

		/**
		 * Shipping state, or the billing state when the order has no shipping state.
		 */
		private function state_from( $shipping_state, $billing_state ): string {
			$state = '' !== (string) $shipping_state ? (string) $shipping_state : (string) $billing_state;

			return strtoupper( trim( $state ) );
		}
	}

	new Bh_State_Product_Replacement();
}
