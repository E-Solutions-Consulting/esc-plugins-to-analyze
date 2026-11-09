<?php
/**
 * Per-product shipping state rules.
 *
 * The licensed states configuration decides where we ship at all. These rules narrow
 * it down per product, for any state and any product, now or later:
 *
 * - Opt-in states (global option, e.g. CA): a product ships there only when its rules
 *   allow that state explicitly. Every other product, including new ones, does not.
 * - Allowed opt-in states (product category or product): the opt-in states the product
 *   may ship to, e.g. CA on the weight-loss category.
 * - Ship only to (product category or product, optional): when set, the product ships
 *   only to these states (opt-in states listed here are allowed too).
 *
 * A product uses the rules of its categories (and their parent categories) unless it is
 * set to its own rules. A variation uses the rules of its product, except a shipping-state
 * replacement variation, which always ships to its exclusive states.
 *
 * Checked at checkout (state list, notice, classic and Store API validation) and by the
 * landing checkout. Renewals keep the product of their subscription and are not checked.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BH_OPT_IN_STATES_OPTION' ) ) {
	define( 'BH_OPT_IN_STATES_OPTION', 'bh_opt_in_shipping_states' );
}
if ( ! defined( 'BH_STATES_RULES_MODE_META' ) ) {
	define( 'BH_STATES_RULES_MODE_META', '_bh_shipping_states_mode' );
}
if ( ! defined( 'BH_ALLOWED_OPT_IN_STATES_META' ) ) {
	define( 'BH_ALLOWED_OPT_IN_STATES_META', '_bh_allowed_opt_in_states' );
}
if ( ! defined( 'BH_SHIP_ONLY_STATES_META' ) ) {
	define( 'BH_SHIP_ONLY_STATES_META', '_bh_ship_only_states' );
}

if ( ! function_exists( 'bh_us_state_codes' ) ) {
	/**
	 * Every US state code we know.
	 *
	 * @return string[]
	 */
	function bh_us_state_codes(): array {
		if ( class_exists( 'AH_States' ) ) {
			return array_map( 'strtoupper', AH_States::get_codes() );
		}

		return array_map( 'strtoupper', array_keys( (array) WC()->countries->get_states( 'US' ) ) );
	}
}

if ( ! function_exists( 'bh_parse_state_codes' ) ) {
	/**
	 * Splits a comma separated list (or an array) into unique upper-case codes.
	 *
	 * @param string|array $value Raw value.
	 * @return string[]
	 */
	function bh_parse_state_codes( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$codes = array_map(
			static function ( $code ) {
				return strtoupper( trim( (string) $code ) );
			},
			$value
		);

		return array_values( array_unique( array_filter( $codes ) ) );
	}
}

if ( ! function_exists( 'bh_unknown_state_codes' ) ) {
	/**
	 * Codes of $codes that are not US states.
	 *
	 * @param string[] $codes State codes.
	 * @return string[]
	 */
	function bh_unknown_state_codes( array $codes ): array {
		return array_values( array_diff( $codes, bh_us_state_codes() ) );
	}
}

if ( ! function_exists( 'bh_get_opt_in_states' ) ) {
	/**
	 * States a product ships to only when its rules allow them.
	 *
	 * @return string[]
	 */
	function bh_get_opt_in_states(): array {
		return bh_parse_state_codes( get_option( BH_OPT_IN_STATES_OPTION, array() ) );
	}
}

if ( ! function_exists( 'bh_get_category_state_rules' ) ) {
	/**
	 * Rules saved on one product category.
	 *
	 * @param int $term_id Product category ID.
	 * @return array{opt_in: string[], ship_only: string[]}
	 */
	function bh_get_category_state_rules( int $term_id ): array {
		return array(
			'opt_in'    => bh_parse_state_codes( get_term_meta( $term_id, BH_ALLOWED_OPT_IN_STATES_META, true ) ),
			'ship_only' => bh_parse_state_codes( get_term_meta( $term_id, BH_SHIP_ONLY_STATES_META, true ) ),
		);
	}
}

