<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WPLoyalty extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WLR_PLUGIN_VERSION' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		$wp_loyalty_main = cfw_get_hook_instance_object( 'woocommerce_before_cart', 'updateFreeProduct' );

		if ( ! $wp_loyalty_main ) {
			return;
		}

		add_action( 'wp', array( $wp_loyalty_main, 'updateFreeProduct' ), 0 );
	}
}
