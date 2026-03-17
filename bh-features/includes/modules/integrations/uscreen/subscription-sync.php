<?php
/**
 * Sync Woo Subscriptions with Uscreen (assignment side) using async jobs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Uscreen_Subscription_Sync {

    /**
     * Register hooks and async workers.
     */
    public static function init() {

        add_action(
            'ah_uscreen_process_subscription_sync',
            [ __CLASS__, 'process_uscreen_sync_job' ],
            10,
            2
        );

        add_action(
            'ah_uscreen_process_subscription_revoke',
            [ __CLASS__, 'process_uscreen_revoke_job' ],
            10,
            2
        );

        add_filter(
            'woocommerce_order_actions',
            [ __CLASS__, 'add_manual_sync_action' ],
            10,
            2
        );

        add_action(
            'woocommerce_order_action_ah_send_to_uscreen',
            [ __CLASS__, 'handle_manual_sync_action' ],
            10,
            1
        );

        add_action(
            'woocommerce_subscription_status_cancelled',
            [ __CLASS__, 'handle_subscription_revocation' ],
            10,
            1
        );

        add_action(
            'woocommerce_subscription_status_expired',
            [ __CLASS__, 'handle_subscription_revocation' ],
            10,
            1
        );

        add_filter(
            'woocommerce_order_actions',
            [ __CLASS__, 'add_manual_revoke_action' ],
            20,
            2
        );

        add_action(
            'woocommerce_order_action_ah_revoke_uscreen',
            [ __CLASS__, 'handle_manual_revoke_action' ],
            10,
            1
        );

        add_action(
            'woocommerce_order_status_completed',
            [ __CLASS__, 'handle_order_completed' ],
            10,
            1
        );

    }

    /**
     * Handle completed parent order: enqueue sync for linked subscriptions.
     *
     * @param int $order_id
     */
    public static function handle_order_completed( $order_id ) {

        if ( ! $order_id ) {
            return;
        }

        if ( ! function_exists( 'wcs_order_contains_subscription' ) || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        if ( ! wcs_order_contains_subscription( $order ) ) {
            return;
        }

        if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
            if ( class_exists( 'AH_Uscreen_Client' ) ) {
                AH_Uscreen_Client::log(
                    sprintf( 'Order %d is a renewal. Skipping Uscreen assignment.', $order_id ),
                    'info'
                );
            }
            return;
        }

        $subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'parent' ] );

        if ( empty( $subscriptions ) ) {
            return;
        }

        foreach ( $subscriptions as $subscription ) {
            if ( $subscription instanceof WC_Subscription ) {
                self::enqueue_uscreen_sync(
                    $subscription->get_id(),
                    'order_completed'
                );
            }
        }
    }

    /**
     * Add manual sync action to order actions list (Subscriptions admin uses this).
     *
     * @param array   $actions
     * @param object  $order
     * @return array
     */
    public static function add_manual_sync_action( $actions, $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return $actions;
        }

        // $subscription = self::get_subscription_from_order_like( $order );

        // if ( ! $subscription ) {
        //     return $actions;
        // }

        $actions['ah_send_to_uscreen'] = __( 'Send to BrelloRise (Uscreen)', 'ah-uscreen' );

        return $actions;
    }

    /**
     * Handle manual sync action: enqueue async sync job.
     *
     * @param object $order
     */
    public static function handle_manual_sync_action( $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return;
        }

        // $subscription = self::get_subscription_from_order_like( $order );

        // if ( ! $subscription ) {
        //     return;
        // }

        self::enqueue_uscreen_sync(
            $subscription->get_id(),
            'manual_action'
        );
    }

    /**
     * Core sync logic executed by async worker.
     *
     * @param WC_Subscription $subscription
     * @param string          $context
     */
    protected static function send_subscription_to_uscreen( WC_Subscription $subscription, $context = 'unknown' ) {

        $mapper_data = AH_Uscreen_Subscription_Mapper::map_subscription( $subscription );

        if ( is_wp_error( $mapper_data ) ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'Uscreen mapper error (context: %s): %s',
                    $context,
                    $mapper_data->get_error_message()
                ),
                'error'
            );
            return;
        }

        if ( empty( $mapper_data['offer_id'] ) ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'Subscription %d does not have an Uscreen offer_id configured. Skipping.',
                    $subscription->get_id()
                ),
                'warning'
            );
            return;
        }

        $customer_id = AH_Uscreen_Client_Helper::ensure_customer_exists( $mapper_data );

        if ( is_wp_error( $customer_id ) ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'Uscreen ensure_customer_exists failed for subscription %d: %s',
                    $subscription->get_id(),
                    $customer_id->get_error_message()
                ),
                'error'
            );

            $subscription->add_order_note(
                sprintf(
                    'BrelloRise: Failed to ensure customer. Error: %s',
                    $customer_id->get_error_message()
                ),
                false,
                true
            );
            $subscription->save();

            return;
        }

        $has_access = AH_Uscreen_Client_Helper::customer_has_wc_access( $customer_id );

        if ( is_wp_error( $has_access ) ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'Error checking existing Uscreen access for customer %d: %s',
                    $customer_id,
                    $has_access->get_error_message()
                ),
                'error'
            );
            return;
        }

        if ( true === $has_access ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'Customer %d already has WC-provisioned Uscreen access. Skipping assignment.',
                    $customer_id
                ),
                'info'
            );

            $subscription->add_order_note(
                'BrelloRise: Customer already has access. Assignment skipped.',
                false,
                true
            );
            $subscription->save();

            return;
        }

        $assigned = AH_Uscreen_Client_Helper::assign_access( $customer_id, $mapper_data['offer_id'] );

        if ( is_wp_error( $assigned ) ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'Failed to assign Uscreen access for customer %d (subscription %d): %s',
                    $customer_id,
                    $subscription->get_id(),
                    $assigned->get_error_message()
                ),
                'error'
            );

            $subscription->add_order_note(
                sprintf(
                    'BrelloRise: Failed to assign access. Error: %s',
                    $assigned->get_error_message()
                ),
                false,
                true
            );
            $subscription->save();

            return;
        }

        $subscription->add_order_note(
            sprintf(
                'BrelloRise: Access assigned successfully for Uscreen customer ID %d (context: %s).',
                $customer_id,
                $context
            ),
            true,
            true
        );
        $subscription->save();

        AH_Uscreen_Client::log(
            sprintf(
                'Access assigned in Uscreen for customer %d from subscription %d (context: %s).',
                $customer_id,
                $subscription->get_id(),
                $context
            ),
            'info'
        );
    }

    /**
     * Handle subscription cancellation/expiration: enqueue async revoke job.
     *
     * @param WC_Subscription $subscription
     */
    public static function handle_subscription_revocation( $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return;
        }

        self::enqueue_uscreen_revoke(
            $subscription->get_id(),
            'auto_cancel_or_expired'
        );
    }

    /**
     * Add manual revoke action to order actions list (Subscriptions admin uses this).
     *
     * @param array   $actions
     * @param object  $order
     * @return array
     */
    public static function add_manual_revoke_action( $actions, $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return $actions;
        }

        // $subscription = self::get_subscription_from_order_like( $order );

        // if ( ! $subscription ) {
        //     return $actions;
        // }

        $actions['ah_revoke_uscreen'] = __( 'Revoke BrelloRise (Uscreen)', 'ah-uscreen' );

        return $actions;
    }

    /**
     * Handle manual revoke action: enqueue async revoke job.
     *
     * @param object $order
     */
    public static function handle_manual_revoke_action( $order ) {

        $subscription = self::get_subscription_from_order_like( $order );

        if ( ! $subscription ) {
            return;
        }

        self::enqueue_uscreen_revoke(
            $subscription->get_id(),
            'manual_action'
        );
    }

    /**
     * Core revoke logic executed by async worker.
     *
     * @param WC_Subscription $subscription
     * @param string          $context
     */
    protected static function revoke_subscription_uscreen_access( WC_Subscription $subscription, $context ) {

        $mapper_data = AH_Uscreen_Subscription_Mapper::map_subscription( $subscription );

        if ( is_wp_error( $mapper_data ) ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'Uscreen revoke mapper error (subscription %d, context %s): %s',
                    $subscription->get_id(),
                    $context,
                    $mapper_data->get_error_message()
                ),
                'error'
            );
            return;
        }

        $customer_id = get_user_meta( $mapper_data['wp_user_id'], '_ah_uscreen_customer_id', true );

        if ( ! $customer_id ) {
            AH_Uscreen_Client::log(
                sprintf(
                    'No Uscreen customer found for user %d. Nothing to revoke.',
                    $mapper_data['wp_user_id']
                ),
                'info'
            );
            return;
        }

        AH_Uscreen_Client_Helper::revoke_wc_accesses( (int) $customer_id );

        $subscription->add_order_note(
            sprintf(
                'BrelloRise: WC-provisioned access revoked (context: %s).',
                $context
            ),
            true,
            true
        );
        $subscription->save();

        AH_Uscreen_Client::log(
            sprintf(
                'Uscreen WC access revoked for customer %d (subscription %d, context %s).',
                $customer_id,
                $subscription->get_id(),
                $context
            ),
            'info'
        );
    }

    /**
     * Enqueue async Uscreen sync job for a subscription.
     *
     * @param int    $subscription_id
     * @param string $context
     */
    protected static function enqueue_uscreen_sync( $subscription_id, $context ) {

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            return;
        }

        as_enqueue_async_action(
            'ah_uscreen_process_subscription_sync',
            [
                'subscription_id' => (int) $subscription_id,
                'context'         => (string) $context,
            ],
            'ah-uscreen'
        );
    }

    /**
     * Enqueue async Uscreen revoke job for a subscription.
     *
     * @param int    $subscription_id
     * @param string $context
     */
    protected static function enqueue_uscreen_revoke( $subscription_id, $context ) {

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            return;
        }

        as_enqueue_async_action(
            'ah_uscreen_process_subscription_revoke',
            [
                'subscription_id' => (int) $subscription_id,
                'context'         => (string) $context,
            ],
            'ah-uscreen'
        );
    }

    /**
     * Process async Uscreen sync job.
     *
     * @param int    $subscription_id
     * @param string $context
     */
    public static function process_uscreen_sync_job( $subscription_id, $context ) {

        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            return;
        }

        $subscription = wcs_get_subscription( (int) $subscription_id );

        if ( ! $subscription instanceof WC_Subscription ) {
            return;
        }

        self::send_subscription_to_uscreen( $subscription, (string) $context );
    }

    /**
     * Process async Uscreen revoke job.
     *
     * @param int    $subscription_id
     * @param string $context
     */
    public static function process_uscreen_revoke_job( $subscription_id, $context ) {

        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            return;
        }

        $subscription = wcs_get_subscription( (int) $subscription_id );

        if ( ! $subscription instanceof WC_Subscription ) {
            return;
        }

        self::revoke_subscription_uscreen_access( $subscription, (string) $context );
    }

    /**
     * Normalize input from order actions into a WC_Subscription when applicable.
     *
     * @param mixed $order
     * @return WC_Subscription|null
     */
    protected static function get_subscription_from_order_like( $order ) {

        if ( $order instanceof WC_Subscription ) {
            return $order;
        }

        if ( function_exists( 'wcs_get_subscription' ) && is_numeric( $order ) ) {
            $maybe = wcs_get_subscription( (int) $order );
            if ( $maybe instanceof WC_Subscription ) {
                return $maybe;
            }
        }

        if ( $order instanceof WC_Order && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $subs = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] );
            if ( ! empty( $subs ) ) {
                $first = reset( $subs );
                if ( $first instanceof WC_Subscription ) {
                    return $first;
                }
            }
        }

        return null;
    }
}
