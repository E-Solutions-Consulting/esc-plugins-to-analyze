<?php

namespace Objectiv\Plugins\Checkout\Action;

use Objectiv\Plugins\Checkout\Managers\AssetManager;
use Exception;

/**
 * @link checkoutwc.com
 * @since 5.4.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class AddToCartAction extends CFWAction {

	public function __construct() {
		parent::__construct( 'cfw_add_to_cart' );
	}

	/**
	 * @throws Exception The exception.
	 */
	public function action() {
		/**
		 * How does all of this work?
		 *
		 * WC_Form_Handler::add_to_cart_action detects all requests with add-to-cart=X and processes the add to cart
		 * The below actions we perform are to 1. clean things up and return updated data
		 * and 2. make sure Woo treats it like their native AJAX add to cart handler
		 */
		$result     = false;
		$redirect   = false;
		$product_id = cfw_apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['add-to-cart'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $product_id && empty( wc_get_notices( 'error' ) ) ) {
			cfw_do_action( 'woocommerce_ajax_added_to_cart', $product_id );
			$result = true;
		}

		$product_id = sanitize_text_field( wp_unslash( $_REQUEST['add-to-cart'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $result ) {
			add_filter( 'cfw_get_data_clear_notices', '__return_false' );
			$redirect = cfw_apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id );
		}

		$this->out(
			array(
				'result'    => $result,
				'cart_hash' => WC()->cart->get_cart_hash(),
				'data'      => AssetManager::get_data(),

				/**
				 * Filter the add to cart redirect URL.
				 *
				 * @since 7.3.0
				 */
				'redirect'  => apply_filters( 'cfw_add_to_cart_redirect', $redirect, $product_id ),
			)
		);
	}
}
