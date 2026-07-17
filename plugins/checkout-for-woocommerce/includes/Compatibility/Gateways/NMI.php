<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class NMI extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WC_NMI_VERSION' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'WP NMI Gateway PCI for WooCommerce',
					GatewaySupport::NOT_SUPPORTED,
					'Gateway does not support Express Checkout. Gateway has known issues with CheckoutWC. We recommend finding alternative gateway.'
				);

				return $gateways;
			}
		);
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'NMI',
			'params' => array(),
		);

		return $compatibility;
	}
}
