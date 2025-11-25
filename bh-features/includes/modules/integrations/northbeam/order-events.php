<?php
/**
 * Northbeam Server-side Order Events
 */

if (!defined('ABSPATH')) exit;

add_action('woocommerce_order_status_completed', function($order_id){

    $order = wc_get_order($order_id);
    if (!$order) return;

    // Example payload for NB
    $payload = [
        'orderId' => $order_id,
        'email'   => $order->get_billing_email(),
        'total'   => $order->get_total(),
        'time'    => time(),
    ];

    if (class_exists('BH_Northbeam_API')) {
        BH_Northbeam_API::send($payload);
    }

});
