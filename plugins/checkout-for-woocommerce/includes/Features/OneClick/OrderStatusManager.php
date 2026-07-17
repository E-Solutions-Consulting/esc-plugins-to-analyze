<?php

namespace Objectiv\Plugins\Checkout\Features\OneClick;

use Objectiv\Plugins\Checkout\SingletonAbstract;

/**
 * Manages order status transitions for post-purchase offers
 *
 * @since 7.0.0
 */
class OrderStatusManager extends SingletonAbstract {

	/**
	 * Status constant for pending offer orders
	 */
	const PENDING_OFFER_STATUS = 'cfw-pending-offer';

	/**
	 * Initialize the manager
	 */
	public function init() {
		// Register custom order status
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_pending_offer_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_pending_offer_to_order_statuses' ) );

		// Intercept order completion for offers
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'maybe_set_pending_offer_status' ), 999, 3 );

		// Handle scheduled status normalization
		add_action( 'cfw_normalize_order_status', array( $this, 'normalize_order_status' ) );

		// Delay order emails for pending offer status
		add_filter( 'woocommerce_email_enabled_customer_processing_order', array( $this, 'maybe_disable_order_email' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'maybe_disable_order_email' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', array( $this, 'maybe_disable_order_email' ), 10, 2 );
	}

	/**
	 * Register the pending offer order status
	 *
	 * @param array $statuses Existing order statuses
	 * @return array Modified statuses
	 */
	public function register_pending_offer_status( $statuses ) {
		$statuses[ 'wc-' . self::PENDING_OFFER_STATUS ] = array(
			'label'                     => _x( 'Pending Offer', 'Order status', 'checkout-wc' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Pending Offer <span class="count">(%s)</span>', 'Pending Offer <span class="count">(%s)</span>', 'checkout-wc' ),
		);
		return $statuses;
	}

	/**
	 * Add pending offer to order statuses list
	 *
	 * @param array $statuses Order statuses
	 * @return array Modified statuses
	 */
	public function add_pending_offer_to_order_statuses( $statuses ) {
		$statuses[ 'wc-' . self::PENDING_OFFER_STATUS ] = _x( 'Pending Offer', 'Order status', 'checkout-wc' );
		return $statuses;
	}

	/**
	 * Maybe set order to pending offer status if offers are queued
	 *
	 * @param string    $status The order status to set.
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order Order object.
	 * @return string Modified status
	 */
	public function maybe_set_pending_offer_status( $status, $order_id, $order ) {
		// Check if post-purchase offers are queued in session
		if ( ! WC()->session ) {
			cfw_debug_log( 'No session - aborting: maybe_set_pending_offer_status()' );
			return $status;
		}
		$session_data = WC()->session->get( 'cfw_post_purchase_data' );

		if ( ! $session_data ||
			! isset( $session_data['order_id'] ) ||
			$session_data['order_id'] !== $order_id ||
			empty( $session_data['pending_bumps'] ) ) {
			cfw_debug_log( 'No session data - aborting: maybe_set_pending_offer_status()' . var_export( $session_data, true ) );
			return $status;
		}

		// Check if payment method supports one-click offers
		$payment_method = $order->get_payment_method();
		$registry       = GatewayRegistry::instance();

		if ( ! $registry->is_gateway_supported( $payment_method ) ) {
			// Gateway doesn't support one-click, skip pending offer status
			cfw_debug_log( 'No gateway - aborting: maybe_set_pending_offer_status()' );
			return $status;
		}

		// Store the intended final status
		$order->update_meta_data( '_cfw_pending_offer_target_status', $status );
		$order->save();

		// Schedule status normalization (30 minutes)
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + ( 30 * MINUTE_IN_SECONDS ),
				'cfw_normalize_order_status',
				array( 'order_id' => $order_id )
			);
		}

		return self::PENDING_OFFER_STATUS;
	}

	/**
	 * Normalize order status after timeout
	 *
	 * @param int $order_id Order ID.
	 */
	public function normalize_order_status( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_status() !== self::PENDING_OFFER_STATUS ) {
			return;
		}

		// Get the target status
		$target_status = $order->get_meta( '_cfw_pending_offer_target_status' );

		if ( empty( $target_status ) ) {
			$target_status = 'processing';
		}

		// Transition to final status
		$order->update_status( $target_status, __( 'Post-purchase offer session expired.', 'checkout-wc' ) );

		// Clean up meta
		$order->delete_meta_data( '_cfw_pending_offer_target_status' );
		$order->save();

		// Clear session data for this order
		if ( ! WC()->session ) {
			return;
		}

		$session_data = WC()->session->get( 'cfw_post_purchase_data' );
		if ( $session_data && isset( $session_data['order_id'] ) && $session_data['order_id'] === $order_id ) {
			WC()->session->set( 'cfw_post_purchase_data', null );
		}
	}

	/**
	 * Complete an offer session and transition to final status
	 *
	 * @param int $order_id Order ID
	 */
	public function complete_offer_session( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_status() !== self::PENDING_OFFER_STATUS ) {
			return;
		}

		// Cancel scheduled action
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'cfw_normalize_order_status', array( 'order_id' => $order_id ) );
		}

		// Get target status
		$target_status = $order->get_meta( '_cfw_pending_offer_target_status' );

		if ( empty( $target_status ) ) {
			$target_status = 'processing';
		}

		// Transition to final status
		$order->update_status( $target_status, __( 'Post-purchase offers completed.', 'checkout-wc' ) );

		// Clean up meta
		$order->delete_meta_data( '_cfw_pending_offer_target_status' );
		$order->save();
	}

	/**
	 * Check if an order session has expired for offers
	 *
	 * @param int    $order_id Order ID
	 * @param string $order_key Order key
	 * @return bool True if expired
	 */
	public function is_order_expired_for_offers( $order_id, $order_key ) {
		// Check if session data exists for this order
		if ( ! WC()->session ) {
			return true;
		}
		$session_data = WC()->session->get( 'cfw_post_purchase_data' );

		// If no session or different order, consider it expired
		if ( ! $session_data ||
			! isset( $session_data['order_id'] ) ||
			$session_data['order_id'] !== $order_id ||
			$session_data['order_key'] !== $order_key ) {
			return true;
		}

		// If session exists but no pending bumps, it's expired
		return empty( $session_data['pending_bumps'] );
	}

	/**
	 * Maybe disable order emails for pending offer status
	 *
	 * @param bool      $enabled Whether email is enabled
	 * @param \WC_Order $order Order object
	 * @return bool Modified enabled status
	 */
	public function maybe_disable_order_email( $enabled, $order ) {
		if ( $order && $order->get_status() === self::PENDING_OFFER_STATUS ) {
			return false;
		}
		return $enabled;
	}
}
