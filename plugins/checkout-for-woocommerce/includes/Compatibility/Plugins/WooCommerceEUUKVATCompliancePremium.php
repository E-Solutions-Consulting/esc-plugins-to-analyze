<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WooCommerceEUUKVATCompliancePremium extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'WooCommerce_EU_VAT_Compliance' );
	}

	public function run() {
		$priority = cfw_apply_filters( 'wc_eu_vat_number_priority', 40 );
		$object   = cfw_get_hook_instance_object( 'woocommerce_checkout_billing', 'vat_number_field', $priority );

		if ( ! $object ) {
			return;
		}

		remove_action( 'woocommerce_checkout_billing', array( $object, 'vat_number_field' ), $priority );
		add_action( 'cfw_checkout_customer_info_tab', array( $object, 'vat_number_field' ), 52 );
	}
}
