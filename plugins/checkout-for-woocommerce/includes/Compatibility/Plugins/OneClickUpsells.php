<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class OneClickUpsells extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'GB_OCU_VER' );
	}

	public function run() {
		$this->add_ocu_checkout_buttons();
	}

	public function add_ocu_checkout_buttons() {
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( ! empty( $gateways['ocustripe'] ) ) {
			if ( 'no' !== $gateways['ocustripe']->apple_pay_enabled ) {
				add_action( 'cfw_checkout_before_customer_info_tab', 'gb_ocu_stripe_apple_pay_display_button', 5 );
			}
		}

		if ( ! empty( $gateways['ocupaypal'] ) ) {
			if ( 'top' === $gateways['ocupaypal']->checkout_page || 'both' === $gateways['ocupaypal']->checkout_page ) {
				add_action( 'cfw_checkout_before_customer_info_tab', array( $this, 'gb_ocu_paypal_display_button' ), 5 );
			}
		}
	}

	public function gb_ocu_paypal_display_button() {
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if (
			! empty( $gateways['ocupaypal'] ) &&
			method_exists( $gateways['ocupaypal'], 'paypal_display_button' )
		) {
			$checkout_page = $gateways['ocupaypal']->checkout_page;

			if ( 'top' === $checkout_page || 'both' === $checkout_page ) {
				echo '<div class="ocu-woocommerce-info" style="text-align: center;">';

				$gateways['ocupaypal']->paypal_display_button();

				echo '</div>';
			}
		}
	}
}
