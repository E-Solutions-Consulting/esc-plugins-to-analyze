<?php
/**
 * Renewal Items Change Flag
 *
 * validate_previous_order_status_before_renewal() cancels a renewal (and the
 * subscription) when the previous renewal order didn't reach "completed".
 * That's wrong for one common CS workflow: at the customer's request, CS
 * swaps the product on the subscription (removes the line item, adds the new
 * one, moves the next payment date) and cancels the previous order as part of
 * the change. The previous order then reads as abandoned and the next renewal
 * wipes out a subscription the customer explicitly asked to keep.
 *
 * When line items are added to or removed from a subscription in wp-admin,
 * this module records the subscription's latest renewal order id in a meta
 * flag. On the next renewal, if the guard is about to reject that exact
 * order and its status is one CS uses when replacing an order
 * (SKIP_STATUSES), the check is waived once: the renewal goes through, a note
 * explains why, and the flag is deleted. A flag pointing at any other order
 * is stale and is simply discarded.
 *
 * Previous orders still in the Telegra pipeline are NOT waived — skipping the
 * check there would open a second order in Telegra for the same patient.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Renewal_Items_Change_Flag' ) ) {

class AH_Renewal_Items_Change_Flag {

    /** Subscription meta: renewal order id that was the latest one when CS changed the items. */
    const META_FLAG = '_ah_items_changed_after_order';

    private $logger;
    private $log_context = [ 'source' => 'ah-renewal-items-change-flag' ];

    public function __construct() {
        $this->logger = wc_get_logger();

        add_action( 'woocommerce_ajax_order_items_added', [ $this, 'on_items_added' ], 10, 2 );
        add_action( 'woocommerce_ajax_order_items_removed', [ $this, 'on_items_removed' ], 10, 4 );
        add_filter( 'ah_skip_previous_order_validation', [ $this, 'maybe_skip_validation' ], 10, 4 );
    }

    /**
     * Previous-order statuses the waiver applies to: the ones CS leaves the
     * replaced order in when swapping products. 'refunded' is included because
     * whether the replaced order ends up cancelled or refunded only depends on
     * whether CS also returned the money in Stripe, not on the customer's intent.
     *
     * @return string[]
     */
    private static function skip_statuses(): array {
        return [ 'cancelled', 'refunded', CANCEL_CUSTOMER_REQUEST ];
    }

    /**
     * @param WC_Order_Item[] $added_items
     * @param WC_Order        $order
     */
    public function on_items_added( $added_items, $order ) {
        $this->flag( $order, 'added' );
    }

    /**
     * @param int                 $item_id
     * @param WC_Order_Item|false $item
     * @param mixed               $changed_stock
     * @param WC_Order            $order
     */
    public function on_items_removed( $item_id, $item, $changed_stock, $order ) {
        if ( $item instanceof WC_Order_Item && 'line_item' !== $item->get_type() ) {
            return; // Fees, shipping and coupons aren't a product change.
        }

        $this->flag( $order, 'removed' );
    }

    /**
     * Point the flag at the subscription's latest renewal order.
     *
     * @param mixed  $order
     * @param string $action
     */
    private function flag( $order, string $action ): void {
        try {
            if ( ! $order instanceof WC_Subscription ) {
                return;
            }

            $latest = self::get_latest_renewal( $order );

            if ( ! $latest ) {
                return; // No renewal yet — the guard has nothing to check.
            }

            // Same instance WooCommerce is editing, so its own save can't drop the flag.
            $order->update_meta_data( self::META_FLAG, $latest->get_id() );
            $order->save_meta_data();

            $this->logger->info( sprintf(
                '[flag] subscription_id=%d items %s — flagged latest renewal order #%d (status=%s)',
                $order->get_id(),
                $action,
                $latest->get_id(),
                $latest->get_status()
            ), $this->log_context );
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[flag] error=%s', $th->getMessage() ), $this->log_context );
        }
    }

    /**
     * Latest renewal order by creation date — the same ordering the guard
     * uses to pick "the previous order".
     *
     * @param WC_Subscription $subscription
     * @return WC_Order|null
     */
    private static function get_latest_renewal( WC_Subscription $subscription ) {
        $latest = null;

        foreach ( $subscription->get_related_orders( 'all', 'renewal' ) as $order ) {
            if ( class_exists( 'AH_Renewal_Intake_Review' ) && AH_Renewal_Intake_Review::is_parked_renewal( $order ) ) {
                continue; // The guard skips these too.
            }

            if ( ! $latest || $order->get_date_created() > $latest->get_date_created() ) {
                $latest = $order;
            }
        }

        return $latest;
    }

    /**
     * Filter callback for ah_skip_previous_order_validation.
     *
     * @param bool            $skip
     * @param WC_Order        $previous_order
     * @param WC_Order        $renewal_order
     * @param WC_Subscription $subscription
     * @return bool
     */
    public function maybe_skip_validation( $skip, $previous_order, $renewal_order, $subscription ) {
        try {
            if ( $skip || ! $subscription instanceof WC_Subscription || ! $previous_order instanceof WC_Order ) {
                return $skip;
            }

            $flagged_id = (int) $subscription->get_meta( self::META_FLAG );

            if ( ! $flagged_id ) {
                return $skip;
            }

            // Single use: whatever the outcome, this flag has been evaluated.
            $subscription->delete_meta_data( self::META_FLAG );
            $subscription->save_meta_data();

            if ( $flagged_id !== $previous_order->get_id() ) {
                return $skip; // Stale — items were changed before a later order.
            }

            if ( ! in_array( $previous_order->get_status(), self::skip_statuses(), true ) ) {
                return $skip; // Completed, genuine failure, or still in the Telegra pipeline.
            }

            $subscription->add_order_note( sprintf(
                'Previous-order check skipped for renewal #%d: previous order #%d is "%s", but the subscription\'s products were changed after that order was created (product change by CS).',
                $renewal_order->get_id(),
                $previous_order->get_id(),
                $previous_order->get_status()
            ) );

            $this->logger->info( sprintf(
                '[maybe_skip_validation] subscription_id=%d renewal_order_id=%d previous_order_id=%d status=%s — check skipped (items changed after previous order)',
                $subscription->get_id(),
                $renewal_order->get_id(),
                $previous_order->get_id(),
                $previous_order->get_status()
            ), $this->log_context );

            return true;
        } catch ( \Throwable $th ) {
            $this->logger->error( sprintf( '[maybe_skip_validation] error=%s', $th->getMessage() ), $this->log_context );
            return $skip;
        }
    }
}

add_action( 'woocommerce_loaded', function () {
    new AH_Renewal_Items_Change_Flag();
} );

}
