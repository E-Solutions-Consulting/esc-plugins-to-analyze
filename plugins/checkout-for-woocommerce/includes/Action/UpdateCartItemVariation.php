<?php

namespace Objectiv\Plugins\Checkout\Action;

use Objectiv\Plugins\Checkout\Managers\AssetManager;

/**
 * @link checkoutwc.com
 * @since 5.4.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class UpdateCartItemVariation extends CFWAction {
	public function __construct() {
		parent::__construct( 'update_cart_item_variation' );
	}

	public function action() {
		if ( ! isset( $_POST['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->out(
				array(
					'result' => false,
				)
			);
		}

		$key               = sanitize_text_field( wp_unslash( $_POST['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$variation_id      = sanitize_text_field( wp_unslash( $_POST['variation_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing
		$variation_product = wc_get_product( $variation_id );

		if ( ! $variation_product ) {
			cfw_debug_log( 'UpdateCartItemVariation: Variation product not found: ' . var_export( $_POST, true ) ); // phpcs:ignore

			$this->out(
				array(
					'result' => false,
				)
			);
		}

		// Update WooCommerce cart item variation
		WC()->cart->cart_contents[ $key ]['variation_id'] = $variation_id;

		// Update WooCommerce cart item variation attributes
		WC()->cart->cart_contents[ $key ]['variation'] = wc_get_product_variation_attributes( $variation_id );

		// Update cart item hash
		WC()->cart->cart_contents[ $key ]['data_hash'] = wc_get_cart_item_data_hash( $variation_product );

		// Refresh cart object product from new variation
		WC()->cart->cart_contents[ $key ]['data'] = $variation_product;

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$this->out(
			array(
				'result' => true,
				'data'   => AssetManager::get_data(),
			)
		);
	}
}
