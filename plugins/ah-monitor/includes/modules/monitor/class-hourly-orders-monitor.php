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
        */

        $start_dt = new DateTime($this->start, $tz);

        $date = $start_dt->format('Y-m-d');

        $start = new DateTime($date . ' 00:00:00', $tz);
        $end   = new DateTime($date . ' 23:59:59', $tz);

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
                SUM(CASE WHEN o.status = 'wc-failed'      THEN 1 ELSE 0 END) AS failed_today,
                SUM(CASE WHEN o.status LIKE 'wc-cancel%'  THEN 1 ELSE 0 END) AS cancelled_today,
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
                ) THEN 1 ELSE 0 END) AS telegra_status_today

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

                SUM(CASE WHEN o.status='wc-failed'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS failed_new_orders,

                SUM(CASE WHEN o.status LIKE 'wc-cancel%'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancelled_new_orders,

                SUM(CASE WHEN o.status='wc-on-hold'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS onhold_new_orders,

                SUM(CASE WHEN o.status='wc-refunded'
                    AND NOT EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS refunded_new_orders,

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

                SUM(CASE WHEN o.status='wc-failed'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS failed_renewal_orders,

                SUM(CASE WHEN o.status LIKE 'wc-cancel%'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS cancelled_renewal_orders,

                SUM(CASE WHEN o.status='wc-on-hold'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS onhold_renewal_orders,

                SUM(CASE WHEN o.status='wc-refunded'
                    AND EXISTS (
                    SELECT 1 FROM {$meta_table} m WHERE m.order_id=o.id AND m.meta_key='_subscription_renewal'
                ) THEN 1 ELSE 0 END) AS refunded_renewal_orders,

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

                SUM(CASE WHEN o.status='wc-failed'
                THEN 1 ELSE 0 END) AS failed_total_orders,

                SUM(CASE WHEN o.status LIKE 'wc-cancel%'
                THEN 1 ELSE 0 END) AS cancelled_orders,

                SUM(CASE WHEN o.status='wc-on-hold'
                THEN 1 ELSE 0 END) AS onhold_total_orders,

                SUM(CASE WHEN o.status='wc-refunded'
                THEN 1 ELSE 0 END) AS refunded_total_orders,

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
            'failed_total_orders'     => (int) $row->failed_total_orders,
            'cancelled_orders'        => (int) $row->cancelled_orders,
            'onhold_total_orders'     => (int) $row->onhold_total_orders,
            'refunded_total_orders'   => (int) $row->refunded_total_orders,
            'total_revenue'           => $total_revenue,

            // New orders
            'new_orders'              => (int) $row->new_orders,
            'completed_new_orders'    => (int) $row->completed_new_orders,
            'failed_new_orders'       => (int) $row->failed_new_orders,
            'cancelled_new_orders'    => (int) $row->cancelled_new_orders,
            'onhold_new_orders'       => (int) $row->onhold_new_orders,
            'refunded_new_orders'     => (int) $row->refunded_new_orders,
            'new_revenue'             => (float) $row->new_revenue,
            'new_synced_telegra'      => (int) $row->new_synced_telegra,
            'new_pending_telegra'     => (int) $row->new_pending_telegra,

            // Renewal orders
            'renewal_orders'          => (int) $row->renewal_orders,
            'completed_renewal_orders'=> (int) $row->completed_renewal_orders,
            'failed_renewal_orders'   => (int) $row->failed_renewal_orders,
            'cancelled_renewal_orders'=> (int) $row->cancelled_renewal_orders,
            'onhold_renewal_orders'   => (int) $row->onhold_renewal_orders,
            'refunded_renewal_orders' => (int) $row->refunded_renewal_orders,
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
     * Build message
     */
    private function build_message__() {

        $today = $this->get_today_totals();
        $range = $this->get_today_range();

        $s = $this->stats;

        $link = $this->get_orders_admin_link();

        $args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $this->start,
            'end_date'      => $this->end,
        ];

        $today_args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $range['start_local'],
            'end_date'      => $range['end_local'],
        ];

        // --- TODAY URLs ---
        $url = add_query_arg( $today_args, admin_url( 'admin.php' ) );
        $today_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-completed' ] ), admin_url( 'admin.php' ) );
        $today_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-failed' ] ), admin_url( 'admin.php' ) );
        $today_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-cancelled' ] ), admin_url( 'admin.php' ) );
        $today_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-on-hold' ] ), admin_url( 'admin.php' ) );
        $today_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-refunded' ] ), admin_url( 'admin.php' ) );
        $today_refunded_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-pending' ] ), admin_url( 'admin.php' ) );
        $today_pending_url_slack = str_replace( ' ', '%20', $url );

        // --- Orders Created URLs ---
        $url = add_query_arg( $args, admin_url( 'admin.php' ) );
        $orders_created_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-completed' ] ), admin_url( 'admin.php' ) );
        $orders_created_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-failed' ] ), admin_url( 'admin.php' ) );
        $orders_created_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-cancelled' ] ), admin_url( 'admin.php' ) );
        $orders_created_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-on-hold' ] ), admin_url( 'admin.php' ) );
        $orders_created_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-refunded' ] ), admin_url( 'admin.php' ) );
        $orders_created_refunded_url_slack = str_replace( ' ', '%20', $url );

        // --- New Orders URLs ---
        $url = add_query_arg( array_merge( $args, [ 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-completed', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-failed', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-cancelled', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-on-hold', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-refunded', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_refunded_url_slack = str_replace( ' ', '%20', $url );

        // --- Renewal Orders URLs ---
        $url = add_query_arg( array_merge( $args, [ 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-completed', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-failed', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-cancelled', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-on-hold', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-refunded', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_refunded_url_slack = str_replace( ' ', '%20', $url );

        // --- Build message ---
        $this->message =
            "Brello Orders Monitor\n\n" .

            "TODAY: <{$today_url_slack}|*{$today->orders_today}*> → Revenue: *$" . number_format( $today->revenue_today ) . "*\n" .
            "```" .
            "Completed: <{$today_completed_url_slack}|{$today->completed_today}>" .
            " | Failed: <{$today_failed_url_slack}|{$today->failed_today}>" .
            " | Cancelled: <{$today_cancelled_url_slack}|{$today->cancelled_today}>" .
            " | On Hold: <{$today_onhold_url_slack}|{$today->onhold_today}>" .
            " | Refunded: <{$today_refunded_url_slack}|{$today->refunded_today}>" .
            " | Pending: <{$today_pending_url_slack}|{$today->pending_today}>" .
            "```\n\n" .

            "Time: `{$this->start}` → `{$this->end}`\n\n" .

            "Orders Created: <{$orders_created_url_slack}|*{$s['orders_created']}*> → Revenue: *$" . number_format( $s['total_revenue'] ) . "*\n" .
            "```" .
            "Completed: <{$orders_created_completed_url_slack}|{$s['completed_total_orders']}>" .
            " | Failed: <{$orders_created_failed_url_slack}|{$s['failed_total_orders']}>" .
            " | Cancelled: <{$orders_created_cancelled_url_slack}|{$s['cancelled_orders']}>" .
            " | On Hold: <{$orders_created_onhold_url_slack}|{$s['onhold_total_orders']}>" .
            " | Refunded: <{$orders_created_refunded_url_slack}|{$s['refunded_total_orders']}>" .
            "```\n" .

            "New Orders: <{$new_orders_url_slack}|*{$s['new_orders']}*> → Revenue: *$" . number_format( $s['new_revenue'] ) . "*\n" .
            "```" .
            "Completed: <{$new_orders_completed_url_slack}|{$s['completed_new_orders']}>" .
            " | Failed: <{$new_orders_failed_url_slack}|{$s['failed_new_orders']}>" .
            " | Cancelled: <{$new_orders_cancelled_url_slack}|{$s['cancelled_new_orders']}>" .
            " | On Hold: <{$new_orders_onhold_url_slack}|{$s['onhold_new_orders']}>" .
            " | Refunded: <{$new_orders_refunded_url_slack}|{$s['refunded_new_orders']}>" .
            "```\n" .
            "```" .
            "Telegra: Synced({$s['new_synced_telegra']}) | Not Yet Synced({$s['new_pending_telegra']})" .
            "```\n" .

            "Renewals: <{$renewals_order_url_slack}|*{$s['renewal_orders']}*> → Revenue: *$" . number_format( $s['renewal_revenue'] ) . "*\n" .
            "```" .
            "Completed: <{$renewals_order_completed_url_slack}|{$s['completed_renewal_orders']}>" .
            " | Failed: <{$renewals_order_failed_url_slack}|{$s['failed_renewal_orders']}>" .
            " | Cancelled: <{$renewals_order_cancelled_url_slack}|{$s['cancelled_renewal_orders']}>" .
            " | On Hold: <{$renewals_order_onhold_url_slack}|{$s['onhold_renewal_orders']}>" .
            " | Refunded: <{$renewals_order_refunded_url_slack}|{$s['refunded_renewal_orders']}>\n" .
            "Telegra: Synced({$s['renewal_synced_telegra']}) | Not Yet Synced({$s['renewal_pending_telegra']})" .
            "```";
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

    private function build_message() {

        $today = $this->get_today_totals();
        $range = $this->get_today_range();

        $s = $this->stats;

        $link = $this->get_orders_admin_link();

        $args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $this->start,
            'end_date'      => $this->end,
        ];

        $today_args = [
            'page'          => 'wc-orders',
            'search-filter' => 'all',
            'filter_action' => 'Filter',
            'start_date'    => $range['start_local'],
            'end_date'      => $range['end_local'],
        ];

        // --- TODAY URLs ---
        $url = add_query_arg( $today_args, admin_url( 'admin.php' ) );
        $today_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-completed' ] ), admin_url( 'admin.php' ) );
        $today_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-failed' ] ), admin_url( 'admin.php' ) );
        $today_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-cancelled' ] ), admin_url( 'admin.php' ) );
        $today_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-on-hold' ] ), admin_url( 'admin.php' ) );
        $today_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-refunded' ] ), admin_url( 'admin.php' ) );
        $today_refunded_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $today_args, [ 'status' => 'wc-pending' ] ), admin_url( 'admin.php' ) );
        $today_pending_url_slack = str_replace( ' ', '%20', $url );

        // --- Orders Created URLs ---
        $url = add_query_arg( $args, admin_url( 'admin.php' ) );
        $orders_created_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-completed' ] ), admin_url( 'admin.php' ) );
        $orders_created_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-failed' ] ), admin_url( 'admin.php' ) );
        $orders_created_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-cancelled' ] ), admin_url( 'admin.php' ) );
        $orders_created_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-on-hold' ] ), admin_url( 'admin.php' ) );
        $orders_created_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-refunded' ] ), admin_url( 'admin.php' ) );
        $orders_created_refunded_url_slack = str_replace( ' ', '%20', $url );

        // --- New Orders URLs ---
        $url = add_query_arg( array_merge( $args, [ 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-completed', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-failed', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-cancelled', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-on-hold', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-refunded', 'shop_order_subtype' => 'original' ] ), admin_url( 'admin.php' ) );
        $new_orders_refunded_url_slack = str_replace( ' ', '%20', $url );

        // --- Renewal Orders URLs ---
        $url = add_query_arg( array_merge( $args, [ 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-completed', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_completed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-failed', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_failed_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-cancelled', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_cancelled_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-on-hold', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_onhold_url_slack = str_replace( ' ', '%20', $url );

        $url = add_query_arg( array_merge( $args, [ 'status' => 'wc-refunded', 'shop_order_subtype' => 'renewal' ] ), admin_url( 'admin.php' ) );
        $renewals_order_refunded_url_slack = str_replace( ' ', '%20', $url );

        // --- Build message ---
        $this->message =
            "Brello Orders Monitor\n\n" .

            "TODAY: {$this->slack_link($today_url_slack, $today->orders_today)} → Revenue: *$" . number_format( $today->revenue_today ) . "*\n" .
            "```" .
            "Completed: {$this->slack_link($today_completed_url_slack, $today->completed_today)}" .
            " | Failed: {$this->slack_link($today_failed_url_slack, $today->failed_today)}" .
            " | Cancelled: {$this->slack_link($today_cancelled_url_slack, $today->cancelled_today)}" .
            " | On Hold: {$this->slack_link($today_onhold_url_slack, $today->onhold_today)}" .
            " | Refunded: {$this->slack_link($today_refunded_url_slack, $today->refunded_today)}" .
            " | Pending: {$this->slack_link($today_pending_url_slack, $today->pending_today)}" .
            " | Telegra: {$today->telegra_status_today}" .
            "```\n\n" .

            "Time: `{$this->start}` → `{$this->end}`\n\n" .

            "Orders Created: {$this->slack_link($orders_created_url_slack, $s['orders_created'])} → Revenue: *$" . number_format( $s['total_revenue'] ) . "*\n" .
            "```" .
            "Completed: {$this->slack_link($orders_created_completed_url_slack, $s['completed_total_orders'])}" .
            " | Failed: {$this->slack_link($orders_created_failed_url_slack, $s['failed_total_orders'])}" .
            " | Cancelled: {$this->slack_link($orders_created_cancelled_url_slack, $s['cancelled_orders'])}" .
            " | On Hold: {$this->slack_link($orders_created_onhold_url_slack, $s['onhold_total_orders'])}" .
            " | Refunded: {$this->slack_link($orders_created_refunded_url_slack, $s['refunded_total_orders'])}" .
            "```\n" .

            "New Orders: {$this->slack_link($new_orders_url_slack, $s['new_orders'])} → Revenue: *$" . number_format( $s['new_revenue'] ) . "*\n" .
            "```" .
            "Completed: {$this->slack_link($new_orders_completed_url_slack, $s['completed_new_orders'])}" .
            " | Failed: {$this->slack_link($new_orders_failed_url_slack, $s['failed_new_orders'])}" .
            " | Cancelled: {$this->slack_link($new_orders_cancelled_url_slack, $s['cancelled_new_orders'])}" .
            " | On Hold: {$this->slack_link($new_orders_onhold_url_slack, $s['onhold_new_orders'])}" .
            " | Refunded: {$this->slack_link($new_orders_refunded_url_slack, $s['refunded_new_orders'])}" .
            "\n" .
            "Telegra: Synced({$s['new_synced_telegra']}) | Not Yet Synced({$s['new_pending_telegra']})" .
            "```\n" .

            "Renewals: {$this->slack_link($renewals_order_url_slack, $s['renewal_orders'])} → Revenue: *$" . number_format( $s['renewal_revenue'] ) . "*\n" .
            "```" .
            "Completed: {$this->slack_link($renewals_order_completed_url_slack, $s['completed_renewal_orders'])}" .
            " | Failed: {$this->slack_link($renewals_order_failed_url_slack, $s['failed_renewal_orders'])}" .
            " | Cancelled: {$this->slack_link($renewals_order_cancelled_url_slack, $s['cancelled_renewal_orders'])}" .
            " | On Hold: {$this->slack_link($renewals_order_onhold_url_slack, $s['onhold_renewal_orders'])}" .
            " | Refunded: {$this->slack_link($renewals_order_refunded_url_slack, $s['refunded_renewal_orders'])}" .
            "\n" .
            "Telegra: Synced({$s['renewal_synced_telegra']}) | Not Yet Synced({$s['renewal_pending_telegra']})" .
            "```";
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



    public function run() {

        $this->collect_stats();

        $this->build_message();

        $slack_status = $this->maybe_send_slack();

        $this->result = [

            'window' => [
                'start' => $this->start,
                'end' => $this->end
            ],

            'stats' => $this->stats,

            'message' => $this->message,

            'slack' => $slack_status,

            'options' => $this->options

        ];

        return $this->result;
    }

}