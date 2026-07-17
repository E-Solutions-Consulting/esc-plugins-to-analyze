<?php
/**
 * One-Click Upsell Gateway Interface
 *
 * Defines the contract that payment gateways must implement to support
 * one-click upsells. Mirrors CartFlows' gateway pattern.
 *
 * @package Objectiv\Plugins\Checkout\Features\OneClick
 */

namespace Objectiv\Plugins\Checkout\Features\OneClick;

/**
 * Gateway Interface
 *
 * @since 10.4.0
 */
interface GatewayInterface {
	/**
	 * Get singleton instance
	 *
	 * Required by CartFlows pattern. Gateway must implement singleton pattern.
	 *
	 * @since 10.4.0
	 * @return self
	 */
	public static function get_instance();

	/**
	 * Process offer payment
	 *
	 * Charges the stored payment method for the offer amount.
	 *
	 * @since 10.4.0
	 *
	 * @param \WC_Order $order Parent order with stored payment method.
	 * @param array     $product Product data array matching CartFlows format:
	 *                           - id: Product ID
	 *                           - variation_id: Variation ID (if applicable)
	 *                           - qty: Quantity
	 *                           - price: Offer price
	 *                           - name: Product name
	 *                           - step_id: Bump ID
	 *                           - action: 'cfw_offer_accepted'
	 *                           - shipping_fee: Shipping fee amount
	 *                           - args: WC product args
	 *
	 * @return bool True if payment successful, false otherwise.
	 */
	public function process_offer_payment( $order, $product );

	/**
	 * Whether gateway supports API refunds
	 *
	 * @since 10.4.0
	 * @return bool True if gateway supports API refunds, false otherwise.
	 */
	public function is_api_refund();

	/**
	 * Process offer refund
	 *
	 * Refunds an accepted offer via the payment gateway API.
	 *
	 * @since 10.4.0
	 *
	 * @param \WC_Order $order Parent order.
	 * @param array     $offer_data Refund data:
	 *                              - transaction_id: Charge ID from gateway
	 *                              - refund_amount: Amount to refund
	 *                              - bump_id: Order bump ID
	 *
	 * @return string|false Transaction/refund ID on success, false on failure.
	 */
	public function process_offer_refund( $order, $offer_data );
}
