<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WooCommerceMemberships extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'wc_memberships' );
	}

	public function pre_init() {
		add_action( 'wc_memberships_discounts_enable_price_html_adjustments', array( $this, 'queue_removal' ) );
	}

	public function queue_removal() {
		add_filter( 'woocommerce_get_item_data', array( $this, 'remove' ), 1 );
	}

	public function remove( $value ) {
		if ( ! $this->is_available() ) {
			return $value;
		}

		$memberships = wc_memberships();

		if ( ! method_exists( $memberships, 'get_member_discounts_instance' ) ) {
			return $value;
		}

		$instance = $memberships->get_member_discounts_instance();
		$callback = array( $instance, 'display_cart_purchasing_discount_message' );

		remove_filter( 'woocommerce_get_item_data', $callback );

		return $value;
	}
}
