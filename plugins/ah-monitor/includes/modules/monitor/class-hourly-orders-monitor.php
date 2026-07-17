<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Hourly_Orders_Monitor {

    private $options = [];
    private $start;
    private $end;

    private $stats = [];
    private $message = '';
    private $message_new = '';
    private $message_renew = '';
    private $message_created = '';
    private $messages_queue = [];
    private $message_daily_overview = '';
    private $message_daily_new_details = '';
    private $message_daily_renewal_details = '';
    private $message_hourly_overview = '';
    private $message_hourly_new_details = '';
    private $message_hourly_renewal_details = '';
    private $result = [];

    public function __construct( $options = [] ) {

        $defaults = [
            'dry_run'    => true,
            'send_slack' => false,
            'start'      => null,
            'end'        => null,
        ];

        $this->options = wp_parse_args( $options, $defaults );

        $this->resolve_time_range();
    }

    private function get_today_range() {

        $tz = wp_timezone();

        /*
        Use monitoring window date instead of server "today"
        Range from 00:00:00 to current monitoring end time
        */

        $start_dt = new DateTime($this->start, $tz);
        $end_dt = new DateTime($this->end, $tz);

        $date = $start_dt->format('Y-m-d');

        $start = new DateTime($date . ' 00:00:00', $tz);
        $end   = $end_dt; // Use actual monitoring end time instead of 23:59:59

        return [
            'start_local' => $start->format('Y-m-d H:i:s'),
            'end_local'   => $end->format('Y-m-d H:i:s'),
            'start_gmt'   => get_gmt_from_date($start->format('Y-m-d H:i:s')),
            'end_gmt'     => get_gmt_from_date($end->format('Y-m-d H:i:s')),
        ];
    }

    private function get_today_totals() {

        global $wpdb;

        $orders_table = $wpdb->prefix . 'wc_orders';
        $meta_table   = $wpdb->prefix . 'wc_orders_meta';

        $range = $this->get_today_range();

        $sql = $wpdb->prepare(
            "
            SELECT

                COUNT(*) AS orders_today,

                SUM(
                    CASE
                    -- New orders: only completed
                    WHEN o.status = 'wc-completed'
                        AND NOT EXISTS (
                            SELECT 1 FROM {$meta_table} m
                            WHERE m.order_id = o.id
                            AND m.meta_key = '_subscription_renewal'
                        )
                    THEN o.total_amount

                    -- Renewal orders: all except cancelled/failed/pending/refunded/on-hold
                    WHEN o.status NOT IN ('wc-cancelled','wc-failed','wc-pending','wc-refunded','wc-on-hold')
                        AND EXISTS (
                            SELECT 1 FROM {$meta_table} m
                            WHERE m.order_id = o.id
                            AND m.meta_key = '_subscription_renewal'
                        )
                    THEN o.total_amount

                    ELSE 0
                    END
                ) AS revenue_today,

                SUM(CASE WHEN o.status = 'wc-completed'  THEN 1 ELSE 0 END) AS completed_today,
                SUM(CASE WHEN o.status = 'wc-send_to_telegra'  THEN 1 ELSE 0 END) AS send_to_telegra_today,
                SUM(CASE WHEN o.status = 'wc-waiting_room'  THEN 1 ELSE 0 END) AS waiting_room_today,
                SUM(CASE WHEN o.status = 'wc-provider_review'  THEN 1 ELSE 0 END) AS provider_review_today,
                SUM(CASE WHEN o.status = 'wc-collect_payment'  THEN 1 ELSE 0 END) AS collect_payment_today,
                SUM(CASE WHEN o.status = 'wc-error_review'  THEN 1 ELSE 0 END) AS error_review_today,
                SUM(CASE WHEN o.status = 'wc-prerequisites'  THEN 1 ELSE 0 END) AS prerequisites_today,
                SUM(CASE WHEN o.status = 'wc-admin_review'  THEN 1 ELSE 0 END) AS admin_review_today,
                SUM(CASE WHEN o.status = 'wc-failed'      THEN 1 ELSE 0 END) AS failed_today,
                SUM(CASE WHEN o.status IN ('wc-cancel_auth_exp', 'wc-cancel_cus_req', 'wc-cancel_pat_rej')  THEN 1 ELSE 0 END) AS cancelled_today,
                SUM(CASE WHEN o.status = 'wc-cancel_auth_exp'  THEN 1 ELSE 0 END) AS cancel_auth_exp_today,
                SUM(CASE WHEN o.status = 'wc-cancel_cus_req'  THEN 1 ELSE 0 END) AS cancel_cus_req_today,
                SUM(CASE WHEN o.status = 'wc-cancel_pat_rej'  THEN 1 ELSE 0 END) AS cancel_pat_rej_today,
                SUM(CASE WHEN o.status = 'wc-on-hold'     THEN 1 ELSE 0 END) AS onhold_today,
                SUM(CASE WHEN o.status = 'wc-refunded'    THEN 1 ELSE 0 END) AS refunded_today,
                SUM(CASE WHEN o.status = 'wc-pending'     THEN 1 ELSE 0 END) AS pending_today,
                SUM(CASE WHEN o.status IN (
                    'wc-send_to_telegra',
                    'wc-waiting_room',
                    'wc-provider_review',
                    'wc-collect_payment',
                    'wc-error_review',
                    'wc-prerequisites',
                    'wc-admin_review'
                ) THEN 1 ELSE 0 END) AS telegra_status_today,

                -- New orders today (no _subscription_renewal)
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m
                    WHERE m.order_id = o.id
                    AND m.meta_key = '_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS new_orders_today,

                -- New orders revenue today (only completed)
                SUM(CASE WHEN o.status = 'wc-completed'
                    AND NOT EXISTS (
                        SELECT 1 FROM {$meta_table} m
                        WHERE m.order_id = o.id
                        AND m.meta_key = '_subscription_renewal'
                    ) THEN o.total_amount ELSE 0 END) AS new_revenue_today,

                -- Renewal orders today (has _subscription_renewal)
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM {$meta_table} m
                    WHERE m.order_id = o.id
                    AND m.meta_key = '_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS renewal_orders_today,

                -- Renewal orders revenue today (all except cancelled/failed/pending/refunded/on-hold)
                SUM(CASE WHEN o.status NOT IN ('wc-cancelled','wc-failed','wc-pending','wc-refunded','wc-on-hold')
                    AND EXISTS (
                        SELECT 1 FROM {$meta_table} m
                        WHERE m.order_id = o.id
                        AND m.meta_key = '_subscription_renewal'
                    ) THEN o.total_amount ELSE 0 END) AS renewal_revenue_today,

                -- -------------------------------------------------------
                -- NEW ORDERS DETAILED BREAKDOWN
                -- -------------------------------------------------------
                SUM(CASE WHEN o.status='wc-completed'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS completed_new_today,

                SUM(CASE WHEN o.status='wc-send_to_telegra'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS send_to_telegra_new_today,

                SUM(CASE WHEN o.status='wc-waiting_room'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS waiting_room_new_today,

                SUM(CASE WHEN o.status='wc-provider_review'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS provider_review_new_today,

                SUM(CASE WHEN o.status='wc-collect_payment'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS collect_payment_new_today,

                SUM(CASE WHEN o.status='wc-error_review'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS error_review_new_today,

                SUM(CASE WHEN o.status='wc-prerequisites'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS prerequisites_new_today,

                SUM(CASE WHEN o.status='wc-admin_review'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS admin_review_new_today,

                SUM(CASE WHEN o.status='wc-failed'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS failed_new_today,

                SUM(CASE WHEN o.status IN ('wc-cancel_auth_exp', 'wc-cancel_cus_req', 'wc-cancel_pat_rej')
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancelled_new_today,

                SUM(CASE WHEN o.status='wc-cancel_auth_exp'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_auth_exp_new_today,

                SUM(CASE WHEN o.status='wc-cancel_cus_req'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_cus_req_new_today,

                SUM(CASE WHEN o.status='wc-cancel_pat_rej'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_pat_rej_new_today,

                SUM(CASE WHEN o.status='wc-on-hold'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS onhold_new_today,

                SUM(CASE WHEN o.status='wc-refunded'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS refunded_new_today,

                SUM(CASE WHEN o.status='wc-pending'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS pending_new_today,

                -- -------------------------------------------------------
                -- RENEWAL ORDERS DETAILED BREAKDOWN
                -- -------------------------------------------------------
                SUM(CASE WHEN o.status='wc-completed'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS completed_renewal_today,

                SUM(CASE WHEN o.status='wc-send_to_telegra'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS send_to_telegra_renewal_today,

                SUM(CASE WHEN o.status='wc-waiting_room'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS waiting_room_renewal_today,

                SUM(CASE WHEN o.status='wc-provider_review'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS provider_review_renewal_today,

                SUM(CASE WHEN o.status='wc-collect_payment'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS collect_payment_renewal_today,

                SUM(CASE WHEN o.status='wc-error_review'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS error_review_renewal_today,

                SUM(CASE WHEN o.status='wc-prerequisites'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS prerequisites_renewal_today,

                SUM(CASE WHEN o.status='wc-admin_review'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS admin_review_renewal_today,

                SUM(CASE WHEN o.status='wc-failed'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS failed_renewal_today,

                SUM(CASE WHEN o.status IN ('wc-cancel_auth_exp', 'wc-cancel_cus_req', 'wc-cancel_pat_rej')
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancelled_renewal_today,

                SUM(CASE WHEN o.status='wc-cancel_auth_exp'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_auth_exp_renewal_today,

                SUM(CASE WHEN o.status='wc-cancel_cus_req'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_cus_req_renewal_today,

                SUM(CASE WHEN o.status='wc-cancel_pat_rej'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_pat_rej_renewal_today,

                SUM(CASE WHEN o.status='wc-on-hold'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS onhold_renewal_today,

                SUM(CASE WHEN o.status='wc-refunded'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS refunded_renewal_today,

                SUM(CASE WHEN o.status='wc-pending'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS pending_renewal_today

            FROM {$orders_table} o

            WHERE
                o.type = 'shop_order'
                AND o.date_created_gmt BETWEEN %s AND %s
            ",
            $range['start_gmt'],
            $range['end_gmt']
        );

        return $wpdb->get_row( $sql );
    }


    /**
     * Resolve monitoring window using WP timezone
     */
    private function resolve_time_range() {

        if ( ! empty( $this->options['start'] ) && ! empty( $this->options['end'] ) ) {
            $this->start = $this->options['start'];
            $this->end   = $this->options['end'];
            return;
        }

        $tz  = wp_timezone();
        $now = new DateTime('now', $tz);

        $now->modify( '-1 hour' );

        $hour = $now->format('Y-m-d H');

        $this->start = $hour . ':00:00';
        $this->end   = $hour . ':59:59';

    }



    /**
     * Collect stats
     */
    private function collect_stats() {

        global $wpdb;

        $orders_table = $wpdb->prefix . 'wc_orders';
        $meta_table   = $wpdb->prefix . 'wc_orders_meta';

        $start_gmt = get_gmt_from_date( $this->start );
        $end_gmt   = get_gmt_from_date( $this->end );

        $sql = $wpdb->prepare(
            "
            SELECT
                COUNT(*) AS orders_created,

                -- -------------------------------------------------------
                -- NEW ORDERS (no _subscription_renewal)
                -- -------------------------------------------------------
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS new_orders,

                SUM(CASE WHEN o.status='wc-completed'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS completed_new_orders,

                SUM(CASE WHEN o.status='wc-send_to_telegra'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS send_to_telegra_new_orders,

                SUM(CASE WHEN o.status='wc-waiting_room'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS waiting_room_new_orders,

                SUM(CASE WHEN o.status='wc-provider_review'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS provider_review_new_orders,

                SUM(CASE WHEN o.status='wc-collect_payment'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS collect_payment_new_orders,

                SUM(CASE WHEN o.status='wc-error_review'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS error_review_new_orders,

                SUM(CASE WHEN o.status='wc-prerequisites'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS prerequisites_new_orders,

                SUM(CASE WHEN o.status='wc-admin_review'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS admin_review_new_orders,

                SUM(CASE WHEN o.status='wc-failed'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS failed_new_orders,

                SUM(CASE WHEN o.status IN ('wc-cancel_auth_exp', 'wc-cancel_cus_req', 'wc-cancel_pat_rej')
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancelled_new_orders,

                SUM(CASE WHEN o.status='wc-cancel_auth_exp'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_auth_exp_new_orders,

                SUM(CASE WHEN o.status='wc-cancel_cus_req'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_cus_req_new_orders,

                SUM(CASE WHEN o.status='wc-cancel_pat_rej'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_pat_rej_new_orders,

                SUM(CASE WHEN o.status='wc-on-hold'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS onhold_new_orders,

                SUM(CASE WHEN o.status='wc-refunded'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS refunded_new_orders,

                SUM(CASE WHEN o.status='wc-pending'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS pending_new_orders,

                -- Revenue: only completed new orders
                SUM(CASE WHEN o.status='wc-completed'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} r WHERE r.order_id=o.id AND r.meta_key='_subscription_renewal'
                ) THEN o.total_amount ELSE 0 END) AS new_revenue,

                -- -------------------------------------------------------
                -- RENEWAL ORDERS (has _subscription_renewal)
                -- -------------------------------------------------------
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS renewal_orders,

                SUM(CASE WHEN o.status='wc-completed'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS completed_renewal_orders,

                SUM(CASE WHEN o.status='wc-send_to_telegra'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS send_to_telegra_renewal_orders,

                SUM(CASE WHEN o.status='wc-waiting_room'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS waiting_room_renewal_orders,

                SUM(CASE WHEN o.status='wc-provider_review'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS provider_review_renewal_orders,

                SUM(CASE WHEN o.status='wc-collect_payment'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS collect_payment_renewal_orders,

                SUM(CASE WHEN o.status='wc-error_review'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS error_review_renewal_orders,

                SUM(CASE WHEN o.status='wc-prerequisites'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS prerequisites_renewal_orders,

                SUM(CASE WHEN o.status='wc-admin_review'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS admin_review_renewal_orders,

                SUM(CASE WHEN o.status='wc-failed'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS failed_renewal_orders,

                SUM(CASE WHEN o.status IN ('wc-cancel_auth_exp', 'wc-cancel_cus_req', 'wc-cancel_pat_rej')
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancelled_renewal_orders,

                SUM(CASE WHEN o.status='wc-cancel_auth_exp'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_auth_exp_renewal_orders,

                SUM(CASE WHEN o.status='wc-cancel_cus_req'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_cus_req_renewal_orders,

                SUM(CASE WHEN o.status='wc-cancel_pat_rej'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancel_pat_rej_renewal_orders,

                SUM(CASE WHEN o.status='wc-on-hold'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS onhold_renewal_orders,

                SUM(CASE WHEN o.status='wc-refunded'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS refunded_renewal_orders,

                SUM(CASE WHEN o.status='wc-pending'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS pending_renewal_orders,

                -- Revenue: all renewal except cancelled/failed/pending/refunded/on-hold
                SUM(CASE WHEN o.status NOT IN ('wc-cancelled','wc-failed','wc-pending','wc-refunded','wc-on-hold')
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} r WHERE r.order_id=o.id AND r.meta_key='_subscription_renewal'
                ) THEN o.total_amount ELSE 0 END) AS renewal_revenue,

                -- -------------------------------------------------------
                -- GLOBAL TOTALS
                -- -------------------------------------------------------
                SUM(CASE WHEN o.status='wc-completed'
                THEN 1 ELSE 0 END) AS completed_total_orders,

                SUM(CASE WHEN o.status='wc-send_to_telegra'
                THEN 1 ELSE 0 END) AS send_to_telegra_total_orders,
                                
                SUM(CASE WHEN o.status='wc-waiting_room'
                THEN 1 ELSE 0 END) AS waiting_room_total_orders,
                                
                SUM(CASE WHEN o.status='wc-provider_review'
                THEN 1 ELSE 0 END) AS provider_review_total_orders,
                                
                SUM(CASE WHEN o.status='wc-collect_payment'
                THEN 1 ELSE 0 END) AS collect_payment_total_orders,
                                
                SUM(CASE WHEN o.status='wc-error_review'
                THEN 1 ELSE 0 END) AS error_review_total_orders,
                                
                SUM(CASE WHEN o.status='wc-prerequisites'
                THEN 1 ELSE 0 END) AS prerequisites_total_orders,
                                
                SUM(CASE WHEN o.status='wc-admin_review'
                THEN 1 ELSE 0 END) AS admin_review_total_orders,

                SUM(CASE WHEN o.status='wc-failed'
                THEN 1 ELSE 0 END) AS failed_total_orders,

                SUM(CASE WHEN o.status IN ('wc-cancel_auth_exp', 'wc-cancel_cus_req', 'wc-cancel_pat_rej')
                THEN 1 ELSE 0 END) AS cancelled_total_orders,

                SUM(CASE WHEN o.status='wc-cancel_auth_exp'
                THEN 1 ELSE 0 END) AS cancel_auth_exp_total_orders,

                SUM(CASE WHEN o.status='wc-cancel_cus_req'
                THEN 1 ELSE 0 END) AS cancel_cus_req_total_orders,

                SUM(CASE WHEN o.status='wc-cancel_pat_rej'
                THEN 1 ELSE 0 END) AS cancel_pat_rej_total_orders,

                SUM(CASE WHEN o.status='wc-on-hold'
                THEN 1 ELSE 0 END) AS onhold_total_orders,

                SUM(CASE WHEN o.status='wc-refunded'
                THEN 1 ELSE 0 END) AS refunded_total_orders,

                SUM(CASE WHEN o.status='wc-pending'
                THEN 1 ELSE 0 END) AS pending_total_orders,

                -- -------------------------------------------------------
                -- TELEGRA SYNC
                -- -------------------------------------------------------
                
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM {$meta_table} r WHERE r.order_id=o.id AND r.meta_key='_subscription_renewal'
                ) AND EXISTS (
                    SELECT 1 FROM {$meta_table} t WHERE t.order_id=o.id AND t.meta_key='telemdnow_entity_id'
                ) THEN 1 ELSE 0 END) AS new_synced_telegra,

                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM {$meta_table} r WHERE r.order_id=o.id AND r.meta_key='_subscription_renewal'
                ) AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} t WHERE t.order_id=o.id AND t.meta_key='telemdnow_entity_id'
                ) THEN 1 ELSE 0 END) AS new_pending_telegra,

                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM {$meta_table} r WHERE r.order_id=o.id AND r.meta_key='_subscription_renewal'
                ) AND EXISTS (
                    SELECT 1 FROM {$meta_table} t WHERE t.order_id=o.id AND t.meta_key='telemdnow_entity_id'
                ) THEN 1 ELSE 0 END) AS renewal_synced_telegra,

                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM {$meta_table} r WHERE r.order_id=o.id AND r.meta_key='_subscription_renewal'
                ) AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} t WHERE t.order_id=o.id AND t.meta_key='telemdnow_entity_id'
                ) THEN 1 ELSE 0 END) AS renewal_pending_telegra

            FROM {$orders_table} o

            WHERE
                o.type = 'shop_order'
                AND o.date_created_gmt BETWEEN %s AND %s
            ",
            $start_gmt,
            $end_gmt
        );

        $row = $wpdb->get_row( $sql );

        $total_revenue =
            (float) $row->new_revenue +
            (float) $row->renewal_revenue;

        $this->stats = [

            // Global
            'orders_created'          => (int) $row->orders_created,
            'completed_total_orders'  => (int) $row->completed_total_orders,
            'send_to_telegra_total_orders'  => (int) $row->send_to_telegra_total_orders,
            'waiting_room_total_orders'  => (int) $row->waiting_room_total_orders,
            'provider_review_total_orders'  => (int) $row->provider_review_total_orders,
            'collect_payment_total_orders'  => (int) $row->collect_payment_total_orders,
            'error_review_total_orders'  => (int) $row->error_review_total_orders,
            'prerequisites_total_orders'  => (int) $row->prerequisites_total_orders,
            'admin_review_total_orders'  => (int) $row->admin_review_total_orders,
            'failed_total_orders'     => (int) $row->failed_total_orders,
            'cancelled_total_orders'        => (int) $row->cancelled_total_orders,
            'cancel_auth_exp_total_orders'  => (int) $row->cancel_auth_exp_total_orders,
            'cancel_cus_req_total_orders'   => (int) $row->cancel_cus_req_total_orders,
            'cancel_pat_rej_total_orders'   => (int) $row->cancel_pat_rej_total_orders,
            'onhold_total_orders'     => (int) $row->onhold_total_orders,
            'refunded_total_orders'   => (int) $row->refunded_total_orders,
            'pending_total_orders'   => (int) $row->pending_total_orders,
            'total_revenue'           => $total_revenue,

            // New orders
            'new_orders'              => (int) $row->new_orders,
            'completed_new_orders'    => (int) $row->completed_new_orders,
            'send_to_telegra_new_orders'    => (int) $row->send_to_telegra_new_orders,
            'waiting_room_new_orders'    => (int) $row->waiting_room_new_orders,
            'provider_review_new_orders'    => (int) $row->provider_review_new_orders,
            'collect_payment_new_orders'    => (int) $row->collect_payment_new_orders,
            'error_review_new_orders'    => (int) $row->error_review_new_orders,
            'prerequisites_new_orders'    => (int) $row->prerequisites_new_orders,
            'admin_review_new_orders'    => (int) $row->admin_review_new_orders,
            'failed_new_orders'       => (int) $row->failed_new_orders,
            'cancelled_new_orders'    => (int) $row->cancelled_new_orders,
            'cancel_auth_exp_new_orders'  => (int) $row->cancel_auth_exp_new_orders,
            'cancel_cus_req_new_orders'   => (int) $row->cancel_cus_req_new_orders,
            'cancel_pat_rej_new_orders'   => (int) $row->cancel_pat_rej_new_orders,
            'onhold_new_orders'       => (int) $row->onhold_new_orders,
            'refunded_new_orders'     => (int) $row->refunded_new_orders,
            'pending_new_orders'     => (int) $row->pending_new_orders,
            'new_revenue'             => (float) $row->new_revenue,
            'new_synced_telegra'      => (int) $row->new_synced_telegra,
            'new_pending_telegra'     => (int) $row->new_pending_telegra,

            // Renewal orders
            'renewal_orders'          => (int) $row->renewal_orders,
            'completed_renewal_orders'=> (int) $row->completed_renewal_orders,
            'send_to_telegra_renewal_orders'    => (int) $row->send_to_telegra_renewal_orders,
            'waiting_room_renewal_orders'    => (int) $row->waiting_room_renewal_orders,
            'provider_review_renewal_orders'    => (int) $row->provider_review_renewal_orders,
            'collect_payment_renewal_orders'    => (int) $row->collect_payment_renewal_orders,
            'error_review_renewal_orders'    => (int) $row->error_review_renewal_orders,
            'prerequisites_renewal_orders'    => (int) $row->prerequisites_renewal_orders,
            'admin_review_renewal_orders'    => (int) $row->admin_review_renewal_orders,            
            'failed_renewal_orders'   => (int) $row->failed_renewal_orders,
            'cancelled_renewal_orders'=> (int) $row->cancelled_renewal_orders,
            'cancel_auth_exp_renewal_orders' => (int) $row->cancel_auth_exp_renewal_orders,
            'cancel_cus_req_renewal_orders'  => (int) $row->cancel_cus_req_renewal_orders,
            'cancel_pat_rej_renewal_orders'  => (int) $row->cancel_pat_rej_renewal_orders,
            'onhold_renewal_orders'   => (int) $row->onhold_renewal_orders,
            'refunded_renewal_orders' => (int) $row->refunded_renewal_orders,
            'pending_renewal_orders' => (int) $row->pending_renewal_orders,
            'renewal_revenue'         => (float) $row->renewal_revenue,
            'renewal_synced_telegra'  => (int) $row->renewal_synced_telegra,
            'renewal_pending_telegra' => (int) $row->renewal_pending_telegra,

        ];
    }

    private function get_orders_admin_link() {

        $tz = wp_timezone();

        /*
        Use monitoring window date
        */

        $start_dt = new DateTime($this->start, $tz);

        $date = $start_dt->format('Y-m-d');

        $url = "admin.php?page=wc-orders&search-filter=all&start_date={$date}&end_date={$date}&filter_action=Filter";

        return admin_url($url);
    }

   
    /**
     * Return a Slack link if value > 0, otherwise plain text.
     */
    private function slack_link( $url, $value ) {
        if ( (int) $value === 0 ) {
            return (string) $value;
        }

        return "<{$url}|{$value}>";
    }
    private function slack_link_full( $url, $text ) {
         return "<{$url}|{$text}>";
    }
    // Función helper para encodear fechas en URLs
    private function encode_date_for_url( $date ) {
        return str_replace( ':', '%3A', $date );
    }

    /**
     * Add message to queue - for specific blocks to maintain complete sections
     */
    private function add_message_to_queue( $message ) {
        if ( ! empty( trim( $message ) ) ) {
            $this->messages_queue[] = $message;
        }
    }

     /**
     * Build message
     */
    private function build_message_link() {

        $today = $this->get_today_totals();
        $range = $this->get_today_range();

        $s = $this->stats;

        $link = $this->get_orders_admin_link();

        $args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $this->encode_date_for_url($this->start),
            'end_date'      => $this->encode_date_for_url($this->end),
        ];

        $today_args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $this->encode_date_for_url($range['start_local']),
            'end_date'      => $this->encode_date_for_url($range['end_local']),
        ];

         // --- Orders Created URLs ---
        $wc_order_status    =   [
                                'completed',
                                'failed',
                                'cancelled',
                                'cancel_auth_exp',
                                'cancel_cus_req',
                                'cancel_pat_rej',
                                'on-hold',
                                'refunded',
                                'pending'
                            ];
        $telegra_order_status =   [
                                'send_to_telegra',
                                'waiting_room',
                                'provider_review',
                                'collect_payment',
                                'error_review',
                                'prerequisites',
                                'admin_review'
                            ];

        $link=[];
        $all_statuses=array_merge($wc_order_status, $telegra_order_status);

        // --- TODAY URLs ---
        $link['today']['all']       =   add_query_arg( $today_args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['today'][$status]   =   add_query_arg( array_merge( $today_args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }
        
        // URLs para NEW orders de today
        $today_new_args = array_merge($today_args, ['shop_order_subtype' => 'original']);
        $link['today']['new']       =   add_query_arg( $today_new_args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['today']['new_' . $status]   =   add_query_arg( array_merge( $today_new_args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }
        
        // URLs para RENEWAL orders de today
        $today_renewal_args = array_merge($today_args, ['shop_order_subtype' => 'renewal']);
        $link['today']['renewal']   =   add_query_arg( $today_renewal_args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['today']['renewal_' . $status]   =   add_query_arg( array_merge( $today_renewal_args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }

        // --- Orders Created URLs ---
        $link['created']['all']             =   add_query_arg( $args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['created'][$status]   =   add_query_arg( array_merge( $args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }

        // --- New Orders URLs ---
        $args[ 'shop_order_subtype']    =   'original';
        $link['new']['all']             =   add_query_arg( $args, admin_url( 'admin.php' ) );

        foreach ($all_statuses as $status) {
            $link['new'][$status]   =   add_query_arg( array_merge( $args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }

        // --- Renewal Orders URLs ---
        $args[ 'shop_order_subtype'] =   'renewal';
        $link['renew']['all']             =   add_query_arg( $args, admin_url( 'admin.php' ) );

        foreach ($all_statuses as $status) {
            $link['renew'][$status]   =   add_query_arg( array_merge( $args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }
    
        foreach ($link as $key => $statuses) {
            foreach ($statuses as $status => $url) {
                $link[$key][$status]    =   str_replace( ' ', '%20', $url );
            }
        }

        $tag_blockcode  = "```";
        $tag_code       = "`";
        $tag_blockquote = ">";

        $start_dt = new DateTime( $this->start );
        $date = $start_dt->format( 'M d, Y' ); // Oct 07, 2025
        
        // === BLOCK 1: DAILY OVERVIEW SUMMARY ===
        $this->message_daily_overview = ":bar_chart: Brello Orders Monitor *{$date}*\n\n";
        $this->message_daily_overview .= "*Daily Overview*\n\n";

        $time   =   substr($range['start_local'], 11) . ' - '.substr($range['end_local'], 11);
        $this->message_daily_overview .= ":clock3: {$tag_code}{$time}{$tag_code} → " . 
            "{$this->slack_link_full($link['today']['all'], ':shopping_trolley: ' . $today->orders_today . ' Orders Created')} - *$" . number_format( $today->revenue_today ) . "*\n\n" .
            /*$tag_blockquote . 
            "{$this->slack_link_full($link['today']['new'], $today->new_orders_today . ' New Orders')} *$" . number_format( $today->new_revenue_today ) . "* | " .
            "{$this->slack_link_full($link['today']['renewal'], $today->renewal_orders_today . ' Renewal Orders')} *$" . number_format( $today->renewal_revenue_today ) . "*\n" . */
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['today']['completed'], 'Completed:' . $today->completed_today)}" .
            " • {$this->slack_link_full($link['today']['refunded'], 'Refunded:' . $today->refunded_today)}" .
            " • {$this->slack_link_full($link['today']['pending'], 'Pending:' . $today->pending_today)}" .
            " • {$this->slack_link_full($link['today']['on-hold'], 'On Hold:' . $today->onhold_today)}" .
            " • {$this->slack_link_full($link['today']['failed'], 'Failed:' . $today->failed_today)}" .
            " • {$this->slack_link_full($link['today']['cancelled'], 'Cancelled:' . $today->cancelled_today)}" .
            /*" • {$this->slack_link_full($link['today']['cancel_auth_exp'], 'AuthExp:' . $today->cancel_auth_exp_today)}" .
            " • {$this->slack_link_full($link['today']['cancel_cus_req'], 'CusReq:' . $today->cancel_cus_req_today)}" .
            " • {$this->slack_link_full($link['today']['cancel_pat_rej'], 'PatRej:' . $today->cancel_pat_rej_today)}" .*/
            "{$tag_blockcode}\n" .
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['today']['send_to_telegra'], 'SendTelegra:' . $today->send_to_telegra_today)}" .
            " • {$this->slack_link_full($link['today']['waiting_room'], 'WaitingRoom:' . $today->waiting_room_today)}" .
            " • {$this->slack_link_full($link['today']['provider_review'], 'ProviderReview:' . $today->provider_review_today)}" .
            " • {$this->slack_link_full($link['today']['collect_payment'], 'CollectPayment:' . $today->collect_payment_today)}" .
            " • {$this->slack_link_full($link['today']['admin_review'], 'AdminReview:' . $today->admin_review_today)}" .
            " • {$this->slack_link_full($link['today']['error_review'], 'ErrorReview:' . $today->error_review_today)}" .
            " • {$this->slack_link_full($link['today']['prerequisites'], 'Prerequisites:' . $today->prerequisites_today)}" .
            "{$tag_blockcode}\n\n";
            
        // === BLOCK 2: DAILY NEW ORDERS DETAILS ===
        $this->message_daily_new_details = '' .
            $tag_blockquote . 
            "{$this->slack_link_full($link['today']['new'], ':sparkles: ' . $today->new_orders_today . ' New Orders')} - *$" . number_format( $today->new_revenue_today ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['today']['new_completed'], 'Completed:' . $today->completed_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_refunded'], 'Refunded:' . $today->refunded_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_pending'], 'Pending:' .  $today->pending_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_on-hold'], 'On Hold:' . $today->onhold_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_failed'], 'Failed:' . $today->failed_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_cancelled'], 'Cancelled:' . $today->cancelled_new_today)}" .
            /*" • {$this->slack_link_full($link['today']['new_cancel_auth_exp'], 'AuthExp:' . $today->cancel_auth_exp_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_cancel_cus_req'], 'CusReq:' . $today->cancel_cus_req_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_cancel_pat_rej'], 'PatRej:' . $today->cancel_pat_rej_new_today)}" .*/
            "{$tag_blockcode}\n". 
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['today']['new_send_to_telegra'], 'SendTelegra:' . $today->send_to_telegra_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_waiting_room'], 'WaitingRoom:' . $today->waiting_room_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_provider_review'], 'ProviderReview:' . $today->provider_review_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_collect_payment'], 'CollectPayment:' . $today->collect_payment_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_admin_review'], 'AdminReview:' . $today->admin_review_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_error_review'], 'ErrorReview:' . $today->error_review_new_today)}" .
            " • {$this->slack_link_full($link['today']['new_prerequisites'], 'Prerequisites:' . $today->prerequisites_new_today)}" .
            "{$tag_blockcode}\n\n";
            
        // === BLOCK 3: DAILY RENEWAL ORDERS DETAILS ===
        $this->message_daily_renewal_details = "\n\n" .
            $tag_blockquote . 
            "{$this->slack_link_full($link['today']['renewal'], ':repeat: ' . $today->renewal_orders_today . ' Renewal orders')} - *$" . number_format( $today->renewal_revenue_today ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['today']['renewal_completed'], 'Completed:' . $today->completed_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_refunded'], 'Refunded:' . $today->refunded_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_pending'], 'Pending:' .  $today->pending_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_on-hold'], 'On Hold:' . $today->onhold_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_failed'], 'Failed:' . $today->failed_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_cancelled'], 'Cancelled:' . $today->cancelled_renewal_today)}" .
            /*" • {$this->slack_link_full($link['today']['renewal_cancel_auth_exp'], 'AuthExp:' . $today->cancel_auth_exp_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_cancel_cus_req'], 'CusReq:' . $today->cancel_cus_req_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_cancel_pat_rej'], 'PatRej:' . $today->cancel_pat_rej_renewal_today)}" .*/
            "{$tag_blockcode}\n" . 
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['today']['renewal_send_to_telegra'], 'SendTelegra:' . $today->send_to_telegra_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_waiting_room'], 'WaitingRoom:' . $today->waiting_room_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_provider_review'], 'ProviderReview:' . $today->provider_review_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_collect_payment'], 'CollectPayment:' . $today->collect_payment_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_admin_review'], 'AdminReview:' . $today->admin_review_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_error_review'], 'ErrorReview:' . $today->error_review_renewal_today)}" .
            " • {$this->slack_link_full($link['today']['renewal_prerequisites'], 'Prerequisites:' . $today->prerequisites_renewal_today)}" .
            "{$tag_blockcode}\n\n";
            
        // === BLOCK 4: HOURLY OVERVIEW SUMMARY ===

        $this->message_hourly_overview .= "*Hourly Overview*\n\n";
        $time   =   substr($this->start, 11) . ' - '.substr($this->end, 11);
        $this->message_hourly_overview .= ":clock3: {$tag_code}{$time}{$tag_code} → " . 
            "{$this->slack_link_full($link['created']['all'], ':shopping_trolley: ' . $s['orders_created'] . ' Orders Created')} - *$" . number_format( $s['total_revenue'] ) . "*\n\n" .
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['created']['completed'], 'Completed:' . $s['completed_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['refunded'], 'Refunded:' . $s['refunded_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['pending'], 'Pending:' .  $s['pending_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['on-hold'], 'On Hold:' . $s['onhold_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['failed'], 'Failed:' . $s['failed_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['cancelled'], 'Cancelled:' . $s['cancelled_total_orders'])}" .
            /*" • {$this->slack_link_full($link['created']['cancel_auth_exp'], 'AuthExp:' . $s['cancel_auth_exp_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['cancel_cus_req'], 'CusReq:' . $s['cancel_cus_req_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['cancel_pat_rej'], 'PatRej:' . $s['cancel_pat_rej_total_orders'])}" .*/
            "{$tag_blockcode}\n" .
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['created']['send_to_telegra'], 'SendTelegra:' . $s['send_to_telegra_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['waiting_room'], 'WaitingRoom:' . $s['waiting_room_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['provider_review'], 'ProviderReview:' . $s['provider_review_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['collect_payment'], 'CollectPayment:' . $s['collect_payment_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['admin_review'], 'AdminReview:' . $s['admin_review_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['error_review'], 'ErrorReview:' . $s['error_review_total_orders'])}" .
            " • {$this->slack_link_full($link['created']['prerequisites'], 'Prerequisites:' . $s['prerequisites_total_orders'])}" .
            "{$tag_blockcode}\n\n";
            
        // === BLOCK 5: HOURLY NEW ORDERS DETAILS ===
        $this->message_hourly_new_details = '' .
            $tag_blockquote . 
            "{$this->slack_link_full($link['new']['all'], ':sparkles: ' . $s['new_orders'] . ' New Orders')} - *$" . number_format( $s['new_revenue'] ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['new']['completed'], 'Completed:' . $s['completed_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['on-hold'], 'On Hold:' . $s['onhold_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['refunded'], 'Refunded:' . $s['refunded_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['pending'], 'Pending:' .  $s['pending_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['failed'], 'Failed:' . $s['failed_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['cancelled'], 'Cancelled:' . $s['cancelled_new_orders'])}" .
            /*" • {$this->slack_link_full($link['new']['cancel_auth_exp'], 'AuthExp:' . $s['cancel_auth_exp_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['cancel_cus_req'], 'CusReq:' . $s['cancel_cus_req_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['cancel_pat_rej'], 'PatRej:' . $s['cancel_pat_rej_new_orders'])}\n" .*/           
            "{$tag_blockcode}\n". 
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['new']['send_to_telegra'], 'SendTelegra:' . $s['send_to_telegra_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['waiting_room'], 'WaitingRoom:' . $s['waiting_room_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['provider_review'], 'ProviderReview:' . $s['provider_review_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['collect_payment'], 'CollectPayment:' . $s['collect_payment_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['admin_review'], 'AdminReview:' . $s['admin_review_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['error_review'], 'ErrorReview:' . $s['error_review_new_orders'])}" .
            " • {$this->slack_link_full($link['new']['prerequisites'], 'Prerequisite:' . $s['prerequisites_new_orders'])}" .
            "{$tag_blockcode}\n\n";

        // === BLOCK 6: HOURLY RENEWAL ORDERS DETAILS ===
        $this->message_hourly_renewal_details = '' .
            $tag_blockquote . 
            "{$this->slack_link_full($link['renew']['all'], ':repeat: ' . $s['renewal_orders'] . ' Renewal orders')} - *$" . number_format( $s['renewal_revenue'] ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['renew']['completed'], 'Completed:' . $s['completed_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['refunded'], 'Refunded:' . $s['refunded_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['pending'], 'Pending:' .  $s['pending_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['on-hold'], 'On Hold:' . $s['onhold_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['failed'], 'Failed:' . $s['failed_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['cancelled'], 'Cancelled:' . $s['cancelled_renewal_orders'])}" .
            /*" • {$this->slack_link_full($link['renew']['cancel_auth_exp'], 'AuthExp:' . $s['cancel_auth_exp_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['cancel_cus_req'], 'CusReq:' . $s['cancel_cus_req_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['cancel_pat_rej'], 'PatRej:' . $s['cancel_pat_rej_renewal_orders'])}\n" . */
            "{$tag_blockcode}\n". 
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "{$this->slack_link_full($link['renew']['send_to_telegra'], 'SendTelegra:' . $s['send_to_telegra_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['waiting_room'], 'WaitingRoom:' . $s['waiting_room_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['provider_review'], 'ProviderReview:' . $s['provider_review_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['collect_payment'], 'CollectPayment:' . $s['collect_payment_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['admin_review'], 'AdminReview:' . $s['admin_review_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['error_review'], 'ErrorReview:' . $s['error_review_renewal_orders'])}" .
            " • {$this->slack_link_full($link['renew']['prerequisites'], 'Prerequisite:' . $s['prerequisites_renewal_orders'])}" .
            "```\n\n";
            
        // Clear previous messages queue
        $this->messages_queue = [];
        
        // Add each block as a separate message to prevent Slack from cutting them
        $this->add_message_to_queue( $this->message_daily_overview );
        $this->add_message_to_queue( $this->message_daily_new_details );
        $this->add_message_to_queue( $this->message_daily_renewal_details );
        $this->add_message_to_queue( $this->message_hourly_overview );
        $this->add_message_to_queue( $this->message_hourly_new_details );
        $this->add_message_to_queue( $this->message_hourly_renewal_details );
        
        // Keep original messages for backward compatibility
        $this->message = $this->message_daily_overview;
        $this->message_created = $this->message_hourly_overview;
        $this->message_new = $this->message_hourly_new_details;
        $this->message_renew = $this->message_hourly_renewal_details;
    }

    private function build_message() {

        $today = $this->get_today_totals();
        $range = $this->get_today_range();

        $s = $this->stats;

        $link = $this->get_orders_admin_link();

        $args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $this->encode_date_for_url($this->start),
            'end_date'      => $this->encode_date_for_url($this->end),
        ];

        $today_args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $this->encode_date_for_url($range['start_local']),
            'end_date'      => $this->encode_date_for_url($range['end_local']),
        ];

         // --- Orders Created URLs ---
        $wc_order_status    =   [
                                'completed',
                                'failed',
                                'cancelled',
                                'cancel_auth_exp',
                                'cancel_cus_req',
                                'cancel_pat_rej',
                                'on-hold',
                                'refunded',
                                'pending'
                            ];
        $telegra_order_status =   [
                                'send_to_telegra',
                                'waiting_room',
                                'provider_review',
                                'collect_payment',
                                'error_review',
                                'prerequisites',
                                'admin_review'
                            ];

        $link=[];
        $all_statuses=array_merge($wc_order_status, $telegra_order_status);

        // --- TODAY URLs ---
        $link['today']['all']       =   add_query_arg( $today_args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['today'][$status]   =   add_query_arg( array_merge( $today_args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }
        
        // URLs para NEW orders de today
        $today_new_args = array_merge($today_args, ['shop_order_subtype' => 'original']);
        $link['today']['new']       =   add_query_arg( $today_new_args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['today']['new_' . $status]   =   add_query_arg( array_merge( $today_new_args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }
        
        // URLs para RENEWAL orders de today
        $today_renewal_args = array_merge($today_args, ['shop_order_subtype' => 'renewal']);
        $link['today']['renewal']   =   add_query_arg( $today_renewal_args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['today']['renewal_' . $status]   =   add_query_arg( array_merge( $today_renewal_args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }

        // --- Orders Created URLs ---
        $link['created']['all']             =   add_query_arg( $args, admin_url( 'admin.php' ) );
        foreach ($all_statuses as $status) {
            $link['created'][$status]   =   add_query_arg( array_merge( $args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }

        // --- New Orders URLs ---
        $args[ 'shop_order_subtype']    =   'original';
        $link['new']['all']             =   add_query_arg( $args, admin_url( 'admin.php' ) );

        foreach ($all_statuses as $status) {
            $link['new'][$status]   =   add_query_arg( array_merge( $args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }

        // --- Renewal Orders URLs ---
        $args[ 'shop_order_subtype'] =   'renewal';
        $link['renew']['all']             =   add_query_arg( $args, admin_url( 'admin.php' ) );

        foreach ($all_statuses as $status) {
            $link['renew'][$status]   =   add_query_arg( array_merge( $args, [ 'status' => 'wc-' . $status ] ), admin_url( 'admin.php' ) );
        }
    
        foreach ($link as $key => $statuses) {
            foreach ($statuses as $status => $url) {
                $link[$key][$status]    =   str_replace( ' ', '%20', $url );
            }
        }

        $tag_blockcode  = "```";
        $tag_code       = "`";
        $tag_blockquote = ">";

        $start_dt = new DateTime( $this->start );
        $date = $start_dt->format( 'M d, Y' ); // Oct 07, 2025
        
        // === BLOCK 1: DAILY OVERVIEW SUMMARY ===
        $this->message_daily_overview = ":bar_chart: Brello Orders Monitor *{$date}*\n\n";
        $this->message_daily_overview .= "*Daily Overview*\n\n";

        $time   =   substr($range['start_local'], 11) . ' - '.substr($range['end_local'], 11);
        $this->message_daily_overview .= ":clock3: {$tag_code}{$time}{$tag_code} → " . 
            "{$this->slack_link_full($link['today']['all'], ':shopping_trolley: ' . $today->orders_today . ' Orders Created')} - *$" . number_format( $today->revenue_today ) . "*\n\n" .
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "Completed: $today->completed_today" .
            " • Refunded: $today->refunded_today" .
            " • Pending: $today->pending_today" .
            " • On Hold: $today->onhold_today" .
            " • Failed: $today->failed_today" .
            " • Cancelled: $today->cancelled_today" .
            "{$tag_blockcode}\n" .
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "SendTelegra: $today->send_to_telegra_today" .
            " • WaitingRoom: $today->waiting_room_today" .
            " • ProviderReview: $today->provider_review_today" .
            " • CollectPayment: $today->collect_payment_today" .
            " • AdminReview: $today->admin_review_today" .
            " • ErrorReview: $today->error_review_today" .
            " • Prerequisites: $today->prerequisites_today" .
            "{$tag_blockcode}\n";
            
        // === BLOCK 2: DAILY NEW ORDERS DETAILS ===
        $this->message_daily_overview .= '' .
            $tag_blockquote . 
            "{$this->slack_link_full($link['today']['new'], ':sparkles: ' . $today->new_orders_today . ' New Orders')} - *$" . number_format( $today->new_revenue_today ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "Completed: $today->completed_new_today" .
            " • Refunded: $today->refunded_new_today" .
            " • Pending:  $today->pending_new_today" .
            " • On Hold: $today->onhold_new_today" .
            " • Failed: $today->failed_new_today" .
            " • Cancelled: $today->cancelled_new_today" .
            "{$tag_blockcode}\n". 
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "SendTelegra: $today->send_to_telegra_new_today" .
            " • WaitingRoom: $today->waiting_room_new_today" .
            " • ProviderReview: $today->provider_review_new_today" .
            " • CollectPayment: $today->collect_payment_new_today" .
            " • AdminReview: $today->admin_review_new_today" .
            " • ErrorReview: $today->error_review_new_today" .
            " • Prerequisites: $today->prerequisites_new_today" .
            "{$tag_blockcode}\n";
            
        // === BLOCK 3: DAILY RENEWAL ORDERS DETAILS ===
        $this->message_daily_overview .= '' .
            $tag_blockquote . 
            "{$this->slack_link_full($link['today']['renewal'], ':repeat: ' . $today->renewal_orders_today . ' Renewal Orders')} - *$" . number_format( $today->renewal_revenue_today ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "Completed: $today->completed_renewal_today" .
            " • Refunded: $today->refunded_renewal_today" .
            " • Pending:  $today->pending_renewal_today" .
            " • On Hold: $today->onhold_renewal_today" .
            " • Failed: $today->failed_renewal_today" .
            " • Cancelled: $today->cancelled_renewal_today" .
            "{$tag_blockcode}\n" . 
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "SendTelegra: $today->send_to_telegra_renewal_today" .
            " • WaitingRoom: $today->waiting_room_renewal_today" .
            " • ProviderReview: $today->provider_review_renewal_today" .
            " • CollectPayment: $today->collect_payment_renewal_today" .
            " • AdminReview: $today->admin_review_renewal_today" .
            " • ErrorReview: $today->error_review_renewal_today" .
            " • Prerequisites: $today->prerequisites_renewal_today" .
            "{$tag_blockcode}\n";
            
        // === BLOCK 4: HOURLY OVERVIEW SUMMARY ===

        $this->message_daily_overview .= "*Hourly Overview*\n\n";
        $time   =   substr($this->start, 11) . ' - '.substr($this->end, 11);
        $this->message_daily_overview .= ":clock3: {$tag_code}{$time}{$tag_code} → " . 
            "{$this->slack_link_full($link['created']['all'], ':shopping_trolley: ' . $s['orders_created'] . ' Orders Created')} - *$" . number_format( $s['total_revenue'] ) . "*\n\n" .
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "Completed: {$s['completed_total_orders']}" .
            " • Refunded: {$s['refunded_total_orders']}" .
            " • Pending:  {$s['pending_total_orders']}" .
            " • On Hold: {$s['onhold_total_orders']}" .
            " • Failed: {$s['failed_total_orders']}" .
            " • Cancelled: {$s['cancelled_total_orders']}" .
            "{$tag_blockcode}\n" .
            $tag_blockquote . 
            "{$tag_blockcode}" .
            "SendTelegra: {$s['send_to_telegra_total_orders']}" .
            " • WaitingRoom: {$s['waiting_room_total_orders']}" .
            " • ProviderReview: {$s['provider_review_total_orders']}" .
            " • CollectPayment: {$s['collect_payment_total_orders']}" .
            " • AdminReview: {$s['admin_review_total_orders']}" .
            " • ErrorReview: {$s['error_review_total_orders']}" .
            " • Prerequisites: {$s['prerequisites_total_orders']}" .
            "{$tag_blockcode}\n";
            
        // === BLOCK 5: HOURLY NEW ORDERS DETAILS ===
        $this->message_daily_overview .= '' .
            $tag_blockquote . 
            "{$this->slack_link_full($link['new']['all'], ':sparkles: ' . $s['new_orders'] . ' New Orders')} - *$" . number_format( $s['new_revenue'] ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "Completed: {$s['completed_new_orders']}" .
            " • On Hold: {$s['onhold_new_orders']}" .
            " • Refunded: {$s['refunded_new_orders']}" .
            " • Pending:  {$s['pending_new_orders']}" .
            " • Failed: {$s['failed_new_orders']}" .
            " • Cancelled: {$s['cancelled_new_orders']}" .
            "{$tag_blockcode}\n". 
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "SendTelegra: {$s['send_to_telegra_new_orders']}" .
            " • WaitingRoom: {$s['waiting_room_new_orders']}" .
            " • ProviderReview: {$s['provider_review_new_orders']}" .
            " • CollectPayment: {$s['collect_payment_new_orders']}" .
            " • AdminReview: {$s['admin_review_new_orders']}" .
            " • ErrorReview: {$s['error_review_new_orders']}" .
            " • Prerequisite: {$s['prerequisites_new_orders']}" .
            "{$tag_blockcode}\n";

        // === BLOCK 6: HOURLY RENEWAL ORDERS DETAILS ===
        $this->message_daily_overview .= '' .
            $tag_blockquote . 
            "{$this->slack_link_full($link['renew']['all'], ':repeat: ' . $s['renewal_orders'] . ' Renewal Orders')} - *$" . number_format( $s['renewal_revenue'] ) . "*\n" .
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "Completed: {$s['completed_renewal_orders']}" .
            " • Refunded: {$s['refunded_renewal_orders']}" .
            " • Pending:  {$s['pending_renewal_orders']}" .
            " • On Hold: {$s['onhold_renewal_orders']}" .
            " • Failed: {$s['failed_renewal_orders']}" .
            " • Cancelled: {$s['cancelled_renewal_orders']}" .
            "{$tag_blockcode}\n". 
             $tag_blockquote . 
            "{$tag_blockcode}" .
            "SendTelegra: {$s['send_to_telegra_renewal_orders']}" .
            " • WaitingRoom: {$s['waiting_room_renewal_orders']}" .
            " • ProviderReview: {$s['provider_review_renewal_orders']}" .
            " • CollectPayment: {$s['collect_payment_renewal_orders']}" .
            " • AdminReview: {$s['admin_review_renewal_orders']}" .
            " • ErrorReview: {$s['error_review_renewal_orders']}" .
            " • Prerequisite: {$s['prerequisites_renewal_orders']}" .
            "```\n\n";
            
        // Clear previous messages queue
        $this->messages_queue = [];
        
        // Add each block as a separate message to prevent Slack from cutting them
        $this->add_message_to_queue( $this->message_daily_overview );
        
        // Keep original messages for backward compatibility
        $this->message = $this->message_daily_overview;

    }

    
    private function maybe_send_slack() {

        if ( $this->options['dry_run'] ) {
            return 'dry_run';
        }

        if ( ! $this->options['send_slack'] ) {
            return 'slack_disabled';
        }

        if ( ! class_exists('AH_Slack_Notifier') ) {
            return 'slack_notifier_missing';
        }

        return AH_Slack_Notifier::send($this->message);
    }

    /**
     * Send all messages in the queue
     */
    private function send_messages_queue() {
        if ( $this->options['dry_run'] ) {
            return 'dry_run';
        }

        if ( ! $this->options['send_slack'] ) {
            return 'slack_disabled';
        }

        if ( ! class_exists('AH_Slack_Notifier') ) {
            return 'slack_notifier_missing';
        }

        $results = [];
        foreach ( $this->messages_queue as $index => $message ) {
            $result = AH_Slack_Notifier::send( $message );
            $results[] = $result;
            
            // Add a small delay between messages to avoid rate limiting
            if ( $index < count( $this->messages_queue ) - 1 ) {
                sleep(1);
            }
        }
        
        return $results;
    }



    public function run() {

        $this->collect_stats();

        $this->build_message();

        // Send all messages using the intelligent queue system
        $slack_status = $this->send_messages_queue();

        $this->result = [

            'window' => [
                'start' => $this->start,
                'end' => $this->end
            ],

            'stats' => $this->stats,

            'messages_sent' => count( $this->messages_queue ),

            'message' => $this->message, // Keep for backward compatibility

            'slack' => $slack_status,

            'options' => $this->options

        ];

        return $this->result;
    }

}