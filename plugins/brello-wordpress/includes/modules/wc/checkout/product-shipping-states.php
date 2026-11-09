<?php
/**
 * Per-product shipping state rules at checkout.
 *
 * - The checkout page lists only the states every product of the cart may ship to.
 * - The state fields explain why a licensed state is missing ("We're sorry, this
 *   product isn't currently available in California.").
 * - Placing the order refuses a shipping state a product of the cart may not ship to,
 *   with the same notice. Runs on the classic checkout and, through the shared
 *   validators, on the Store API checkout (express checkout).
 *
 * The list is filtered only while the checkout page is rendered: the order submission
 * keeps the full list, so the customer gets this notice instead of WooCommerce's
 * "not a valid state".
 * See common/product-shipping-states-helpers.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bh_Product_Shipping_States_Checkout' ) ) {

	class Bh_Product_Shipping_States_Checkout {

		const ERROR_CODE = 'bh_product_state_blocked';

		/** Above this many blocked states the notice lists the allowed ones instead. */
		const MAX_LISTED_STATES = 5;

		public function __construct() {
			// After AH_States (10) and the state descriptions (999).
			add_filter( 'woocommerce_states', array( $this, 'filter_checkout_states' ), 1000 );
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_state_notice' ), 1000 );

			// Before the state product replacement (PHP_INT_MAX), so a refused order is never swapped.
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 20, 2 );
		}

		/**
		 * Removes the states a product of the cart may not ship to (checkout page only).
		 *
		 * @param array $states Country => states.
		 * @return array
		 */
		public function filter_checkout_states( $states ) {
			if ( ! is_array( $states ) || empty( $states['US'] ) || ! is_array( $states['US'] ) || ! $this->is_checkout_page() ) {
				return $states;
			}

			foreach ( bh_cart_blocked_states( WC()->cart ) as $code ) {
				unset( $states['US'][ $code ] );
			}

			return $states;
		}

		/**
		 * Adds the notice under the state fields when the cart hides a licensed state.
		 *
		 * @param array $fields Checkout fields.
		 * @return array
		 */
		public function add_state_notice( $fields ) {
			if ( ! is_array( $fields ) || ! $this->is_checkout_page() ) {
				return $fields;
			}

			$message = $this->notice_for_cart();
			if ( '' === $message ) {
				return $fields;
			}

			foreach ( array( 'billing', 'shipping' ) as $group ) {
				$key = $group . '_state';
				if ( isset( $fields[ $group ][ $key ] ) && is_array( $fields[ $group ][ $key ] ) ) {
					// Plain text: WooCommerce prints the description through wp_kses_post().
					$fields[ $group ][ $key ]['description'] = $message;
					$fields[ $group ][ $key ]['class']       = array_merge( (array) ( $fields[ $group ][ $key ]['class'] ?? array() ), array( 'bh-state-blocked' ) );
				}
			}

			return $fields;
		}

		/**
		 * Refuses a shipping state a product of the cart may not ship to.
		 *
		 * @param array    $data   Posted checkout data (shipping_* already falls back to billing_*).
		 * @param WP_Error $errors Validation errors.
		 */
		public function validate( $data, $errors ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout verified the nonce.
			if ( ! empty( $_POST['woocommerce_checkout_update_totals'] ) || ! $errors instanceof WP_Error || ! function_exists( 'WC' ) ) {
				return;
			}

			$state = strtoupper( trim( (string) ( ! empty( $data['shipping_state'] ) ? $data['shipping_state'] : ( $data['billing_state'] ?? '' ) ) ) );
			if ( '' === $state ) {
				return;
			}

			if ( bh_cart_products_blocked_in_state( WC()->cart, $state ) ) {
				$errors->add( self::ERROR_CODE, bh_state_blocked_message( array( $state ) ) );
			}
		}

		/**
		 * Notice for the licensed states the cart hides, empty when it hides none.
		 */
		private function notice_for_cart(): string {
			$licensed = $this->licensed_states();
			$blocked  = array_values( array_intersect( bh_cart_blocked_states( WC()->cart ), $licensed ) );
			if ( empty( $blocked ) ) {
				return '';
			}

			if ( count( $blocked ) <= self::MAX_LISTED_STATES ) {
				return bh_state_blocked_message( $blocked );
			}

			$allowed = array_values( array_diff( $licensed, $blocked ) );
			$names   = array();
			foreach ( $allowed as $code ) {
				$name    = AH_States::get_name( $code );
				$names[] = $name ? $name : $code;
			}

			$message = empty( $names )
				? __( "We're sorry, this product isn't currently available in any state we ship to.", 'bh-features' )
				/* translators: %s: state names */
				: sprintf( __( 'This product is only available in %s.', 'bh-features' ), implode( ', ', $names ) );

			return (string) apply_filters( 'bh_state_blocked_message', $message, $blocked );
		}

		/**
		 * States this customer would see without the product rules (licensed states,
		 * plus the test states of a request that came through the test states link).
		 *
		 * @return string[]
		 */
		private function licensed_states(): array {
			if ( ! class_exists( 'AH_States' ) ) {
				return array();
			}
			$states = AH_States::get_states_for_current_user( AH_States::get_all() );

			return array_map( 'strtoupper', array_keys( (array) ( $states['US'] ?? array() ) ) );
		}

		/**
		 * The checkout page itself, not the order submission, order-pay or order-received.
		 */
		private function is_checkout_page(): bool {
			if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'is_checkout' ) || ! did_action( 'wp' ) ) {
				return false;
			}
			if ( ! is_checkout() || is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
				return false;
			}

			return function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;
		}
	}

	new Bh_Product_Shipping_States_Checkout();
}
