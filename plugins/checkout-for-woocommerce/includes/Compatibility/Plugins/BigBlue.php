<?php
/**
 * Big Blue Compatibility
 *
 * @package Objectiv\Plugins\Checkout\Compatibility
 */

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class BigBlue extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'BIGBLUE_VERSION' );
	}

	public function run() {
		$instance = cfw_get_hook_instance_object( 'woocommerce_review_order_before_payment', 'inject_preact_root_div' );

		if ( ! $instance ) {
			return;
		}

		add_action( 'cfw_checkout_after_shipping_methods', array( $instance, 'inject_preact_root_div' ), 9 );
	}
}
