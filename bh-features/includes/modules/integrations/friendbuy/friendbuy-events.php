<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Friendbuy_Events {

    public static function init() {

        add_action(
            'woocommerce_order_status_completed',
            [ __CLASS__, 'schedule_purchase_event' ],
            20,
            1
        );

        add_action(
            'ah_friendbuy_process_purchase',
            [ __CLASS__, 'process_purchase_event' ],
            10,
            1
        );
    }

    /**
     * Schedule async job.
     */
    public static function schedule_purchase_event( $order_id ) {

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $coupons = $order->get_coupon_codes();
        if ( empty( $coupons ) ) {
            return;
        }

        // Skip renewal orders
        if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
            return;
        }

        // Skip resubscription orders
        if ( function_exists( 'wcs_order_contains_resubscribe' ) && wcs_order_contains_resubscribe( $order ) ) {
            return;
        }

        // Skip switch orders
        if ( function_exists( 'wcs_order_contains_switch' ) && wcs_order_contains_switch( $order ) ) {
            return;
        }

        if ( $order->get_meta( '_friendbuy_scheduled' ) ) {
            return;
        }

        $order->update_meta_data( '_friendbuy_scheduled', 1 );
        $order->save();

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        $logger->info( "Friendbuy async event queued for order {$order_id}", $context );

        if ( function_exists( 'as_enqueue_async_action' ) ) {

            as_enqueue_async_action(
                'ah_friendbuy_process_purchase',
                [ 'order_id' => $order_id ],
                'ah-friendbuy'
            );
        }
    }

    /**
     * Async processor.
     */
    public static function process_purchase_event( $args ) {

        $order_id = is_array( $args ) ? $args['order_id'] : $args;

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( $order->get_meta( '_friendbuy_sent' ) ) {
            $logger->info( "Order {$order_id} already sent.", $context );
            return;
        }

        $payload = self::build_payload( $order );

        $result = AH_Friendbuy_API::send_purchase_event( $payload );

        if ( $result ) {

            $order->update_meta_data( '_friendbuy_sent', current_time( 'mysql' ) );
            $order->save();

            $logger->info( "Friendbuy event sent for order {$order_id}", $context );

        } else {

            $logger->error( "Friendbuy failed for order {$order_id}. Retrying in 60 seconds.", $context );

            if ( function_exists( 'as_schedule_single_action' ) ) {

                as_schedule_single_action(
                    time() + 60,
                    'ah_friendbuy_process_purchase',
                    [ 'order_id' => $order_id ],
                    'ah-friendbuy'
                );
            }
        }
    }

    /**
     * Build payload.
     */
    private static function build_payload( $order ) {

        return [
            'orderId'       => (string) $order->get_order_number(),
            'email'         => $order->get_billing_email(),
            'customerId'    => (string) $order->get_customer_id(),
            'firstName'     => $order->get_billing_first_name(),
            'lastName'      => $order->get_billing_last_name(),
            'amount'        => (float) $order->get_total(),
            'currency'      => $order->get_currency(),
            'isNewCustomer' => $order->get_customer_id() ? false : true,
            'couponCode'    => implode( ', ', $order->get_coupon_codes() ),
        ];
    }
}

AH_Friendbuy_Events::init();
