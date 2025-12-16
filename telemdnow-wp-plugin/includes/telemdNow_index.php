<?php

class TelemdNow {



    public function __construct() {


        $this->load_dependencies();
    }

    private function load_dependencies() {

        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/woocommerce_webhook.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-telemdNow_order_actions.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/telemdNow_api_logs.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/telemdNow_webhook_logs.php';
    }
    public static function get_telegra_order_status() {

        return array(
             'requires_waiting_room_egress'    => __('Waiting Room', 'woocommerce'),
             'requires_provider_review'    => __('Requires Provider Review', 'woocommerce'),
             'requires_order_processing' => __('Requires Order Processing', 'woocommerce'),
             'requires_affiliate_review'    => __('Requires Affiliate Review', 'woocommerce'),
             'completed'  => __('Completed', 'woocommerce'),
             'cancelled'  => __('Cancelled', 'woocommerce'),
             'requires_prerequisite_completion'   => __('Requires Prerequisites', 'woocommerce'),
             'requires_admin_review'     => __('Requires Admin Review', 'woocommerce'),
           );
     
          
     }
 
     public static function get_custom_woo_order_status() {
 
         return  array(
             'wc-waiting_room'    => __('Waiting Room', 'woocommerce'),
             'wc-provider_review'    => __('Provider Review', 'woocommerce'),
             'wc-collect_payment' => __('Collect Payment', 'woocommerce'),
             'wc-error_review'    => __('Error - Review', 'woocommerce'),
             'wc-completed'  => __('Completed', 'woocommerce'),
             'wc-cancelled'  => __('Cancelled', 'woocommerce'),
             'wc-prerequisites'   => __('Require Prerequisites', 'woocommerce'),
             'wc-admin_review'   => __('Admin Review', 'woocommerce'),
           );
         
     }

     public static function get_telegra_order_actions() {
 
        return  array(
            'send_order_to_pharmacy'  => __('Send Order To Pharmacies', 'woocommerce'),
            'cancel_order'  => __('Cancel Order', 'woocommerce'),
            'expedite'  => __('Expedite Order', 'woocommerce'),
            'leave_waiting_room'  => __('Leave the Waiting Room', 'woocommerce'),
          );
        
    }
}