<?php
/**
 * Attentive Unified Events Handler
 *
 * WC hooks enqueue Action Scheduler jobs immediately and return.
 * All HTTP calls to Attentive happen inside the AS workers (async, background).
 * Deduplication is handled via BH_Attentive_Events_Log in bh_external DB —
 * no metas written to wc_orders_meta.
 *
 * AS hooks registered here:
 *   bh_attentive_process_order          — checkout / thankyou
 *   bh_attentive_process_status_change  — order status changes
 *
 * @package    BH_Features
 * @subpackage Integrations/Attentive
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Unified_Events {

    /**
     * Initialize hooks
     */
    public function __construct() {

        // WC hooks — enqueue only, no HTTP calls here.
        add_action( 'woocommerce_thankyou',            [ $this, 'enqueue_order_success' ],        20, 1 );
        add_action( 'woocommerce_order_status_changed',[ $this, 'enqueue_status_change' ],        20, 4 );

        // AS workers — where the actual work happens.
        add_action( 'bh_attentive_process_order',         [ $this, 'worker_process_order' ],         10, 1 );
        add_action( 'bh_attentive_process_status_change', [ $this, 'worker_process_status_change' ], 10, 1 );

        // Note: no "initialized" log here — it fired on every page load / AS job
        // and flooded the log, making real entries hard to find.
    }

    // =========================================================================
    // ENQUEUE — runs in the WC request, must return fast
    // =========================================================================

    /**
     * Enqueue async job for new checkout order.
     * Hook: woocommerce_thankyou
     */
    public function enqueue_order_success( int $order_id ): void {

        if ( ! $order_id ) {
            return;
        }

        as_enqueue_async_action(
            'bh_attentive_process_order',
            [ [ 'order_id' => $order_id ] ],
            'bh-attentive'
        );

        $this->log( 'Enqueued order job', [ 'order_id' => $order_id ] );
    }

    /**
     * Enqueue async job for order status change.
     * Hook: woocommerce_order_status_changed
     */
    public function enqueue_status_change( int $order_id, string $old_status, string $new_status, $order ): void {

        if ( $old_status === $new_status ) {
            return;
        }

        as_enqueue_async_action(
            'bh_attentive_process_status_change',
            [ [
                'order_id'   => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
            ] ],
            'bh-attentive'
        );

        $this->log( 'Enqueued status change job', [
            'order_id'   => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ] );
    }

    // =========================================================================
    // WORKERS — run by Action Scheduler in the background
    // =========================================================================

    /**
     * Process new order: subscribe user, send subscription event, send order event.
     * AS hook: bh_attentive_process_order
     *
     * @param array $args  [ 'order_id' => int ]
     */
    public function worker_process_order( array $args ): void {

        $order_id = (int) ( $args['order_id'] ?? 0 );

        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( 'Order not found in worker', [ 'order_id' => $order_id ] );
            return;
        }

        // Deduplication via external DB — if row exists, already processed.
        if ( BH_Attentive_Events_Log::order_was_triggered( $order_id, BH_Attentive_Events_Log::EVENT_ORDER_PROCESSED ) ) {
            $this->log( 'Order already processed — skipping', [ 'order_id' => $order_id ] );
            return;
        }

        $this->log( 'Worker: processing order', [ 'order_id' => $order_id ] );

        $phone = $this->normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        // 1. Transactional profile — ensures user exists for events.
        $this->subscribe_user( $phone, $email );

        // 2. Marketing subscription — only if customer opted in.
        $this->handle_marketing_subscription( $order, $phone, $email );

        // 3. Subscription activated event.
        if ( $this->is_subscription_order( $order ) ) {
            $this->send_subscription_event( $order );
        }

        // 4. Order status event.
        $this->send_order_event( $order );

        // 5. Mark as processed in external DB.
        BH_Attentive_Events_Log::order_mark_triggered(
            $order_id,
            BH_Attentive_Events_Log::EVENT_ORDER_PROCESSED
        );

        $this->log( 'Worker: order processing complete', [ 'order_id' => $order_id ] );
    }

    /**
     * Process order status change: subscribe user, send status event.
     * AS hook: bh_attentive_process_status_change
     *
     * @param array $args  [ 'order_id' => int, 'old_status' => string, 'new_status' => string ]
     */
    public function worker_process_status_change( array $args ): void {

        $order_id   = (int) ( $args['order_id'] ?? 0 );
        $old_status = $args['old_status'] ?? '';
        $new_status = $args['new_status'] ?? '';

        if ( ! $order_id || ! $new_status ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( 'Order not found in worker', [ 'order_id' => $order_id ] );
            return;
        }

        // Deduplication: one event per unique (order, status) combination.
        $event_key = BH_Attentive_Events_Log::EVENT_STATUS_FIRED . '_' . $new_status;

        if ( BH_Attentive_Events_Log::order_was_triggered( $order_id, $event_key ) ) {
            $this->log( 'Status event already fired — skipping', [
                'order_id' => $order_id,
                'status'   => $new_status,
            ] );
            return;
        }

        $this->log( 'Worker: processing status change', [
            'order_id'   => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ] );

        $phone = $this->normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        // 1. Transactional profile — ensures user exists.
        $this->subscribe_user( $phone, $email );

        // 2. Status change event.
        $this->send_status_change_event( $order, $old_status, $new_status );

        // 3. Mark as fired in external DB.
        BH_Attentive_Events_Log::order_mark_triggered( $order_id, $event_key, [
            'old_status' => $old_status,
            'new_status' => $new_status,
        ] );

        $this->log( 'Worker: status change complete', [
            'order_id'   => $order_id,
            'new_status' => $new_status,
        ] );
    }

    // =========================================================================
    // PRIVATE — API CALLS
    // =========================================================================

    /**
     * Subscribe user with transactional source ID.
     * Creates the Attentive profile so events can be associated to it.
     */
    private function subscribe_user( string $phone, string $email ): void {

        if ( empty( $phone ) && empty( $email ) ) {
            $this->log( 'Cannot subscribe — no phone or email' );
            return;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            $this->log( 'API key not configured — skipping subscription' );
            return;
        }

        $transactional_source_id = $settings['transactional_source_id'] ?? '1334963';

        $data = [
            'user'           => [ 'phone' => $phone ],
            'signUpSourceId' => $transactional_source_id,
        ];

        if ( ! empty( $email ) ) {
            $data['user']['email'] = $email;
        }

        $response = wp_remote_post(
            trailingslashit( $settings['api_base_url'] ?? 'https://api.attentivemobile.com/v1' ) . 'subscriptions',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $data ),
                'blocking' => false,
                'timeout'  => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Subscribe user error', [ 'error' => $response->get_error_message() ] );
        }
    }

    /**
     * Send order status event to Attentive.
     */
    private function send_order_event( WC_Order $order ): void {

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return;
        }

        $phone      = $this->normalize_phone( $order->get_billing_phone() );
        $email      = $order->get_billing_email();
        $status     = $order->get_status();
        $event_type = 'OrderStatus_' . ucfirst( str_replace( '-', '_', $status ) );

        $payload = [
            'type'            => $event_type,
            'externalEventId' => wp_generate_uuid4(),
            'occurredAt'      => gmdate( 'c' ),
            'user'            => [ 'email' => $email, 'phone' => $phone ],
            'properties'      => $this->build_order_properties( $order ),
        ];

        $this->send_event_payload( $payload, $settings );

        $this->log( 'Order event sent', [ 'order_id' => $order->get_id(), 'event_type' => $event_type ] );
    }

    /**
     * Send order status change event.
     */
    private function send_status_change_event( WC_Order $order, string $old_status, string $new_status ): void {

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return;
        }

        $phone      = $this->normalize_phone( $order->get_billing_phone() );
        $email      = $order->get_billing_email();
        $event_type = 'OrderStatus_' . ucfirst( str_replace( '-', '_', $new_status ) );

        $properties                = $this->build_order_properties( $order );
        $properties['old_status']  = $old_status;
        $properties['new_status']  = $new_status;

        $payload = [
            'type'            => $event_type,
            'externalEventId' => wp_generate_uuid4(),
            'occurredAt'      => gmdate( 'c' ),
            'user'            => [ 'email' => $email, 'phone' => $phone ],
            'properties'      => $properties,
        ];

        $this->send_event_payload( $payload, $settings );

        $this->log( 'Status change event sent', [
            'order_id'   => $order->get_id(),
            'event_type' => $event_type,
        ] );
    }

    /**
     * Send subscription activated event + cycle_stage attribute.
     */
    private function send_subscription_event( WC_Order $order ): void {

        $subscriptions = wcs_get_subscriptions_for_order( $order );

        if ( empty( $subscriptions ) ) {
            return;
        }

        $subscription = reset( $subscriptions );

        if ( ! $subscription instanceof WC_Subscription ) {
            return;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return;
        }

        $phone         = $this->normalize_phone( $subscription->get_billing_phone() );
        $email         = $subscription->get_billing_email();
        $renewal_count = $subscription->get_completed_payment_count() - 1;

        if ( $renewal_count <= 0 ) {
            $cycle_stage = 'cycle_stage_1';
        } elseif ( $renewal_count === 1 ) {
            $cycle_stage = 'cycle_stage_2';
        } else {
            $cycle_stage = 'cycle_stage_3';
        }

        $payload = [
            'type'            => 'SubscriptionStatus_Activated',
            'externalEventId' => wp_generate_uuid4(),
            'occurredAt'      => gmdate( 'c' ),
            'user'            => [ 'email' => $email, 'phone' => $phone ],
            'properties'      => [
                'subscription_id' => (string) $subscription->get_id(),
                'status'          => 'active',
                'cycle_stage'     => $cycle_stage,
                'order_id'        => (string) $order->get_id(),
            ],
        ];

        $this->send_event_payload( $payload, $settings );

        // Set cycle_stage as custom attribute.
        $this->send_attributes(
            $phone,
            $email,
            [ $cycle_stage => true ],
            $settings
        );

        $this->log( 'Subscription event sent', [
            'subscription_id' => $subscription->get_id(),
            'cycle_stage'     => $cycle_stage,
        ] );
    }

    /**
     * Handle marketing subscription (opt-in only).
     */
    private function handle_marketing_subscription( WC_Order $order, string $phone, string $email ): void {

        $logger  = wc_get_logger();
        $context = [ 'source' => 'bh_marketing' ];

        $user_id            = $order->get_user_id();
        $order_id           = $order->get_id();
        $checkout_pref = $order->get_meta( '_bh_marketing_subscription', true );

        if ( $checkout_pref !== 'yes' ) {
            $checkout_pref = AH_Order_Meta::get( $order_id, '_bh_marketing_subscription_checkout_consent' ) ?? '';
        }

        $user_pref = $user_id ? get_user_meta( $user_id, 'bh_marketing_subscription', true ) : '';

        if ( $user_pref !== 'yes' && $checkout_pref !== 'yes' ) {
            return;
        }

        $settings            = BH_Attentive_Config::get_settings();
        $api_key             = $settings['api_key'] ?? '';
        $marketing_source_id = $settings['marketing_source_id'] ?? '1334962';

        if ( empty( $api_key ) || ! class_exists( 'BH_Attentive_API_Client' ) ) {
            return;
        }

        try {
            $api_client = new BH_Attentive_API_Client();
            $result     = $api_client->subscribe_user( $phone, $email, $marketing_source_id );

            if ( is_wp_error( $result ) ) {
                $logger->error( "MARKETING: Failed — Order {$order_id}: " . $result->get_error_message(), $context );
                $order->update_meta_data( '_bh_marketing_subscription_status', 'failed' );
                $order->update_meta_data( '_bh_marketing_subscription_error', $result->get_error_message() );
                AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_status', 'failed' );
                AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_error', $result->get_error_message() );
            } else {
                $logger->info( "MARKETING: Success — Order {$order_id}", $context );
                $order->update_meta_data( '_bh_marketing_subscription_status', 'success' );
                $order->update_meta_data( '_bh_marketing_subscription_phone', $phone );
                $order->update_meta_data( '_bh_marketing_subscription_email', $email );
                AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_status', 'success' );
                AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_phone', $phone );
                AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_email', $email );

                if ( $user_id ) {
                    update_user_meta( $user_id, 'bh_marketing_subscription_status', 'success' );
                    update_user_meta( $user_id, 'bh_marketing_subscription_updated', current_time( 'mysql' ) );
                }
            }

            $order->save();

        } catch ( Exception $e ) {
            $logger->error( "MARKETING: Exception — Order {$order_id}: " . $e->getMessage(), $context );
            $order->update_meta_data( '_bh_marketing_subscription_status', 'failed' );
            $order->update_meta_data( '_bh_marketing_subscription_error', $e->getMessage() );
            AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_status', 'failed' );
            AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_error', $e->getMessage() );
            $order->save();
        }
    }

    // =========================================================================
    // PRIVATE — HELPERS
    // =========================================================================

    /**
     * POST an event payload to /v1/events/custom (non-blocking).
     */
    private function send_event_payload( array $payload, array $settings ): void {

        $api_key = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return;
        }

        wp_remote_post(
            trailingslashit( $settings['api_base_url'] ?? 'https://api.attentivemobile.com/v1' ) . 'events/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $payload ),
                'blocking' => false,
                'timeout'  => 10,
            ]
        );
    }

    /**
     * POST custom attributes to /v1/attributes/custom (non-blocking).
     */
    private function send_attributes( string $phone, string $email, array $attributes, array $settings ): void {

        $api_key = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return;
        }

        wp_remote_post(
            trailingslashit( $settings['api_base_url'] ?? 'https://api.attentivemobile.com/v1' ) . 'attributes/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( [
                    'user'       => [ 'phone' => $phone, 'email' => $email ],
                    'properties' => $attributes,
                ] ),
                'blocking' => false,
                'timeout'  => 10,
            ]
        );
    }

    /**
     * Build common order properties array.
     */
    private function build_order_properties( WC_Order $order ): array {

        $properties = [
            'order_id'              => (string) $order->get_id(),
            'order_number'          => $order->get_order_number(),
            'status'                => $order->get_status(),
            'order_total'           => $order->get_total(),
            'currency'              => $order->get_currency(),
            'is_subscription_order' => $this->is_subscription_order( $order ),
        ];

        $items = $order->get_items();

        if ( ! empty( $items ) ) {
            $product_names = [];
            foreach ( $items as $item ) {
                $product_names[] = $item->get_name();
            }
            $properties['products']    = implode( ', ', array_slice( $product_names, 0, 3 ) );
            $properties['items_count'] = count( $items );
        }

        return $properties;
    }

    /**
     * Normalize phone via shared helper.
     */
    private function normalize_phone( string $phone ): string {
        return BH_Attentive_Helper::normalize_phone( $phone );
    }

    /**
     * Check if order contains a WC Subscription.
     */
    private function is_subscription_order( WC_Order $order ): bool {
        return function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order );
    }

    /**
     * Log via shared helper.
     */
    private function log( string $message, array $context = [] ): void {
        BH_Attentive_Helper::log( $message, $context );
    }
}