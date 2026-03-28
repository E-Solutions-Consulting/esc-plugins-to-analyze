<?php
/**
 * AH Cart
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Cart' ) ) {

class AH_Cart {

    public function __construct() {
        add_filter('woocommerce_is_sold_individually', [ $this, 'force_individual_products_cart'], 10, 2);
        /**
		 *	Remove Notice about success add to cart product
		 */
		add_filter( 'woocommerce_notice_types', [$this, 'woocommerce_notice_types'], 100);

		add_filter( 'woocommerce_add_notice', [$this, 'remove_subscription_messages'], 100 );

        /*
		*	Rules Add To Cart
		*/
		add_filter( 'woocommerce_add_to_cart_validation', [$this, 'strict_cart_restrictions'], 30, 6);

		add_filter( 'woocommerce_product_single_add_to_cart_text', [$this, 'change_product_single_add_to_cart_text'], 10, 2);

    }

	function remove_subscription_messages($message){
		if ( strpos( $message, 'A subscription has been removed from your cart.' ) !== false ) {
			$message	=	'';
		}
		return $message;
	}

	function force_individual_products_cart($sold_individually, $product){
		return true;
	}

    /*
	*	removing Cart added message
	*/
	function woocommerce_notice_types($types){
		$types=['error', 'notice'];
		return $types;
	}

    /*
	*	Rules
	*	1: Only 1 weight-loss product in the entire cart
	*	2: For SUBSCRIPTIONS (all)
	*	3: For NORMAL products (avoid exact duplicates)
	*/
	function strict_cart_restrictions($passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array()) {
		$product = wc_get_product($product_id);
		$is_weight_loss = has_term('weight-loss', 'product_cat', $product_id);
		// Rule 1
		if ($is_weight_loss) {
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				if (has_term('weight-loss', 'product_cat', $cart_item['product_id'])) {
					WC()->cart->remove_cart_item($cart_item_key);
					break;
				}
			}
		}
		// Rule 2
		if ($product->is_type('subscription') || $product->is_type('variable-subscription')) {
			$quantity = 1;
			
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				$cart_product = wc_get_product($cart_item['product_id']);
				if ($cart_item['product_id'] == $product_id) {
					WC()->cart->remove_cart_item($cart_item_key);
					break;
				}
			}
		}
		// Rule 3
		else {
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				if ($cart_item['product_id'] == $product_id && $cart_item['variation_id'] == $variation_id) {
					WC()->cart->set_quantity($cart_item_key, $quantity);
					wp_safe_redirect(wc_get_checkout_url());
					exit;
				}
			}
		}

		return $passed;
	}

    function change_product_single_add_to_cart_text( $text, $product ) {
        if (has_term(['protocol', 'weight-loss'], 'product_cat', $product->get_id())) {
			$text	=	'SEE IF YOU QUALIFY';
		}
        return $text;
    }

}

/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new AH_Cart();
});

}