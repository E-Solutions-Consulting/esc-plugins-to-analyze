<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Medizin extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'MEDIZIN_THEME_NAME' );
	}

	public function run() {
		remove_action( 'woocommerce_checkout_after_order_review', 'woocommerce_checkout_payment', 20 );
	}
}
