<?php
/**
 * Renewal Items Change Flag — one-off backfill (admin tool)
 *
 * AH_Renewal_Items_Change_Flag only flags product changes made after it was
 * deployed. Subscriptions CS already edited before that would still be
 * cancelled on their next renewal. This page finds them and sets the flag.
 *
 * A subscription is a candidate when it's active/on-hold, its latest renewal
 * order (same ordering as the guard, skipping renewals parked by
 * AH_Renewal_Intake_Review) is in a status the flag waives, and WooCommerce
 * logged an admin line-item change ("Added line items: ..." / "Deleted ...")
 * on the subscription AFTER that renewal was created.
 *
 * Opening the page only reads. "Apply" recomputes the list server-side and
 * writes the flag (plus a note on each subscription). Safe to run more than
 * once: already-flagged subscriptions are left alone. Remove from the loader
 * once it has been run in production.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'BH_Items_Change_Flag_Backfill_Admin' ) ) {

class BH_Items_Change_Flag_Backfill_Admin {

    const ACTION   = 'bh_items_change_flag_backfill';
    const NONCE    = 'bh_items_change_flag_backfill_nonce';
    const STATUSES = [ 'cancelled', 'refunded', 'cancel_cus_req' ];

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_page' ], 60 );
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_apply' ] );
    }

    public static function add_admin_page() {
        $parent = defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-features';
        add_submenu_page(
            $parent,
            'Items Change Flag Backfill',
            'Items Change Backfill',
            'manage_woocommerce',
            $parent . '--items-change-backfill',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    /**
     * Candidate subscriptions, one row each: sub_id, sub_status, renewal_id,
     * renewal_status, renewal_created (GMT), change_at (GMT), flagged (existing flag value).
     *
     * @return object[]
     */
    public static function find_candidates(): array {
        global $wpdb;

        $p      = $wpdb->prefix;
        $flag   = AH_Renewal_Items_Change_Flag::META_FLAG;
        $parked = class_exists( 'AH_Renewal_Intake_Review' ) ? AH_Renewal_Intake_Review::META_PARKED_RENEWAL : '_ah_renewal_review_parked';

        $values = [];
        foreach ( self::STATUSES as $status ) {
            $values[] = 'wc-' . $status;
            $values[] = $status; // A few rows are stored without the prefix.
        }
        $in = implode( ',', array_map( function ( $s ) use ( $wpdb ) { return $wpdb->prepare( '%s', $s ); }, $values ) );

        $sql = "
            WITH r AS (
                SELECT CAST(m.meta_value AS UNSIGNED) AS sub_id, o.id AS renewal_id, o.status AS renewal_status, o.date_created_gmt AS renewal_created,
                       ROW_NUMBER() OVER (PARTITION BY m.meta_value ORDER BY o.date_created_gmt DESC, o.id DESC) AS rn
                FROM {$p}wc_orders_meta m
                JOIN {$p}wc_orders o ON o.id = m.order_id AND o.type = 'shop_order'
                WHERE m.meta_key = '_subscription_renewal'
                  AND NOT EXISTS ( SELECT 1 FROM {$p}wc_orders_meta pk WHERE pk.order_id = o.id AND pk.meta_key = %s AND pk.meta_value = 'yes' )
            )
            SELECT * FROM (
                SELECT s.id AS sub_id, s.status AS sub_status, r.renewal_id, r.renewal_status, r.renewal_created,
                       ( SELECT MAX(c.comment_date_gmt) FROM {$wpdb->comments} c
                         WHERE c.comment_post_ID = s.id AND c.comment_type = 'order_note'
                           AND ( c.comment_content LIKE 'Added line items:%%' OR c.comment_content LIKE 'Deleted %%' )
                           AND c.comment_date_gmt > r.renewal_created ) AS change_at,
                       ( SELECT f.meta_value FROM {$p}wc_orders_meta f WHERE f.order_id = s.id AND f.meta_key = %s LIMIT 1 ) AS flagged
                FROM r
                JOIN {$p}wc_orders s ON s.id = r.sub_id AND s.type = 'shop_subscription'
                WHERE r.rn = 1 AND s.status IN ('wc-active','wc-on-hold') AND r.renewal_status IN ({$in})
            ) x
            WHERE x.change_at IS NOT NULL
            ORDER BY x.sub_id";

        return $wpdb->get_results( $wpdb->prepare( $sql, $parked, $flag ) ) ?: [];
    }

    public static function render_admin_page() {
        $rows    = self::find_candidates();
        $pending = array_filter( $rows, function ( $r ) { return empty( $r->flagged ); } );
        $done    = isset( $_GET['flagged'] ) ? absint( $_GET['flagged'] ) : null;
        ?>
        <div class="wrap">
            <h1>Items Change Flag Backfill</h1>
            <p class="description" style="max-width:820px;">
                Subscriptions whose last renewal order was cancelled or refunded, and whose products were
                changed by CS <strong>after</strong> that order was created. Without the flag, their next
                renewal cancels the subscription. Applying sets a one-time flag so that renewal goes through
                normally, and adds a note to each subscription. Opening this page changes nothing.
            </p>

            <?php if ( null !== $done ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html( $done ); ?> subscription(s) flagged.</p></div>
            <?php endif; ?>

            <p>
                <strong><?php echo count( $rows ); ?></strong> candidate(s) —
                <strong><?php echo count( $pending ); ?></strong> pending,
                <?php echo count( $rows ) - count( $pending ); ?> already flagged.
            </p>

            <?php if ( $pending ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
                    <?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
                    <?php submit_button( 'Apply to ' . count( $pending ) . ' subscription(s)', 'primary', 'submit', false, [ 'onclick' => "return confirm('Set the flag on these subscriptions?');" ] ); ?>
                </form>
            <?php endif; ?>

            <table class="widefat striped" style="margin-top:1em;">
                <thead>
                    <tr><th>Subscription</th><th>Status</th><th>Next payment</th><th>Last renewal</th><th>Renewal status</th><th>Renewal created (GMT)</th><th>Products changed (GMT)</th><th>Flag</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $rows as $r ) :
                    $sub = wcs_get_subscription( (int) $r->sub_id ); ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $sub ? $sub->get_edit_order_url() : '#' ); ?>">#<?php echo esc_html( $r->sub_id ); ?></a></td>
                        <td><?php echo esc_html( preg_replace( '/^wc-/', '', $r->sub_status ) ); ?></td>
                        <td><?php echo esc_html( $sub ? ( $sub->get_date( 'next_payment' ) ?: '-' ) : '-' ); ?></td>
                        <td>#<?php echo esc_html( $r->renewal_id ); ?></td>
                        <td><?php echo esc_html( preg_replace( '/^wc-/', '', $r->renewal_status ) ); ?></td>
                        <td><?php echo esc_html( $r->renewal_created ); ?></td>
                        <td><?php echo esc_html( $r->change_at ); ?></td>
                        <td><?php echo $r->flagged ? esc_html( 'set (#' . $r->flagged . ')' ) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_apply() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Not allowed.' );
        }

        check_admin_referer( self::ACTION, self::NONCE );

        $flagged = 0;

        foreach ( self::find_candidates() as $r ) {
            if ( ! empty( $r->flagged ) ) {
                continue;
            }

            $sub = wcs_get_subscription( (int) $r->sub_id );

            if ( ! $sub ) {
                continue;
            }

            $sub->update_meta_data( AH_Renewal_Items_Change_Flag::META_FLAG, (int) $r->renewal_id );
            $sub->save_meta_data();
            $sub->add_order_note( sprintf(
                'Backfill: products were changed after renewal order #%d (status "%s") was created — the previous-order check will be skipped once on the next renewal.',
                $r->renewal_id,
                preg_replace( '/^wc-/', '', $r->renewal_status )
            ) );
            $flagged++;
        }

        wc_get_logger()->info(
            sprintf( '[backfill] user_id=%d flagged=%d', get_current_user_id(), $flagged ),
            [ 'source' => 'ah-renewal-items-change-flag' ]
        );

        $parent = defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-features';
        wp_safe_redirect( add_query_arg( [ 'page' => $parent . '--items-change-backfill', 'flagged' => $flagged ], admin_url( 'admin.php' ) ) );
        exit;
    }
}

BH_Items_Change_Flag_Backfill_Admin::init();

}
