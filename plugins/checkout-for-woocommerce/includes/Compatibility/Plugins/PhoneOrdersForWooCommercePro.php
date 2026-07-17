<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class PhoneOrdersForWooCommercePro extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WC_PHONE_ORDERS_BASENAME' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		if ( empty( $_POST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( 'phone-orders-for-woocommerce' !== $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		define( 'CFW_DISABLE_TEMPLATES', true );
	}
}
