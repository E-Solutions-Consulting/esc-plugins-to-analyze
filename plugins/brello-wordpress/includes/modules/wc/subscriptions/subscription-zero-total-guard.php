<?php
/**
 * Subscription Zero-Total Guard
 *
 * When CS swaps a subscription's product in wp-admin, WooCommerce recalculates
 * the totals when an item is REMOVED but not when one is ADDED. Removing the
 * old product first leaves the subscription with no items and a $0 total;
 * adding the new product afterwards keeps that $0 unless CS clicks
 * "Recalculate". The next renewal is then created at $0, WooCommerce
 * Subscriptions marks it paid on the spot (payment_complete() on any $0
 * renewal) and it goes to fulfilment for free.
 *
 * Two layers:
 *  A. Items added to a subscription in wp-admin recalculate its totals right
 *     away, re-applying its coupons (so a 100% recurring coupon keeps covering
 *     the new product). The "Recalculate" button alone does NOT re-apply them.
 *  B. A renewal created at $0 whose line items are worth more than $0 (i.e. the
 *     $0 comes from stale subscription totals, not from a 100% coupon or a
 *     free-renewal delivery) is held: it is not marked paid, not sent to
 *     fulfilment, and both orders get a note for CS. The subscription stays
 *     on-hold (WCS puts it there before creating the renewal).
 *
 * Only renewal orders are checked. Orders created with wc_create_order()
 * (e.g. $0 welcome-kit orders) never pass through wcs_renewal_order_created.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Subscription_Zero_Total_Guard' ) ) {

class AH_Subscription_Zero_Total_Guard {

    /** Renewal order meta: held because its $0 total doesn't match its items. */
    const META_HOLD = '_ah_zero_total_hold';

    private $logger;
    private $log_context = [ 'source' => 'ah-subscription-zero-total-guard' ];

    public function __construct() {
        $this->logger = wc_get_logger();

        add_action( 'woocommerce_ajax_order_items_added', [ $this, 'recalculate_after_items_added' ], 20, 2 );

        // After AH_Free_Renewals (20), so free-renewal deliveries are already tagged.
        add_filter( 'wcs_renewal_order_created', [ $this, 'maybe_hold_renewal' ], 25, 2 );
        add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', [ $this, 'block_auto_payment_complete' ], 10, 2 );
    }

    /**
     * What the order's total should be after a recalculation — computed in
     * memory, nothing is saved. Coupons are re-applied the same way
     * WC_Abstract_Order::recalculate_coupons() does, so a product swapped on a
     * subscription with a 100% recurring coupon correctly comes out at $0
     * (its new line item still carries the full price until coupons are
     * re-applied).
     *
     * @param WC_Abstract_Order $order
     * @return float
     */
    public static function expected_total( WC_Abstract_Order $order ): float {
        $sum     = 0.0;
        $coupons = $order->get_items( 'coupon' );

        if ( $coupons ) {
            $discounts = new WC_Discounts( $order ); // Starts from line subtotals (pre-discount).

            foreach ( $coupons as $coupon_item ) {
                $coupon = self::coupon_for_item( $order, $coupon_item );

                if ( $coupon ) {
                    $discounts->apply_coupon( $coupon, false );
                }
            }

            foreach ( $order->get_items( 'line_item' ) as $item ) {
                $sum += (float) $item->get_subtotal() + (float) $item->get_subtotal_tax();
            }

            $sum -= array_sum( $discounts->get_discounts_by_item() );
        } else {
            foreach ( $order->get_items( 'line_item' ) as $item ) {
                $sum += (float) $item->get_total() + (float) $item->get_total_tax();
            }
        }

        foreach ( $order->get_items( [ 'fee', 'shipping' ] ) as $item ) {
            $sum += (float) $item->get_total() + (float) $item->get_total_tax();
        }

        return max( 0.0, round( $sum, wc_get_price_decimals() ) );
    }

    /**
     * Coupon object for a coupon line, built the way recalculate_coupons()
     * does (including a fixed-amount stand-in when the coupon was deleted).
     *
     * @param WC_Abstract_Order    $order
     * @param WC_Order_Item_Coupon $coupon_item
     * @return WC_Coupon|null
     */
    private static function coupon_for_item( WC_Abstract_Order $order, $coupon_item ) {
        $code      = $coupon_item->get_code();
        $coupon_id = wc_get_coupon_id_by_code( $code );

        if ( $coupon_id ) {
            $coupon = new WC_Coupon( $coupon_id );
        } else {
            $coupon = new WC_Coupon();
            $coupon->set_code( $code );
            $coupon->set_virtual( true );
            $coupon->set_discount_type( 'fixed_cart' );
            $coupon->set_amount( (float) $coupon_item->get_discount() + ( $order->get_prices_include_tax() ? (float) $coupon_item->get_discount_tax() : 0 ) );
        }

        return apply_filters( 'woocommerce_order_recalculate_coupons_coupon_object', $coupon, $code, $coupon_item, $order );
    }

    /**
     * Recalculate an order/subscription for real: re-apply its coupons when it
     * has any (so a 100% coupon keeps covering a swapped product), otherwise
     * the same recalculation as the "Recalculate" button. Saves.
     *
     * @param WC_Abstract_Order $order
     */
    public static function recalculate( WC_Abstract_Order $order ): void {
        if ( $order->get_items( 'coupon' ) ) {
            $order->recalculate_coupons();
        } else {
            $order->calculate_totals( true );
        }
    }

    /**
     * Whether an order's stored total is $0 while its items say otherwise.
     *
     * @param WC_Abstract_Order $order
     * @return bool
     */
    public static function has_stale_zero_total( WC_Abstract_Order $order ): bool {
        return (float) $order->get_total() <= 0 && self::expected_total( $order ) > 0;
    }

    /**
     * A. Recalculate a subscription's totals as soon as items are added to it in wp-admin.
     *
     * @param WC_Order_Item[] $added_items
     * @param WC_Order        $order
     */
    public function recalculate_after_items_added( $added_items, $order ) {
        try {
            if ( ! $order instanceof WC_Subscription ) {
                return;
            }

            $before = (float) $order->get_total();
            self::recalculate( $order );

            if ( (float) $order->get_total() !== $before ) {
                $order->add_order_note( sprintf(
                    'Totals recalculated automatically after adding items: %s → %s.',
                    wc_price( $before, [ 'currency' => $order->get_currency() ] ),
                    wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] )
                ) );
            }
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[recalculate_after_items_added] error=%s', $th->getMessage() ), $this->log_context );
        }
    }

    /**
     * B. Hold a $0 renewal whose items are worth more than $0.
     *
     * @param WC_Order        $renewal_order
     * @param WC_Subscription $subscription
     * @return WC_Order
     */
    public function maybe_hold_renewal( $renewal_order, $subscription ) {
        try {
            if ( ! $renewal_order instanceof WC_Order || ! $subscription instanceof WC_Subscription ) {
                return $renewal_order;
            }

            if ( $renewal_order->has_status( [ 'cancelled', 'trash' ] ) ) {
                return $renewal_order;
            }

            if ( 'yes' === $renewal_order->get_meta( '_bh_free_renewal_delivery' ) ) {
                return $renewal_order; // Intentional $0 free-renewal delivery.
            }

            if ( ! self::has_stale_zero_total( $renewal_order ) ) {
                return $renewal_order;
            }

            $expected = self::expected_total( $renewal_order );
            $currency = [ 'currency' => $renewal_order->get_currency() ];

            $renewal_order->update_meta_data( self::META_HOLD, 'yes' );
            $renewal_order->add_order_note( sprintf(
                'Held for review: this renewal was created at %s but its products add up to %s (the subscription totals were not recalculated after a product change). It was NOT marked as paid and NOT sent to fulfilment. Resolve it from Brello > Zero-Total Review (recalculates with coupons re-applied), or set it to Processing if $0 is intended.',
                wc_price( 0, $currency ),
                wc_price( $expected, $currency )
            ) );
            $renewal_order->save();

            $subscription->add_order_note( sprintf(
                'Renewal #%d held for review: created at $0 while its products add up to %s. Recalculate this subscription\'s totals (Brello > Zero-Total Review) and resolve the renewal.',
                $renewal_order->get_id(),
                wc_price( $expected, $currency )
            ) );

            $this->logger->warning( sprintf(
                '[maybe_hold_renewal] subscription_id=%d renewal_order_id=%d expected=%s — held (stale $0 total)',
                $subscription->get_id(),
                $renewal_order->get_id(),
                $expected
            ), $this->log_context );
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[maybe_hold_renewal] error=%s', $th->getMessage() ), $this->log_context );
        }

        return $renewal_order;
    }

    /**
     * Keep WooCommerce Subscriptions' automatic payment_complete() from marking
     * a held $0 renewal as paid. Once the order is recalculated (total > 0) a
     * real payment goes through as usual.
     *
     * @param string[] $statuses
     * @param WC_Order $order
     * @return string[]
     */
    public function block_auto_payment_complete( $statuses, $order ) {
        if ( $order instanceof WC_Order && 'yes' === $order->get_meta( self::META_HOLD ) && (float) $order->get_total() <= 0 ) {
            return [];
        }

        return $statuses;
    }
}

add_action( 'woocommerce_loaded', function () {
    new AH_Subscription_Zero_Total_Guard();
} );

}
