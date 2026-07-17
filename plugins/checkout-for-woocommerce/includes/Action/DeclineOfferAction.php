<?php

namespace Objectiv\Plugins\Checkout\Action;

use Objectiv\Plugins\Checkout\Features\OneClick\OrderStatusManager;
use Objectiv\Plugins\Checkout\Features\OrderBumps;

/**
 * Decline Offer Action
 *
 * Handles declining of post-purchase one-click offers.
 *
 * @package Objectiv\Plugins\Checkout\Action
 * @since 10.4.0
 */
class DeclineOfferAction extends CFWAction {

	/**
	 * Constructor
	 *
	 * @since 10.4.0
	 * @param string $id Action ID.
	 */
	public function __construct( string $id ) {
		parent::__construct( $id );
	}

	/**
	 * Action code to execute
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function action() {
		// Verify nonce
		$bump_id = isset( $_POST['bump_id'] ) ? absint( $_POST['bump_id'] ) : 0;

		if ( ! $bump_id || ! check_ajax_referer( 'cfw_process_offer_' . $bump_id, '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'checkout-wc' ) ) );
			return;
		}

		// Verify order ownership
		$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

		if ( ! $order_id || ! $order_key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order information.', 'checkout-wc' ) ) );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_order_key() !== $order_key ) {
			wp_send_json_error( array( 'message' => __( 'Order verification failed.', 'checkout-wc' ) ) );
			return;
		}

		// Get session data
		if ( ! WC()->session ) {
			// Session unavailable - just reload to thank you page
			wp_send_json_success(
				array(
					'message' => __( 'Redirecting...', 'checkout-wc' ),
					'reload'  => true,
				)
			);
			return;
		}

		$session_data = WC()->session->get( 'cfw_post_purchase_data' );

		if ( ! $session_data ) {
			// Session expired is OK - just redirect to thank you page
			wp_send_json_success(
				array(
					'message' => __( 'Session expired. Redirecting...', 'checkout-wc' ),
					'reload'  => true,
				)
			);
			return;
		}

		// Mark bump as declined
		$session_data['handled_bumps'][ $bump_id ] = 'declined';
		$session_data['pending_bumps'] = array_diff( $session_data['pending_bumps'], array( $bump_id ) );
		WC()->session->set( 'cfw_post_purchase_data', $session_data );

		cfw_debug_log( 'Post-purchase bump ' . $bump_id . ' declined for order ' . $order_id );

		// Check if this was the last bump and complete the offer session
		if ( empty( $session_data['pending_bumps'] ) ) {
			$order = wc_get_order( $order_id );
			if ( $order && $order->get_status() === 'cfw-pending-offer' ) {
				OrderStatusManager::instance()->complete_offer_session( $order_id );
				cfw_debug_log( 'Completed offer session for order ' . $order_id . ' after declining last bump' );
			}
		}

		// Return success with reload flag
		wp_send_json_success(
			array(
				'message' => __( 'Offer declined.', 'checkout-wc' ),
				'reload'  => true,
			)
		);
	}
}