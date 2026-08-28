<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Friendbuy_Events {

    const AS_HOOK      = 'ah_friendbuy_process_purchase';
    const AS_GROUP     = 'ah-friendbuy';
    const MAX_ATTEMPTS = 3;
    const RETRY_DELAY  = 60;

    public static function init() {

        add_action(
            'woocommerce_order_status_completed',
            [ __CLASS__, 'schedule_purchase_event' ],
            20,
            1
        );

        add_action(
            self::AS_HOOK,
            [ __CLASS__, 'process_purchase_event' ],
            10,
            2
        );
    }

    /**
     * Schedule async job.
     *
     * @param int $order_id
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

        if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
            return;
        }

        if ( function_exists( 'wcs_order_contains_resubscribe' ) && wcs_order_contains_resubscribe( $order ) ) {
            return;
        }

        if ( function_exists( 'wcs_order_contains_switch' ) && wcs_order_contains_switch( $order ) ) {
            return;
        }

        if ( $order->get_meta( '_friendbuy_sent' ) ) {
            return;
        }

        if ( $order->get_meta( '_friendbuy_give_up' ) ) {
            return;
        }

        if ( self::has_pending_action_for_order( $order_id ) ) {
            return;
        }

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        $logger->info( "Friendbuy async event queued for order {$order_id}", $context );

        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action(
                self::AS_HOOK,
                [
                    'order_id' => (int) $order_id,
                    'attempt'  => 1,
                ],
                self::AS_GROUP,
                true
            );
        }
    }

    /**
     * Async processor.
     *
     * Handles unpacked Action Scheduler args (order_id, attempt) and legacy
     * single-array payloads ['order_id' => id] or ['order_id' => id, 'attempt' => n].
     *
     * @param mixed $order_id_or_args Order ID or legacy args array.
     * @param mixed $attempt          Attempt number when args are unpacked.
     */
    public static function process_purchase_event( $order_id_or_args, $attempt = null ) {

        if ( is_array( $order_id_or_args ) ) {
            $order_id = (int) ( $order_id_or_args['order_id'] ?? 0 );
            $attempt  = isset( $order_id_or_args['attempt'] )
                ? (int) $order_id_or_args['attempt']
                : 1;
        } else {
            $order_id = (int) $order_id_or_args;
            $attempt  = null !== $attempt ? (int) $attempt : 1;
        }

        if ( ! $order_id ) {
            return;
        }

        if ( $attempt < 1 ) {
            $attempt = 1;
        }

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

        if ( $order->get_meta( '_friendbuy_give_up' ) ) {
            $logger->info( "Order {$order_id} already marked give up.", $context );
            return;
        }

        $payload = self::build_payload( $order );
        $result  = AH_Friendbuy_API::send_purchase_event( $payload );

        if ( $result ) {
            $order->update_meta_data( '_friendbuy_sent', current_time( 'mysql' ) );
            $order->save();

            $logger->info( "Friendbuy event sent for order {$order_id}", $context );
            return;
        }

        if ( $attempt >= self::MAX_ATTEMPTS ) {
            $order->update_meta_data( '_friendbuy_give_up', current_time( 'mysql' ) );
            $order->save();

            $logger->error(
                "Friendbuy failed for order {$order_id}. Max attempts ({$attempt}) reached.",
                $context
            );
            return;
        }

        $next_attempt = $attempt + 1;

        if ( self::has_pending_attempt( $order_id, $next_attempt ) ) {
            $logger->info(
                "Friendbuy retry attempt {$next_attempt} already pending for order {$order_id}.",
                $context
            );
            return;
        }

        $logger->error(
            "Friendbuy failed for order {$order_id} (attempt {$attempt}). Retry {$next_attempt} in " . self::RETRY_DELAY . ' seconds.',
            $context
        );

        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action(
                time() + self::RETRY_DELAY,
                self::AS_HOOK,
                [
                    'order_id' => (int) $order_id,
                    'attempt'  => $next_attempt,
                ],
                self::AS_GROUP,
                true
            );
        }
    }

    /**
     * Build payload.
     *
     * @param WC_Order $order
     * @return array
     */
    private static function build_payload( $order ) {

        $payload = [
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

        if ( class_exists( 'AH_Friendbuy_Referral_Tracker' ) ) {
            $referral_code = AH_Friendbuy_Referral_Tracker::get_referral_code( $order->get_id() );
            if ( $referral_code !== '' ) {
                $payload['referralCode'] = $referral_code;
            }
        }

        return $payload;
    }

    /**
     * True if any pending job exists for this order (attempts 1..3 or legacy args).
     *
     * @param int $order_id
     * @return bool
     */
    private static function has_pending_action_for_order( $order_id ) {
        if ( ! function_exists( 'as_next_scheduled_action' ) ) {
            return false;
        }

        $order_id = (int) $order_id;

        if ( as_next_scheduled_action(
            self::AS_HOOK,
            [ 'order_id' => $order_id ],
            self::AS_GROUP
        ) ) {
            return true;
        }

        for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
            if ( self::has_pending_attempt( $order_id, $attempt ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $order_id
     * @param int $attempt
     * @return bool
     */
    private static function has_pending_attempt( $order_id, $attempt ) {
        if ( ! function_exists( 'as_next_scheduled_action' ) ) {
            return false;
        }

        return (bool) as_next_scheduled_action(
            self::AS_HOOK,
            [
                'order_id' => (int) $order_id,
                'attempt'  => (int) $attempt,
            ],
            self::AS_GROUP
        );
    }
}

AH_Friendbuy_Events::init();
