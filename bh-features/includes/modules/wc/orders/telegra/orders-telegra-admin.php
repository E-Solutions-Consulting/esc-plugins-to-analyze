<?php
/**
 * AH Orders Telegra Admin
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Orders_Telegra_Admin' ) ) {

class AH_Orders_Telegra_Admin {    

    public function __construct() {
        add_action( 'add_meta_boxes', [$this, 'add_widget_telegra_metabox'] );

        // add_filter('woocommerce_order_actions', [$this, 'hb_woocommerce_order_actions']);
        // add_action('woocommerce_order_action_resend_to_telegra', [$this, 'hb_woocommerce_order_action_resend_to_telegra']);
    }

    /*
	*	Add Widget Telegra Info
	*/
	function add_widget_telegra_metabox() {
		$order_screen_id = wcs_get_page_screen_id( 'shop_order' );
	    $screen = get_current_screen();
	    if ($screen && $screen->post_type === 'shop_order') {
	        $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

	        $entity_id = get_post_meta($order_id, 'telemdnow_entity_id', true);
	        if(empty($entity_id))
	        	return ;

            add_meta_box(
                'telegra_info_metabox',
                'Telegra Info',
                [$this, 'display_widget_telegra'],
                $order_screen_id,
                'side',
                'default'
            );
	    }
	}
	function display_widget_telegra($order) {
		$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if(empty($order_id)){
			echo 'No Info Available';
			return ;
		}
	    $entity_id = get_post_meta($order_id, 'telemdnow_entity_id', true);
	    echo '<p><a href="https://affiliate-admin.telegramd.com/orders/' . esc_html($entity_id) . '" target="_blank">Click to view order details at Telegra</a></p>';
	}

    /*
	* Custom Order Actions
	*/
	function hb_woocommerce_order_actions($actions) {
		global $theorder;

		if (is_a($theorder, 'WC_Order') && !is_a($theorder, 'WC_Subscription') && function_exists('send_order_to_telegra')) {
			$actions['resend_to_telegra'] = __('Resend order to Telegra', 'text-domain');
		}

		return $actions;
	}

	function hb_woocommerce_order_action_resend_to_telegra($order) {
		if (is_a($order, 'WC_Order') && !is_a($order, 'WC_Subscription') && function_exists('send_order_to_telegra')) {
			//$order_id = $order->get_id();
			//send_order_to_telegra($order_id);
			$order_status = get_option( 'telemdnow_trigger_action' );
            if ( ! empty( $order_status ) ) {
                $order->update_status( $order_status );
            }
		}
	}

    

}
/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new AH_Orders_Telegra_Admin();
});

}

