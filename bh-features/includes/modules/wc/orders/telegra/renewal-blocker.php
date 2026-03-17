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

        add_action( 
            'woocommerce_checkout_process', 
            [ $this, 'block_checkout_for_invalid_renewals' ] 
        );

        add_filter( 
            'wcs_can_subscription_be_renewed', 
            [ $this, 'block_automatic_renewals_by_state' ], 
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
     * Expired Jan 15
     */
    public function block_subscription_renewal__( $renewal_order, $subscription ) {

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

    /**
     * Block renewal orders based on restricted states.
     */
    public function block_subscription_renewal( $renewal_order, $subscription ) {

        try {
            if ( ! is_a( $renewal_order, 'WC_Order' ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
                return $renewal_order;
            }

            $sub_id = $subscription->get_id();

            $allowed_during_period     = AH_Licensed_States_Manager::get_state_codes_by_status('available');
            $state = strtoupper( $subscription->get_shipping_state() );

            if ( in_array( $state, $allowed_during_period, true ) ) {
                //$this->log("Allow sub #{$sub_id}: State {$state} is WHITELISTED.");
                return $renewal_order;
            }

            // LOG blocked action
            //$this->log("BLOCK renewal for sub #{$sub_id} — Restricted State={$state}");
            $reason = "Renewal blocked: state {$state} restricted.";

            $renewal_order->update_status( 'wc-cancelled', $reason );
            $renewal_order->update_meta_data( '_mark_for_cleanup', 1 );
            $renewal_order->save();

            $subscription->update_status('on-hold');
            $subscription->update_meta_data('_on_hold_facility_move', 1);
            $subscription->update_meta_data('_cancelled_state_' . strtolower($state), 1);

            $subscription->add_order_note(
                "⚠️ Renewal skipped. Subscription on-hold due to {$state} restrictions (SEP Facility Move)."
            );

            $subscription->save();

            //$this->log("Subscription #{$sub_id} moved to on-hold (facility_move flag set).");

        } catch (Throwable $e) {
            $this->log("ERROR: " . $e->getMessage(), 'error');
        }

        return $renewal_order;
    }


    public function block_checkout_for_invalid_renewals() {

        if ( !function_exists( 'wcs_cart_contains_renewal' ) || !wcs_cart_contains_renewal() ) {
             return ;
        }

        if ( ! WC()->cart ) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $item ) {

            if ( empty( $item['subscription_renewal']['subscription_id'] ) ) {
                continue;
            }

            $subscription_id = absint( $item['subscription_renewal']['subscription_id'] );
            $subscription    = wcs_get_subscription( $subscription_id );

            if ( ! $subscription instanceof WC_Subscription ) {
                continue;
            }

            $state = strtoupper( $subscription->get_shipping_state() );

            if ( ! AH_States::is_allowed( $state ) ) {

                wc_get_logger()->warning(
                    'Checkout blocked: early renewal not allowed for state',
                    [
                        'source' => $this->log_source,
                        'subscription_id' => $subscription_id,
                        'state' => $state,
                    ]
                );

                wc_add_notice(
                    'Renewals are currently unavailable for your state (' . esc_html( $state ) . ').',
                    'error'
                );

                return;
            }
        }
    }

    public function block_automatic_renewals_by_state( $can_renew, $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return $can_renew;
        }

        $state = strtoupper( $subscription->get_shipping_state() );

        if ( ! AH_States::is_allowed( $state ) ) {

            wc_get_logger()->warning(
                'Automatic renewal blocked: state not allowed',
                [
                    'source' => $this->log_source,
                    'subscription_id' => $subscription->get_id(),
                    'state' => $state,
                ]
            );

            return false;
        }

        return $can_renew;
    }
}

add_action( 'woocommerce_loaded', function() {
    if ( class_exists( 'AH_Orders_Telegra_Renewal_Blocker' ) ) {
        new AH_Orders_Telegra_Renewal_Blocker();
    }
});

}