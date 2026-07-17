<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class DonationForWooCommerce extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WC_DONATION_URL' );
	}

	public function run() {
		$instance = cfw_get_hook_instance_object( 'woocommerce_review_order_before_payment', 'display_wc_donation_on_checkout' );

		if ( $instance ) {
			remove_action( 'woocommerce_review_order_before_payment', array( $instance, 'display_wc_donation_on_checkout' ), 10, 0 );
			add_action( 'cfw_before_payment_methods_block', array( $instance, 'display_wc_donation_on_checkout' ) );
		}
	}
}
