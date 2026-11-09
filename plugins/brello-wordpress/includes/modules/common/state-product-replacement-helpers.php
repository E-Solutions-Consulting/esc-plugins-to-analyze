<?php
/**
 * Shipping-state product replacement helpers.
 *
 * Some states cannot receive a variation as sold (e.g. California cannot ship
 * three months at once). For those, a variation of a separate state product
 * (its own SKU and renewal cycle, e.g. "Tirzepatide CA") replaces it when the
 * order ships to that state:
 *
 * - the replacement variation stores the variation it replaces
 *   (_bh_replaces_variation) and the shipping states it is for
 *   (_bh_exclusive_shipping_states);
 * - it is hidden on the storefront and only reaches the cart through the swap;
 * - a cart that holds the replacement but ships elsewhere is swapped back.
 *
 * The checkout swaps the cart as soon as the shipping state is entered, so the
 * order review shows the product and plan that will be charged, and again when
 * the order is placed (classic and Store API / express checkout) in case the
 * state changed without a refresh. The Telegra landing checkout does not call it
 * yet. Renewal, resubscribe, switch and initial-payment items keep the product of
 * their subscription or order.
 *
 * A replacement takes the coupon product restrictions of the variation (and of
 * the product) it replaces, so a swap never changes which coupons apply.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BH_REPLACES_VARIATION_META' ) ) {
	define( 'BH_REPLACES_VARIATION_META', '_bh_replaces_variation' );
}
if ( ! defined( 'BH_EXCLUSIVE_STATES_META' ) ) {
	define( 'BH_EXCLUSIVE_STATES_META', '_bh_exclusive_shipping_states' );
}
if ( ! defined( 'BH_STATE_ORIGINAL_ITEM_KEY' ) ) {
	define( 'BH_STATE_ORIGINAL_ITEM_KEY', 'bh_state_original_item' );
}

if ( ! function_exists( 'bh_get_state_replacement_config' ) ) {
	/**
	 * Replacement settings of a variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array{replaces: int, states: string[]} replaces = 0 when the variation is not a replacement.
	 */
	function bh_get_state_replacement_config( int $variation_id ): array {
		$replaces = (int) get_post_meta( $variation_id, BH_REPLACES_VARIATION_META, true );
		$states   = get_post_meta( $variation_id, BH_EXCLUSIVE_STATES_META, true );
		$states   = is_array( $states ) ? array_values( array_filter( array_map( 'strtoupper', $states ) ) ) : array();

		if ( $replaces <= 0 || empty( $states ) ) {
			return array(
				'replaces' => 0,
				'states'   => array(),
			);
		}

		return array(
			'replaces' => $replaces,
			'states'   => $states,
		);
	}
}

if ( ! function_exists( 'bh_is_state_replacement_variation' ) ) {
	/**
	 * Whether a variation is a shipping-state replacement (hidden on the storefront).
	 *
	 * @param int $variation_id Variation ID.
	 * @return bool
	 */
	function bh_is_state_replacement_variation( int $variation_id ): bool {
		return bh_get_state_replacement_config( $variation_id )['replaces'] > 0;
	}
}

if ( ! function_exists( 'bh_get_state_replacements_of' ) ) {
	/**
	 * Replacement variations configured for a variation, keyed by replacement ID.
	 *
	 * @param int $variation_id Variation that may be replaced.
	 * @return array<int, string[]> Replacement variation ID => its shipping states.
	 */
	function bh_get_state_replacements_of( int $variation_id ): array {
		if ( $variation_id <= 0 ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'meta_key'       => BH_REPLACES_VARIATION_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'     => $variation_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			)
		);

		$replacements = array();
		foreach ( $ids as $id ) {
			$config = bh_get_state_replacement_config( (int) $id );
			if ( $config['replaces'] === $variation_id ) {
				$replacements[ (int) $id ] = $config['states'];
			}
		}

		return $replacements;
	}
}

if ( ! function_exists( 'bh_get_state_replacement_map' ) ) {
	/**
	 * Every replacement variation, grouped by the variation it replaces and by that
	 * variation's product.
	 *
	 * Cached for the request.
	 *
	 * @param bool $refresh Read the replacements again.
	 * @return array<int, int[]> Replaced variation ID or its product ID => replacement variation IDs.
	 */
	function bh_get_state_replacement_map( bool $refresh = false ): array {
		static $map = null;
		if ( null !== $map && ! $refresh ) {
			return $map;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => BH_REPLACES_VARIATION_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
			)
		);

		$map = array();
		foreach ( $ids as $id ) {
			$replaces = bh_get_state_replacement_config( (int) $id )['replaces'];
			if ( $replaces > 0 ) {
				$map[ $replaces ][] = (int) $id;
				$replaced_product   = (int) wp_get_post_parent_id( $replaces );
				if ( $replaced_product > 0 ) {
					$map[ $replaced_product ][] = (int) $id;
				}
			}
		}

		return $map;
	}
}

