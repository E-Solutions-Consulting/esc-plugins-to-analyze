<?php
/**
 * Renewal Order Duplicate Guard
 *
 * wcs_create_renewal_order() has no built-in idempotency check. The native
 * "Process renewal"/"Create pending renewal order" admin actions, a racing
 * Action Scheduler retry, the customer's own early-renewal button, and this
 * plugin's own /bh/v1/subscriptions/{id}/renew endpoint can all independently
 * end up creating a renewal order for the same subscription within seconds of
 * each other — doubling a charge, or letting two free-renewal decisions
 * (AH_Free_Renewals) run against stale, pre-race order history.
 *
 * Every renewal order still passes through the 'wcs_renewal_order_created'
 * filter no matter which path created it, so that's the single place this can
 * be caught. Runs at priority 5, ahead of AH_Free_Renewals (20) and anything
 * else that might act on the order, so a duplicate never reaches money-moving
 * or cycle-counting code.
 *
 * Two layers of defense:
 *  1. An atomic wp_cache_add() lock per subscription — the first renewal order
 *     created for a subscription within LOCK_TTL wins it; anything else for
 *     that subscription in the same window is a duplicate outright. This is
 *     what actually closes the race, but only holds while the object cache is
 *     persistent (Redis, in this environment) and within its short TTL.
 *  2. A fallback scan of sibling renewal orders created within
 *     RACE_WINDOW_SECONDS of this one, for when the lock has already expired
 *     (a slower race) or the object cache isn't persistent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_Renewal_Duplicate_Guard' ) ) {

class AH_Renewal_Duplicate_Guard {

	const RACE_WINDOW_SECONDS = 120;
	const LOCK_TTL            = 30;
	const LOCK_GROUP          = 'bh-features';

	private $logger;
	private $log_context = [ 'source' => 'ah-renewal-duplicate-guard' ];

	public function __construct() {
		$this->logger = wc_get_logger();

		add_filter( 'wcs_renewal_order_created', [ $this, 'cancel_if_duplicate' ], 5, 2 );
	}

	/**
	 * Cancel this renewal order if it's a duplicate of one already created for
	 * the same subscription.
	 *
	 * @param WC_Order        $renewal_order
	 * @param WC_Subscription $subscription
	 * @return WC_Order
	 */
	public function cancel_if_duplicate( $renewal_order, $subscription ) {
		try {
			if ( ! $renewal_order instanceof WC_Order || ! $subscription instanceof WC_Subscription ) {
				return $renewal_order;
			}

			if ( $renewal_order->has_status( [ 'cancelled', 'trash' ] ) ) {
				return $renewal_order;
			}

			$original_id = $this->find_duplicate_of( $subscription, $renewal_order );

			if ( ! $original_id ) {
				return $renewal_order;
			}

			$renewal_order->update_status(
				'cancelled',
				sprintf(
					/* translators: %d: order id of the original renewal order kept. */
					__( 'Duplicate renewal order: another renewal order (#%d) for this subscription was created seconds earlier — cancelled to prevent a double charge.', 'bh-features' ),
					$original_id
				)
			);

			// Unlink it from the subscription's renewal-order relation entirely — not
			// just cancel it. Anything that walks get_related_orders('renewal') to find
			// "the previous order" (this module's own cycle counting, and this plugin's
			// validate_previous_order_status_before_renewal(), which cancels the whole
			// subscription if the previous renewal isn't 'completed') must never see this
			// duplicate at all, or it reads as a real delivery that never completed.
			if ( class_exists( 'WCS_Related_Order_Store' ) ) {
				WCS_Related_Order_Store::instance()->delete_relation( $renewal_order, $subscription, 'renewal' );
			}

			$this->logger->warning( sprintf(
				'[cancel_if_duplicate] subscription_id=%d duplicate_renewal_order_id=%d original_renewal_order_id=%d — cancelled duplicate',
				$subscription->get_id(),
				$renewal_order->get_id(),
				$original_id
			), $this->log_context );
		} catch ( \Throwable $th ) {
			$this->logger->error( sprintf(
				'[cancel_if_duplicate] error=%s',
				$th->getMessage()
			), $this->log_context );

			if ( function_exists( 'bh_plugins_error_log' ) ) {
				bh_plugins_error_log( [
					'error'    => $th->getMessage(),
					'function' => 'AH_Renewal_Duplicate_Guard::cancel_if_duplicate',
				] );
			}
		}

		return $renewal_order;
	}

	/**
	 * Order id this renewal is a duplicate of, or 0 if it isn't one. Checks the
	 * atomic lock first (closes tight races), then falls back to a sibling scan
	 * (catches slower races once the lock has expired).
	 *
	 * @param WC_Subscription $subscription
	 * @param WC_Order        $renewal_order
	 * @return int
	 */
	private function find_duplicate_of( WC_Subscription $subscription, WC_Order $renewal_order ): int {
		$lock_key = 'ah_renewal_lock_' . $subscription->get_id();

		if ( ! wp_cache_add( $lock_key, $renewal_order->get_id(), self::LOCK_GROUP, self::LOCK_TTL ) ) {
			$holder = (int) wp_cache_get( $lock_key, self::LOCK_GROUP );

			if ( $holder && $holder !== $renewal_order->get_id() ) {
				return $holder;
			}
		}

		return $this->find_earlier_sibling( $subscription, $renewal_order );
	}

	/**
	 * Earlier (lower id) renewal order for the same subscription created within
	 * the race window. Only a lower id counts as "the original" so two
	 * overlapping creations never both cancel each other.
	 *
	 * @param WC_Subscription $subscription
	 * @param WC_Order        $renewal_order
	 * @return int
	 */
	private function find_earlier_sibling( WC_Subscription $subscription, WC_Order $renewal_order ): int {
		$current_id = $renewal_order->get_id();
		$created    = $renewal_order->get_date_created();
		$created_ts = $created ? $created->getTimestamp() : time();

		foreach ( $subscription->get_related_orders( 'ids', 'renewal' ) as $id ) {
			$id = (int) $id;

			if ( $id >= $current_id ) {
				continue;
			}

			$other = wc_get_order( $id );

			if ( ! $other || $other->has_status( [ 'cancelled', 'trash' ] ) ) {
				continue;
			}

			$other_created = $other->get_date_created();
			$other_ts      = $other_created ? $other_created->getTimestamp() : 0;

			if ( abs( $created_ts - $other_ts ) <= self::RACE_WINDOW_SECONDS ) {
				return $id;
			}
		}

		return 0;
	}
}

add_action( 'woocommerce_loaded', function () {
	new AH_Renewal_Duplicate_Guard();
} );

}
