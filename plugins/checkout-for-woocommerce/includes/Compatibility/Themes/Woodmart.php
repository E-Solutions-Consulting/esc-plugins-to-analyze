<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Woodmart extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WOODMART_THEME_DIR' );
	}

	public function run() {
		add_filter(
			'woocommerce_review_order_before_cart_contents',
			function () {
				remove_filter( 'woocommerce_checkout_cart_item_visible', '__return_false' );
			},
			1000
		);

		if ( ! function_exists( 'woodmart_lazy_loading_deinit' ) ) {
			return;
		}

		woodmart_lazy_loading_deinit( true );
	}
}
