<?php
/**
 * Attentive Subscription Status Sync
 *
 * Keeps a `subscription_status` custom attribute on the Attentive profile in
 * sync with the WooCommerce subscription status.
 *
 * Why this exists
 * ---------------
 * Segments built on the `SubscriptionStatus_Activated` *event* cannot express
 * "is an active member": that event only fires once, at checkout, on a new
 * subscription — never on renewals, and never for members who joined before
 * the integration existed. Any segment using it needs a rolling time window
 * ("at least once in the last 91 days"), so it drains as those events age out.
 * The only durable fix is a profile attribute reflecting current state, which
 * this module maintains.
 *
 * The attribute MUST be written through BH_Attentive_Helper::set_attributes(),
 * which sends property keys verbatim. BH_Attentive_API_Client::set_custom_attributes()
 * rewrites underscores to spaces ("subscription status") and would create a
 * second, mismatched attribute.
 *
 * Pattern matches the other event modules: the WC hook only enqueues an Action
 * Scheduler job; the HTTP call happens in the background worker.
 *
 * @package    BH_Features
 * @subpackage Integrations/Attentive
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Subscription_Status_Sync {

    const AS_HOOK   = 'bh_attentive_sync_subscription_status';
    const AS_GROUP  = 'bh-attentive';
    const ATTRIBUTE = 'subscription_status';

    public function __construct() {
        // WC Subscriptions: fires on every subscription status transition.
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'enqueue_sync' ], 20, 3 );

        // Background worker.
        add_action( self::AS_HOOK, [ $this, 'worker_sync_status' ], 10, 1 );
    }

    /**
     * Map a WooCommerce subscription status to the value stored in Attentive.
     * Kept identical to the backfill script so live updates and seeded values
     * never diverge.
     *
     * @param string $status WC status without the wc- prefix.
     * @return string
     */
    public static function map_status( $status ) {
        $map = [
            'active'         => 'active',
            'on-hold'        => 'on-hold',
            'cancelled'      => 'cancelled',
            'pending-cancel' => 'pending-cancel',
            'expired'        => 'expired',
            'pending'        => 'pending',
            'switched'       => 'switched',
        ];

        return $map[ $status ] ?? $status;
    }

    /**
     * Enqueue-only handler — runs in the request that changed the status.
     *
     * @param WC_Subscription $subscription
     * @param string          $new_status
     * @param string          $old_status
     */
    public function enqueue_sync( $subscription, $new_status, $old_status ) {

        if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_id' ) ) {
            return;
        }

        if ( $new_status === $old_status ) {
            return;
        }

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            return;
        }

        as_enqueue_async_action(
            self::AS_HOOK,
            [ [
                'subscription_id' => $subscription->get_id(),
                'new_status'      => $new_status,
            ] ],
            self::AS_GROUP
        );
    }

    /**
     * Background worker — writes the attribute to Attentive.
     *
     * @param array $args [ subscription_id, new_status ]
     */
    public function worker_sync_status( $args ) {

        $subscription_id = (int) ( $args['subscription_id'] ?? 0 );
        $new_status      = (string) ( $args['new_status'] ?? '' );

        if ( ! $subscription_id || '' === $new_status ) {
            return;
        }

        if ( ! class_exists( 'BH_Attentive_Helper' ) || ! function_exists( 'wcs_get_subscription' ) ) {
            return;
        }

        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription ) {
            return;
        }

        $phone = BH_Attentive_Helper::normalize_phone( $subscription->get_billing_phone() );
        $email = $subscription->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            BH_Attentive_Helper::log( '[SubStatus] No phone or email — skipping', [
                'subscription_id' => $subscription_id,
            ] );
            return;
        }

        $value = self::map_status( $new_status );

        BH_Attentive_Helper::set_attributes( $phone, $email, [ self::ATTRIBUTE => $value ] );

        BH_Attentive_Helper::log( '[SubStatus] Attribute synced', [
            'subscription_id' => $subscription_id,
            self::ATTRIBUTE   => $value,
        ] );
    }
}
