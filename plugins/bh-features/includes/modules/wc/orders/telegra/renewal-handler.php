<?php
/**
 * AH Orders Telegra Renewal Handler
 *
 * Handles renewal payment completion and routes the order to Telegra.
 * - No metadata modifications (DB safe for large installations)
 * - Only affects subscription renewals
 * - Multi-layer capture (Stripe final, WC payment complete, subscription renewal)
 * - Anti-loop protection
 * - HPOS compatible
 * - Minimal and domain-specific
 */

if ( ! class_exists( 'AH_Orders_Telegra_Renewal_Handler' ) ) {

class AH_Orders_Telegra_Renewal_Handler {

	/**
	 * Tracks processed orders inside the current request.
	 * Prevents repeated execution without touching the database.
	 *
	 * @var array<int,bool>
	 */
	protected static $processed = [];

	/** @var WC_Logger|null */
	protected $logger = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( function_exists( 'wc_get_logger' ) ) {
			$this->logger = wc_get_logger();
		}
		$this->init_hooks();
	}

	/**
	 * Register hooks for detecting renewal payment completion.
	 */
	protected function init_hooks() {

		// 1. Stripe final response hook (highest reliability for Stripe renewals)
		add_action(
			'wc_gateway_stripe_process_response',
			[ $this, 'on_stripe_finalized' ],
			999,
			2
		);

		// 2. WooCommerce payment complete (universal fallback)
		add_action(
			'woocommerce_payment_complete',
			[ $this, 'on_payment_complete' ],
			10,
			1
		);

		// 3. Subscriptions-level renewal payment complete (fallback)
		add_action(
			'woocommerce_subscription_renewal_payment_complete',
			[ $this, 'on_subscription_renewal_payment_complete' ],
			20,
			2
		);

		// 
		add_action(
			'woocommerce_order_status_changed',
			[ $this, 'change_free_orders_to_telemdnow_status' ],
			10,
			3
		);

		add_filter( 'ah_should_process_free_orders', 
			[ $this, 'disable_free_order_processing_during_blackout_period'], 
			10, 
			2 
		);
	}

	/* -------------------------------------------------------------------------
	 *  HOOK #1 — STRIPE FINAL RESPONSE (PRIMARY)
	 * ---------------------------------------------------------------------- */

	/**
	 * Triggered when Stripe completes its full processing cycle.
	 *
	 * @param array    $response
	 * @param WC_Order $order
	 */
	public function on_stripe_finalized( $response, $order ) {

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id = $order->get_id();

		// Prevent repeated execution
		if ( $this->is_processed( $order_id ) ) {
			return;
		}

		$this->mark_processed( $order_id );

		// Ensure renewal order
		if ( ! $this->is_renewal_order( $order ) ) {
			return;
		}

		// Ensure payment is complete
		if ( ! $order->has_status( 'processing' ) ) {
			return;
		}
		//stripe_finalized
		$this->route_to_telegra( $order, 'sf' );
	}

	/* -------------------------------------------------------------------------
	 *  HOOK #2 — WOOCOMMERCE PAYMENT COMPLETE (UNIVERSAL FALLBACK)
	 * ---------------------------------------------------------------------- */

	/**
	 * Triggered when WooCommerce considers the payment fully complete.
	 *
	 * @param int $order_id
	 */
	public function on_payment_complete( $order_id ) {

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Prevent repeated execution
		if ( $this->is_processed( $order_id ) ) {
			return;
		}

		$this->mark_processed( $order_id );

		// Only handle renewals
		if ( ! $this->is_renewal_order( $order ) ) {
			return;
		}
		//payment_complete
		$this->route_to_telegra( $order, 'pc' );
	}

	/* -------------------------------------------------------------------------
	 *  HOOK #3 — SUBSCRIPTIONS RENEWAL PAYMENT COMPLETE (FALLBACK)
	 * ---------------------------------------------------------------------- */

	/**
	 * Triggered when a subscription renewal payment is completed.
	 *
	 * @param WC_Subscription $subscription
	 * @param WC_Order        $order
	 */
	public function on_subscription_renewal_payment_complete( $subscription, $order ) {

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id = $order->get_id();

		// Prevent repeated execution
		if ( $this->is_processed( $order_id ) ) {
			return;
		}

		$this->mark_processed( $order_id );

		// Ensure renewal order
		if ( ! $this->is_renewal_order( $order ) ) {
			return;
		}
		//subscription_renewal_payment_complete
		$this->route_to_telegra( $order, 'srpc' );
	}

	/**
	 * Handle free orders (total = 0) by setting them to the status
	 * configured in Telemdnow plugin (telemdnow_trigger_action option)
	 * when they include a subscription.
	 *
	 * @param int    $order_id
	 * @param string $old_status
	 * @param string $new_status
	 */
	function change_free_orders_to_telemdnow_status( $order_id, $old_status, $new_status ) {

		// Only run when order moves into processing
		if ( $new_status !== 'processing' ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// ---------------------------
		// FILTER: enable/disable this workflow
		// ---------------------------
		$enabled = apply_filters( 'ah_should_process_free_orders', true, $order );

		if ( ! $enabled ) {
			wc_get_logger()->info(
				"Skipped free-order handler for order #{$order_id} (disabled via filter).",
				[ 'source' => 'ah-free-orders' ]
			);
			return;
		}

		// ---------------------------
		// Early return if order total > 0
		// ---------------------------
		if ( floatval( $order->get_total() ) > 0 ) {
			return;
		}

		// ---------------------------
		// Check subscription association
		// ---------------------------
		if (
			! function_exists( 'wcs_order_contains_subscription' ) ||
			! function_exists( 'wcs_is_subscription' )
		) {
			return; // Subscriptions plugin is not available
		}

		$has_subscription =
			wcs_order_contains_subscription( $order_id ) ||
			wcs_is_subscription( $order_id );

		if ( ! $has_subscription ) {
			return;
		}

		// ---------------------------
		// Update status
		// ---------------------------
		try {
			$order_status = get_option( 'telemdnow_trigger_action' );
			if ( ! empty( $order_status ) ) {
				$order->update_status( $order_status, 'Order total is 0 → moved to '. $order_status .' by free-order handler.' );
			}

			wc_get_logger()->info(
				"Order #{$order_id} moved to {$order_status} (free total + subscription).",
				[ 'source' => 'ah-free-orders' ]
			);

		} catch ( Throwable $e ) {

			wc_get_logger()->error(
				"ERROR updating order #{$order_id}: " . $e->getMessage(),
				[ 'source' => 'ah-free-orders' ]
			);
		}
	}

	/**
	 * Disable free order processing during the blackout window.
	 *
	 * This is hooked into the filter 'ah_should_process_free_orders'.
	 *
	 * @param bool     $should_process Current flag.
	 * @param WC_Order $order          WC Order instance.
	 * @return bool
	 */
	function disable_free_order_processing_during_blackout_period( $should_process, $order ) {

	    if ( ! $order instanceof WC_Order ) {
	        return $should_process;
	    }

	    // --------------------------------------------------
	    // Detect renewal vs new order
	    // --------------------------------------------------
	    $is_renewal = function_exists( 'wcs_order_contains_renewal' )
	        && wcs_order_contains_renewal( $order );

	    // --------------------------------------------------
	    // Resolve customer state (billing by default)
	    // --------------------------------------------------
	    $state_code = $order->get_shipping_state();

	    if ( empty( $state_code ) ) {
	        wc_get_logger()->warning(
	            "Order #{$order->get_id()} has no shipping state. Blocking processing by default.",
	            [ 'source' => 'ah-free-orders' ]
	        );
	        return false;
	    }

	    $state_status = AH_Licensed_States_Manager::get_state_status( $state_code );

	    // --------------------------------------------------
	    // RULE 1: Renewal orders
	    // Only ALLOW if state === available
	    // --------------------------------------------------
	    if ( $is_renewal ) {

	        if ( 'available' !== $state_status ) {

	            wc_get_logger()->info(
	                "Renewal order #{$order->get_id()} blocked. State {$state_code} ({$state_status}) is not available.",
	                [ 'source' => 'ah-free-orders' ]
	            );

	            return false;
	        }

	        return $should_process;
	    }

	    // --------------------------------------------------
	    // RULE 2: New orders
	    // Block ONLY if state === unavailable
	    // --------------------------------------------------
	    if ( 'unavailable' === $state_status ) {

	        wc_get_logger()->info(
	            "New order #{$order->get_id()} blocked. State {$state_code} is unavailable.",
	            [ 'source' => 'ah-free-orders' ]
	        );

	        return false;
	    }

	    return $should_process;
	}

	/* -------------------------------------------------------------------------
	 *  CORE LOGIC
	 * ---------------------------------------------------------------------- */

	/**
	 * Route renewal order to Telegra by switching it to Status 
	 * configured in Telemdnow plugin (telemdnow_trigger_action option)
	 *
	 * @param WC_Order $order
	 * @param string   $context
	 */
	protected function route_to_telegra( WC_Order $order, $context ) {

		$current_status = $order->get_status();
		$order_status	= get_option( 'telemdnow_trigger_action' );

		// Skip if already routed
		if ( $current_status === $order_status ) {
			return;
		}

		/**
		 * Developers may customize the routing status.
		 */
		$target_status = apply_filters(
			'ah_telegra_renewal_target_status',
			$order_status,
			$order,
			$context
		);

		$order->update_status(
			$target_status,
			"{$context} — Routing to Telegra.<br>"
		);

		$this->log(
			"Order #{$order->get_id()} routed to {$target_status} ({$context})"
		);
	}

	/* -------------------------------------------------------------------------
	 *  HELPERS
	 * ---------------------------------------------------------------------- */

	/**
	 * Check if order is a renewal order.
	 */
	protected function is_renewal_order( $order ) {
		return function_exists( 'wcs_order_contains_renewal' )
			&& wcs_order_contains_renewal( $order );
	}

	/**
	 * Has the order already been processed in this request?
	 */
	protected function is_processed( $order_id ) {
		return isset( self::$processed[ $order_id ] );
	}

	/**
	 * Mark order as processed in this request.
	 */
	protected function mark_processed( $order_id ) {
		self::$processed[ $order_id ] = true;
	}

	/**
	 * Optional logging (disabled by default).
	 */
	protected function log( $message, $level = 'info' ) {

		if ( ! apply_filters( 'ah_telegra_renewal_logging_enabled', true ) ) {
			return;
		}

		if ( $this->logger ) {
			$this->logger->log(
				$level,
				$message,
				[ 'source' => 'ah_orders_telegra_renewal_handler' ]
			);
		}
	}
}

add_action( 'woocommerce_loaded', function() {
    if ( class_exists( 'AH_Orders_Telegra_Renewal_Handler' ) ) {
        new AH_Orders_Telegra_Renewal_Handler();
    }
});

}
