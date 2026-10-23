<?php
/**
 * Subscription Zero-Total Review (admin tool)
 *
 * Lists what AH_Subscription_Zero_Total_Guard is about:
 *  1. Active/on-hold subscriptions whose stored total is $0 while their items
 *     add up to more — their next renewal would be created at $0.
 *  2. Renewal orders the guard held for review.
 *
 * CS/finance decide case by case. "Recalculate" runs the same recalculation as
 * the "Recalculate" button on the subscription screen and leaves a note; it is
 * re-checked server-side and only applied to subscriptions still in that state.
 * Opening the page changes nothing.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'BH_Subscription_Zero_Total_Review_Admin' ) ) {

class BH_Subscription_Zero_Total_Review_Admin {

    const ACTION = 'bh_zero_total_recalculate';
    const NONCE  = 'bh_zero_total_recalculate_nonce';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_page' ], 60 );
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_recalculate' ] );
    }

    private static function page_slug(): string {
        return ( defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-features' ) . '--zero-total-review';
    }

    public static function add_admin_page() {
        add_submenu_page(
            defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-features',
            'Zero-Total Review',
            'Zero-Total Review',
            'manage_woocommerce',
            self::page_slug(),
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    /**
     * Active/on-hold subscriptions with a $0 total whose items say otherwise.
     *
     * @return WC_Subscription[]
     */
    public static function find_stale_subscriptions(): array {
        global $wpdb;

        $p   = $wpdb->prefix;
        $ids = $wpdb->get_col( "
            SELECT s.id
            FROM {$p}wc_orders s
            JOIN {$p}woocommerce_order_items oi ON oi.order_id = s.id AND oi.order_item_type = 'line_item'
            JOIN {$p}woocommerce_order_itemmeta im ON im.order_item_id = oi.order_item_id AND im.meta_key = '_line_total'
            WHERE s.type = 'shop_subscription' AND s.status IN ('wc-active','wc-on-hold') AND s.total_amount <= 0
            GROUP BY s.id
            HAVING SUM(CAST(im.meta_value AS DECIMAL(12,2))) > 0
            ORDER BY s.id" );

        $subs = [];

        foreach ( $ids as $id ) {
            $sub = wcs_get_subscription( (int) $id );

            if ( $sub && AH_Subscription_Zero_Total_Guard::has_stale_zero_total( $sub ) ) {
                $subs[] = $sub;
            }
        }

        return $subs;
    }

    /**
     * Renewal orders held by the guard.
     *
     * @return WC_Order[]
     */
    public static function find_held_renewals(): array {
        return wc_get_orders( [
            'limit'      => 200,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'meta_query' => [ [ 'key' => AH_Subscription_Zero_Total_Guard::META_HOLD, 'value' => 'yes' ] ],
        ] );
    }

    /**
     * Latest admin line-item change note on a subscription.
     *
     * @param int $subscription_id
     * @return string
     */
    private static function last_item_change( int $subscription_id ): string {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( "
            SELECT comment_date, comment_author FROM {$wpdb->comments}
            WHERE comment_post_ID = %d AND comment_type = 'order_note'
              AND ( comment_content LIKE 'Added line items:%%' OR comment_content LIKE 'Deleted %%' )
            ORDER BY comment_date DESC LIMIT 1", $subscription_id ) );

        return $row ? $row->comment_date . ' — ' . ( $row->comment_author ?: 'system' ) : '—';
    }

    public static function render_admin_page() {
        $subs  = self::find_stale_subscriptions();
        $held  = self::find_held_renewals();
        $done     = isset( $_GET['recalculated'] ) ? absint( $_GET['recalculated'] ) : null;
        $released = isset( $_GET['released'] ) ? absint( $_GET['released'] ) : null;
        ?>
        <div class="wrap">
            <h1>Zero-Total Review</h1>
            <p class="description" style="max-width:820px;">
                Subscriptions whose total is <strong>$0</strong> while their products are worth more, usually because
                the product was changed without clicking <em>Recalculate</em>. Their next renewal would be created
                at $0 and sent to fulfilment for free (the renewal guard now holds those renewals instead).
                Review each one: if the $0 is a mistake, recalculate it. Opening this page changes nothing.
            </p>

            <?php if ( null !== $done ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html( $done ); ?> subscription(s) recalculated, <?php echo esc_html( (int) $released ); ?> held renewal(s) resolved.</p></div>
            <?php endif; ?>

            <h2>Subscriptions with a stale $0 total (<?php echo count( $subs ); ?>)</h2>
            <?php if ( $subs ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
                <?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
                <table class="widefat striped">
                    <thead>
                        <tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.bh-zt-sub').forEach(c=>c.checked=this.checked)"></td><th>Subscription</th><th>Status</th><th>Total</th><th>Products add up to</th><th>Products</th><th>Next payment</th><th>Last product change</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $subs as $sub ) : ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="bh-zt-sub" name="subscription_ids[]" value="<?php echo esc_attr( $sub->get_id() ); ?>"></th>
                            <td><a href="<?php echo esc_url( $sub->get_edit_order_url() ); ?>">#<?php echo esc_html( $sub->get_id() ); ?></a></td>
                            <td><?php echo esc_html( $sub->get_status() ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $sub->get_total(), [ 'currency' => $sub->get_currency() ] ) ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( AH_Subscription_Zero_Total_Guard::expected_total( $sub ), [ 'currency' => $sub->get_currency() ] ) ); ?></td>
                            <td><?php echo esc_html( implode( ', ', array_map( function ( $i ) { return $i->get_name(); }, array_values( $sub->get_items() ) ) ) ); ?></td>
                            <td><?php echo esc_html( $sub->get_date( 'next_payment' ) ?: '—' ); ?></td>
                            <td><?php echo esc_html( self::last_item_change( $sub->get_id() ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><?php submit_button( 'Recalculate selected', 'primary', 'submit', false, [ 'onclick' => "return confirm('Recalculate the totals of the selected subscriptions?');" ] ); ?></p>
            </form>
            <?php else : ?>
                <p>None.</p>
            <?php endif; ?>

            <h2>Renewals held for review (<?php echo count( $held ); ?>)</h2>
            <?php if ( $held ) : ?>
                <p class="description" style="max-width:820px;">
                    "Resolve" recalculates the renewal with its coupons re-applied. If it still comes out at $0 (e.g. a
                    100% recurring coupon) it is released and processed normally; otherwise it is released at the
                    recalculated amount and waits for payment.
                </p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
                <?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
                <table class="widefat striped">
                    <thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.bh-zt-ren').forEach(c=>c.checked=this.checked)"></td><th>Renewal</th><th>Status</th><th>Total</th><th>Products add up to</th><th>Subscription</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ( $held as $order ) :
                        $sub_ids = function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ? array_keys( wcs_get_subscriptions_for_renewal_order( $order ) ) : []; ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="bh-zt-ren" name="renewal_ids[]" value="<?php echo esc_attr( $order->get_id() ); ?>"></th>
                            <td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_id() ); ?></a></td>
                            <td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( AH_Subscription_Zero_Total_Guard::expected_total( $order ), [ 'currency' => $order->get_currency() ] ) ); ?></td>
                            <td><?php foreach ( $sub_ids as $sid ) : $s = wcs_get_subscription( $sid ); ?>
                                <a href="<?php echo esc_url( $s ? $s->get_edit_order_url() : '#' ); ?>">#<?php echo esc_html( $sid ); ?></a>
                            <?php endforeach; ?></td>
                            <td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><?php submit_button( 'Resolve selected renewals', 'primary', 'submit', false, [ 'onclick' => "return confirm('Recalculate and release the selected renewals?');" ] ); ?></p>
                </form>
            <?php else : ?>
                <p>None.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_recalculate() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Not allowed.' );
        }

        check_admin_referer( self::ACTION, self::NONCE );

        $ids  = isset( $_POST['subscription_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['subscription_ids'] ) ) : [];
        $done = 0;

        foreach ( $ids as $id ) {
            $sub = wcs_get_subscription( $id );

            // Re-checked server-side: only subscriptions still in the stale state.
            if ( ! $sub || ! $sub->has_status( [ 'active', 'on-hold' ] ) || ! AH_Subscription_Zero_Total_Guard::has_stale_zero_total( $sub ) ) {
                continue;
            }

            AH_Subscription_Zero_Total_Guard::recalculate( $sub );
            $sub->add_order_note( sprintf(
                'Totals recalculated from Zero-Total Review: $0 → %s.',
                wc_price( $sub->get_total(), [ 'currency' => $sub->get_currency() ] )
            ), false, true );
            $done++;
        }

        // Held renewals: recalculate (coupons re-applied) and release the hold.
        $renewal_ids = isset( $_POST['renewal_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['renewal_ids'] ) ) : [];
        $released    = 0;

        foreach ( $renewal_ids as $id ) {
            $order = wc_get_order( $id );

            if ( ! $order || 'yes' !== $order->get_meta( AH_Subscription_Zero_Total_Guard::META_HOLD ) || ! $order->has_status( [ 'pending', 'on-hold' ] ) ) {
                continue;
            }

            AH_Subscription_Zero_Total_Guard::recalculate( $order );

            $order = wc_get_order( $id );
            $order->delete_meta_data( AH_Subscription_Zero_Total_Guard::META_HOLD );
            $order->save();

            if ( (float) $order->get_total() <= 0 ) {
                // $0 confirmed after re-applying coupons (e.g. a 100% recurring coupon) — process it like any $0 renewal.
                $order->add_order_note( 'Zero-Total Review: recalculated with coupons re-applied — $0 confirmed. Hold released and processed.', false, true );
                $order->payment_complete();
            } else {
                $order->add_order_note( sprintf(
                    'Zero-Total Review: recalculated to %s. Hold released — this renewal now awaits payment.',
                    wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] )
                ), false, true );
            }

            $released++;
        }

        wc_get_logger()->info(
            sprintf( '[zero-total-review] user_id=%d recalculated=%d released=%d subs=%s renewals=%s', get_current_user_id(), $done, $released, implode( ',', $ids ), implode( ',', $renewal_ids ) ),
            [ 'source' => 'ah-subscription-zero-total-guard' ]
        );

        wp_safe_redirect( add_query_arg( [ 'page' => self::page_slug(), 'recalculated' => $done, 'released' => $released ], admin_url( 'admin.php' ) ) );
        exit;
    }
}

BH_Subscription_Zero_Total_Review_Admin::init();

}