if ( ! function_exists( 'bh_add_state_replacements_to_product_ids' ) ) {
	/**
	 * Adds the replacements of the listed variations and products to a coupon's product IDs.
	 *
	 * @param int[] $product_ids Product IDs of a coupon restriction.
	 * @return int[]
	 */
	function bh_add_state_replacements_to_product_ids( array $product_ids ): array {
		$map = $product_ids ? bh_get_state_replacement_map() : array();
		foreach ( $product_ids as $id ) {
			foreach ( $map[ (int) $id ] ?? array() as $replacement_id ) {
				if ( ! in_array( $replacement_id, $product_ids, true ) ) {
					$product_ids[] = $replacement_id;
				}
			}
		}

		return $product_ids;
	}
}

if ( ! function_exists( 'bh_get_cart_payable_amounts' ) ) {
	/**
	 * What the cart charges now and on each renewal schedule, after recalculating it.
	 *
	 * Kept apart so a lower renewal never hides a higher charge today.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array{now: string, recurring: array<string, string>} Recurring totals keyed by billing schedule.
	 */
	function bh_get_cart_payable_amounts( WC_Cart $cart ): array {
		$cart->calculate_totals();

		$amounts = array(
			'now'       => wc_format_decimal( $cart->get_total( 'edit' ), wc_get_price_decimals() ),
			'recurring' => array(),
		);
		// WooCommerce Subscriptions keeps the recurring carts on the cart, keyed by billing schedule.
		foreach ( (array) ( $cart->recurring_carts ?? array() ) as $schedule => $recurring_cart ) {
			if ( $recurring_cart instanceof WC_Cart ) {
				$amounts['recurring'][ (string) $schedule ] = wc_format_decimal( $recurring_cart->get_total( 'edit' ), wc_get_price_decimals() );
			}
		}
		ksort( $amounts['recurring'] );

		return $amounts;
	}
}

if ( ! function_exists( 'bh_resolve_variation_for_state' ) ) {
	/**
	 * Variation that must be sold when the order ships to $state.
	 *
	 * - A replacement variation shipping outside its states resolves to the variation it replaces.
	 * - A variation with a replacement for $state resolves to that replacement.
	 * - Anything else resolves to itself.
	 *
	 * @param int    $variation_id Variation in the cart.
	 * @param string $state        Shipping state code.
	 * @return int
	 */
	function bh_resolve_variation_for_state( int $variation_id, string $state ): int {
		$state  = strtoupper( trim( $state ) );
		$base   = $variation_id;
		$config = bh_get_state_replacement_config( $variation_id );

		if ( $config['replaces'] > 0 ) {
			if ( in_array( $state, $config['states'], true ) ) {
				return $variation_id;
			}
			$base = $config['replaces'];
		}

		if ( '' !== $state ) {
			foreach ( bh_get_state_replacements_of( $base ) as $replacement_id => $states ) {
				if ( in_array( $state, $states, true ) ) {
					return $replacement_id;
				}
			}
		}

		return $base;
	}
}