if ( ! function_exists( 'bh_product_state_rules_cache' ) ) {
	/**
	 * Per-request cache of the effective product rules (product ID => rules).
	 * Emptied whenever a rule meta or a product's categories change.
	 *
	 * @return array
	 */
	function &bh_product_state_rules_cache(): array {
		static $cache = array();

		return $cache;
	}

	/**
	 * Empties the rules cache.
	 */
	function bh_flush_product_state_rules_cache() {
		$cache = &bh_product_state_rules_cache();
		$cache = array();
	}

	$bh_flush_on_meta = static function ( $meta_ids, $object_id, $meta_key ) {
		if ( in_array( $meta_key, array( BH_STATES_RULES_MODE_META, BH_ALLOWED_OPT_IN_STATES_META, BH_SHIP_ONLY_STATES_META ), true ) ) {
			bh_flush_product_state_rules_cache();
		}
	};
	foreach ( array( 'post', 'term' ) as $bh_meta_type ) {
		foreach ( array( 'added', 'updated', 'deleted' ) as $bh_meta_change ) {
			add_action( "{$bh_meta_change}_{$bh_meta_type}_meta", $bh_flush_on_meta, 10, 3 );
		}
	}
	add_action( 'set_object_terms', 'bh_flush_product_state_rules_cache', 10, 0 );
	add_action( 'edited_product_cat', 'bh_flush_product_state_rules_cache', 10, 0 );
	unset( $bh_flush_on_meta, $bh_meta_type, $bh_meta_change );
}

if ( ! function_exists( 'bh_get_product_state_rules' ) ) {
	/**
	 * Effective rules of a product (not a variation).
	 *
	 * Own rules when the product is set to them; otherwise the union of the rules of its
	 * categories and their parents.
	 *
	 * @param int $product_id Product ID.
	 * @return array{opt_in: string[], ship_only: string[], source: string} source = 'product' | 'categories'.
	 */
	function bh_get_product_state_rules( int $product_id ): array {
		$cache = &bh_product_state_rules_cache();
		if ( isset( $cache[ $product_id ] ) ) {
			return $cache[ $product_id ];
		}

		if ( 'custom' === get_post_meta( $product_id, BH_STATES_RULES_MODE_META, true ) ) {
			$rules = array(
				'opt_in'    => bh_parse_state_codes( get_post_meta( $product_id, BH_ALLOWED_OPT_IN_STATES_META, true ) ),
				'ship_only' => bh_parse_state_codes( get_post_meta( $product_id, BH_SHIP_ONLY_STATES_META, true ) ),
				'source'    => 'product',
			);
		} else {
			$rules    = array(
				'opt_in'    => array(),
				'ship_only' => array(),
				'source'    => 'categories',
			);
			$term_ids = wc_get_product_term_ids( $product_id, 'product_cat' );
			foreach ( $term_ids as $term_id ) {
				$term_ids = array_merge( $term_ids, get_ancestors( $term_id, 'product_cat', 'taxonomy' ) );
			}
			foreach ( array_unique( array_map( 'intval', $term_ids ) ) as $term_id ) {
				$category           = bh_get_category_state_rules( $term_id );
				$rules['opt_in']    = array_merge( $rules['opt_in'], $category['opt_in'] );
				$rules['ship_only'] = array_merge( $rules['ship_only'], $category['ship_only'] );
			}
			$rules['opt_in']    = array_values( array_unique( $rules['opt_in'] ) );
			$rules['ship_only'] = array_values( array_unique( $rules['ship_only'] ) );
		}

		$cache[ $product_id ] = $rules;

		return $rules;
	}
}

