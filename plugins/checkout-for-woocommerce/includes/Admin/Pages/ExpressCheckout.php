<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * Express Checkout admin options
 *
 * @link checkoutwc.com
 * @since 9.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class ExpressCheckout extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Express Checkout', 'checkout-wc' ), 'cfw_manage_express_checkout', 'express-checkout' );
	}

	public function output() {
		?>
		<div id="cfw-admin-pages-express-checkout"></div>
		<?php
	}

	/**
	 * Enqueues the necessary scripts for the current page.
	 *
	 * @return void
	 */
	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$this->set_script_data(
			array(
				'settings'             => array(
					'disable_express_checkout' => SettingsManager::instance()->get_setting( 'disable_express_checkout' ) === 'yes',
				),
				'woocommerce_settings' => array(),
				'params'               => array(
					/**
					 * Filter detected gateways
					 *
					 * @since 9.0.0
					 * @param array $gateways
					 */
					'gateways' => apply_filters( 'cfw_detected_gateways', array() ),
				),
				'plan'                 => $this->get_plan_data(),
			)
		);
	}
}
