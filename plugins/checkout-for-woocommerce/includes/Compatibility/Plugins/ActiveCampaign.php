<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class ActiveCampaign extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'activecampaign_for_woocommerce_build_container' );
	}

	public function run() {
		$ActiveCampaign_Public = cfw_get_hook_instance_object( 'woocommerce_after_checkout_billing_form', 'handle_woocommerce_checkout_form' );

		if ( ! empty( $ActiveCampaign_Public ) ) {
			/**
			 * Filters hook to render Active Campaign checkbox output
			 *
			 * @since 2.0.0
			 *
			 * @param string $render_on The action hook to render on
			 */
			$render_on = apply_filters( 'cfw_active_campaign_checkbox_hook', 'cfw_checkout_before_payment_method_tab_nav' );

			add_action( $render_on, array( $ActiveCampaign_Public, 'handle_woocommerce_checkout_form' ) );
		}
	}
}
