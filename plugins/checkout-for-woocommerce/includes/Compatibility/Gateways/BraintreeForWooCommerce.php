<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class BraintreeForWooCommerce extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'BFWC_PLUGIN_NAME' ) || defined( 'WC_BRAINTREE_PLUGIN_NAME' );
	}

	public function run() {
		remove_action( 'woocommerce_checkout_before_customer_details', 'wc_braintree_banner_checkout_template' );
		add_action( 'cfw_payment_request_buttons', array( $this, 'render_banner_buttons' ) );
		add_filter( 'wc_braintree_paypal_button_options', array( $this, 'force_pp_button_height' ) );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
			$gateways[] = new DetectedPaymentGateway(
				'Payment Plugins Braintree For WooCommerce',
				GatewaySupport::FULLY_SUPPORTED
			);

			return $gateways;
			}
		);
	}

	public function render_banner_buttons() {
		$gateways = array();
		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $id => $gateway ) {
			if ( $gateway->supports( 'wc_braintree_banner_checkout' ) && $gateway->banner_checkout_enabled() ) {
				$gateways[ $id ] = $gateway;
			}
		}

		if ( count( $gateways ) > 0 ) {
			foreach ( $gateways as $gateway ) :?>
				<div class="wc-braintree-banner-gateway wc_braintree_banner_gateway_<?php echo esc_attr( $gateway->id ); ?>"><?php $gateway->banner_fields(); ?></div>
				<?php
			endforeach;
		}
	}

	public function force_pp_button_height( $options ) {
		if ( ! is_cfw_page() ) {
			return $options;
		}

		$options['height'] = 42;

		return $options;
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'BraintreeForWooCommerce',
			'params' => array(),
		);

		return $compatibility;
	}
}
