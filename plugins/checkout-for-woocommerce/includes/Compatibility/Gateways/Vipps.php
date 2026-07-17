<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class Vipps extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WOO_VIPPS_VERSION' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Pay with Vipps for WooCommerce',
					GatewaySupport::PARTIALLY_SUPPORTED,
					'May fail on required fields because the gateway was designed to show the button at the end of checkout.'
				);

				return $gateways;
			}
		);
	}

	public function run() {
		add_action( 'cfw_payment_request_buttons', array( $this, 'add_vipps_button' ) );
	}

	public function add_vipps_button() {
		$button = do_shortcode( '[woo_vipps_express_checkout_button]' );

		if ( ! empty( $button ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $button;
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
