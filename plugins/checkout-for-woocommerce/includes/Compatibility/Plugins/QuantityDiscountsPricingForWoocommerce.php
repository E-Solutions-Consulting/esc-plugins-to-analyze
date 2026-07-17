<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class QuantityDiscountsPricingForWoocommerce extends CompatibilityAbstract {

	public function is_available(): bool {
		return class_exists( 'PlugfyQDP_Main_Class_Alpha' );
	}

	public function run_on_wp_loaded() {
		add_filter( 'cfw_cart_item_discount', array( $this, 'show_quantity_discount_on_cart_item' ), 10, 2 );
	}

	public function show_quantity_discount_on_cart_item( $price_html, $cart_item ) {
		if ( ! isset( $cart_item['plugify_discount'] ) || 'valid' !== $cart_item['plugify_discount'] ) {

			return $price_html;
		}

		$product = $cart_item['data'];

		$original_price = (float) $product->get_regular_price();
		$sale_price     = (float) $product->get_price();

		if ( $sale_price < $original_price ) {
			if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) && 'yes' === get_option( 'woocommerce_calc_taxes' ) ) {
				$original_price = wc_get_price_including_tax( $product, array( 'price' => $original_price ) );
				$sale_price     = wc_get_price_including_tax( $product );
			}

			return wc_format_sale_price( $original_price, $sale_price );
		}

		return $price_html;
	}
}
