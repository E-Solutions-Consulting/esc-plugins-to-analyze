<?php
/**
 * Subscription admin product rules
 *
 * CS builds subscriptions by hand in wp-admin. The storefront rules (one
 * subscription product per cart, one subscription per product/category) live
 * in the cart and checkout, which the admin never goes through: "Add item(s)"
 * on a subscription calls WC_AJAX::add_order_item() → $order->add_product()
 * directly. Two human errors come from that:
 *
 *  1. A second product (or quantity > 1) on one subscription. A subscription
 *     holds ONE product; bundles are their own single product in the catalog.
 *  2. A billing schedule that doesn't match the product. The Schedule box is
 *     filled by hand, independently of the product, so a 3-month variation
 *     ends up billed monthly (or daily, see BH_DEFAULT_BILLING_*).
 *
 * This module:
 *  - Refuses, when adding items in wp-admin, a second product, more than one
 *    product per request, a quantity other than 1, non-subscription products
 *    and a product with plans added without choosing one (the variable
 *    product itself instead of a variation); a quantity edited above 1 afterwards is saved as 1, and one
 *    edited below 1 is not saved.
 *    Fees (Friendbuy "Referral Discount"), coupons and shipping aren't
 *    products and are never checked.
 *  - Answers the schedule of the product just added (AJAX), so the Schedule
 *    box is filled from it (admin/js/bh-features-admin-subscription-billing.js).
 *    The product variation is the source of truth; a product whose name
 *    contradicts its configured schedule ("6 Month" billed every 3 months) is
 *    flagged.
 *  - While a saved subscription breaks these rules, shows a red box right
 *    above the subscription details widget (every problem) and a short one in
 *    the Schedule box (schedule problems, full text in a help tip, selects
 *    outlined) — next to the subscription, not among the page-top notices. It never rewrites a saved
 *    subscription: changing its schedule on the server would move its dates
 *    and scheduled renewals.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Subscription_Admin_Product_Rules' ) ) {

class AH_Subscription_Admin_Product_Rules {

    public function __construct() {
        add_filter( 'woocommerce_ajax_add_order_item_validation', [ $this, 'validate_added_item' ], 10, 4 );
        add_action( 'woocommerce_before_save_order_item', [ $this, 'keep_single_quantity' ] );
        add_action( 'wp_ajax_bh_subscription_product_schedule', [ $this, 'ajax_product_schedule' ] );
        // Above the subscription details widget, full width: HPOS screen, then the legacy post screen.
        add_action( 'order_edit_form_top', [ $this, 'render_details_box' ] );
        add_action( 'edit_form_top', [ $this, 'render_details_box' ] );
        add_action( 'wcs_subscription_schedule_after_billing_schedule', [ $this, 'render_schedule_box' ] );
    }

    /**
     * Billing schedule of a subscription product or variation.
     *
     * A variable product has no schedule of its own: WooCommerce Subscriptions
     * answers with the one of its cheapest variation, which is not a plan
     * anybody chose.
     *
     * @param WC_Product|false $product
     * @return array{period: string, interval: string}|null Null for non-subscription products and variable parents.
     */
    public static function get_product_schedule( $product ): ?array {
        if ( ! $product instanceof WC_Product || ! class_exists( 'WC_Subscriptions_Product' ) || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
            return null;
        }

        if ( self::is_variable_parent( $product ) ) {
            return null;
        }

        $period   = (string) WC_Subscriptions_Product::get_period( $product );
        $interval = (string) WC_Subscriptions_Product::get_interval( $product );

        if ( '' === $period ) {
            return null;
        }

        return [ 'period' => $period, 'interval' => '' !== $interval ? $interval : '1' ];
    }

    /**
     * A product with plans (variations) picked as a whole, instead of one of
     * its variations. The "Add product(s)" search lists both, and WooCommerce
     * only refuses the plain "variable" type, not "variable-subscription".
     *
     * @param WC_Product|false $product
     * @return bool
     */
    public static function is_variable_parent( $product ): bool {
        return $product instanceof WC_Product && $product->is_type( [ 'variable', 'variable-subscription' ] );
    }

    /**
     * The product's name states a plan length ("6 Month", "3 MONTH PLAN",
     * "Monthly") that its configured schedule contradicts — e.g. a "6 Month"
     * variation duplicated from the 3-month one without changing its interval.
     *
     * @param WC_Product $product
     * @param array      $schedule From get_product_schedule().
     * @return string Message for CS, or '' when the name states no length or agrees.
     */
    public static function get_name_warning( $product, array $schedule ): string {
        $name = $product instanceof WC_Product ? wp_strip_all_tags( $product->get_name() ) : '';

        if ( preg_match( '/(\d+)\s*-?\s*months?\b/i', $name, $m ) ) {
            $months = (int) $m[1];
        } elseif ( preg_match( '/\bmonthly\b/i', $name ) ) {
            $months = 1;
        } else {
            return '';
        }

        if ( 'month' === $schedule['period'] && (int) $schedule['interval'] === $months ) {
            return '';
        }

        return sprintf(
            /* translators: 1: product name, 2: product schedule */
            __( 'The product "%1$s" is configured to bill %2$s, which contradicts its name: fix the product\'s subscription schedule first.', 'bh-features' ),
            $name,
            self::format_schedule( $schedule['interval'], $schedule['period'] )
        );
    }

    /**
     * AJAX: schedule of the product CS just added, read at that moment (the
     * subscription screen may have been open before the product was created
     * or edited).
     */
    public function ajax_product_schedule() {
        check_ajax_referer( 'bh-subscription-product-schedule', 'security' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( null, 403 );
        }

        $product  = wc_get_product( isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0 );
        $schedule = self::get_product_schedule( $product );

        if ( ! $schedule ) {
            wp_send_json_error( null, 404 );
        }

        wp_send_json_success( $schedule + [
            'name'        => wp_strip_all_tags( $product->get_name() ),
            'nameWarning' => self::get_name_warning( $product, $schedule ),
        ] );
    }

    /**
     * Text for an "Add item(s)" error. WooCommerce shows it with
     * window.alert(), which is plain text: the steps are lines that start
     * with a bullet character.
     *
     * @param string[] $intro Lines above the steps.
     * @param string[] $steps One step per line.
     * @return string
     */
    private static function alert_with_steps( array $intro, array $steps ): string {
        $lines   = $intro;
        $lines[] = '';

        foreach ( $steps as $step ) {
            $lines[] = "\u{2022} " . $step;
        }

        return implode( "\n", $lines );
    }

    /**
     * "Add item(s)" on a subscription in wp-admin.
     *
     * @param WP_Error   $error
     * @param WC_Product $product
     * @param WC_Order   $order
     * @param int|float  $qty
     * @return WP_Error
     */
    public function validate_added_item( $error, $product, $order, $qty ) {
        if ( ! $error instanceof WP_Error || ! $order instanceof WC_Subscription ) {
            return $error;
        }

        // A subscription that already has a product: same message whether one
        // product or several are being added.
        $current = $order->get_items( 'line_item' );

        if ( ! empty( $current ) ) {
            $names = implode( ', ', array_map( function ( $item ) {
                return $item->get_name();
            }, $current ) );

            $error->add( 'ah_one_product', self::alert_with_steps(
                [
                    __( 'This subscription already has a product assigned.', 'bh-features' ),
                    sprintf(
                        /* translators: %s: product(s) already on the subscription */
                        __( 'A subscription can only include one product. This subscription already includes %s.', 'bh-features' ),
                        $names
                    ),
                ],
                [
                    __( 'To replace the current product: Remove the existing product first, then add the new product.', 'bh-features' ),
                    __( 'For a plan with multiple medications: Select the appropriate bundle plan from the list of available products.', 'bh-features' ),
                    __( 'For a separate medication or plan: Create a new subscription instead.', 'bh-features' ),
                ]
            ) );
            return $error;
        }

        // Empty subscription. The modal can add several rows in one request;
        // each row is added before the next is validated, so refuse the whole
        // request up front. It always posts one empty row too (a new one
        // appears each time a product is picked) and WC_AJAX skips rows
        // without an id: count only rows with a product.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_AJAX::add_order_item() already checked the nonce.
        $rows      = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : [];
        $requested = count( array_filter( $rows, function ( $row ) {
            return is_array( $row ) && ! empty( $row['id'] );
        } ) );

        if ( $requested > 1 ) {
            $error->add( 'ah_one_product', self::alert_with_steps(
                [ __( 'A subscription can only include one product. Add a single product.', 'bh-features' ) ],
                [
                    __( 'For a plan with multiple medications: Select the appropriate bundle plan from the list of available products.', 'bh-features' ),
                    __( 'For a separate medication or plan: Create a new subscription instead.', 'bh-features' ),
                ]
            ) );
            return $error;
        }

        // Exactly one unit. WC_AJAX has already passed the posted quantity
        // through wc_stock_amount(), so a fraction arrives here as 0.
        if ( 1.0 !== (float) $qty ) {
            $error->add( 'ah_one_product', __( 'The quantity must be 1: a subscription holds a single unit of its product.', 'bh-features' ) );
            return $error;
        }

        if ( self::is_variable_parent( $product ) ) {
            $error->add( 'ah_one_product', sprintf(
                /* translators: %s: product name */
                __( '"%s" has several plans. Add the plan you need (e.g. its "3 Month" option), not the product itself.', 'bh-features' ),
                $product->get_name()
            ) );
            return $error;
        }

        if ( ! self::get_product_schedule( $product ) ) {
            $error->add( 'ah_one_product', sprintf(
                /* translators: %s: product name */
                __( '"%s" is not a subscription product. A subscription can only hold a subscription product.', 'bh-features' ),
                $product->get_name()
            ) );
        }

        return $error;
    }

    /**
     * Editing a subscription's items in wp-admin (inline edit, or the whole
     * form's "Update", which posts every item again): a quantity changed to
     * more than 1 is saved as 1, with the line totals brought back to the unit
     * price. A quantity changed to less than 1 has no unit price to derive, so
     * the change is dropped: the item keeps the quantity and totals it had.
     * (That is a fraction, which WooCommerce stores as 0; a literal 0 in the
     * form removes the item before it gets here.) The quantity field is also locked
     * to 1 in the UI; this covers posts that bypass it. Only a CHANGED quantity
     * is touched, so saving an old subscription that already has a quantity
     * other than 1 doesn't rewrite what it charges (get_problems() still flags
     * it).
     *
     * @param WC_Order_Item $item
     */
    public function keep_single_quantity( $item ) {
        try {
            if ( ! $item instanceof WC_Order_Item_Product || ! array_key_exists( 'quantity', $item->get_changes() ) ) {
                return;
            }

            $qty = (float) $item->get_quantity();

            if ( 1.0 === $qty || ! $item->get_order() instanceof WC_Subscription ) {
                return;
            }

            if ( $qty < 1 ) {
                // get_data() is the item as stored, without the pending changes.
                // A new item has nothing stored to go back to: it is left as
                // posted and get_problems() flags it.
                if ( $item->get_id() ) {
                    $stored = $item->get_data();

                    $item->set_quantity( $stored['quantity'] );
                    $item->set_total( $stored['total'] );
                    $item->set_subtotal( $stored['subtotal'] );
                    $item->set_taxes( $stored['taxes'] );
                }
                return;
            }

            $unit = function ( $amount ) use ( $qty ) {
                return wc_format_decimal( (float) $amount / $qty );
            };
            $taxes = $item->get_taxes();

            $item->set_quantity( 1 );
            $item->set_total( $unit( $item->get_total() ) );
            $item->set_subtotal( $unit( $item->get_subtotal() ) );
            $item->set_taxes( [
                'total'    => array_map( $unit, $taxes['total'] ?? [] ),
                'subtotal' => array_map( $unit, $taxes['subtotal'] ?? [] ),
            ] );
        } catch ( \Throwable $th ) {
            if ( function_exists( 'bh_plugins_error_log' ) ) {
                bh_plugins_error_log( [
                    'error'    => $th->getMessage(),
                    'function' => 'AH_Subscription_Admin_Product_Rules::keep_single_quantity',
                ] );
            }
        }
    }

    /**
     * Problems on a saved subscription, as messages for CS.
     *
     * @param WC_Subscription $subscription
     * @return string[]
     */
    public static function get_problems( WC_Subscription $subscription ): array {
        return array_column( self::collect_problems( $subscription ), 'message' );
    }

    /**
     * Only the problems about the billing schedule (shown in the Schedule box):
     * a short line plus the full message for its help tip.
     *
     * @param WC_Subscription $subscription
     * @return array<int, array{short: string, message: string}>
     */
    public static function get_schedule_problems( WC_Subscription $subscription ): array {
        return array_values( array_filter( self::collect_problems( $subscription ), function ( $problem ) {
            return 'schedule' === $problem['type'];
        } ) );
    }

    /**
     * @param WC_Subscription $subscription
     * @return array<int, array{type: string, message: string, short?: string, code?: string, details?: array}> type: 'product' or 'schedule'.
     */
    private static function collect_problems( WC_Subscription $subscription ): array {
        $items    = $subscription->get_items( 'line_item' );
        $problems = [];

        if ( count( $items ) > 1 ) {
            $problems[] = [ 'type' => 'product', 'message' => sprintf(
                /* translators: %d: number of products */
                __( 'It has %d products. A subscription can only have one: keep the right product and remove the rest.', 'bh-features' ),
                count( $items )
            ) ];
            return $problems;
        }

        $item = reset( $items );

        if ( ! $item instanceof WC_Order_Item_Product ) {
            return $problems;
        }

        if ( 1.0 !== (float) $item->get_quantity() ) {
            $problems[] = [ 'type' => 'product', 'message' => sprintf(
                /* translators: %d: quantity */
                __( 'The product quantity is %d. It must be 1.', 'bh-features' ),
                $item->get_quantity()
            ) ];
        }

        if ( self::is_variable_parent( $item->get_product() ) ) {
            $problems[] = [ 'type' => 'product', 'message' => sprintf(
                /* translators: %s: product name */
                __( '"%s" was added without choosing a plan. Remove it and add the right plan (e.g. its "3 Month" option), then check the schedule and the price.', 'bh-features' ),
                $item->get_name()
            ) ];
            return $problems;
        }

        $schedule = self::get_product_schedule( $item->get_product() );

        if ( ! $schedule ) {
            $problems[] = [ 'type' => 'product', 'message' => sprintf(
                /* translators: %s: product name */
                __( '"%s" is not a subscription product (or no longer exists).', 'bh-features' ),
                $item->get_name()
            ) ];
            return $problems;
        }

        $name_warning = self::get_name_warning( $item->get_product(), $schedule );

        if ( $name_warning ) {
            $problems[] = [ 'type' => 'schedule', 'short' => __( 'The product\'s configuration contradicts its name.', 'bh-features' ), 'message' => $name_warning ];
        }

        $sub_period   = (string) $subscription->get_billing_period();
        $sub_interval = (string) $subscription->get_billing_interval();

        if ( $sub_period !== $schedule['period'] || $sub_interval !== $schedule['interval'] ) {
            $plan    = self::format_plan( $schedule['interval'], $schedule['period'] );
            $current = '' === $sub_period
                ? __( 'This subscription has no payment schedule', 'bh-features' )
                : sprintf(
                    /* translators: %s: subscription schedule, e.g. "every 6 months" */
                    __( 'This subscription is currently scheduled for %s', 'bh-features' ),
                    self::format_schedule( $sub_interval, $sub_period )
                );
            $summary = sprintf(
                /* translators: 1: what the subscription has now, 2: product name, 3: "a" or "an", 4: product plan, e.g. "3-month" */
                __( '%1$s, but %2$s is %3$s %4$s plan.', 'bh-features' ),
                $current,
                $item->get_name(),
                self::plan_article( $schedule['interval'] ),
                $plan
            );

            $problems[] = [
                'type'    => 'schedule',
                'code'    => 'schedule_mismatch',
                'short'   => __( 'Does not match the product\'s configuration.', 'bh-features' ),
                // One sentence: used as the help tip in the Schedule box.
                'message' => $summary . ' ' . __( 'Update the Payment schedule to match, then confirm the next payment date: it is not recalculated automatically.', 'bh-features' ),
                // Paragraphs for the box above the subscription.
                'details' => [
                    [ 'text' => $summary ],
                    [ 'text' => sprintf(
                        /* translators: %s: product plan, e.g. "3-month" */
                        __( 'Update the Payment schedule (in the Schedule box) to match the correct %s schedule, then select Update.', 'bh-features' ),
                        $plan
                    ) ],
                    [
                        'label' => __( 'Important:', 'bh-features' ),
                        'text'  => __( 'Confirm that the next payment date is also correct. The date will not be recalculated automatically when the schedule is changed.', 'bh-features' ),
                    ],
                ],
            ];
        }

        return $problems;
    }

    /**
     * @param string $interval
     * @param string $period
     * @return string e.g. "every 3 months"
     */
    public static function format_schedule( string $interval, string $period ): string {
        if ( function_exists( 'wcs_get_subscription_period_strings' ) ) {
            return sprintf( 'every %s', wcs_get_subscription_period_strings( (int) $interval, $period ) );
        }

        return sprintf( 'every %s %s', $interval, $period );
    }

    /**
     * @param string $interval
     * @param string $period
     * @return string e.g. "3-month"
     */
    public static function format_plan( string $interval, string $period ): string {
        return sprintf( '%d-%s', (int) $interval, $period );
    }

    /**
     * @param string $interval
     * @return string "an" before a number that starts with a vowel sound (8, 11, 18), "a" otherwise.
     */
    private static function plan_article( string $interval ): string {
        return in_array( (int) $interval, [ 8, 11, 18 ], true ) ? 'an' : 'a';
    }

    /**
     * Red box listing the problems, placed inside the subscription screen
     * (not among the page-top admin notices, where it goes unnoticed).
     *
     * A single problem that comes with its own paragraphs is shown as
     * paragraphs; anything else as a list, one item per problem.
     *
     * @param string $title
     * @param array  $problems As returned by collect_problems().
     * @param string $class
     */
    private static function render_box( string $title, array $problems, string $class ): void {
        echo '<div class="' . esc_attr( $class ) . '" role="alert" style="clear:both;margin:12px 0;padding:10px 12px;border:1px solid #d63638;border-left-width:4px;border-radius:2px;background:#fcf0f1;">';
        echo '<p style="margin:0 0 6px;font-weight:600;color:#8a2424;"><span class="dashicons dashicons-warning" style="font-size:18px;margin-right:4px;vertical-align:text-bottom;"></span>' . esc_html( $title ) . '</p>';

        if ( 1 === count( $problems ) && ! empty( $problems[0]['details'] ) ) {
            self::render_problem( $problems[0] );
        } else {
            echo '<ul style="margin:0 0 0 18px;list-style:disc;">';
            foreach ( $problems as $problem ) {
                echo '<li style="margin:2px 0;">';
                self::render_problem( $problem );
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    /**
     * One problem: its paragraphs when it has them, its message otherwise.
     *
     * @param array $problem
     */
    private static function render_problem( array $problem ): void {
        if ( empty( $problem['details'] ) ) {
            echo esc_html( $problem['message'] );
            return;
        }

        foreach ( $problem['details'] as $paragraph ) {
            echo '<p style="margin:4px 0;">';
            if ( ! empty( $paragraph['label'] ) ) {
                echo '<strong>' . esc_html( $paragraph['label'] ) . '</strong> ';
            }
            echo esc_html( $paragraph['text'] ) . '</p>';
        }
    }

    /**
     * Every problem, above the subscription details widget (outside it, so it
     * doesn't push the subscription's own fields around).
     *
     * @param WC_Order|WP_Post $order_or_post HPOS passes the order, the legacy screen the post.
     */
    public function render_details_box( $order_or_post ) {
        try {
            $subscription = $order_or_post instanceof WP_Post
                ? ( 'shop_subscription' === $order_or_post->post_type ? wcs_get_subscription( $order_or_post->ID ) : null )
                : $order_or_post;

            if ( ! $subscription instanceof WC_Subscription || ! current_user_can( 'edit_shop_orders' ) ) {
                return;
            }

            $problems = self::collect_problems( $subscription );

            if ( $problems ) {
                $only_schedule_mismatch = 1 === count( $problems ) && 'schedule_mismatch' === ( $problems[0]['code'] ?? '' );

                self::render_box(
                    $only_schedule_mismatch
                        ? __( 'Action Required: Review and Adjust Schedule', 'bh-features' )
                        : __( 'Action Required: Review This Subscription', 'bh-features' ),
                    $problems,
                    'bh-subscription-problems'
                );
            }
        } catch ( \Throwable $th ) {
            self::log_error( $th, 'render_details_box' );
        }
    }

    /**
     * Schedule problems of the SAVED subscription, right under its billing
     * schedule: one short line each, a one-sentence explanation in a help tip
     * (the full text is in the box above the subscription), and the two
     * selects outlined in red. The JS removes it once CS sets the schedule
     * from the product (the form then matches it).
     *
     * @param WC_Subscription $subscription
     */
    public function render_schedule_box( $subscription ) {
        try {
            if ( ! $subscription instanceof WC_Subscription || ! current_user_can( 'edit_shop_orders' ) ) {
                return;
            }

            $problems = self::get_schedule_problems( $subscription );

            if ( ! $problems ) {
                return;
            }

            echo '<style id="bh-saved-schedule-problem-style">#billing-schedule select#_billing_interval,#billing-schedule select#_billing_period{border-color:#d63638;box-shadow:0 0 0 1px #d63638;}</style>';
            echo '<div class="bh-saved-schedule-problem" role="alert" style="clear:both;margin:10px 0;padding:6px 8px;border:1px solid #d63638;border-left-width:4px;border-radius:2px;background:#fcf0f1;">';
            foreach ( $problems as $problem ) {
                echo '<p style="margin:2px 0;font-weight:600;color:#8a2424;"><span class="dashicons dashicons-warning" style="font-size:16px;width:16px;height:16px;margin-right:3px;vertical-align:text-bottom;"></span>'
                    . esc_html( $problem['short'] ) . ' '
                    . ( function_exists( 'wc_help_tip' ) ? wc_help_tip( $problem['message'] ) : '' )
                    . '</p>';
            }
            echo '</div>';
        } catch ( \Throwable $th ) {
            self::log_error( $th, 'render_schedule_box' );
        }
    }

    private static function log_error( \Throwable $th, string $method ): void {
        if ( function_exists( 'bh_plugins_error_log' ) ) {
            bh_plugins_error_log( [
                'error'    => $th->getMessage(),
                'function' => 'AH_Subscription_Admin_Product_Rules::' . $method,
            ] );
        }
    }
}

add_action( 'woocommerce_loaded', function () {
    new AH_Subscription_Admin_Product_Rules();
} );

}