if ( ! function_exists( 'bh_apply_state_product_replacement' ) ) {
	/**
	 * Swaps the cart items whose variation does not match the shipping state.
	 *
	 * The cart is changed (not only the order) because WooCommerce Subscriptions
	 * builds the subscriptions from the cart on every checkout path. Cart item data
	 * (e.g. Telegra fields) is kept. Totals are recalculated after a swap; when the
	 * amount due now or on any renewal changed, an error is returned so a caller that
	 * places the order can stop it: the customer sees the new totals, and the next
	 * submission (with nothing left to swap) places it.
	 *
	 * @param WC_Cart $cart  Cart about to become an order.
	 * @param string  $state Shipping state code.
	 * @return array{swapped: array<int, array{from: int, to: int}>, errors: string[], total_changed: bool}
	 */
	function bh_apply_state_product_replacement( WC_Cart $cart, string $state ): array {
		$result = array(
			'swapped'       => array(),
			'errors'        => array(),
			'total_changed' => false,
		);
		$amount_before = null;

		/**
		 * Kill switch for the shipping-state product replacement.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'bh_state_product_replacement_enabled', true ) ) {
			return $result;
		}

		$own_keys = array( 'key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash', 'line_tax_data', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax' );

		foreach ( $cart->get_cart() as $key => $item ) {
			$from = (int) ( $item['variation_id'] ?? 0 );
			if ( $from <= 0 ) {
				continue;
			}
			// Renewal, resubscribe, switch and initial-payment items belong to an existing subscription or order: its product is not changed here.
			if ( isset( $item['subscription_renewal'] ) || isset( $item['subscription_resubscribe'] ) || isset( $item['subscription_switch'] ) || isset( $item['subscription_initial_payment'] ) ) {
				continue;
			}

			$to = bh_resolve_variation_for_state( $from, $state );
			if ( $to === $from ) {
				continue;
			}

			// Subscription variations check purchasability on the parent only, so status and price are checked here.
			$target         = wc_get_product( $to );
			$target_product = $target instanceof WC_Product_Variation ? wc_get_product( $target->get_parent_id() ) : null;
			if ( ! $target_product || ! $target_product->is_type( 'variable-subscription' ) || 'publish' !== $target_product->get_status() || 'publish' !== $target->get_status() || '' === $target->get_price() || ! $target->is_purchasable() || ! $target->is_in_stock() ) {
				$result['errors'][] = sprintf(
					/* translators: %s: product name */
					__( '%s is not available for the selected shipping state. Please contact support.', 'bh-features' ),
					$item['data'] instanceof WC_Product ? $item['data']->get_name() : '#' . $from
				);
				continue;
			}

			// The variation and attributes the customer chose, kept on the item across swaps.
			$original = isset( $item[ BH_STATE_ORIGINAL_ITEM_KEY ]['variation_id'] )
				? $item[ BH_STATE_ORIGINAL_ITEM_KEY ]
				: array(
					'variation_id' => $from,
					'variation'    => (array) ( $item['variation'] ?? array() ),
				);

			// Attributes of the target; an "any" attribute takes the value chosen originally, else the current one.
			$attributes = $target->get_variation_attributes();
			foreach ( $attributes as $name => $value ) {
				if ( '' !== $value ) {
					continue;
				}
				if ( isset( $original['variation'][ $name ] ) ) {
					$attributes[ $name ] = $original['variation'][ $name ];
				} elseif ( isset( $item['variation'][ $name ] ) ) {
					$attributes[ $name ] = $item['variation'][ $name ];
				}
			}
			$item_data = array_diff_key( $item, array_flip( $own_keys ) );
			if ( (int) $original['variation_id'] === $to ) {
				unset( $item_data[ BH_STATE_ORIGINAL_ITEM_KEY ] );
			} else {
				$item_data[ BH_STATE_ORIGINAL_ITEM_KEY ] = $original;
			}

			if ( null === $amount_before ) {
				$amount_before = bh_get_cart_payable_amounts( $cart );
			}

			// Remove first, so a stock shared by both items counts only the final quantity;
			// a failed add puts the original item back. A cart left empty for that moment and
			// saved (WooCommerce and CheckoutWC save it on removal) loses the chosen shipping
			// methods and the Store API draft order from the session: they are put back after.
			$session_data = array();
			if ( WC()->session ) {
				foreach ( array( 'chosen_shipping_methods', 'previous_shipping_methods', 'shipping_method_counts', 'store_api_draft_order' ) as $session_key ) {
					$session_data[ $session_key ] = WC()->session->get( $session_key );
				}
			}
			$cart->remove_cart_item( $key );
			$new_key = $cart->add_to_cart( $target_product->get_id(), (int) $item['quantity'], $to, $attributes, $item_data );
			if ( ! $new_key ) {
				$cart->restore_cart_item( $key );
			}
			foreach ( $session_data as $session_key => $value ) {
				WC()->session->set( $session_key, $value );
			}
			if ( ! $new_key ) {
				$result['errors'][] = sprintf(
					/* translators: %s: product name */
					__( '%s could not be updated for the selected shipping state. Please contact support.', 'bh-features' ),
					$target->get_name()
				);
				continue;
			}

			$result['swapped'][] = array(
				'from' => $from,
				'to'   => $to,
			);
		}

		if ( null !== $amount_before ) {
			$amount_after = bh_get_cart_payable_amounts( $cart );
			if ( $result['swapped'] && $amount_after !== $amount_before ) {
				$result['total_changed'] = true;
				$result['errors'][]      = __( 'Your order was updated for the selected shipping state and its totals changed. Please review them and place the order again.', 'bh-features' );
			}
		}

		if ( $result['swapped'] ) {
			wc_get_logger()->info(
				'Cart variations swapped for the shipping state.',
				array(
					'source'        => 'bh-state-product-replacement',
					'state'         => strtoupper( $state ),
					'user_id'       => get_current_user_id(),
					'swapped'       => $result['swapped'],
					'total_changed' => $result['total_changed'],
				)
			);
		}

		return $result;
	}
}
