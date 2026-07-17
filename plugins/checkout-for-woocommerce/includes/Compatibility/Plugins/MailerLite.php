<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class MailerLite extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'woo_ml_load' );
	}

	public function run() {
		$checkout_position      = MailerLiteSettings::getInstance()->getMlOption( 'checkout_position', 'checkout_billing' );
		$checkout_position_hook = 'woocommerce_' . $checkout_position;

		if ( 'checkout_billing_email' === $checkout_position ) {
			remove_filter( 'woocommerce_checkout_fields', 'woo_ml_billing_checkout_fields', PHP_INT_MAX );
			add_action( 'cfw_after_customer_info_account_details', 'woo_ml_checkout_label' );
		}

		if ( 'checkout_after_customer_details' === $checkout_position ) {
			remove_action( $checkout_position_hook, 'woo_ml_checkout_label', 20 );
			add_action( 'cfw_after_customer_info_account_details', 'woo_ml_checkout_label' );
		}

		if ( 'checkout_shipping' === $checkout_position ) {
			remove_action( $checkout_position_hook, 'woo_ml_checkout_label', 20 );
			add_action( 'cfw_checkout_customer_info_tab', 'woo_ml_checkout_label', 55 );
		}

		if ( 'checkout_billing' === $checkout_position ) {
			remove_action( $checkout_position_hook, 'woo_ml_checkout_label', 20 );
			add_action( 'cfw_checkout_payment_method_tab', 'woo_ml_checkout_label', 22 );
		}
	}
}
