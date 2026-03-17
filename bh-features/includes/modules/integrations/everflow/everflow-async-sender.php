<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Everflow_Async_Sender {

    const GROUP = 'ah-everflow';

    public function __construct() {

        /**
         * Add order action dropdown
         */
        add_filter( 'woocommerce_order_actions', [ $this, 'add_order_action' ] );

        /**
         * Trigger async enqueue
         */
        add_action( 'woocommerce_order_action_ah_send_to_everflow', [ $this, 'enqueue_async_send' ] );

        /**
         * Async worker
         */
        add_action( 'ah_process_everflow_send', [ $this, 'process_async_send' ], 10, 1 );
    }

    /**
     * Register action in WC admin
     */
    public function add_order_action( $actions ) {
        $actions['ah_send_to_everflow'] =   __( 'Send order to Everflow (async)', 'ah' );
        return $actions;
    }

    /**
     * Queue async job (NO DB writes)
     */
    public function enqueue_async_send( $order ) {

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        as_enqueue_async_action(
            'ah_process_everflow_send',
            [ $order->get_id() ],
            self::GROUP
        );
    }

    /**
     * Async processor
     */
    public function process_async_send__( $order_id ) {

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-everflow' ];

        $logger->info(
            'order_id received: ' . $order_id,
            $context
        );

        $order_id = (int) $order_id;

        if ( $order_id <= 0 ) {
            $logger->error('Invalid order id', $context);
            return;
        }

        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            $logger->error("Order not found {$order_id}", $context);
            return;
        }

        /**
         * Read existing EFT transaction id
         */
        $transaction_id = $order->get_meta( 'eftid' );

        if ( empty( $transaction_id ) ) {
            $logger->error(
                "Missing EFTID for order {$order_id}",
                $context
            );
            return;
        }

        /**
         * Build lightweight order payload
         * (avoid large URLs)
         */
        $items = [];

        foreach ( $order->get_items() as $item ) {

            $product = $item->get_product();

            $items[] = [
                'id'   => $product ? $product->get_id() : null,
                'qty'  => $item->get_quantity(),
                'total'=> $item->get_total(),
            ];
        }

        // $order_json = [
        //     'order_id' => $order_id,
        //     'currency' => $order->get_currency(),
        //     'items'    => $items,
        // ];

        /**
         * Coupons
         */
        $coupons = [];

        foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {

            $coupons[] = [
                'code'         => $coupon_item->get_code(),
                'discount'     => (float) $coupon_item->get_discount(),
                'discount_tax' => (float) $coupon_item->get_discount_tax(),
            ];
        }

        /**
         * Fees (Friendbuy credits, manual fees, etc)
         */
        $fees = [];

        foreach ( $order->get_items( 'fee' ) as $fee_item ) {

            $fees[] = [
                'name'       => $fee_item->get_name(),
                'total'      => (float) $fee_item->get_total(),
                'total_tax'  => (float) $fee_item->get_total_tax(),
            ];
        }

        /**
         * Shipping lines
         */
        $shipping_lines = [];

        foreach ( $order->get_items( 'shipping' ) as $shipping ) {

            $shipping_lines[] = [
                'method'     => $shipping->get_method_title(),
                'total'      => (float) $shipping->get_total(),
                'total_tax'  => (float) $shipping->get_total_tax(),
            ];
        }

        /**
         * Order totals (WC authoritative values)
         */
        $totals = [
            'subtotal'        => (float) $order->get_subtotal(),
            'discount_total'  => (float) $order->get_discount_total(),
            'discount_tax'    => (float) $order->get_discount_tax(),
            'shipping_total'  => (float) $order->get_shipping_total(),
            'shipping_tax'    => (float) $order->get_shipping_tax(),
            'fee_total'       => (float) $order->get_total_fees(),
            'tax_total'       => (float) $order->get_total_tax(),
            'grand_total'     => (float) $order->get_total(),
        ];

        /**
         * Final order payload
         */
        $order_json = [
            'order_id' => $order_id,
            'currency' => $order->get_currency(),

            'items'    => $items,

            'coupons'  => $coupons,
            'fees'     => $fees,
            'shipping' => $shipping_lines,

            'totals'   => $totals,
        ];

        $logger->info(
            'Everflow payload: ' . wp_json_encode($order_json),
            $context
        );



        /**
         * Build endpoint
         */
        $endpoint = add_query_arg(
            [
                'nid'            => 3697,
                'transaction_id' => rawurlencode( $transaction_id ),
                'email'          => rawurlencode( $order->get_billing_email() ),
                'order_id'       => $order_id,
                'amount'         => $order->get_total(),
                'order'          => wp_json_encode( $order_json ),
            ],
            'https://www.p9wkp5ctrk.com/'
        );

        $logger->info(
            "Sending Everflow async order {$order_id}: {$endpoint}",
            $context
        );

        /**
         * External request
         */
        $response = wp_remote_get(
            $endpoint,
            [
                'timeout'   => 30,
                'blocking'  => true,
                'sslverify' => true,
            ]
        );

        if ( is_wp_error( $response ) ) {

            $logger->error(
                "Everflow failed {$order_id}: " .
                $response->get_error_message(),
                $context
            );

            // Allow Action Scheduler retry
            throw new Exception(
                'Everflow request failed: ' .
                $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body( $response );

        $logger->info(
            "Everflow response {$order_id}: {$body}",
            $context
        );
    }

    public function process_async_send( $order_id ) {

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-everflow' ];

        $logger->info(
            'order_id received: ' . $order_id,
            $context
        );

        $order_id = (int) $order_id;

        if ( $order_id <= 0 ) {
            $logger->error('Invalid order id', $context);
            return;
        }

        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            $logger->error("Order not found {$order_id}", $context);
            return;
        }

        /**
         * Read existing EFT transaction id
         */
        $transaction_id = $order->get_meta( 'eftid' );

        if ( empty( $transaction_id ) ) {
            $logger->error(
                "Missing EFTID for order {$order_id}",
                $context
            );
            return;
        }

        /**
         * Everflow expects PRICE PER PRODUCT format
         */
        $items = [];

        foreach ( $order->get_items() as $item ) {

            $product = $item->get_product();

            if ( ! $product ) {
                continue;
            }

            // Everflow requires SKU
            $sku = $product->get_sku();

            if ( empty( $sku ) ) {
                $logger->warning(
                    "Product without SKU skipped (order {$order_id})",
                    $context
                );
                continue;
            }

            $qty = (int) $item->get_quantity();

            // UNIT PRICE (not line total)
            $unit_price = $qty > 0
                ? ( (float) $item->get_total() / $qty )
                : 0;

            $items[] = [
                'ps'  => (string) $sku,
                'p'   => number_format( $unit_price, 2, '.', '' ),
                'qty' => $qty,
            ];
        }

        /**
         * Everflow minimal payload
         */
        $order_json = [
            'oid'   => (string) $order_id,
            'amt'   => number_format( (float) $order->get_total(), 2, '.', '' ),
            'items' => $items,
        ];

        $logger->info(
            'Everflow payload: ' . wp_json_encode( $order_json ),
            $context
        );

        /**
         * Build endpoint
         */
        $endpoint = add_query_arg(
            [
                'nid'            => 3697,
                'transaction_id' => rawurlencode( $transaction_id ),
                'email'          => rawurlencode( $order->get_billing_email() ),
                'order_id'       => $order_id,
                'amount'         => $order->get_total(),
                'order'          => wp_json_encode( $order_json ),
            ],
            'https://www.p9wkp5ctrk.com/'
        );

        $logger->info(
            "Sending Everflow async order {$order_id}: {$endpoint}",
            $context
        );

        /**
         * External request
         */
        $response = wp_remote_get(
            $endpoint,
            [
                'timeout'   => 30,
                'blocking'  => true,
                'sslverify' => true,
            ]
        );

        if ( is_wp_error( $response ) ) {

            $logger->error(
                "Everflow failed {$order_id}: " .
                $response->get_error_message(),
                $context
            );

            // Allow Action Scheduler retry
            throw new Exception(
                'Everflow request failed: ' .
                $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body( $response );

        $logger->info(
            "Everflow response {$order_id}: {$body}",
            $context
        );
    }
}

new AH_Everflow_Async_Sender();
