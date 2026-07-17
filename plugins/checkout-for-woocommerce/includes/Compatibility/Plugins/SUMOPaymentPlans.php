<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class SUMOPaymentPlans extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\SUMO_PP_Order_PaymentPlan' );
	}

	public function run() {
		try {
			add_action( 'cfw_checkout_before_payment_methods', array( \SUMO_PP_Order_PaymentPlan::instance(), 'render_plan_selector' ) );
		} catch ( Exception $e ) {
			wc_get_logger()->error( 'CheckoutWC: Failed to load SUMO plan selector: ' . $e->getMessage(), array( 'source' => 'checkout-wc' ) );
		}
	}
}
