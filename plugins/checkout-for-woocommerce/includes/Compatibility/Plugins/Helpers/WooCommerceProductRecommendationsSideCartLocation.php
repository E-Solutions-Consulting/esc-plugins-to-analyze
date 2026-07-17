<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins\Helpers;

class WooCommerceProductRecommendationsSideCartLocation extends \WC_PRL_Location {
	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->id        = 'cfw_side_cart';
		$this->title     = __( 'CheckoutWC Side Cart', 'woocommerce-product-recommendations' );
		$this->cacheable = false;

		$this->defaults = array(
			'engine_type' => array( 'cart' ),
			'priority'    => 10,
			'args_number' => 0,
		);

		parent::__construct();
	}

	/**
	 * Check if the current location page is active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return true;
	}

	/**
	 * Setup all supported hooks based on the location id.
	 *
	 * @return void
	 */
	protected function setup_hooks() {
		$this->hooks = array();

		$this->hooks['cfw_after_side_cart_items_table'] = array(
			'id'       => 'cfw_after_side_cart_items_table',
			'label'    => __( 'CheckoutWC: After Side Cart Items', 'checkout-wc' ),
			'priority' => 10,
		);

		$this->hooks['cfw_side_cart_footer_start'] = array(
			'id'       => 'cfw_side_cart_footer_start',
			'label'    => __( 'CheckoutWC: Before Side Cart Footer Above Promo Code', 'checkout-wc' ),
			'priority' => 10,
		);

		$this->hooks['cfw_before_side_cart_totals'] = array(
			'id'       => 'cfw_before_side_cart_totals',
			'label'    => __( 'CheckoutWC: Before Side Cart Totals', 'checkout-wc' ),
			'priority' => 10,
		);

		$this->hooks['cfw_after_side_cart_totals'] = array(
			'id'       => 'cfw_after_side_cart_totals',
			'label'    => __( 'CheckoutWC: After Side Cart Totals', 'checkout-wc' ),
			'priority' => 10,
		);

		$this->hooks['cfw_after_side_cart_proceed_to_checkout_button'] = array(
			'id'       => 'cfw_after_side_cart_proceed_to_checkout_button',
			'label'    => __( 'CheckoutWC: After Side Cart Buttons', 'checkout-wc' ),
			'priority' => 10,
		);
	}
}
