<?php

namespace Objectiv\Plugins\Checkout\Shipping;

use Automattic\WooCommerce\Enums\ProductTaxStatus;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use WC_Shipping_Local_Pickup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout for WooCommerce Local Pickup Shipping Method.
 *
 * A local pickup shipping method that inherits from WC_Shipping_Local_Pickup
 * but doesn't support shipping zones for simpler configuration.
 *
 * @class       CFW_Shipping_Local_Pickup
 * @extends     WC_Shipping_Local_Pickup
 * @package     CheckoutWC\Shipping
 */
class CFW_Shipping_Local_Pickup extends \WC_Shipping_Local_Pickup {

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID (ignored for non-zone methods).
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = 'cfw_local_pickup';
		$this->instance_id        = 0; // Always 0 for non-zone methods
		$this->method_title       = __( 'Local Pickup', 'checkout-wc' );
		$this->method_description = __( 'Allow customers to pick up orders themselves. This version does not require shipping zones.', 'checkout-wc' );
		$this->supports           = array();

		// Call parent init method
		$this->init();
	}

	/**
	 * Initialize local pickup.
	 */
	public function init() {
		// Define user set variables.
		$this->title      = __( 'Local Pickup', 'checkout-wc' );
		$this->tax_status = ProductTaxStatus::NONE;
		$this->cost       = '';
		$this->enabled    = $this->is_enabled();
	}

	/**
	 * Init form fields for non-zone version.
	 */
	public function init_form_fields() {
		// Silence is golden
	}

	/**
	 * Calculate local pickup shipping.
	 *
	 * @param array $package Package information.
	 */
	public function calculate_shipping( $package = array() ) {
		$this->add_rate(
			array(
				'id'       => $this->id,
				'label'    => $this->title,
				'cost'     => $this->cost,
				'taxes'    => false,   // default values (taxes will be automatically calculated)
				'calc_tax' => 'per_order',
			)
		);
	}

	/**
	 * Check if this shipping method is available.
	 *
	 * @param array $package Package information.
	 * @return bool
	 */
	public function is_available( $package ): bool {
		return wc_shipping_enabled() && $this->is_enabled();
	}

	public function is_enabled(): bool {
		return PlanManager::can_access_feature( 'enable_pickup', 'plus' );
	}
}
