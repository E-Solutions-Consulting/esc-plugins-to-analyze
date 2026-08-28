<?php
/**
 * Migration renewal blocker.
 *
 * Mirrors AH_Orders_Telegra_Renewal_Blocker: when a renewal order is
 * generated for a migrated customer, the order is cancelled and marked
 * for cleanup, and the subscription is moved to on-hold with a flag.
 * The subscription itself is never cancelled.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Renewal_Blocker {

    const SUBSCRIPTION_FLAG = '_migrated_to_platform';

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_filter( 'wcs_renewal_order_created', array( __CLASS__, 'block_renewal_for_migrated_user' ), 5, 2 );
        add_filter( 'woocommerce_subscription_can_be_updated_to_active', array( __CLASS__, 'block_reactivation_for_migrated_user' ), 10, 2 );
    }

    /**
     * Cancel the generated renewal order when the customer is migrated.
     *
     * @param WC_Order        $renewal_order
     * @param WC_Subscription $subscription
     * @return WC_Order
     */
    public static function block_renewal_for_migrated_user( $renewal_order, $subscription ) {
        try {
            if ( ! is_a( $renewal_order, 'WC_Order' ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
                return $renewal_order;
            }

            $user_id = $subscription->get_user_id();

            if ( ! $user_id || ! AH_Migration_Status::is_migrated( $user_id ) ) {
                return $renewal_order;
            }

            $reason = 'Renewal blocked: customer migrated to Patient Platform.';

            $renewal_order->update_status( 'wc-cancelled', $reason );
            $renewal_order->update_meta_data( '_mark_for_cleanup', 1 );
            $renewal_order->save();

            $subscription->update_status( 'on-hold' );
            $subscription->update_meta_data( self::SUBSCRIPTION_FLAG, 1 );
            $subscription->add_order_note( '⚠️ Renewal skipped. Customer migrated to Patient Platform — subscription on-hold, managed externally.' );
            $subscription->save();

            AH_Migration_Logger::log( sprintf(
                'Blocked renewal order #%d for subscription #%d (user #%d migrated).',
                $renewal_order->get_id(),
                $subscription->get_id(),
                $user_id
            ) );

        } catch ( Throwable $e ) {
            AH_Migration_Logger::log( 'Renewal blocker error: ' . $e->getMessage(), 'error' );
        }

        return $renewal_order;
    }

    /**
     * Prevent flagged subscriptions from being reactivated while the
     * customer remains migrated.
     *
     * @param bool            $can_update
     * @param WC_Subscription $subscription
     * @return bool
     */
    public static function block_reactivation_for_migrated_user( $can_update, $subscription ) {
        if ( ! $subscription instanceof WC_Subscription ) {
            return $can_update;
        }

        $user_id = $subscription->get_user_id();

        if ( $user_id && AH_Migration_Status::is_migrated( $user_id ) ) {
            return false;
        }

        return $can_update;
    }
}