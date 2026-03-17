<?php
/**
 * Attentive Stripe Events Handler
 * 
 * Handles ALL Stripe payment events and sends to Attentive
 * Priority #2: payment_failed, card_expiring, payment_recovered
 * 
 * Covers ALL payment failure scenarios:
 * - Subscription renewal payment failed
 * - Pre-Authorization Expired
 * - Card declined on checkout
 * - General Stripe payment errors
 * 
 * Uses BH_Attentive_Helper for shared functions
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Stripe_Events {

    /**
     * Initialize Stripe events handler
     */
    public function __construct() {
        
        // ============================================================
        // PAYMENT FAILED HOOKS
        // ============================================================
        
        // 1. Subscription renewal payment failed
        add_action(
            'woocommerce_subscription_renewal_payment_failed',
            [ $this, 'handle_renewal_payment_failed' ],
            20,
            2
        );

        // 2. General Stripe payment error (checkout failures)
        add_action(
            'wc_gateway_stripe_process_payment_error',
            [ $this, 'handle_stripe_payment_error' ],
            20,
            2
        );

        // 3. Order status changed - catch Pre-Authorization Expired & Failed orders
        add_action(
            'woocommerce_order_status_changed',
            [ $this, 'handle_order_status_for_payment_failure' ],
            20,
            4
        );

        // ============================================================
        // PAYMENT RECOVERED HOOKS
        // ============================================================
        
        // Subscription renewal payment complete (for recovery detection)
        add_action(
            'woocommerce_subscription_renewal_payment_complete',
            [ $this, 'handle_payment_recovered' ],
            20,
            2
        );

        // Order status changed to completed/processing after failed (recovery)
        add_action(
            'woocommerce_order_status_changed',
            [ $this, 'handle_order_status_for_recovery' ],
            21,
            4
        );

        // ============================================================
        // CARD EXPIRING HOOKS
        // ============================================================
        
        // Check for expiring cards (runs daily via WP Cron)
        add_action(
            'bh_attentive_check_expiring_cards',
            [ $this, 'check_expiring_cards' ]
        );

        // Schedule daily cron if not exists
        if ( ! wp_next_scheduled( 'bh_attentive_check_expiring_cards' ) ) {
            wp_schedule_event( time(), 'daily', 'bh_attentive_check_expiring_cards' );
        }

        BH_Attentive_Helper::log( '[Stripe] Events handler initialized with full coverage' );
    }

    // ============================================================
    // PAYMENT FAILED HANDLERS
    // ============================================================

    /**
     * Handle subscription renewal payment failed
     * Hook: woocommerce_subscription_renewal_payment_failed
     */
    public function handle_renewal_payment_failed( $subscription, $renewal_order ) {
        
        if ( ! $renewal_order instanceof WC_Order ) {
            return;
        }

        $this->send_payment_failed_event( $renewal_order, 'renewal_payment_failed', [
            'subscription_id' => (string) $subscription->get_id(),
        ] );
    }

    /**
     * Handle Stripe payment error directly (checkout failures)
     * Hook: wc_gateway_stripe_process_payment_error
     */
    public function handle_stripe_payment_error( $error, $order ) {
        
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $error_message = is_string( $error ) ? $error : 'payment_error';
        
        $this->send_payment_failed_event( $order, $error_message );
    }

    /**
     * Handle order status changes for payment failures
     * Catches: Pre-Authorization Expired, Failed status, etc.
     * Hook: woocommerce_order_status_changed
     */
    public function handle_order_status_for_payment_failure( $order_id, $old_status, $new_status, $order ) {
        
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        // Only check specific status changes that indicate payment failure
        $failure_statuses = [ 'failed', 'cancelled' ];
        
        if ( ! in_array( $new_status, $failure_statuses, true ) ) {
            return;
        }

        // Get recent order notes to check for Stripe-related failures
        $notes = wc_get_order_notes( [
            'order_id' => $order_id,
            'limit'    => 10,
        ] );

        $failure_reason = null;

        foreach ( $notes as $note ) {
            $note_content = strtolower( $note->content );
            
            // Check for Pre-Authorization Expired
            if ( strpos( $note_content, 'pre-authorization expired' ) !== false ) {
                $failure_reason = 'authorization_expired';
                break;
            }
            
            // Check for authorization expired
            if ( strpos( $note_content, 'authorization expired' ) !== false ) {
                $failure_reason = 'authorization_expired';
                break;
            }
            
            // Check for card declined
            if ( strpos( $note_content, 'card declined' ) !== false || 
                 strpos( $note_content, 'card was declined' ) !== false ) {
                $failure_reason = 'card_declined';
                break;
            }
            
            // Check for insufficient funds
            if ( strpos( $note_content, 'insufficient funds' ) !== false ) {
                $failure_reason = 'insufficient_funds';
                break;
            }
            
            // Check for expired card
            if ( strpos( $note_content, 'expired card' ) !== false ) {
                $failure_reason = 'expired_card';
                break;
            }

            // Check for general Stripe failure
            if ( strpos( $note_content, 'stripe' ) !== false && 
                 ( strpos( $note_content, 'failed' ) !== false || strpos( $note_content, 'error' ) !== false ) ) {
                $failure_reason = 'stripe_error';
                break;
            }
        }

        // If we found a Stripe-related failure reason, send event
        if ( $failure_reason ) {
            $this->send_payment_failed_event( $order, $failure_reason );
        }
    }

    /**
     * Core function to send payment failed event
     * Used by all payment failure handlers
     */
    private function send_payment_failed_event( WC_Order $order, $failure_reason, $extra_props = [] ) {
        
        // Prevent duplicate processing
        $meta_key = '_attentive_payment_failed_sent';
        if ( $order->get_meta( $meta_key, true ) ) {
            BH_Attentive_Helper::log( '[Stripe] Payment failed event already sent - skipping', [
                'order_id' => $order->get_id(),
            ] );
            return;
        }

        BH_Attentive_Helper::log( '[Stripe] Processing payment failed event', [
            'order_id'       => $order->get_id(),
            'failure_reason' => $failure_reason,
        ] );

        $phone = BH_Attentive_Helper::normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            BH_Attentive_Helper::log( '[Stripe] No phone or email - skipping', [
                'order_id' => $order->get_id(),
            ] );
            return;
        }

        // Ensure user is subscribed to Attentive
        BH_Attentive_Helper::subscribe_user( $phone, $email );

        // Generate dynamic payment recovery URL for Attentive email CTA
        $payment_update_url = $order->get_checkout_payment_url();

        // Build properties
        $properties = array_merge( [
            'failure_reason'     => $failure_reason,
            'order_id'           => (string) $order->get_id(),
            'order_number'       => $order->get_order_number(),
            'order_total'        => $order->get_total(),
            'currency'           => $order->get_currency(),
            'payment_update_url' => $payment_update_url,
        ], $extra_props );

        // Send payment failed event
        BH_Attentive_Helper::send_event( 'Stripe_Payment_Failed', $phone, $email, $properties );

        // Set Custom Attributes for Segments
        BH_Attentive_Helper::set_attributes( $phone, $email, [
            'payment_failed'      => true,
            'last_failure_reason' => $failure_reason,
        ] );

        // Mark as sent
        $order->update_meta_data( $meta_key, 'yes' );
        $order->update_meta_data( '_attentive_payment_failed_time', current_time( 'mysql' ) );
        $order->update_meta_data( '_attentive_payment_failed_reason', $failure_reason );
        $order->save();

        BH_Attentive_Helper::log( '[Stripe] Payment failed event sent successfully', [
            'order_id'       => $order->get_id(),
            'failure_reason' => $failure_reason,
        ] );
    }

    // ============================================================
    // PAYMENT RECOVERED HANDLERS
    // ============================================================

    /**
     * Handle subscription renewal payment complete (for recovery detection)
     * Hook: woocommerce_subscription_renewal_payment_complete
     */
    public function handle_payment_recovered( $subscription, $renewal_order ) {
        
        if ( ! $renewal_order instanceof WC_Order ) {
            return;
        }

        $this->send_payment_recovered_event( $renewal_order, [
            'subscription_id' => (string) $subscription->get_id(),
        ] );
    }

    /**
     * Handle order status change to completed/processing after failed
     * Hook: woocommerce_order_status_changed
     */
    public function handle_order_status_for_recovery( $order_id, $old_status, $new_status, $order ) {
        
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        // Only check if moving to success status
        $success_statuses = [ 'processing', 'completed' ];
        
        if ( ! in_array( $new_status, $success_statuses, true ) ) {
            return;
        }

        // Check if this was previously a failed payment
        $was_failed = $order->get_meta( '_attentive_payment_failed_sent', true );
        
        if ( ! $was_failed ) {
            return;
        }

        $this->send_payment_recovered_event( $order );
    }

    /**
     * Core function to send payment recovered event
     */
    private function send_payment_recovered_event( WC_Order $order, $extra_props = [] ) {
        
        // Check if this was a recovery (order previously had payment failed)
        $was_failed = $order->get_meta( '_attentive_payment_failed_sent', true );
        
        if ( ! $was_failed ) {
            // Not a recovery, just normal payment - skip
            return;
        }

        // Prevent duplicate processing
        $meta_key = '_attentive_payment_recovered_sent';
        if ( $order->get_meta( $meta_key, true ) ) {
            BH_Attentive_Helper::log( '[Stripe] Payment recovered event already sent - skipping', [
                'order_id' => $order->get_id(),
            ] );
            return;
        }

        BH_Attentive_Helper::log( '[Stripe] Processing payment recovered event', [
            'order_id' => $order->get_id(),
        ] );

        $phone = BH_Attentive_Helper::normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            return;
        }

        // Ensure user is subscribed
        BH_Attentive_Helper::subscribe_user( $phone, $email );

        // Get original failure reason
        $original_failure = $order->get_meta( '_attentive_payment_failed_reason', true );

        // Build properties
        $properties = array_merge( [
            'order_id'         => (string) $order->get_id(),
            'order_number'     => $order->get_order_number(),
            'order_total'      => $order->get_total(),
            'currency'         => $order->get_currency(),
            'recovery_time'    => current_time( 'mysql' ),
            'original_failure' => $original_failure ?: 'unknown',
        ], $extra_props );

        // Send payment recovered event
        BH_Attentive_Helper::send_event( 'Stripe_Payment_Recovered', $phone, $email, $properties );

        // Set Custom Attributes for Segments (reset payment_failed)
        BH_Attentive_Helper::set_attributes( $phone, $email, [
            'payment_failed'    => false,
            'payment_recovered' => true,
        ] );

        // Mark as sent
        $order->update_meta_data( $meta_key, 'yes' );
        $order->save();

        BH_Attentive_Helper::log( '[Stripe] Payment recovered event sent successfully', [
            'order_id' => $order->get_id(),
        ] );
    }

    // ============================================================
    // CARD EXPIRING HANDLER
    // ============================================================

    /**
     * Check for expiring cards (runs daily)
     * Sends Stripe_Card_Expiring event for cards expiring within 30 days
     */
    public function check_expiring_cards() {
        
        BH_Attentive_Helper::log( '[Stripe] Starting expiring cards check' );

        // Check if WooCommerce Subscriptions is active
        if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
            BH_Attentive_Helper::log( '[Stripe] WooCommerce Subscriptions not active' );
            return;
        }

        // Get all active subscriptions
        $subscriptions = wcs_get_subscriptions( [
            'subscription_status'    => 'active',
            'subscriptions_per_page' => -1,
        ] );

        if ( empty( $subscriptions ) ) {
            BH_Attentive_Helper::log( '[Stripe] No active subscriptions found' );
            return;
        }

        $expiring_threshold_days = 30;
        $cards_checked = 0;
        $expiring_found = 0;

        foreach ( $subscriptions as $subscription ) {
            
            // Get card expiry from subscription meta
            $exp_month = $subscription->get_meta( '_stripe_card_exp_month', true );
            $exp_year = $subscription->get_meta( '_stripe_card_exp_year', true );

            if ( empty( $exp_month ) || empty( $exp_year ) ) {
                // Try to get from payment token
                if ( class_exists( 'WC_Payment_Tokens' ) ) {
                    $tokens = WC_Payment_Tokens::get_customer_tokens( $subscription->get_customer_id(), 'stripe' );
                    foreach ( $tokens as $token ) {
                        if ( $token->is_default() && method_exists( $token, 'get_expiry_month' ) ) {
                            $exp_month = $token->get_expiry_month();
                            $exp_year = $token->get_expiry_year();
                            break;
                        }
                    }
                }
            }

            if ( empty( $exp_month ) || empty( $exp_year ) ) {
                continue;
            }

            $cards_checked++;

            // Calculate if card expires within threshold
            $exp_date = strtotime( "{$exp_year}-{$exp_month}-01" );
            $threshold_date = strtotime( "+{$expiring_threshold_days} days" );

            if ( $exp_date <= $threshold_date && $exp_date > time() ) {
                
                // Check if already notified
                $meta_key = '_attentive_card_expiring_sent_' . $exp_year . $exp_month;
                if ( $subscription->get_meta( $meta_key, true ) ) {
                    continue;
                }

                $expiring_found++;

                BH_Attentive_Helper::log( '[Stripe] Card expiring detected', [
                    'subscription_id' => $subscription->get_id(),
                    'exp_month'       => $exp_month,
                    'exp_year'        => $exp_year,
                ] );

                $phone = BH_Attentive_Helper::normalize_phone( $subscription->get_billing_phone() );
                $email = $subscription->get_billing_email();

                if ( empty( $phone ) && empty( $email ) ) {
                    continue;
                }

                // Ensure user is subscribed
                BH_Attentive_Helper::subscribe_user( $phone, $email );

                // Send card expiring event
                BH_Attentive_Helper::send_event( 'Stripe_Card_Expiring', $phone, $email, [
                    'subscription_id'    => (string) $subscription->get_id(),
                    'card_exp_month'     => $exp_month,
                    'card_exp_year'      => $exp_year,
                    'days_until_expiry'  => max( 0, ceil( ( $exp_date - time() ) / DAY_IN_SECONDS ) ),
                ] );

                // Set Custom Attribute for Segments
                BH_Attentive_Helper::set_attributes( $phone, $email, [
                    'card_expiring' => true,
                ] );

                // Mark as notified
                $subscription->update_meta_data( $meta_key, 'yes' );
                $subscription->save();
            }
        }

        BH_Attentive_Helper::log( '[Stripe] Expiring cards check complete', [
            'cards_checked'  => $cards_checked,
            'expiring_found' => $expiring_found,
        ] );
    }
}
