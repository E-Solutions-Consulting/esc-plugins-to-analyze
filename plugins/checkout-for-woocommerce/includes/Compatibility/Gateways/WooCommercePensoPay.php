<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class WooCommercePensoPay extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'wc_pensopay_woocommerce_inactive_notice' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'WooCommerce PensoPay',
					GatewaySupport::NOT_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	public function run() {
		WC()->payment_gateways();

		$WooCommercePensoPayMolliePay = cfw_get_hook_instance_object( 'woocommerce_checkout_before_customer_details', 'insert_woocommerce_pensopay_mobilepay_checkout', 10 );

		if ( ! empty( $WooCommercePensoPayMolliePay ) ) {
			remove_action( 'woocommerce_checkout_before_customer_details', array( $WooCommercePensoPayMolliePay, 'insert_woocommerce_pensopay_mobilepay_checkout' ), 10 );

			if ( method_exists( $WooCommercePensoPayMolliePay, 'is_gateway_available' ) && $WooCommercePensoPayMolliePay->is_gateway_available() ) {
				add_action( 'cfw_payment_request_buttons', array( $WooCommercePensoPayMolliePay, 'insert_woocommerce_pensopay_mobilepay_checkout' ), 10 );
			}
		}
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'WooCommercePensoPay',
			'params' => array(),
		);

		return $compatibility;
	}
}
