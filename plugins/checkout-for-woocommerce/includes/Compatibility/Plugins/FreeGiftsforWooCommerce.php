<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class FreeGiftsforWooCommerce extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'FGF_PLUGIN_FILE' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_action( 'cfw_cart_updated', array( $this, 'update_cart_gifts' ) );

		/**
		 * Whether to prevent redirecting during add to cart when Free Gifts for WooComemrce is active
		 *
		 * @param bool $prevent_redirect Whether to prevent redirecting during add to cart
		 * @return bool
		 * @since 10.1.0
		 */
		if ( apply_filters( 'cfw_compatibility_free_gifts_for_woocommerce_prevent_redirect', false ) ) {
			return;
		}

		add_action( 'wp', array( $this, 'prevent_redirect' ), 0 );
	}

	public function run() {
		if ( ! method_exists( '\\FGF_Gift_Products_Handler', 'get_gift_display_checkout_page_current_location' ) ) {
			return;
		}

		$customize_hook = \FGF_Gift_Products_Handler::get_gift_display_checkout_page_current_location();

		if ( 'woocommerce_checkout_order_review' === $customize_hook['hook'] ) {
			// Hook for the gift display in the checkout page.
			remove_action(
				'woocommerce_checkout_order_review',
				array(
					'\\FGF_Gift_Products_Handler',
					'render_gift_products_checkout_page',
				),
				$customize_hook['priority']
			);

			add_action(
				'cfw_checkout_main_container_start',
				array(
					'\\FGF_Gift_Products_Handler',
					'render_gift_products_checkout_page',
				)
			);
		}
	}

	public function prevent_redirect() {
		// Fix for Free Gifts for WooCommerce that causes add to cart output to be hijacked with side cart
		remove_action( 'wp', array( 'FGF_Gift_Products_Handler', 'add_to_cart_automatic_gift_product' ) );
	}

	public function update_cart_gifts() {
		try {
			\FGF_Rule_Handler::reset();
		} catch ( \Exception $e ) {
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'checkout-wc' ) );
		}

		\FGF_Gift_Products_Handler::automatic_gift_product( false );
		\FGF_Gift_Products_Handler::bogo_gift_product( false );
		\FGF_Gift_Products_Handler::remove_gift_products();
	}
}
