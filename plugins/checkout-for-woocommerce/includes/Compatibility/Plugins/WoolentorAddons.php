<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WoolentorAddons extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WOOLENTOR_VERSION' );
	}

	public function run() {
		$instance = \WooLentor_Page_Action::instance();

		if ( ! $instance ) {
			return;
		}

		remove_action( 'woocommerce_cart_item_name', array( $instance, 'add_product_thumbnail' ), 10 );
	}
}
