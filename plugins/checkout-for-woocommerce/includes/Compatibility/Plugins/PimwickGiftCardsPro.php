<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class PimwickGiftCardsPro extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'PWGC_VERSION' );
	}

	public function run() {
		add_filter( 'cfw_checkout_main_container_classes', array( $this, 'add_shim_class' ) );
		add_filter( 'pre_option_pwgc_redeem_checkout_location', array( $this, 'force_location' ) );
	}

	public function add_shim_class( $classes ) {
		return "$classes woocommerce-checkout-review-order";
	}

	public function force_location( $location ) {
		return 'before_checkout_form';
	}
}
