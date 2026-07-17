<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Exception;
use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;
use PaymentPlugins\WooCommerce\PPCP\Main;
use PaymentPlugins\WooCommerce\PPCP\Payments\PaymentGateways;

class PaymentPluginsPayPal extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\PaymentPlugins\WooCommerce\PPCP\PluginValidation' );
	}

	public function pre_init() {
		// Treat ppc-create-order AJAX as a checkout page so cfw_is_checkout() returns true.
		// This is required for local pickup field suppression to work during PayPal express checkout.
		add_filter( 'cfw_is_checkout', [ $this, 'is_checkout' ] );

		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Payment Plugins for PayPal WooCommerce',
					GatewaySupport::FULLY_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	public function is_checkout( bool $is_checkout ): bool {
		if ( isset( $_GET['wc-ajax'] ) && 'ppc-create-order' === $_GET['wc-ajax'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return $is_checkout;
	}

	/**
	 * Run the compatibility code
	 */
	public function run() {
		add_filter( 'wc_ppcp_add_payment_method_data', [ $this, 'add_payment_method_data' ], 10 );
	}

	public function add_payment_method_data( $data ): array {
		if ( ! is_cfw_page() ) {
			return $data;
		}

		if ( ! isset( $data['buttons'] ) ) {
			return $data;
		}

		foreach ( $data['buttons'] as $index => $button ) {
			$data['buttons'][ $index ]['height'] = '42';
		}

		return $data;
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = [
			'class'  => 'PaymentPluginsPayPal',
			'params' => [],
		];

		return $compatibility;
	}
}
