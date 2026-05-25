<?php
/**
 * AH Orders Telegra Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Orders_Telegra_Admin' ) ) {

class AH_Orders_Telegra_Admin {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_widget_telegra_metabox' ] );
        add_filter( 'woocommerce_order_actions', [ $this, 'hb_woocommerce_order_actions' ] );
        add_action( 'woocommerce_order_action_resend_to_telegra', [ $this, 'hb_woocommerce_order_action_resend_to_telegra' ] );
    }

    /**
     * Adds the Telegra Info metabox to the order screen (only if entity_id exists).
     */
    function add_widget_telegra_metabox() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'shop_order' ) {
            return;
        }

        $order_id  = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        $entity_id = get_post_meta( $order_id, 'telemdnow_entity_id', true );
        if ( empty( $entity_id ) ) {
            return;
        }

        $order_screen_id = wcs_get_page_screen_id( 'shop_order' );
        add_meta_box(
            'telegra_info_metabox',
            'Telegra Info',
            [ $this, 'display_widget_telegra' ],
            $order_screen_id,
            'side',
            'high'
        );
    }

    /**
     * Renders the Telegra Info metabox content.
     */
    function display_widget_telegra( $order ) {
        $order_id  = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        if ( empty( $order_id ) ) {
            echo '<p>No Info Available</p>';
            return;
        }
        $entity_id = get_post_meta( $order_id, 'telemdnow_entity_id', true );
        echo '<p><a href="https://affiliate-admin.telegramd.com/orders/' . esc_html( $entity_id ) . '" target="_blank">Click to view order details at Telegra</a></p>';
    }

    /**
     * Registers the "Resend order to Telegra" action in the order actions dropdown.
     */
    function hb_woocommerce_order_actions( $actions ) {
        global $theorder;

        if (
            is_a( $theorder, 'WC_Order' ) &&
            ! is_a( $theorder, 'WC_Subscription' ) &&
            function_exists( 'send_order_to_telegra' )
        ) {
            $actions['resend_to_telegra'] = __( 'Resend order to Telegra', 'bh-features' );
        }

        return $actions;
    }

    /**
     * Executes the resend: calls send_order_to_telegra() directly, bypassing
     * the status-change guard that skips orders with an existing telemdnow_entity_id.
     */
    function hb_woocommerce_order_action_resend_to_telegra( $order ) {
        if (
            ! is_a( $order, 'WC_Order' ) ||
            is_a( $order, 'WC_Subscription' ) ||
            ! function_exists( 'send_order_to_telegra' )
        ) {
            return;
        }

        $order_id = $order->get_id();
        $logger   = wc_get_logger();
        $context  = [ 'source' => 'telegra-resend' ];

        $logger->info( "Resend to Telegra triggered for order #{$order_id}.", $context );

        send_order_to_telegra( $order_id );

        $logger->info( "send_order_to_telegra() completed for order #{$order_id}.", $context );
    }
}

add_action( 'woocommerce_loaded', function() {
    new AH_Orders_Telegra_Admin();
} );

}