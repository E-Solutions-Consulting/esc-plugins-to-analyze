<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class WebToffeeStripe extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'EH_STRIPE_VERSION' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Stripe Payment Plugin for WooCommerce (WebToffee)',
					GatewaySupport::FULLY_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	public function run() {
		$this->maybe_rehook_gpay();
		$this->maybe_rehook_applepay();
	}

	public function maybe_rehook_gpay() {
		$instance = cfw_get_hook_instance_object( 'woocommerce_before_checkout_form', 'eh_add_payment_request_button' );

		if ( ! $instance ) {
			return;
		}

		// Remove theirs
		remove_action( 'woocommerce_before_checkout_form', array( $instance, 'eh_add_payment_request_button' ) );
		remove_action( 'woocommerce_before_checkout_form', array( $instance, 'display_payment_request_button_separator' ) );

		// Add ours
		add_action( 'cfw_payment_request_buttons', array( $instance, 'eh_add_payment_request_button' ) );
	}

	public function maybe_rehook_applepay() {
		$instance = cfw_get_hook_instance_object( 'woocommerce_before_checkout_form', 'add_apple_pay_button' );

		if ( ! $instance ) {
			return;
		}

		// Remove theirs
		remove_action( 'woocommerce_before_checkout_form', array( $instance, 'add_apple_pay_button' ) );
		remove_action( 'woocommerce_before_checkout_form', array( $instance, 'display_payment_request_button_separator' ) );

		// Add ours
		add_action( 'cfw_payment_request_buttons', array( $instance, 'add_apple_pay_button' ) );
	}
}
