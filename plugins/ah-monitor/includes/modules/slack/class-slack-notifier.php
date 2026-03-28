<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Slack_Notifier {

    /**
     * Send message to Slack webhook.
     */
    public static function send( $message ) {

        $webhook_url = defined( 'AH_SLACK_CHANNEL_MONITOR_ORDERS' ) ? AH_SLACK_CHANNEL_MONITOR_ORDERS : '';

        if ( empty( $webhook_url ) ) {
            return;
        }

        $payload = [
            'text' => $message,
        ];

        $response	=	wp_remote_post( $webhook_url, [
            'body' => json_encode( $payload ),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ] );
        
    }

}