<?php
/**
 * Northbeam API Client
 */

if (!defined('ABSPATH')) exit;

class BH_Northbeam_API {

    public static function send($payload) {

        $url = "https://api.northbeam.io/v1/order";

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            error_log("[Northbeam] API Error: " . $response->get_error_message());
            return false;
        }

        error_log("[Northbeam] API Response: " . wp_remote_retrieve_body($response));
        return true;
    }
}