if ( ! function_exists( 'bh_product_ships_to_state' ) ) {
	/**
	 * Whether a product or variation may ship to a state under its own rules.
	 *
	 * Does not check the licensed states configuration: that check stays where it is.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $state      State code.
	 * @return bool
	 */
	function bh_product_ships_to_state( int $product_id, string $state ): bool {
		$state = strtoupper( trim( $state ) );
		if ( '' === $state || $product_id <= 0 ) {
			return true;
		}

		$base_id = $product_id;
		if ( 'product_variation' === get_post_type( $product_id ) ) {
			if ( function_exists( 'bh_get_state_replacement_config' ) ) {
				$replacement = bh_get_state_replacement_config( $product_id );
				if ( $replacement['replaces'] > 0 && in_array( $state, $replacement['states'], true ) ) {
					return (bool) apply_filters( 'bh_product_ships_to_state', true, $product_id, $state );
				}
			}
			$base_id = (int) wp_get_post_parent_id( $product_id );
		}

		$rules = bh_get_product_state_rules( $base_id );

		if ( $rules['ship_only'] ) {
			$allowed = in_array( $state, $rules['ship_only'], true );
		} elseif ( in_array( $state, bh_get_opt_in_states(), true ) ) {
			$allowed = in_array( $state, $rules['opt_in'], true );
		} else {
			$allowed = true;
		}

		/**
		 * Filters whether a product may ship to a state.
		 *
		 * @param bool   $allowed    Result of the product rules.
		 * @param int    $product_id Product or variation ID.
		 * @param string $state      State code.
		 */
		return (bool) apply_filters( 'bh_product_ships_to_state', $allowed, $product_id, $state );
	}
}

if ( ! function_exists( 'bh_cart_item_is_checked_for_state' ) ) {
	/**
	 * Whether a cart item is a new purchase the state rules apply to.
	 * Renewals and payments of an existing order keep the product they already have.
	 *
	 * @param array $item Cart item.
	 * @return bool
	 */
	function bh_cart_item_is_checked_for_state( array $item ): bool {
		return empty( $item['subscription_renewal'] ) && empty( $item['subscription_initial_payment'] );
	}
}

if ( ! function_exists( 'bh_cart_products_blocked_in_state' ) ) {
	/**
	 * Products of the cart that may not ship to $state.
	 *
	 * @param WC_Cart|null $cart  Cart.
	 * @param string       $state State code.
	 * @return array<int, string> Product or variation ID => name.
	 */
	function bh_cart_products_blocked_in_state( $cart, string $state ): array {
		if ( ! $cart instanceof WC_Cart || '' === trim( $state ) ) {
			return array();
		}

		$blocked = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! bh_cart_item_is_checked_for_state( $item ) ) {
				continue;
			}
			$id = (int) ( ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? 0 ) );
			if ( $id > 0 && ! bh_product_ships_to_state( $id, $state ) ) {
				$product        = $item['data'] ?? null;
				$blocked[ $id ] = $product instanceof WC_Product ? $product->get_name() : (string) $id;
			}
		}

		return $blocked;
	}
}

if ( ! function_exists( 'bh_cart_blocked_states' ) ) {
	/**
	 * States the products of the cart may not ship to.
	 *
	 * @param WC_Cart|null $cart Cart.
	 * @return string[]
	 */
	function bh_cart_blocked_states( $cart ): array {
		if ( ! $cart instanceof WC_Cart || $cart->is_empty() ) {
			return array();
		}

		$blocked = array();
		foreach ( bh_us_state_codes() as $state ) {
			if ( bh_cart_products_blocked_in_state( $cart, $state ) ) {
				$blocked[] = $state;
			}
		}

		return $blocked;
	}
}

if ( ! function_exists( 'bh_state_blocked_message' ) ) {
	/**
	 * Notice shown when a product may not ship to one or more states.
	 *
	 * @param string[] $states State codes.
	 * @return string
	 */
	function bh_state_blocked_message( array $states ): string {
		$names = array();
		foreach ( $states as $state ) {
			$name    = class_exists( 'AH_States' ) ? AH_States::get_name( $state ) : null;
			$names[] = $name ? $name : $state;
		}

		$list = count( $names ) > 1
			? implode( ', ', array_slice( $names, 0, -1 ) ) . ' ' . __( 'or', 'bh-features' ) . ' ' . end( $names )
			: (string) reset( $names );

		/* translators: %s: state name(s) */
		$message = sprintf( __( "We're sorry, this product isn't currently available in %s.", 'bh-features' ), $list );

		/**
		 * Filters the notice for products that may not ship to some states.
		 *
		 * @param string   $message Notice.
		 * @param string[] $states  State codes.
		 */
		return (string) apply_filters( 'bh_state_blocked_message', $message, $states );
	}
}
