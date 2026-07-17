<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class WooSquarePro extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WOO_SQUARE_PLUGIN_PATH' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'WooSquare Pro',
					GatewaySupport::NOT_SUPPORTED,
					'Switch to <a class="text-blue-600 underline" target="_blank" href="https://wordpress.org/plugins/woocommerce-square/">WooCommerce Square</a>',
					new AlternativePlugin(
						'woocommerce-square',
						'WooCommerce Square'
					)
				);

				return $gateways;
			}
		);
	}

	public function run() {
		$this->reorder_payment_tab();
	}

	public function reorder_payment_tab() {
		remove_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_methods', 10 );
		remove_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_tab_content_billing_address', 20 );

		add_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_tab_content_billing_address', 10 );
		add_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_methods', 20 );
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'WooSquarePro',
			'params' => array(),
		);

		return $compatibility;
	}
}
