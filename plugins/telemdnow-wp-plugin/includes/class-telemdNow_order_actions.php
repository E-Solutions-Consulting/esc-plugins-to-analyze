<?php

class TelemdNow_Order_Actions {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {

        add_action('woocommerce_order_status_changed', array($this, 'woocommerce_order_status_changed'), 99, 4);
    }

    function woocommerce_order_status_changed($order_id, $old_status, $new_status, $order) {

        $orderStatusArray = json_decode(get_option('telegra_woo_actions'), true);

        $postcheckout = !empty(get_post_meta($order_id, 'telemdnow_entity_id', true)) ? get_post_meta($order_id, 'telemdnow_entity_id', true) : $order->get_meta('telemdnow_entity_id');
        $precheckout = !empty(get_post_meta($order_id, 'telemdnow_order_id', true)) ? get_post_meta($order_id, 'telemdnow_order_id', true) : $order->get_meta('telemdnow_order_id');

        $telegraOrderId = (!empty($postcheckout)) ? $postcheckout : $precheckout;

        if (empty($telegraOrderId)) {
            return false;
        }

        if( empty($orderStatusArray) || !isset($orderStatusArray['wc-' . $new_status])) {
            plugin_log('no order action defined for order status ' . $new_status .  ', order id ' . $order_id);
            return false;
        }

        switch ($orderStatusArray['wc-' . $new_status]) {
            case "send_order_to_pharmacy":
                if (get_post_meta($order_id, 'order_sent_pharmacy')) {
                    $order->add_order_note('order already sent to pharmacy ');
                    return false;
                }
                $this->send_order_to_pharmacy($order_id, $telegraOrderId,  $order);
                break;
            case "cancel_order":
                if (get_post_meta($order_id, 'telegra_order_cancelled')) {
                    $order->add_order_note('order already cancelled ');
                    return false;
                }
                $this->cancel_telegra_order($order_id, $telegraOrderId, $order);
                break;
            case "expedite":
                if (get_post_meta($order_id, 'telegra_order_expedite')) {
                    $order->add_order_note('order already in expedite ');
                    return false;
                }
                $this->expedite_telegra_order($order_id, $telegraOrderId, $order);
                break;

            case "leave_waiting_room":
                if (get_post_meta($order_id, 'leave_waiting_room')) {
                    $order->add_order_note('order already in leaveWaitingRoom ');
                    return false;
                }
                $this->leave_waiting_room($order_id, $telegraOrderId, $order);
                break;
            default:
                plugin_log('no order action defined for order status ' . $new_status);
        }
    }

    public function send_order_to_pharmacy__original($order_id, $telegraOrderId, $order) {
        try {
            $affiliate_private_token = get_authenticationToken();
            $data = array(
                'orderIdentifier' => $telegraOrderId,
            );

            $curl = curl_init();
            $telemdnow_rest_url = get_option('telemdnow_rest_url');
            $api_url = $telemdnow_rest_url . '/orders/actions/sendToPharmacyRecipients?access_token=' . $affiliate_private_token;
            $data_sent = json_encode($data);
            curl_setopt_array($curl, array(
                CURLOPT_URL => $api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data_sent,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);

            $res = json_decode($response);


            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($httpCode != 200 && $httpCode != 201) {
                $order->add_order_note('Error in send order to pharmacy action. Please check error logs for more detail');
                telemdnow_api_error($httpCode, $api_url, 'POST', $data_sent, $response);
            } else {
                $order->add_order_note('Sent to pharmacy order action executed');
                update_post_meta($order_id, 'order_sent_pharmacy', true);
            }

            curl_close($curl);
        } catch (Exception $e) {
            plugin_log('error in telegra order send to pharmacy action' . $e->getMessage());
        }
    }

    public function send_order_to_pharmacy($order_id, $telegraOrderId, $order) {
        try {
            $affiliate_private_token = get_authenticationToken();
            $data = array(
                'orderIdentifier' => $telegraOrderId,
            );

            $telemdnow_rest_url = get_option('telemdnow_rest_url');
            $api_url = $telemdnow_rest_url . '/orders/actions/sendToPharmacyRecipients?access_token=' . $affiliate_private_token;
            $data_sent = json_encode($data);

            $max_retries = 3;
            $attempt = 0;
            $success = false;

            while ($attempt < $max_retries && !$success) {
                $attempt++;
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $api_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $data_sent,
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json'
                    ),
                ));

                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($httpCode == 504) {
                    // Retry after 30 seconds if not last attempt
                    if ($attempt < $max_retries) {
                        sleep(30);
                    }
                } elseif ($httpCode == 200 || $httpCode == 201) {
                    $success = true;
                    break;
                } else {
                    // Any other HTTP code → stop retrying
                    break;
                }
            }

