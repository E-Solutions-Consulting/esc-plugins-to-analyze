<?php
/**
 * One-Click Upsell Order Manager
 *
 * Handles adding accepted offers to orders. Supports two strategies:
 * 1. Add to existing order (default)
 * 2. Create child order
 *
 * @package Objectiv\Plugins\Checkout\Features\OneClick
 */

namespace Objectiv\Plugins\Checkout\Features\OneClick;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * Offer Order Manager Class
 *
 * @since 10.4.0
 */
class OfferOrderManager {
	/**
	 * Singleton instance
	 *
	 * @since 10.4.0
	 * @var OfferOrderManager
	 */
	private static $instance;

	/**
	 * Get singleton instance
	 *
	 * @since 10.4.0
	 * @return OfferOrderManager
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 10.4.0
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Add offer to order
	 *
	 * Routes to appropriate strategy based on offer expiration and filters.
	 *
	 * @since 10.4.0
	 *
	 * @param \WC_Order $order Parent order.
	 * @param array     $product_data Product data from gateway processing.
	 * @return bool|int True/item_id on success (add to existing), child_order_id (create child), false on failure.
	 */
	public function add_offer_to_order( $order, $product_data ) {
		$order_id = $order->get_id();
		$order_key = $order->get_order_key();

		// Check if we should force creating a new order
		$force_new_order = apply_filters( 'cfw_force_offer_creates_new_order', false, $order, $product_data );

		// Check if order session has expired (after 30-minute timeout)
		$is_expired = OrderStatusManager::instance()->is_order_expired_for_offers( $order_id, $order_key );

		// Determine strategy
		$create_child_order = $force_new_order || $is_expired;

		/**
		 * Filter whether to create a child order for the offer
		 *
		 * @param bool $create_child_order Whether to create a child order
		 * @param \WC_Order $order The parent order
		 * @param array $product_data Product data
		 * @since 7.0.0
		 */
		$create_child_order = apply_filters( 'cfw_offer_should_create_child_order', $create_child_order, $order, $product_data );

		// Route to appropriate method
		if ( $create_child_order ) {
			return $this->create_child_order( $order, $product_data, $is_expired );
		}

		return $this->add_to_existing_order( $order, $product_data );
	}

	/**
	 * Add offer to existing order
	 *
	 * Adds product as line item to the parent order and recalculates totals.
	 *
	 * @since 10.4.0
	 *
	 * @param \WC_Order $order Parent order.
	 * @param array     $product_data Product data.
	 * @return int|false Order item ID on success, false on failure.
	 */
	protected function add_to_existing_order( $order, $product_data ) {
		// Get product
		$product = wc_get_product( $product_data['id'] );
		if ( ! $product ) {
			return false;
		}

		// Add product as line item
		$item_id = $order->add_product(
			$product,
			$product_data['qty'],
			isset( $product_data['args'] ) ? $product_data['args'] : array()
		);

		if ( ! $item_id ) {
			return false;
		}

		// Add item metadata
		wc_add_order_item_meta( $item_id, '_cfw_order_bump_id', $product_data['step_id'] );
		wc_add_order_item_meta( $item_id, '_cfw_one_click_upsell', 'yes' );

		// Recalculate totals
		$order->calculate_totals();

		// Add order metadata
		$order->update_meta_data( '_cfw_has_offer', true );
		$order->update_meta_data( '_cfw_offer_bump_' . $product_data['step_id'], true );
		$order->save();

		// Record the offer payment in WooCommerce
		if ( isset( $product_data['price'] ) && $product_data['price'] > 0 ) {
			$payment_amount = $product_data['price'] * $product_data['qty'];
			$order->add_order_note(
				sprintf(
					__( 'Order bump payment of %s received for %s', 'checkout-wc' ),
					wc_price( $payment_amount ),
					$product->get_name()
				)
			);

			// Order is already paid, just save changes
			$order->save();
		}

		/**
		 * Fires after offer added to existing order
		 *
		 * @since 10.4.0
		 *
		 * @param \WC_Order $order Parent order.
		 * @param array     $product_data Product data.
		 * @param int       $item_id Order item ID.
		 */
		do_action( 'cfw_offer_added_to_order', $order, $product_data, $item_id );

		return $item_id;
	}

	/**
	 * Create child order for offer
	 *
	 * Creates a separate order for the offer, linked to the parent order.
	 * Mirrors CartFlows' child order pattern.
	 *
	 * @since 10.4.0
	 *
	 * @param \WC_Order $order Parent order.
	 * @param array     $product_data Product data.
	 * @param bool      $is_expired Whether the offer session has expired.
	 * @return int|false Child order ID on success, false on failure.
	 */
	protected function create_child_order( $order, $product_data, $is_expired = false ) {
		// Create new order with parent relationship
		$child_order = wc_create_order( array(
			'customer_id' => $order->get_customer_id(),
			'parent'      => $order->get_id(),
			'status'      => 'pending',
		) );

		if ( ! $child_order ) {
			return false;
		}

		// Copy customer data from parent
		$child_order->set_billing_address( $order->get_address( 'billing' ) );
		$child_order->set_shipping_address( $order->get_address( 'shipping' ) );
		$child_order->set_payment_method( $order->get_payment_method() );
		$child_order->set_payment_method_title( $order->get_payment_method_title() );
		$child_order->set_currency( $order->get_currency() );

		// Copy customer note if exists
		if ( $order->get_customer_note() ) {
			$child_order->set_customer_note( $order->get_customer_note() );
		}

		// Add product
		$product = wc_get_product( $product_data['id'] );
		if ( ! $product ) {
			$child_order->delete( true );
			return false;
		}

		$child_order->add_product( $product, $product_data['qty'] );

		// Set child order metadata
		$child_order->update_meta_data( '_cfw_is_offer_order', true );
		$child_order->update_meta_data( '_cfw_offer_parent_id', $order->get_id() );
		$child_order->update_meta_data( '_cfw_offer_bump_id', $product_data['step_id'] );

		// Calculate totals
		$child_order->calculate_totals();

		// Set status to processing (since parent was paid, but don't trigger payment_complete hooks)
		$child_order->set_status( 'processing' );

		// Add note explaining this is from an offer
		$note = $is_expired
			? __( 'Order created from post-purchase offer (accepted after timeout).', 'checkout-wc' )
			: __( 'Order created from post-purchase offer.', 'checkout-wc' );
		$child_order->add_order_note( $note );

		$child_order->save();

		// Link parent to child
		$child_orders = $order->get_meta( '_cfw_offer_child_orders', true );
		if ( ! is_array( $child_orders ) ) {
			$child_orders = array();
		}
		$child_orders[] = $child_order->get_id();
		$order->update_meta_data( '_cfw_offer_child_orders', $child_orders );

		// Add note to parent order
		$parent_note = sprintf(
			__( 'Child order #%s created from post-purchase offer.', 'checkout-wc' ),
			$child_order->get_order_number()
		);
		$order->add_order_note( $parent_note );
		$order->save();

		/**
		 * Fires after child order created
		 *
		 * @since 10.4.0
		 *
		 * @param \WC_Order $child_order Child order.
		 * @param \WC_Order $order Parent order.
		 * @param array     $product_data Product data.
		 */
		do_action( 'cfw_offer_child_order_created', $child_order, $order, $product_data );

		return $child_order->get_id();
	}
}
