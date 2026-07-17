<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class IgniteWooGiftCertificatesPro extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( 'Ignite_Gift_Certs' );
	}

	public function run() {
		// Allow discount calculations to run more than once
		add_action( 'ignitewoo_gift_cert_remove_after_calculate_totals', '__return_false' );
	}
}
