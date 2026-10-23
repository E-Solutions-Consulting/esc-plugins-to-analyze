<?php
/**
 * Renewal Intake Review
 *
 * When the next renewal is due but the PREVIOUS renewal order never reached
 * "completed" — stuck anywhere in the Telegra pipeline (send_to_telegra,
 * on-hold, waiting_room, provider_review, collect_payment, error_review,
 * admin_review, prerequisites) — the subscription used to be cancelled
 * outright the instant the next renewal tried to fire (see
 * validate_previous_order_status_before_renewal()). That was a deliberate
 * decision at the time (avoid billing customers who never actually used the
 * service), but doing it silently and instantly meant genuinely in-progress
 * cases could get cut off with no visibility, and nobody had a queue to
 * catch and fix it before the fact.
 *
 * Confirmed against real order history before naming this: only the very
 * first of those statuses (send_to_telegra) is actually waiting on the
 * customer, for their intake form. Every status after it means the customer
 * already did their part, and the order is waiting on Telegra or CS to act
 * — this is a Telegra/CS follow-up queue, not a "customer didn't finish"
 * queue. (`pending`/`failed` are handled separately and still cancel
 * immediately — those are genuine payment failures, confirmed against real
 * data to have nothing to do with this pipeline.)
 *
 * This module gives that case a distinct, visible holding status instead of
 * an instant cancellation: RENEWAL_PENDING_REVIEW. CS can resolve it
 * manually at any time from the subscription's status dropdown (reactivate,
 * or cancel outright); if nobody does within the grace period (default 30
 * days, filterable via `ah_renewal_review_grace_days` or the
 * `ah_renewal_review_grace_days` option), it's cancelled automatically — the
 * same eventual outcome as before, just delayed and no longer invisible.
 *
 * While the subscription is under review:
 *  - The stuck order reaching "completed" reactivates it right away — the
 *    patient was served, nothing left to review.
 *  - The stuck order being cancelled or refunded does NOT resolve anything on
 *    its own: the customer may be leaving, or may want to renew later on a
 *    date of their choosing. The subscription stays in review with a note, and
 *    CS decides.
 *  - When CS reactivates the subscription from wp-admin, that decision is
 *    recorded (META_RESOLVED_ORDER) so the next renewal isn't cancelled
 *    because the stuck order ended up cancelled/refunded. Single use, and only
 *    for that order — same shape as AH_Renewal_Items_Change_Flag.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Renewal_Intake_Review' ) ) {

class AH_Renewal_Intake_Review {

    const GRACE_DAYS_OPTION  = 'ah_renewal_review_grace_days';
    const GRACE_DAYS_DEFAULT = 30;
    const TIMEOUT_HOOK       = 'ah_renewal_intake_review_timeout';
    const META_TRIGGER_ORDER = '_ah_renewal_review_order_id';

    /**
     * Statuses a subscription can enter review from. 'on-hold' matters most:
     * WC_Subscriptions_Manager::process_renewal() (scheduler, admin "Process
     * renewal", /bh/v1/subscriptions/{id}/renew) puts the subscription on-hold
     * BEFORE creating the renewal order, so that's the status it has by the
     * time validate_previous_order_status_before_renewal() runs.
     */
    const ENTRY_STATUSES = [ 'active', 'on-hold' ];

    /**
     * Renewal order meta: this renewal was cancelled only because its
     * subscription was sent to review. It stays related to the subscription
     * (visible to CS) but must never count as "the previous order" — otherwise
     * the first renewal after the subscription is reactivated sees a cancelled
     * previous order and cancels everything.
     */
    const META_PARKED_RENEWAL = '_ah_renewal_review_parked';

    /**
     * Subscription meta: the stuck order CS already decided on by reactivating
     * the subscription out of review. The next renewal skips the
     * previous-order check once if that order ended up in RESOLVED_STATUSES.
     */
    const META_RESOLVED_ORDER = '_ah_renewal_review_resolved_order';

    /**
     * Stuck-order statuses that a CS reactivation waives. Still-in-pipeline
     * statuses are never waived — that would open a second order in Telegra.
     */
    const RESOLVED_STATUSES = [ 'cancelled', 'refunded', CANCEL_CUSTOMER_REQUEST, CANCEL_AUTHORIZATION_EXPIRED, CANCEL_PATIENT_REJECTED ];

    /** Set while this module reactivates a subscription itself, so it isn't mistaken for a CS decision. */
    private static $auto_reactivating = false;

    /**
     * @param WC_Order $renewal_order
     */
    public static function mark_parked_renewal( WC_Order $renewal_order ): void {
        $renewal_order->update_meta_data( self::META_PARKED_RENEWAL, 'yes' );
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    public static function is_parked_renewal( $order ): bool {
        return $order instanceof WC_Order && 'yes' === $order->get_meta( self::META_PARKED_RENEWAL );
    }

    private $logger;
    private $log_context = [ 'source' => 'ah-renewal-intake-review' ];

    public function __construct() {
        $this->logger = wc_get_logger();

        add_action( 'init', [ $this, 'register_status' ], 20 );
        add_filter( 'wcs_subscription_statuses', [ $this, 'add_status_label' ] );

        // Entering the review status: not one of WC_Subscription's hardcoded
        // switch cases, so it falls to the `default` branch, which defers to
        // this generic filter.
        add_filter( 'woocommerce_can_subscription_be_updated_to', [ $this, 'allow_entering_review' ], 10, 3 );

        // Leaving the review status: 'active' and 'cancelled' ARE hardcoded
        // switch cases in WC_Subscription::can_be_updated_to(), so the
        // generic filter above is never consulted for them — each has its
        // own per-status filter applied to the switch's result instead.
        add_filter( 'woocommerce_can_subscription_be_updated_to_active', [ $this, 'allow_leaving_review' ], 10, 2 );
        add_filter( 'woocommerce_can_subscription_be_updated_to_cancelled', [ $this, 'allow_leaving_review' ], 10, 2 );
        add_filter( 'woocommerce_can_subscription_be_updated_to_on-hold', [ $this, 'allow_leaving_review' ], 10, 2 );

        add_action( self::TIMEOUT_HOOK, [ $this, 'handle_timeout' ], 10, 2 );

        // Priority 5: reactivate BEFORE AH_Subscription_Next_Payment_Date (10),
        // which only moves the next payment date of 'active' subscriptions and
        // bases it on the order's completion date.
        add_action( 'woocommerce_order_status_completed', [ $this, 'on_stuck_order_completed' ], 5, 1 );
        add_action( 'woocommerce_order_status_changed', [ $this, 'on_stuck_order_status_changed' ], 20, 4 );
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'on_leaving_review' ], 10, 3 );
        add_filter( 'ah_skip_previous_order_validation', [ $this, 'maybe_skip_validation' ], 10, 4 );
    }

    /**
     * Registers the underlying post_status so WordPress/WooCommerce accept
     * it as a real status when set via update_status().
     */
    public function register_status() {
        register_post_status( 'wc-' . RENEWAL_PENDING_REVIEW, [
            'label'                     => _x( 'Renewal On Hold - Needs Review', 'Subscription status', 'bh-features' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Renewal On Hold - Needs Review (%s)',
                'Renewal On Hold - Needs Review (%s)'
            ),
        ] );
    }

    /**
     * Makes the status show up with a proper label in the subscriptions
     * admin list filter, status dropdown, and wcs_get_subscription_statuses().
     */
    public function add_status_label( $statuses ) {
        $statuses[ 'wc-' . RENEWAL_PENDING_REVIEW ] = _x( 'Renewal On Hold - Needs Review', 'Subscription status', 'bh-features' );
        return $statuses;
    }

    /**
     * Only flag_for_review() below is allowed to move a subscription INTO
     * review, and only from ENTRY_STATUSES — matches how it's actually reached.
     */
    public function allow_entering_review( $can_be_updated, $new_status, $subscription ) {
        $new_status = ( 'wc-' === substr( $new_status, 0, 3 ) ) ? substr( $new_status, 3 ) : $new_status;

        if ( $new_status === RENEWAL_PENDING_REVIEW ) {
            return $subscription->has_status( self::ENTRY_STATUSES );
        }

        return $can_be_updated;
    }

    /**
     * CS (or the grace-period timeout) can move a subscription OUT of
     * review: back to 'active' (resolved fine), straight to 'cancelled'
     * (confirmed not fine, no need to wait out the timer), or to 'on-hold'
     * (customer contacted support and the case is being handled). Leaving
     * review also stops the timeout and the reminder emails.
     */
    public function allow_leaving_review( $can_be_updated, $subscription ) {
        if ( $subscription->has_status( RENEWAL_PENDING_REVIEW ) ) {
            return true;
        }

        return $can_be_updated;
    }

    /**
     * Parks a subscription for review instead of cancelling it outright,
     * and schedules the grace-period timeout. Called from
     * validate_previous_order_status_before_renewal() when the previous
     * renewal order is paid but never reached "completed".
     *
     * @param WC_Subscription $subscription
     * @param WC_Order        $stuck_order The previous renewal order that never completed.
     */
    public static function flag_for_review( WC_Subscription $subscription, WC_Order $stuck_order ): void {
        if ( ! $subscription->has_status( self::ENTRY_STATUSES ) ) {
            // The renewal order is already cancelled by the caller — returning
            // silently would leave the subscription stranded with no review and
            // no timeout. Fall back to the pre-review behavior instead.
            $subscription->update_status(
                'cancelled',
                sprintf(
                    'Renewal Order Cancelled: previous order (ID: %d) has not reached "completed" yet (status: "%s") — subscription status "%s" cannot enter review, cancelled instead.',
                    $stuck_order->get_id(),
                    $stuck_order->get_status(),
                    $subscription->get_status()
                )
            );
            return;
        }

        $grace_days = self::get_grace_days();

        $subscription->update_meta_data( self::META_TRIGGER_ORDER, $stuck_order->get_id() );
        $subscription->update_status(
            RENEWAL_PENDING_REVIEW,
            sprintf(
                'Renewal paused for CS review: previous order #%d never reached "completed" (status: "%s") — pending Telegra/CS follow-up. Auto-cancels in %d day(s) if unresolved.',
                $stuck_order->get_id(),
                $stuck_order->get_status(),
                $grace_days
            )
        );

        self::schedule_timeout( $subscription->get_id(), $stuck_order->get_id(), $grace_days );

        // Customer notice + reminders (see review-emails/loader.php).
        do_action( 'ah_renewal_review_started', $subscription, $stuck_order, $grace_days );
    }

    /**
     * Grace period in days before an unresolved review auto-cancels.
     * Adjustable without a settings screen via `wp option update
     * ah_renewal_review_grace_days <n>`, or via the filter.
     */
    private static function get_grace_days(): int {
        $days = (int) get_option( self::GRACE_DAYS_OPTION, self::GRACE_DAYS_DEFAULT );
        $days = apply_filters( 'ah_renewal_review_grace_days', $days > 0 ? $days : self::GRACE_DAYS_DEFAULT );

        return max( 1, (int) $days );
    }

    /**
     * Schedules (once) the Action Scheduler job that resolves the review
     * automatically once the grace period elapses.
     */
    private static function schedule_timeout( int $subscription_id, int $stuck_order_id, int $grace_days ): void {
        $existing = as_get_scheduled_actions( [
            'hook'   => self::TIMEOUT_HOOK,
            'args'   => [ $subscription_id, $stuck_order_id ],
            'status' => \ActionScheduler_Store::STATUS_PENDING,
        ], 'ids' );

        if ( ! empty( $existing ) ) {
            return;
        }

        $timestamp = time() + ( $grace_days * DAY_IN_SECONDS );

        as_schedule_single_action(
            $timestamp,
            self::TIMEOUT_HOOK,
            [ $subscription_id, $stuck_order_id ],
            'renewal-intake-review'
        );
    }

    /**
     * Grace-period timeout callback. Re-checks the stuck order's CURRENT
     * status (it may have completed on its own since flag_for_review() ran)
     * before deciding: reactivate if resolved, cancel if not. Does nothing
     * if CS already resolved it manually in the meantime (subscription is
     * no longer in the review status).
     */
    public function handle_timeout( $subscription_id, $stuck_order_id ) {
        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription instanceof WC_Subscription ) {
            return;
        }

        if ( ! $subscription->has_status( RENEWAL_PENDING_REVIEW ) ) {
            // CS already resolved this manually (reactivated or cancelled).
            $this->logger->info( sprintf(
                '[handle_timeout] subscription_id=%d already resolved (status=%s) — no action',
                $subscription_id,
                $subscription->get_status()
            ), $this->log_context );
            return;
        }

        $stuck_order = wc_get_order( $stuck_order_id );

        if ( $stuck_order && $stuck_order->has_status( 'completed' ) ) {
            // Normally already handled the moment the order completed
            // (on_stuck_order_completed); this is the fallback.
            self::auto_reactivate(
                $subscription,
                sprintf(
                    'Renewal review resolved automatically: order #%d reached "completed" during the %d-day grace period.',
                    $stuck_order_id,
                    self::get_grace_days()
                )
            );

            // Same next payment date rule as a live completion: completion date + plan days.
            if ( class_exists( 'AH_Subscription_Next_Payment_Date' ) && AH_Subscription_Next_Payment_Date::$instance ) {
                AH_Subscription_Next_Payment_Date::$instance->update_on_order_completed( (int) $stuck_order_id );
            }

            $this->logger->info( sprintf(
                '[handle_timeout] subscription_id=%d reactivated — order #%d completed during grace period',
                $subscription_id,
                $stuck_order_id
            ), $this->log_context );

            return;
        }

        $subscription->update_status(
            'cancelled',
            sprintf(
                'Renewal review grace period (%d day(s)) expired with no resolution — order #%d never reached "completed" (status: "%s").',
                self::get_grace_days(),
                $stuck_order_id,
                $stuck_order ? $stuck_order->get_status() : 'unknown/deleted'
            )
        );

        $this->logger->warning( sprintf(
            '[handle_timeout] subscription_id=%d cancelled after grace period — order #%d still not completed (status=%s)',
            $subscription_id,
            $stuck_order_id,
            $stuck_order ? $stuck_order->get_status() : 'unknown/deleted'
        ), $this->log_context );
    }

    /**
     * Reactivation done by this module (not a CS decision).
     */
    private static function auto_reactivate( WC_Subscription $subscription, string $note ): void {
        self::$auto_reactivating = true;

        try {
            $subscription->update_status( 'active', $note );
        } finally {
            self::$auto_reactivating = false;
        }
    }

    /**
     * Subscriptions currently under review because of this order.
     *
     * @param WC_Order $order
     * @return WC_Subscription[]
     */
    private static function subscriptions_reviewing( WC_Order $order ): array {
        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return [];
        }

        $found = [];

        foreach ( wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] ) as $subscription ) {
            if ( $subscription->has_status( RENEWAL_PENDING_REVIEW )
                && (int) $subscription->get_meta( self::META_TRIGGER_ORDER ) === $order->get_id() ) {
                $found[] = $subscription;
            }
        }

        return $found;
    }

    /**
     * The order a subscription is under review for reached "completed":
     * nothing left to review, reactivate right away. Runs before
     * AH_Subscription_Next_Payment_Date, which then sets the next payment date
     * from this order's completion date like for any other completed order.
     *
     * @param int $order_id
     */
    public function on_stuck_order_completed( $order_id ) {
        try {
            $order = wc_get_order( $order_id );

            if ( ! $order instanceof WC_Order ) {
                return;
            }

            foreach ( self::subscriptions_reviewing( $order ) as $subscription ) {
                self::auto_reactivate(
                    $subscription,
                    sprintf( 'Renewal review resolved automatically: order #%d reached "completed".', $order_id )
                );

                $this->logger->info( sprintf(
                    '[on_stuck_order_completed] subscription_id=%d reactivated — order #%d completed',
                    $subscription->get_id(),
                    $order_id
                ), $this->log_context );
            }
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[on_stuck_order_completed] error=%s', $th->getMessage() ), $this->log_context );
        }
    }

    /**
     * The order a subscription is under review for was cancelled or refunded:
     * leave it to CS (the customer may be leaving, or may want to renew on
     * another date), just note it on the subscription.
     *
     * @param int      $order_id
     * @param string   $from
     * @param string   $to
     * @param WC_Order $order
     */
    public function on_stuck_order_status_changed( $order_id, $from, $to, $order ) {
        try {
            if ( ! $order instanceof WC_Order || ! in_array( $to, self::RESOLVED_STATUSES, true ) ) {
                return;
            }

            foreach ( self::subscriptions_reviewing( $order ) as $subscription ) {
                $subscription->add_order_note( sprintf(
                    'Order #%d (the reason this subscription is under review) is now "%s". Needs a CS decision: reactivate the subscription (set the next payment date the customer asked for first) or cancel it. Auto-cancels at the end of the grace period if nobody decides.',
                    $order_id,
                    $to
                ) );
            }
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[on_stuck_order_status_changed] error=%s', $th->getMessage() ), $this->log_context );
        }
    }

    /**
     * Leaving review: drop the pending timeout, and when CS reactivates the
     * subscription, record that decision for the next renewal. Also covers CS
     * moving it review → on-hold while handling the case and reactivating it
     * later: an old trigger order is harmless, maybe_skip_validation() only
     * honors it while it's still the subscription's previous order.
     *
     * @param WC_Subscription $subscription
     * @param string          $new_status
     * @param string          $old_status
     */
    public function on_leaving_review( $subscription, $new_status, $old_status ) {
        try {
            if ( ! $subscription instanceof WC_Subscription || ! in_array( $old_status, [ RENEWAL_PENDING_REVIEW, 'on-hold' ], true ) ) {
                return;
            }

            $stuck_order_id = (int) $subscription->get_meta( self::META_TRIGGER_ORDER );

            if ( ! $stuck_order_id ) {
                return; // Never been under review.
            }

            if ( RENEWAL_PENDING_REVIEW === $old_status && function_exists( 'as_unschedule_all_actions' ) ) {
                as_unschedule_all_actions( self::TIMEOUT_HOOK, [ $subscription->get_id(), $stuck_order_id ], 'renewal-intake-review' );
            }

            if ( 'active' !== $new_status || self::$auto_reactivating || ! current_user_can( 'edit_shop_orders' ) ) {
                return;
            }

            $subscription->update_meta_data( self::META_RESOLVED_ORDER, $stuck_order_id );
            $subscription->save_meta_data();

            $this->logger->info( sprintf(
                '[on_leaving_review] subscription_id=%d reactivated by user #%d — order #%d marked as resolved by CS',
                $subscription->get_id(),
                get_current_user_id(),
                $stuck_order_id
            ), $this->log_context );
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[on_leaving_review] error=%s', $th->getMessage() ), $this->log_context );
        }
    }

    /**
     * Filter callback for ah_skip_previous_order_validation: honor a CS
     * reactivation once, for the order the review was about.
     *
     * @param bool            $skip
     * @param WC_Order        $previous_order
     * @param WC_Order        $renewal_order
     * @param WC_Subscription $subscription
     * @return bool
     */
    public function maybe_skip_validation( $skip, $previous_order, $renewal_order, $subscription ) {
        try {
            if ( $skip || ! $subscription instanceof WC_Subscription || ! $previous_order instanceof WC_Order ) {
                return $skip;
            }

            $resolved_id = (int) $subscription->get_meta( self::META_RESOLVED_ORDER );

            if ( ! $resolved_id ) {
                return $skip;
            }

            // Single use, whatever the outcome.
            $subscription->delete_meta_data( self::META_RESOLVED_ORDER );
            $subscription->save_meta_data();

            if ( $resolved_id !== $previous_order->get_id() || ! $previous_order->has_status( self::RESOLVED_STATUSES ) ) {
                return $skip; // Stale, completed, or still in the pipeline (→ review again).
            }

            $subscription->add_order_note( sprintf(
                'Previous-order check skipped for renewal #%d: previous order #%d is "%s", but CS reactivated this subscription out of renewal review after reviewing that order.',
                $renewal_order->get_id(),
                $previous_order->get_id(),
                $previous_order->get_status()
            ) );

            return true;
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[maybe_skip_validation] error=%s', $th->getMessage() ), $this->log_context );
            return $skip;
        }
    }
}

add_action( 'woocommerce_loaded', function () {
    new AH_Renewal_Intake_Review();
} );

}
