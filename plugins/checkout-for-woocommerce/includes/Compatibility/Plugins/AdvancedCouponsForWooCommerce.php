<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use ACFWF\Helpers\Plugin_Constants;
use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class AdvancedCouponsForWooCommerce extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'ACFWF' ) && class_exists( '\ACFWF\Helpers\Plugin_Constants' ) && version_compare( Plugin_Constants::VERSION, '4.5.7', '>=' );
	}

	public function run() {
		$checkout = cfw_get_hook_instance_object( 'woocommerce_checkout_order_review', 'display_checkout_tabbed_box', 11 );

		if ( ! $checkout ) {
			return;
		}

		add_action( 'cfw_coupon_module_end', array( $checkout, 'display_checkout_tabbed_box' ), 11 );
	}
}
