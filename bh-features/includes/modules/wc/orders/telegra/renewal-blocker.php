<?php

if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists( 'AH_Orders_Telegra_Renewal_Blocker' ) ) {

class AH_Orders_Telegra_Renewal_Blocker {

    /** @var WC_Logger */
    protected $logger;

    /** @var string WC log source name */
    protected $log_source = 'ah-renewal-blocker';

    public function __construct() {

        // Initialize logger
        $this->logger = wc_get_logger();

        add_filter(
            'wcs_renewal_order_created',
            [ $this, 'block_subscription_renewal' ],
            10,
            2
        );
    }

    /**
     * Wrapper to log in WooCommerce → Status → Logs.
     */
    protected function log( $message, $level = 'info' ) {
        $this->logger->log(
            $level,
            $message,
            [ 'source' => $this->log_source ]
        );
    }

    /**
     * Block renewal orders based on date range + restricted states.
     * FIRST FILTER MUST BE THE DATE RANGE.
     */
    public function block_subscription_renewal( $renewal_order, $subscription ) {

        try {
            if ( ! is_a( $renewal_order, 'WC_Order' ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
                $this->log("Skip: invalid objects for renewal hook.");
                return $renewal_order;
            }

            $sub_id = $subscription->get_id();

            // --------------------------------------------------
            // 1) FIRST FILTER: STATES & RULES
            // --------------------------------------------------

            $restricted_states = ['CT', 'FL'];
            $allowed_during_period     = AH_Licensed_States_Manager::get_state_codes_by_status('available');
            $print_allowed_during_period = implode( ', ', $allowed_during_period );
            $this->log("- WHITELIST States: {$print_allowed_during_period}.");

            $state = strtoupper( $subscription->get_shipping_state() );
            /*
            if ( ! in_array( $state, $restricted_states, true ) ) {
                $this->log("Allow sub #{$sub_id}: State {$state} not restricted.");
                return $renewal_order;
            }
            */
            if ( in_array( $state, $allowed_during_period, true ) ) {
                $this->log("Allow sub #{$sub_id}: State {$state} is WHITELISTED.");
                return $renewal_order;
            }

            // LOG blocked action
            $this->log("BLOCK renewal for sub #{$sub_id} — Restricted State={$state}");

            // --------------------------------------------------
            // 2) SECOND FILTER: DATE RANGE VALIDATION
            // --------------------------------------------------

            $next_payment_ts = (new DateTimeImmutable('now', wp_timezone()))->getTimestamp();

            if ( empty( $next_payment_ts ) ) {
                $this->log("Skip sub #{$sub_id}: No next_payment date.");
                return $renewal_order;
            }

            $tz = wp_timezone();

            $range_start = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                '2025-12-09 00:00:00',
                $tz
            )->getTimestamp();

            $range_end = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                '2026-01-15 23:59:59',
                $tz
            )->getTimestamp();

            if ( $next_payment_ts < $range_start || $next_payment_ts > $range_end ) {
                $this->log("Allow sub #{$sub_id}: next_payment (" . gmdate('Y-m-d H:i:s', $next_payment_ts) . ") outside restricted window.");
                return $renewal_order;
            }

            $this->log("Sub #{$sub_id}: next_payment within blackout window: (".gmdate('Y-m-d H:i:s', $next_payment_ts).")");

            // --------------------------------------------------
            // 3) BLOCK THE RENEWAL ORDER
            // --------------------------------------------------

            $reason = "Renewal blocked: state {$state} restricted during blackout period.";

            $renewal_order->update_status( 'wc-cancelled', $reason );
            $renewal_order->update_meta_data( '_mark_for_cleanup', 1 );
            $renewal_order->save();

            $this->log("Marked renewal order #{$renewal_order->get_id()} for cleanup.");

            // --------------------------------------------------
            // 4) UPDATE SUBSCRIPTION
            // --------------------------------------------------

            $subscription->update_status('on-hold');
            $subscription->update_meta_data('_on_hold_facility_move', 1);
            $subscription->update_meta_data('_cancelled_state_' . strtolower($state), 1);

            $subscription->add_order_note(
                "⚠️ Renewal skipped. Subscription on-hold due to {$state} restrictions (09 Dec 2025 → 15 Jan 2026)."
            );

            $subscription->save();

            $this->log("Subscription #{$sub_id} moved to on-hold (facility_move flag set).");

        } catch (Throwable $e) {
            $this->log("ERROR: " . $e->getMessage(), 'error');
        }

        return $renewal_order;
    }
}

add_action( 'woocommerce_loaded', function() {
    if ( class_exists( 'AH_Orders_Telegra_Renewal_Blocker' ) ) {
        new AH_Orders_Telegra_Renewal_Blocker();
    }
});

}