<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class AfterPayKrokedil extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'ARVATO_CHECKOUT_LIVE' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'AfterPay for WooCommerce',
					GatewaySupport::NOT_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	public function run() {
		$this->add_thickbox();
		$this->customer_precheck();
	}

	public function add_thickbox() {
		if ( cfw_is_checkout() ) {
			add_thickbox();
		}
	}

	public function customer_precheck() {
		global $wc_afterpay_pre_check_customer;

		add_action(
			'cfw_checkout_before_payment_method_terms_checkbox',
			array(
				$wc_afterpay_pre_check_customer,
				'display_pre_check_form',
			)
		);
	}
}
