<?php
class Woocommerce_Webhook{

    private $table_name;
    private $custom_woo_statuses;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'telemdnow_webhook_logs';
        $this->custom_woo_statuses = TelemdNow::get_custom_woo_order_status();

        add_action('init', array($this, 'telemdnow_add_custom_order_status'));
        add_filter('wc_order_statuses', array($this, 'telemdnow_add_custom_order_status_to_dropdown'));
        add_action( 'rest_api_init', array($this, 'register_telemdnow_api_route') ); 
    }

    function telemdnow_add_custom_order_status() {

        foreach($this->custom_woo_statuses as $key => $status) {

            register_post_status($key, array(
                'label' => $status,
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop($status.' <span class="count">(%s)</span>', $status.' <span class="count">(%s)</span>')
            ));

        }
    }

    function telemdnow_add_custom_order_status_to_dropdown($order_statuses) {
        $new_order_statuses = array();
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            if ('wc-processing' === $key) {
                foreach($this->custom_woo_statuses as $statusKey => $value) {
                    $new_order_statuses[$statusKey] =  $value;
                }
            }
        }
        return $new_order_statuses;
    }

    function register_telemdnow_api_route() {

        register_rest_route('telegra', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'telegramdnow_update_order_status'),
            'permission_callback' => '__return_true',
        ));

    }

    function retrieve_and_update_wc_order_status($external_identifier, $order_status = '', $order_status_array = [], $event_title = '') 
    {
        $update_success = false;

        if ( empty( $external_identifier ) ) 
        {
            return $update_success;
        }

        $order = wc_get_order( $external_identifier );
        $request_response ='Order status not mapped for order Id ' . $order->get_id();

        if ( $order ) 
        {
            if ( !empty( $order_status_array[ $order_status ] ) ) 
            {
                $new_status = $order_status_array[ $order_status ];
                $order->add_order_note( 'The status was changed in Telegra: ' . $event_title . '. The order will transition to ' . $new_status . ' because of Telegra status mappings.' );
                $order->update_status( $new_status );

                $request_response = 'Order status mapped successfully for order Id ' . $order->get_id();
            }
        } 
        else 
        {
            return $update_success;
        }

        return ['request_response' => $request_response, 'order' => $order];
    }


    function telegramdnow_update_order_status(WP_REST_Request $request) {
        $data_received = $request->get_body();
    
        $targetEntity = $request->get_param('targetEntity');
        $orderId = $targetEntity['id'];
        $eventTitle = $request->get_param('eventTitle');
        $eventData = $request->get_param('eventData');
        $orderStatus = $eventData['newStatus'];

        if (empty($orderId) || empty($orderStatus)) {
            $request_response = 'OrderId and status are required';
            $this->telemdnow_webhook_logs('400',  $data_received, $request_response);

            return new WP_Error('invalid_request', $request_response, array('status' => 400));
        }
    
        $args = array(
            'post_type' => array('shop_order_placehold', 'shop_order'),
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                'relation' => 'OR',
                [
                    'key' => 'telemdnow_order_id',
                    'value' => $orderId,
                    'compare' => '='
                ],
                [
                    'key' => 'telemdnow_entity_id',
                    'value' => $orderId,
                    'compare' => '='
                ],
            )
        );

        // Ensure $external_identifier is valid
        $external_identifier = isset($targetEntity['externalIdentifier']) ? $targetEntity['externalIdentifier'] : null;

        // $orderStatusArray = array('requires_provider_review' => 'wc-provider_review', 'requires_order_processing' => 'wc-collect_payment', 'requires_affiliate_review' => 'wc-error_review');
        $orderStatusArray = json_decode(get_option('telegra_woo_status'), true);

        $query = new WP_Query($args);

        if (!$query->have_posts()) 
        {
            $order_data = $this->retrieve_and_update_wc_order_status($external_identifier, $orderStatus, $orderStatusArray, $eventTitle);

            if ($order_data && isset($order_data['order'])) 
            {
                $order = $order_data['order'];
                $request_response = $order_data['request_response']; 

                // $this->telemdnow_webhook_logs('200', $data_received, $request_response);
            
                $orders[] = array( 
                    'woo_order_id' => $order->get_id(),
                    'telegra_order_id' => $orderId,
                    'status'       => $order->get_status(),
                    'total'        => $order->get_total(),
                );
            
                return new WP_REST_Response($orders, 200);
            }
            else
            {
                $request_response = 'Order not found';
                $this->telemdnow_webhook_logs('404',  $data_received, $request_response);
                return new WP_Error('no_orders', $request_response, array('status' => 404));
            }
        }
    
        $orders = array();
    
        while ($query->have_posts()) {
            $query->the_post();
            $order = wc_get_order(get_the_ID());
            $request_response = 'Order status not mapped for order Id ' . $order->get_id();

            if (!empty($orderStatusArray[$orderStatus])) {
        
                $order->add_order_note('The status was changed in Telegra: ' . $eventTitle . '. The order will transition to ' . $orderStatusArray[$orderStatus] . ' because of Telegra status mappings.');
                $order->update_status($orderStatusArray[$orderStatus]);
                $request_response = 'Order status mapped successfully for order Id ' . $order->get_id();
            }

        
            $orders[] = array(
                'woo_order_id' => $order->get_id(),
                'telegra_order_id' => $orderId,
                'status' => $order->get_status(),
                'total' => $order->get_total(),
            );
        }
    
        wp_reset_postdata();
    
        // $this->telemdnow_webhook_logs('200',  $data_received, $request_response);
    
        return new WP_REST_Response($orders, 200);
    }

    function telemdnow_webhook_logs($status,  $data_received, $request_response) {
        try {
            global $wpdb;
    
    
            $insert = $wpdb->insert(
                $this->table_name, 
                array(
                    'request_status' => $status,
                    'request_url' =>  site_url() . '/wp-json/telegra/webhook',
                    'request_type' => 'POST',
                    'data_received' => $data_received,
                    'request_response' => $request_response,
                    'created_at' => current_time('mysql', false),
    
                )
            );
            if ($insert) {
                //successfully inserted.
            } else {
                plugin_log('error in telegra webhook logs insert query' . $wpdb->last_error);
            }
        } catch (Exception $e) {
            plugin_log('error in webhook error log insert' . $e->getMessage());
        }
    }

}
new Woocommerce_Webhook();