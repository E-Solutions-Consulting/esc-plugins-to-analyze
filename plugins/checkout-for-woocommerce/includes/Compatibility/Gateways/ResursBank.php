<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class ResursBank extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'RB_WOO_VERSION' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter( 'cfw_payment_method_li_class', array( $this, 'put_payment_method_class_at_end' ) );

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Resurs Bank',
					GatewaySupport::NOT_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	/**
	 * The ResursBank plugin assumes that the payment method class is at the end of the classname. This function ensures that that is the case.
	 *
	 * @param string $class_string The class string to check.
	 *
	 * @return string
	 */
	public function put_payment_method_class_at_end( string $class_string ): string {
		if ( preg_match( '/payment_method_[^\s]+$/', $class_string ) ) {
			return $class_string;
		}

		$classes              = explode( ' ', $class_string );
		$ordered_classes      = array();
		$payment_method_class = '';

		foreach ( $classes as $class ) {
			if ( ! preg_match( '/(payment_method_[^\s]+)/', $class ) ) {
				$ordered_classes[] = $class;
				continue;
			}

			$payment_method_class = $class;
		}

		$ordered_classes[] = $payment_method_class;

		return join( ' ', array_filter( $ordered_classes ) );
	}
}
