<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class Mercado extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WC_MERCADOPAGO_BASENAME' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Mercado Pago payments for WooCommerce',
					GatewaySupport::NOT_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'Mercado',
			'params' => array(),
		);

		return $compatibility;
	}
}
