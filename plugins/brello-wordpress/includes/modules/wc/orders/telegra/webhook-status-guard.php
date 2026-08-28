<?php
/**
 * Blocks telemdnow-wp-plugin's Telegra webhook (POST /telegra/webhook) from
 * regressing orders that are already completed/refunded/cancelled. The
 * external plugin calls $order->update_status() unconditionally, so a
 * stale or out-of-order webhook can move a finished order backwards
 * (e.g. completed -> collect_payment) and leave it stuck there.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Telegra_Webhook_Status_Guard' ) ) {

class AH_Telegra_Webhook_Status_Guard {

    const FINAL_STATUSES = array( 'completed', 'refunded', 'cancelled' );

    /**
     * Priority 999: ACF's own rest_pre_dispatch hook (ACF_Rest_Api::initialize) unconditionally
     * resets the filtered value back to null, so this must run after it to actually short-circuit.
     */
    public function __construct() {
        add_filter( 'rest_pre_dispatch', array( $this, 'block_status_regression' ), 999, 3 );
    }

    /**
     * Short-circuits the webhook response when every matched order is already final, so telemdnow-wp-plugin's callback never runs.
     */
    public function block_status_regression( $response, $server, $request ) {

        if ( null !== $response || 'POST' !== $request->get_method() ) {
            return $response;
        }

        if ( '/telegra/webhook' !== untrailingslashit( $request->get_route() ) ) {
            return $response;
        }

        $target_entity        = $request->get_param( 'targetEntity' );
        $telegra_order_id     = is_array( $target_entity ) ? ( $target_entity['id'] ?? null ) : null;
        $external_identifier  = is_array( $target_entity ) ? ( $target_entity['externalIdentifier'] ?? null ) : null;

        $orders = $this->find_orders( $telegra_order_id, $external_identifier );

        if ( empty( $orders ) ) {
            return $response;
        }

        $protected_orders = array_filter( $orders, function( $order ) {
            return $order->has_status( self::FINAL_STATUSES );
        } );

        if ( empty( $protected_orders ) ) {
            return $response;
        }

        $results = array();

        foreach ( $protected_orders as $order ) {
            wc_get_logger()->info(
                sprintf(
                    'Blocked Telegra status regression on order #%d (status: %s, telegra order id: %s)',
                    $order->get_id(),
                    $order->get_status(),
                    $telegra_order_id
                ),
                array( 'source' => 'ah-telegra-webhook-guard' )
            );

            $results[] = array(
                'woo_order_id'     => $order->get_id(),
                'telegra_order_id' => $telegra_order_id,
                'status'           => $order->get_status(),
                'total'            => $order->get_total(),
                'note'             => 'Skipped: order already in a final status.',
            );
        }

        return new WP_REST_Response( $results, 200 );
    }

    /**
     * Mirrors telemdnow-wp-plugin's own order lookup (meta_query by telemdnow_order_id/telemdnow_entity_id, falling back to externalIdentifier as a direct order ID), via wc_get_orders() so it queries wc_orders_meta directly under HPOS instead of falling back to WP_Query's post meta lookup.
     */
    private function find_orders( $telegra_order_id, $external_identifier ) {

        $orders = array();

        if ( ! empty( $telegra_order_id ) ) {
            global $wpdb;
            $ids = array();
            foreach ( array( 'telemdnow_entity_id', 'telemdnow_order_id' ) as $meta_key ) {
                $order_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                        $meta_key,
                        $telegra_order_id
                    )
                );
                if ( $order_id ) {
                    $ids[] = (int) $order_id;
                }
            }
            foreach ( array_unique( $ids ) as $oid ) {
                $order = wc_get_order( $oid );
                if ( $order ) {
                    $orders[] = $order;
                }
            }
        }

        if ( empty( $orders ) && ! empty( $external_identifier ) ) {
            $order = wc_get_order( $external_identifier );
            if ( $order ) {
                $orders[] = $order;
            }
        }

        return $orders;
    }
}

add_action( 'woocommerce_loaded', function() {
    if ( class_exists( 'AH_Telegra_Webhook_Status_Guard' ) ) {
        new AH_Telegra_Webhook_Status_Guard();
    }
} );

}
