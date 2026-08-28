<?php
/**
 * Checkout UI Customizations Module
 *
 * @description
 * This module contains all UI customizations for the checkout,
 * including custom fields, custom step content, integrations with
 * multistep checkout plugins, and HTML output modifications.
 *
 * Important: Keep only UI-related logic here.
 * Validation logic should remain inside the checkout-validation module.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_WC_Admin' ) ) {

class AH_WC_Admin {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20 );
        add_action('woocommerce_admin_order_data_after_shipping_address', [ $this, 'display_warning_message_if_shipping_state_not_licensed' ], 10, 1);
    }

    public function enqueue_scripts($hook) {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $allowed_screens = [
            // Orders
            'post',                 // edit post (legacy)
            'woocommerce_page_wc-orders', // HPOS orders

            // Subscriptions
            'shop_subscription',    // legacy subscription
            'woocommerce_page_wc-subscriptions',
        ];

        if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
            return;
        }
		wp_enqueue_script( 
            'ah-admin-order', 
            plugin_dir_url( __FILE__ ) . 'assets/js/admin-order.js',
            array( 'jquery' ), 
            null, 
            true
        );
		
        $states			=	AH_States::get_all();
        $_states        =   AH_States::get_states_for_current_user($states, true);
		$licensed_states=	array_keys($_states['US']);
		wp_localize_script('ah-admin-order', 'allowedStates', $licensed_states);
	}

	function display_warning_message_if_shipping_state_not_licensed($order) {
        $states		=	AH_States::get_all();
        $states     =   AH_States::get_states_for_current_user($states, true);
        $restricted =	array_keys($states['US']);
	    $billing_state = $order->get_shipping_state();

	    if (!in_array($billing_state, $restricted)) {
	        echo '<div class="warning-message text">
					<strong>Warning:</strong> This state (' . esc_html($billing_state) . ') is not licensed for shipping.
	              </div>';
	    }
	}
}

add_action( 'woocommerce_loaded', function() {
    if ( class_exists( 'AH_WC_Admin' ) ) {
        new AH_WC_Admin();
    }
});

}