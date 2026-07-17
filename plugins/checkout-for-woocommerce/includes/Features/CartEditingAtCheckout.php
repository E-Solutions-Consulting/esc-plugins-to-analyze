<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Interfaces\ItemInterface;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * Cart editing at checkout feature
 *
 * @link checkoutwc.com
 * @since 5.0.0
 */
class CartEditingAtCheckout extends FeaturesAbstract {
	protected function run_if_cfw_is_enabled() {
		add_action( 'cfw_update_checkout_after_customer_save', array( $this, 'handle_update_checkout' ), 10, 1 );
	}

	/**
	 * Handle update_checkout
	 *
	 * @param string $raw_post_data The post data.
	 */
	public function handle_update_checkout( string $raw_post_data ) {
		parse_str( $raw_post_data, $post_data );

		if ( ! isset( $post_data['cart'] ) ) {
			return;
		}

		cfw_update_cart( $post_data['cart'], false, 'checkout' );

		// Check cart has contents.
		if ( WC()->cart->is_empty() && ! is_customize_preview() && cfw_apply_filters( 'woocommerce_checkout_redirect_empty_cart', true ) ) {
			/**
			 * Filters whether to suppress checkout is not available message
			 * when editing cart results in empty cart
			 *
			 * @since 3.14.0
			 *
			 * @param bool $supress_notice Whether to suppress the message
			 */
			if ( false === apply_filters( 'cfw_cart_edit_redirect_suppress_notice', false ) ) {
				wc_add_notice( __( 'Checkout is not available whilst your cart is empty.', 'checkout-wc' ), 'notice' );
			}

			// Allow shortcodes to be used in empty cart redirect URL field
			// This is necessary so that WPML (etc) can swap in a locale specific URL
			$cart_editing_redirect_url = do_shortcode( $this->settings_getter->get_setting( 'cart_edit_empty_cart_redirect' ) );

			$redirect = empty( $cart_editing_redirect_url ) ? wc_get_cart_url() : $cart_editing_redirect_url;

			add_filter(
				'cfw_update_checkout_redirect',
				function () use ( $redirect ) {
					return $redirect;
				}
			);
		}
	}
}
