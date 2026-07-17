<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class Oppcw extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'woocommerce_oppcw_add_errors' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_is_checkout',
			function ( $is_checkout ) {
				global $post;

				if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'woocommerce_oppcw' ) ) {
					return false;
				}

				return $is_checkout;
			}
		);

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Open Payment Platform',
					GatewaySupport::NOT_SUPPORTED
				);

				return $gateways;
			}
		);
	}
}