            // Add final note based on success or failure
            if ($success) {
                $order->add_order_note('Sent to pharmacy order action executed');
                update_post_meta($order_id, 'order_sent_pharmacy', true);
            } else {
                // $order->add_order_note('Error in send order to pharmacy action. Please check error logs for more detail');
                // telemdnow_api_error($httpCode, $api_url, 'POST', $data_sent, $response);
                $inserted_id    =   telemdnow_api_error($httpCode, $api_url, 'POST', $data_sent, $response);
                $order->add_order_note(
                    sprintf(
                        'Error in send order to pharmacy action%s. Please check <a href="%s" target="_blank">error logs</a> for more detail.',
                        telemdnow_api_error_message($response),
                        telemdnow_api_error_log_url($inserted_id)
                    )
                );
            }

        } catch (Exception $e) {
            plugin_log('error in telegra order send to pharmacy action' . $e->getMessage());
        }
    }

    public function cancel_telegra_order($order_id, $telegraOrderId, $order) {

        try {

            $affiliate_private_token = get_authenticationToken();
            $curl = curl_init();
            $telemdnow_rest_url = get_option('telemdnow_rest_url');
            $api_url = $telemdnow_rest_url . '/orders/' . $telegraOrderId . '/actions/cancel?access_token=' . $affiliate_private_token;

            curl_setopt_array($curl, array(
                CURLOPT_URL => $api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);

            $res = json_decode($response);

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($httpCode != 200 && $httpCode != 201) {
                $order->add_order_note('Error in cancel order action. Please check error logs for more detail');
                telemdnow_api_error($httpCode, $api_url, 'POST', '', $response);
            } else {
                $order->add_order_note('Cancel order action executed at telegra');
                update_post_meta($order_id, 'telegra_order_cancelled', true);
            }

            curl_close($curl);
        } catch (Exception $e) {
            plugin_log('error in telegra order cancel action' . $e->getMessage());
        }
    }

    public function expedite_telegra_order($order_id, $telegraOrderId, $order) {

        try {

            $affiliate_private_token = get_authenticationToken();
            $curl = curl_init();
            $telemdnow_rest_url = get_option('telemdnow_rest_url');
            $api_url = $telemdnow_rest_url . '/orders/' . $telegraOrderId . '/actions/expedite?access_token=' . $affiliate_private_token;
            $data = array(
                'expedited' => true,
            );
            $data_sent = json_encode($data);
            curl_setopt_array($curl, array(
                CURLOPT_URL => $api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $data_sent,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);

            $res = json_decode($response);

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($httpCode != 200 && $httpCode != 201) {
                $order->add_order_note('Error in expedite order action. Please check error logs for more detail');
                telemdnow_api_error($httpCode, $api_url, 'PUT', $data_sent, $response);
            } else {
                $order->add_order_note('Expedite order action executed at telegra');
                update_post_meta($order_id, 'telegra_order_expedite', true);
            }

            curl_close($curl);
        } catch (Exception $e) {
            plugin_log('error in telegra order expedite action' . $e->getMessage());
        }
    }

    public function leave_waiting_room($order_id, $telegraOrderId, $order) {

        try {
            $affiliate_private_token = get_authenticationToken();
            $curl = curl_init();
            $telemdnow_rest_url = get_option('telemdnow_rest_url');
            $api_url = $telemdnow_rest_url . '/orders/' . $telegraOrderId . '/actions/leaveWaitingRoom?access_token=' . $affiliate_private_token;

            curl_setopt_array($curl, array(
                CURLOPT_URL => $api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);

            $res = json_decode($response);

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($httpCode != 200 && $httpCode != 201) {
                $order->add_order_note('Error in leaveWaitingRoom order action. Please check error logs for more detail');
                telemdnow_api_error($httpCode, $api_url, 'POST', '', $response);
            } else {
                $order->add_order_note('Leave Waiting Room order action executed at telegra');
                update_post_meta($order_id, 'leave_waiting_room', true);
            }

            curl_close($curl);
        } catch (Exception $e) {
            plugin_log('error in telegra order leaveWaitingRoom action' . $e->getMessage());
        }
    }
}
// new TelemdNow_Order_Actions();
TelemdNow_Order_Actions::get_instance();
