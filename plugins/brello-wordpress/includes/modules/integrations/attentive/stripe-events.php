<?php
/**
 * Attentive Stripe Events Handler
 *
 * WC/Stripe hooks enqueue Action Scheduler jobs immediately and return.
 * All HTTP calls to Attentive happen inside the AS workers (async, background).
 * Deduplication uses BH_Attentive_Events_Log in bh_external DB —
 * no metas written to wc_orders_meta or subscription meta.
 *
 * AS hooks registered here:
 *   bh_attentive_process_payment_failed    — all payment failure scenarios
 *   bh_attentive_process_payment_recovered — payment recovery
 *   bh_attentive_process_card_expiring     — card expiring per subscription
 *
 * @package    BH_Features
 * @subpackage Integrations/Attentive
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Stripe_Events {

    /**
     * Initialize hooks
     */
    public function __construct() {

        // ── Payment failed — enqueue only ──────────────────────────────────
        add_action( 'woocommerce_subscription_renewal_payment_failed',
            [ $this, 'enqueue_renewal_payment_failed' ], 20, 2 );

        add_action( 'wc_gateway_stripe_process_payment_error',
            [ $this, 'enqueue_stripe_payment_error' ], 20, 2 );

        add_action( 'woocommerce_order_status_changed',
            [ $this, 'enqueue_order_status_payment_failure' ], 20, 4 );

        // ── Payment recovered — enqueue only ───────────────────────────────
        add_action( 'woocommerce_subscription_renewal_payment_complete',
            [ $this, 'enqueue_payment_recovered' ], 20, 2 );

        add_action( 'woocommerce_order_status_changed',
            [ $this, 'enqueue_order_status_recovery' ], 21, 4 );

        // ── Card expiring — cron check ─────────────────────────────────────
        add_action( 'bh_attentive_check_expiring_cards', [ $this, 'check_expiring_cards' ] );

        if ( ! wp_next_scheduled( 'bh_attentive_check_expiring_cards' ) ) {
            wp_schedule_event( time(), 'daily', 'bh_attentive_check_expiring_cards' );
        }

        // ── AS workers ─────────────────────────────────────────────────────
        add_action( 'bh_attentive_process_payment_failed',    [ $this, 'worker_payment_failed' ],    10, 1 );
        add_action( 'bh_attentive_process_payment_recovered', [ $this, 'worker_payment_recovered' ], 10, 1 );
        add_action( 'bh_attentive_process_card_expiring',     [ $this, 'worker_card_expiring' ],     10, 1 );

        // Note: no "initialized" log here — it fired on every page load / AS job
        // and flooded the log, making real entries hard to find.
    }

    // =========================================================================
    // ENQUEUE — payment failed
    // =========================================================================

    /**
     * Hook: woocommerce_subscription_renewal_payment_failed
     */
    public function enqueue_renewal_payment_failed( $subscription, $renewal_order ): void {

        if ( ! $renewal_order instanceof WC_Order ) {
            return;
        }

        $this->enqueue_failed( $renewal_order->get_id(), 'renewal_payment_failed', [
            'subscription_id' => (string) $subscription->get_id(),
        ] );
    }

    /**
     * Hook: wc_gateway_stripe_process_payment_error
     */
    public function enqueue_stripe_payment_error( $error, $order ): void {

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $reason = is_string( $error ) ? $error : 'payment_error';

        $this->enqueue_failed( $order->get_id(), $reason );
    }

    /**
     * Hook: woocommerce_order_status_changed (priority 20)
     * Catches: Pre-Authorization Expired, card declined, insufficient funds, etc.
     */
    public function enqueue_order_status_payment_failure( int $order_id, string $old_status, string $new_status, $order ): void {

        if ( ! in_array( $new_status, [ 'failed', 'cancelled' ], true ) ) {
            return;
        }

        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        $failure_reason = $this->detect_stripe_failure_from_notes( $order_id );

        if ( $failure_reason ) {
            $this->enqueue_failed( $order_id, $failure_reason );
        }
    }

    // =========================================================================
    // ENQUEUE — payment recovered
    // =========================================================================

    /**
     * Hook: woocommerce_subscription_renewal_payment_complete
     */
    public function enqueue_payment_recovered( $subscription, $renewal_order ): void {

        if ( ! $renewal_order instanceof WC_Order ) {
            return;
        }

        as_enqueue_async_action(
            'bh_attentive_process_payment_recovered',
            [ [
                'order_id'        => $renewal_order->get_id(),
                'subscription_id' => (string) $subscription->get_id(),
            ] ],
            'bh-attentive'
        );
    }

    /**
     * Hook: woocommerce_order_status_changed (priority 21)
     * Catches manual recovery: order moves to processing/completed after a failure.
     */
    public function enqueue_order_status_recovery( int $order_id, string $old_status, string $new_status, $order ): void {

        if ( ! in_array( $new_status, [ 'processing', 'completed' ], true ) ) {
            return;
        }

        // Only enqueue if there was a recorded payment failure for this order.
        if ( ! BH_Attentive_Events_Log::order_is_active( $order_id, BH_Attentive_Events_Log::EVENT_PAYMENT_FAILED ) ) {
            return;
        }

        as_enqueue_async_action(
            'bh_attentive_process_payment_recovered',
            [ [ 'order_id' => $order_id ] ],
            'bh-attentive'
        );
    }

    // =========================================================================
    // WORKERS — payment failed
    // =========================================================================

    /**
     * AS hook: bh_attentive_process_payment_failed
     *
     * @param array $args  [ 'order_id' => int, 'failure_reason' => string, 'extra_props' => array ]
     */
    public function worker_payment_failed( array $args ): void {

        $order_id       = (int) ( $args['order_id'] ?? 0 );
        $failure_reason = $args['failure_reason'] ?? 'unknown';
        $extra_props    = $args['extra_props'] ?? [];

        if ( ! $order_id ) {
            return;
        }

        // Deduplication — one payment_failed event per order.
        if ( BH_Attentive_Events_Log::order_was_triggered( $order_id, BH_Attentive_Events_Log::EVENT_PAYMENT_FAILED ) ) {
            BH_Attentive_Helper::log( '[Stripe] Payment failed already sent — skipping', [
                'order_id' => $order_id,
            ] );
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $phone = BH_Attentive_Helper::normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            return;
        }

        BH_Attentive_Helper::log( '[Stripe] Worker: sending payment failed event', [
            'order_id'       => $order_id,
            'failure_reason' => $failure_reason,
        ] );

        BH_Attentive_Helper::subscribe_user( $phone, $email );

        $properties = array_merge( [
            'failure_reason'     => $failure_reason,
            'order_id'           => (string) $order_id,
            'order_number'       => $order->get_order_number(),
            'order_total'        => $order->get_total(),
            'currency'           => $order->get_currency(),
            'payment_update_url' => $order->get_checkout_payment_url(),
        ], $extra_props );

        BH_Attentive_Helper::send_event( 'Stripe_Payment_Failed', $phone, $email, $properties, false );

        BH_Attentive_Helper::set_attributes( $phone, $email, [
            'payment_failed'      => true,
            'last_failure_reason' => $failure_reason,
        ], false );

        // Record in external DB — also stores failure_reason for recovery to read.
        BH_Attentive_Events_Log::order_mark_triggered(
            $order_id,
            BH_Attentive_Events_Log::EVENT_PAYMENT_FAILED,
            [ 'failure_reason' => $failure_reason ]
        );

        BH_Attentive_Helper::log( '[Stripe] Payment failed event sent', [ 'order_id' => $order_id ] );
    }

    // =========================================================================
    // WORKERS — payment recovered
    // =========================================================================

    /**
     * AS hook: bh_attentive_process_payment_recovered
     *
     * @param array $args  [ 'order_id' => int, 'subscription_id' => string (optional) ]
     */
    public function worker_payment_recovered( array $args ): void {

        $order_id        = (int) ( $args['order_id'] ?? 0 );
        $subscription_id = $args['subscription_id'] ?? '';

        if ( ! $order_id ) {
            return;
        }

        // Only process if there is an active (unresolved) payment failure.
        if ( ! BH_Attentive_Events_Log::order_is_active( $order_id, BH_Attentive_Events_Log::EVENT_PAYMENT_FAILED ) ) {
            BH_Attentive_Helper::log( '[Stripe] No active payment failure — skipping recovery', [
                'order_id' => $order_id,
            ] );
            return;
        }

        // Deduplication — one recovery event per order.
        if ( BH_Attentive_Events_Log::order_was_triggered( $order_id, BH_Attentive_Events_Log::EVENT_PAYMENT_RECOVERED ) ) {
            BH_Attentive_Helper::log( '[Stripe] Recovery already sent — skipping', [
                'order_id' => $order_id,
            ] );
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $phone = BH_Attentive_Helper::normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            return;
        }

        // Read original failure reason from external DB.
        $failure_event    = BH_Attentive_Events_Log::order_get_event( $order_id, BH_Attentive_Events_Log::EVENT_PAYMENT_FAILED );
        $original_failure = $failure_event['extra']['failure_reason'] ?? 'unknown';

        BH_Attentive_Helper::log( '[Stripe] Worker: sending payment recovered event', [
            'order_id'         => $order_id,
            'original_failure' => $original_failure,
        ] );

        BH_Attentive_Helper::subscribe_user( $phone, $email );

        $properties = [
            'order_id'         => (string) $order_id,
            'order_number'     => $order->get_order_number(),
            'order_total'      => $order->get_total(),
            'currency'         => $order->get_currency(),
            'recovery_time'    => current_time( 'mysql' ),
            'original_failure' => $original_failure,
        ];

        if ( $subscription_id ) {
            $properties['subscription_id'] = $subscription_id;
        }

        BH_Attentive_Helper::send_event( 'Stripe_Payment_Recovered', $phone, $email, $properties, false );

        BH_Attentive_Helper::set_attributes( $phone, $email, [
            'payment_failed'    => false,
            'payment_recovered' => true,
        ], false );

        // Mark recovery in external DB + resolve the original failure.
        BH_Attentive_Events_Log::order_mark_triggered( $order_id, BH_Attentive_Events_Log::EVENT_PAYMENT_RECOVERED );
        BH_Attentive_Events_Log::order_mark_resolved(
            $order_id,
            BH_Attentive_Events_Log::EVENT_PAYMENT_FAILED,
            BH_Attentive_Events_Log::REASON_RECOVERED
        );

        BH_Attentive_Helper::log( '[Stripe] Payment recovered event sent', [ 'order_id' => $order_id ] );
    }

    // =========================================================================
    // CARD EXPIRING — cron enqueue + worker
    // =========================================================================

    /**
     * Daily cron: enqueue one AS job per expiring card found.
     * Hook: bh_attentive_check_expiring_cards
     */
    public function check_expiring_cards(): void {

        BH_Attentive_Helper::log( '[Stripe] Starting expiring cards check' );

        if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
            return;
        }

        $subscriptions = wcs_get_subscriptions( [
            'subscription_status'    => 'active',
            'subscriptions_per_page' => -1,
        ] );

        if ( empty( $subscriptions ) ) {
            return;
        }

        $threshold_days = 30;
        $enqueued       = 0;

        foreach ( $subscriptions as $subscription ) {

            [ $exp_month, $exp_year ] = $this->get_card_expiry( $subscription );

            if ( ! $exp_month || ! $exp_year ) {
                continue;
            }

            $exp_date       = strtotime( "{$exp_year}-{$exp_month}-01" );
            $threshold_date = strtotime( "+{$threshold_days} days" );

            if ( $exp_date > $threshold_date || $exp_date <= time() ) {
                continue;
            }

            // Deduplication: one notification per subscription per month.
            $event_key = BH_Attentive_Events_Log::EVENT_CARD_EXPIRING . '_' . $exp_year . $exp_month;

            if ( BH_Attentive_Events_Log::sub_was_triggered( $subscription->get_id(), $event_key ) ) {
                continue;
            }

            as_enqueue_async_action(
                'bh_attentive_process_card_expiring',
                [ [
                    'subscription_id' => $subscription->get_id(),
                    'exp_month'       => $exp_month,
                    'exp_year'        => $exp_year,
                ] ],
                'bh-attentive'
            );

            $enqueued++;
        }

        BH_Attentive_Helper::log( '[Stripe] Expiring cards check complete', [ 'enqueued' => $enqueued ] );
    }

    /**
     * AS hook: bh_attentive_process_card_expiring
     *
     * @param array $args  [ 'subscription_id' => int, 'exp_month' => string, 'exp_year' => string ]
     */
    public function worker_card_expiring( array $args ): void {

        $subscription_id = (int) ( $args['subscription_id'] ?? 0 );
        $exp_month       = $args['exp_month'] ?? '';
        $exp_year        = $args['exp_year'] ?? '';

        if ( ! $subscription_id || ! $exp_month || ! $exp_year ) {
            return;
        }

        $event_key = BH_Attentive_Events_Log::EVENT_CARD_EXPIRING . '_' . $exp_year . $exp_month;

        // Double-check deduplication inside the worker.
        if ( BH_Attentive_Events_Log::sub_was_triggered( $subscription_id, $event_key ) ) {
            return;
        }

        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription ) {
            return;
        }

        $phone = BH_Attentive_Helper::normalize_phone( $subscription->get_billing_phone() );
        $email = $subscription->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            return;
        }

        BH_Attentive_Helper::log( '[Stripe] Worker: sending card expiring event', [
            'subscription_id' => $subscription_id,
            'exp_month'       => $exp_month,
            'exp_year'        => $exp_year,
        ] );

        BH_Attentive_Helper::subscribe_user( $phone, $email );

        $exp_date = strtotime( "{$exp_year}-{$exp_month}-01" );

        BH_Attentive_Helper::send_event( 'Stripe_Card_Expiring', $phone, $email, [
            'subscription_id'   => (string) $subscription_id,
            'card_exp_month'    => $exp_month,
            'card_exp_year'     => $exp_year,
            'days_until_expiry' => max( 0, (int) ceil( ( $exp_date - time() ) / DAY_IN_SECONDS ) ),
        ], false );

        BH_Attentive_Helper::set_attributes( $phone, $email, [
            'card_expiring' => true,
        ], false );

        BH_Attentive_Events_Log::sub_mark_triggered( $subscription_id, $event_key, [
            'exp_month' => $exp_month,
            'exp_year'  => $exp_year,
        ] );

        BH_Attentive_Helper::log( '[Stripe] Card expiring event sent', [
            'subscription_id' => $subscription_id,
        ] );
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Enqueue a payment failed AS job.
     */
    private function enqueue_failed( int $order_id, string $failure_reason, array $extra_props = [] ): void {

        as_enqueue_async_action(
            'bh_attentive_process_payment_failed',
            [ [
                'order_id'       => $order_id,
                'failure_reason' => $failure_reason,
                'extra_props'    => $extra_props,
            ] ],
            'bh-attentive'
        );

        BH_Attentive_Helper::log( '[Stripe] Enqueued payment failed job', [
            'order_id'       => $order_id,
            'failure_reason' => $failure_reason,
        ] );
    }

    /**
     * Inspect recent order notes to detect a Stripe-related failure reason.
     */
    private function detect_stripe_failure_from_notes( int $order_id ): ?string {

        $notes = wc_get_order_notes( [ 'order_id' => $order_id, 'limit' => 10 ] );

        foreach ( $notes as $note ) {
            $text = strtolower( $note->content );

            if ( strpos( $text, 'pre-authorization expired' ) !== false
              || strpos( $text, 'authorization expired' ) !== false ) {
                return 'authorization_expired';
            }

            if ( strpos( $text, 'card declined' ) !== false
              || strpos( $text, 'card was declined' ) !== false ) {
                return 'card_declined';
            }

            if ( strpos( $text, 'insufficient funds' ) !== false ) {
                return 'insufficient_funds';
            }

            if ( strpos( $text, 'expired card' ) !== false ) {
                return 'expired_card';
            }

            if ( strpos( $text, 'stripe' ) !== false
              && ( strpos( $text, 'failed' ) !== false || strpos( $text, 'error' ) !== false ) ) {
                return 'stripe_error';
            }
        }

        return null;
    }

    /**
     * Get card expiry from subscription meta or default payment token.
     *
     * @return array  [ exp_month, exp_year ] — both empty string if not found.
     */
    private function get_card_expiry( $subscription ): array {

        $exp_month = $subscription->get_meta( '_stripe_card_exp_month', true );
        $exp_year  = $subscription->get_meta( '_stripe_card_exp_year', true );

        if ( empty( $exp_month ) || empty( $exp_year ) ) {
            if ( class_exists( 'WC_Payment_Tokens' ) ) {
                $tokens = WC_Payment_Tokens::get_customer_tokens( $subscription->get_customer_id(), 'stripe' );
                foreach ( $tokens as $token ) {
                    if ( $token->is_default() && method_exists( $token, 'get_expiry_month' ) ) {
                        $exp_month = $token->get_expiry_month();
                        $exp_year  = $token->get_expiry_year();
                        break;
                    }
                }
            }
        }

        return [ $exp_month ?: '', $exp_year ?: '' ];
    }
}