<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class RouteApp extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'ROUTEAPP_VERSION' );
	}

	public function run() {
		add_action( 'cfw_checkout_cart_summary', array( $this, 'add_widget' ), 22 );
	}

	public function add_widget() {
		echo do_shortcode( '[route]' );
	}
}
