<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WooCommercePhoneOrders extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\IGN_Manual_Phone_Orders' );
	}

	public function pre_init() {
		add_filter(
			'cfw_is_checkout',
			function ( $is_checkout ) {
				global $post;

				if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'woocommerce_manual_phone_order' ) ) {
					return false;
				}

				return $is_checkout;
			}
		);
	}
}
