<?php
/**
 * Free Renewals — core lifecycle logic.
 *
 * Generalises the "one paid delivery + X uncharged deliveries every N days,
 * repeating for as long as the subscription itself is active" plan family.
 * Parameters live on the subscription variation:
 *
 *   _bh_renewal_days        N — days between deliveries (existing field).
 *   _bh_free_renewals       X — number of uncharged renewals per cycle (this module).
 *   _bh_plan_duration_days  T — explicit expiry in days (optional; inert when empty,
 *                               native "Stop renewing after" governs the term — unrelated
 *                               to the free-renewal cycle below).
 *
 * A product is part of this family when _bh_free_renewals >= 1. All overrides key
 * off that single fact — no product IDs or categories are hard-coded.
 *
 * The subscription never stops itself because of this module: it renews on its
 * normal schedule for as long as it's active (or until T/the native term ends it,
 * which is an unrelated, orthogonal concern). What repeats is the CHARGE cycle: 1
 * charged delivery, then X free ($0) deliveries, then charged again, then X free
 * again, indefinitely. E.g. X=1: charge, free, charge, free, charge, free, ...
 *
 * Every renewal order still goes through wcs_create_renewal_order() no matter which
 * path creates it (scheduler, admin "Process renewal"/"Create pending renewal order",
 * customer early renewal, PayPal IPN, ...), and that function always applies the
 * 'wcs_renewal_order_created' filter before returning — handle_renewal_order_created()
 * is the single choke point that decides, for each new renewal, whether it's the next
 * free delivery in the current cycle or a normal charged one that resets the cycle.
 * Nothing is ever blocked or cancelled; every renewal order is created and kept.
 *
 * Free deliveries are tagged with the META_FREE_DELIVERY order meta so the cycle
 * position can be derived from the actual renewal orders on demand, rather than
 * from a running counter that could drift out of sync.
 *
 * The "1 charged delivery" baseline only exists because checkout always charges
 * the parent order. If a subscription's product is changed onto a free-renewal
 * plan directly (no checkout/switch charge — e.g. an admin edits the subscription
 * item), that baseline charge never happened. has_charged_delivery_for_product()
 * guards against this: the cycle can't start counting free deliveries for a
 * product until it finds a real charge behind it, so the first renewal after such
 * a change is always charged instead of being mistaken for the first free one.
 *
 * This module is additive and only intervenes for free-renewal plans, leaving
 * normal subscriptions untouched.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_Free_Renewals' ) ) {

class AH_Free_Renewals {

	const META_FREE_RENEWALS = '_bh_free_renewals';
	const META_PLAN_DURATION = '_bh_plan_duration_days';
	const META_FREE_DELIVERY = '_bh_free_renewal_delivery';

	const NON_DELIVERY_STATUSES = [ 'cancelled', 'failed', 'refunded', 'pending-cancel' ];

	private $logger;
	private $log_context = [ 'source' => 'ah-free-renewals' ];

	public function __construct() {
		$this->logger = wc_get_logger();

		add_filter( 'wcs_renewal_order_created', [ $this, 'handle_renewal_order_created' ], 20, 2 );
		add_action( 'woocommerce_checkout_subscription_created', [ $this, 'set_plan_end_on_checkout' ], 20, 3 );
	}

	/**
	 * Number of uncharged renewals configured on the product, per cycle. 0 = not a
	 * free-renewal plan.
	 *
	 * @param WC_Product $product
	 * @return int
	 */
	public static function get_free_renewals( WC_Product $product ): int {
		return max( 0, (int) get_post_meta( $product->get_id(), self::META_FREE_RENEWALS, true ) );
	}

	/**
	 * Explicit plan duration in days. 0 = inert (native term governs expiry).
	 * Unrelated to the free-renewal charge cycle — see set_plan_end_on_checkout().
	 *
	 * @param WC_Product $product
	 * @return int
	 */
	public static function get_plan_duration_days( WC_Product $product ): int {
		return max( 0, (int) get_post_meta( $product->get_id(), self::META_PLAN_DURATION, true ) );
	}

	/**
	 * Whether the product belongs to the free-renewal plan family.
	 *
	 * @param WC_Product $product
	 * @return bool
	 */
	public static function is_free_renewal_plan( WC_Product $product ): bool {
		return self::get_free_renewals( $product ) >= 1;
	}

	/**
	 * Resolve the plan product (variation preferred) from a subscription.
	 *
	 * @param WC_Subscription $subscription
	 * @return WC_Product|null
	 */
	private static function get_plan_product_from_subscription( WC_Subscription $subscription ) {
		foreach ( $subscription->get_items() as $item ) {
			$product = wc_get_product( $item->get_variation_id() ?: $item->get_product_id() );

			if ( $product ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Full charge-to-charge cycle length in weeks, for customer-facing copy (e.g.
	 * renewal reminder emails): one charged delivery plus the configured free
	 * deliveries, each spaced _bh_renewal_days apart. E.g. a 6-month plan with
	 * 77-day renewals and 1 free renewal charges every 77 * 2 = 154 days ≈ 22 weeks,
	 * even though a delivery (free or charged) happens every 11.
	 *
	 * Public so other modules (e.g. the subscription reminder email templates) can
	 * show the real charge cadence instead of the per-delivery interval.
	 *
	 * @param WC_Subscription $subscription
	 * @return int|null Weeks, or null if this isn't a free-renewal plan.
	 */
	public static function get_charge_cycle_weeks_for_subscription( WC_Subscription $subscription ): ?int {
		$product = self::get_plan_product_from_subscription( $subscription );

		if ( ! $product || ! self::is_free_renewal_plan( $product ) || ! function_exists( 'bh_get_renewal_days_for_product' ) ) {
			return null;
		}

		$days = bh_get_renewal_days_for_product( $product ) * ( self::get_free_renewals( $product ) + 1 );

		return (int) round( $days / 7 );
	}

	/**
	 * Whether a renewal order was zeroed by this module (a free delivery), as
	 * opposed to a normal charged renewal.
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	private static function is_free_delivery( WC_Order $order ): bool {
		return 'yes' === $order->get_meta( self::META_FREE_DELIVERY );
	}

	/**
	 * How many free deliveries have happened in the CURRENT cycle — i.e. walking
	 * back from the most recent renewal, how many consecutive free ($0) renewals
	 * there have been since the last charged one (or since the initial order, if
	 * the plan hasn't charged a renewal yet). Cancelled/failed/refunded renewals
	 * are skipped so they neither consume the cycle nor block a legitimate retry.
	 *
	 * The walk also stops at the first renewal that doesn't contain the CURRENT
	 * plan product — a product switch is always a hard cycle boundary, otherwise
	 * a customer bouncing between two different free-renewal variations could
	 * inherit the free-delivery count built up under the other one.
	 *
	 * Related orders aren't guaranteed to come back in any particular order across
	 * WC Subscriptions' different data store implementations, so this sorts by
	 * order ID (monotonically increasing) rather than assuming an order.
	 *
	 * @param WC_Subscription $subscription
	 * @param int             $product_id Current plan product/variation id.
	 * @param int             $exclude_id Renewal order id to skip (e.g. the one being created).
	 * @return int
	 */
	private static function count_free_deliveries_in_cycle( WC_Subscription $subscription, int $product_id, int $exclude_id = 0 ): int {
		$deliveries = [];

		foreach ( $subscription->get_related_orders( 'ids', 'renewal' ) as $id ) {
			$id = (int) $id;

			if ( $exclude_id && $id === $exclude_id ) {
				continue;
			}

			$renewal = wc_get_order( $id );

			if ( ! $renewal || in_array( $renewal->get_status(), self::NON_DELIVERY_STATUSES, true ) ) {
				continue;
			}

			$deliveries[ $id ] = $renewal;
		}

		krsort( $deliveries );

		$count = 0;

		foreach ( $deliveries as $renewal ) {
			if ( ! self::order_contains_product( $renewal, $product_id ) ) {
				break; // Different plan product — the current cycle can't extend past a switch.
			}

			if ( ! self::is_free_delivery( $renewal ) ) {
				break; // Hit the last charged renewal — the current cycle starts right after it.
			}

			$count++;
		}

		return $count;
	}

	/**
	 * Whether an order's line items include the given product/variation.
	 *
	 * @param WC_Order $order
	 * @param int      $product_id
	 * @return bool
	 */
	private static function order_contains_product( WC_Order $order, int $product_id ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( (int) ( $item->get_variation_id() ?: $item->get_product_id() ) === $product_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the subscription's CURRENT plan product already has a charged (total
	 * > 0) order behind it — the parent order, a past non-free renewal, or a paid
	 * switch order. False means this product was just put on the subscription (e.g.
	 * a product change made directly on the subscription, with no checkout/switch
	 * charge) and has never actually been paid for on it. In that case the cycle's
	 * one charged delivery hasn't happened yet, so the next renewal must be a normal
	 * charge — treating it as the first free delivery would charge $0 for a plan
	 * that was never paid for.
	 *
	 * Walks the renewal history most-recent-first and stops at the first renewal
	 * for a DIFFERENT product — same boundary rule as count_free_deliveries_in_cycle().
	 * Without it, switching away and back to this product would find the old,
	 * stale charge from before the switch and treat it as still valid, letting the
	 * first delivery after returning start a new free cycle it never re-paid for.
	 *
	 * @param WC_Subscription $subscription
	 * @param WC_Product      $product
	 * @param int             $exclude_id Renewal order id to skip (e.g. the one being created).
	 * @return bool
	 */
	private static function has_charged_delivery_for_product( WC_Subscription $subscription, WC_Product $product, int $exclude_id = 0 ): bool {
		$product_id = $product->get_id();
		$renewals   = [];

		foreach ( $subscription->get_related_orders( 'ids', 'renewal' ) as $id ) {
			$id = (int) $id;

			if ( $exclude_id && $id === $exclude_id ) {
				continue;
			}

			$renewal = wc_get_order( $id );

			if ( ! $renewal || in_array( $renewal->get_status(), self::NON_DELIVERY_STATUSES, true ) ) {
				continue;
			}

			$renewals[ $id ] = $renewal;
		}

		krsort( $renewals );

		foreach ( $renewals as $renewal ) {
			if ( ! self::order_contains_product( $renewal, $product_id ) ) {
				return false; // Different product most recently — anything before this is stale.
			}

			if ( ! self::is_free_delivery( $renewal ) && $renewal->get_total() > 0 ) {
				return true;
			}
		}

		// No renewal history for this product yet (fresh subscription, or it has never
		// been switched away from) — fall back to the switch order and parent order.
		foreach ( $subscription->get_related_orders( 'ids', 'switch' ) as $id ) {
			$switch_order = wc_get_order( (int) $id );

			if ( $switch_order && $switch_order->is_paid() && $switch_order->get_total() > 0 && self::order_contains_product( $switch_order, $product_id ) ) {
				return true;
			}
		}

		$parent = $subscription->get_parent();

		if ( $parent && $parent->get_total() > 0 && self::order_contains_product( $parent, $product_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Zero every monetary component of an order so it needs no payment, and tag it
	 * as a free delivery so the cycle position can be derived later.
	 *
	 * @param WC_Order $order
	 */
	private function zero_order( WC_Order $order ): void {
		foreach ( $order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			if ( is_callable( [ $item, 'set_subtotal' ] ) ) {
				$item->set_subtotal( 0 );
			}

			if ( is_callable( [ $item, 'set_subtotal_tax' ] ) ) {
				$item->set_subtotal_tax( 0 );
			}

			if ( is_callable( [ $item, 'set_total_tax' ] ) ) {
				$item->set_total_tax( 0 );
			}

			if ( is_callable( [ $item, 'set_taxes' ] ) ) {
				$item->set_taxes( [ 'subtotal' => [], 'total' => [] ] );
			}

			$item->set_total( 0 );
			$item->save();
		}

		$order->set_discount_total( 0 );
		$order->set_discount_tax( 0 );
		$order->set_shipping_total( 0 );
		$order->set_shipping_tax( 0 );
		$order->set_cart_tax( 0 );
		$order->calculate_totals( false );
		$order->set_total( 0 );
		$order->update_meta_data( self::META_FREE_DELIVERY, 'yes' );
		$order->add_order_note( __( 'Free-renewal delivery: charge suppressed, routed to fulfilment at $0.', 'bh-features' ) );
		$order->save();
	}

	/**
	 * On renewal creation: decide whether this delivery is free or charged, based
	 * on how many free deliveries the current cycle has already produced.
	 *
	 * Below the cap, the renewal is zeroed (another free delivery in this cycle).
	 * At the cap, the renewal is left as a normal charged renewal — WooCommerce
	 * Subscriptions and the payment gateway handle it exactly like any other
	 * renewal — which starts a new cycle for the deliveries that follow it.
	 *
	 * @param WC_Order        $renewal_order
	 * @param WC_Subscription $subscription
	 * @return WC_Order
	 */
	public function handle_renewal_order_created( $renewal_order, $subscription ) {
		try {
			if ( ! $renewal_order instanceof WC_Order || ! $subscription instanceof WC_Subscription ) {
				return $renewal_order;
			}

			if ( $renewal_order->has_status( [ 'cancelled', 'trash' ] ) ) {
				// Already cancelled by AH_Renewal_Duplicate_Guard (or anything else) —
				// nothing to zero or count here.
				return $renewal_order;
			}

			$product = self::get_plan_product_from_subscription( $subscription );

			if ( ! $product || ! self::is_free_renewal_plan( $product ) ) {
				return $renewal_order;
			}

			if ( ! self::has_charged_delivery_for_product( $subscription, $product, $renewal_order->get_id() ) ) {
				if ( $renewal_order->get_total() <= 0 ) {
					// No prior charge for this product AND this delivery is already $0
					// (e.g. a recurring coupon). Tag it as free rather than pretending a
					// charge baseline was established — the real first charge still has
					// to happen on a later renewal for the cycle to mean anything.
					$this->zero_order( $renewal_order );

					$this->logger->info( sprintf(
						'[handle_renewal_order_created] subscription_id=%d renewal_order_id=%d — no prior charge and already $0 (coupon or manual edit), tagged as free delivery',
						$subscription->get_id(),
						$renewal_order->get_id()
					), $this->log_context );

					return $renewal_order;
				}

				$renewal_order->add_order_note(
					__( 'Free-renewal: no prior charge found for this plan on this subscription (e.g. a product change with no checkout/switch charge) — charged normally instead of starting as a free delivery.', 'bh-features' )
				);
				$renewal_order->save();

				$this->logger->info( sprintf(
					'[handle_renewal_order_created] subscription_id=%d renewal_order_id=%d — charged (no prior charge for product_id=%d on this subscription)',
					$subscription->get_id(),
					$renewal_order->get_id(),
					$product->get_id()
				), $this->log_context );

				return $renewal_order;
			}

			$max         = self::get_free_renewals( $product );
			$free_so_far = self::count_free_deliveries_in_cycle( $subscription, $product->get_id(), $renewal_order->get_id() );

			if ( $free_so_far < $max ) {
				$this->zero_order( $renewal_order );

				$this->logger->info( sprintf(
					'[handle_renewal_order_created] subscription_id=%d renewal_order_id=%d — zeroed (%d/%d free this cycle)',
					$subscription->get_id(),
					$renewal_order->get_id(),
					$free_so_far + 1,
					$max
				), $this->log_context );

				return $renewal_order;
			}

			if ( $renewal_order->get_total() <= 0 ) {
				// Something outside this module (a 100%-off recurring coupon, a manual
				// edit) already zeroed this order. Leaving it untagged would make the
				// next cycle think a real charge happened here and reset around it —
				// tag it as free so the count stays honest about what was actually paid.
				$this->zero_order( $renewal_order );

				$this->logger->info( sprintf(
					'[handle_renewal_order_created] subscription_id=%d renewal_order_id=%d — externally zeroed (coupon or manual edit), tagged as free delivery instead of a charge',
					$subscription->get_id(),
					$renewal_order->get_id()
				), $this->log_context );

				return $renewal_order;
			}

			$renewal_order->add_order_note(
				__( 'Free-renewal cycle complete: this delivery is charged normally, and a new free-renewal cycle starts after it.', 'bh-features' )
			);
			$renewal_order->save();

			$this->logger->info( sprintf(
				'[handle_renewal_order_created] subscription_id=%d renewal_order_id=%d — charged, cycle reset (max=%d free per cycle)',
				$subscription->get_id(),
				$renewal_order->get_id(),
				$max
			), $this->log_context );
		} catch ( \Throwable $th ) {
			$this->logger->error( sprintf(
				'[handle_renewal_order_created] error=%s',
				$th->getMessage()
			), $this->log_context );

			if ( function_exists( 'bh_plugins_error_log' ) ) {
				bh_plugins_error_log( [
					'error'    => $th->getMessage(),
					'function' => 'AH_Free_Renewals::handle_renewal_order_created',
				] );
			}
		}

		return $renewal_order;
	}

	/**
	 * Optional explicit term. Inert unless _bh_plan_duration_days is set: when set,
	 * the subscription end date is pinned to date_created + T days. When empty, the
	 * native "Stop renewing after" setting governs the term and this does nothing.
	 * Unrelated to the free-renewal charge cycle — this only ends the subscription
	 * itself, on whatever cadence T describes.
	 *
	 * @param WC_Subscription $subscription
	 * @param WC_Order        $order
	 * @param mixed           $recurring_cart
	 */
	public function set_plan_end_on_checkout( WC_Subscription $subscription, WC_Order $order, $recurring_cart ): void {
		try {
			$product = self::get_plan_product_from_subscription( $subscription );

			if ( ! $product || ! self::is_free_renewal_plan( $product ) ) {
				return;
			}

			$duration = self::get_plan_duration_days( $product );

			if ( $duration < 1 ) {
				return;
			}

			$base_date = wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );
			$end_date  = date( 'Y-m-d H:i:s', strtotime( "+{$duration} days", strtotime( $base_date ) ) );

			$subscription->update_dates( [ 'end' => $end_date ] );
			$subscription->save();

			$this->logger->info( sprintf(
				'[set_plan_end_on_checkout] subscription_id=%d duration_days=%d end=%s',
				$subscription->get_id(),
				$duration,
				$end_date
			), $this->log_context );
		} catch ( \Throwable $th ) {
			$this->logger->error( sprintf(
				'[set_plan_end_on_checkout] subscription_id=%d error=%s',
				$subscription->get_id(),
				$th->getMessage()
			), $this->log_context );

			if ( function_exists( 'bh_plugins_error_log' ) ) {
				bh_plugins_error_log( [
					'error'    => $th->getMessage(),
					'function' => 'AH_Free_Renewals::set_plan_end_on_checkout',
					'args'     => [ $subscription->get_id(), $order->get_id() ],
				] );
			}
		}
	}
}

}
