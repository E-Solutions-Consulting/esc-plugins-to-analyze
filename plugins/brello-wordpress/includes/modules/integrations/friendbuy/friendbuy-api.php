<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Friendbuy_API {

    const OPTION_KEY = 'ah_friendbuy_auth_data';
    const AUTH_URL   = 'https://mapi.fbot.me/v1/authorization';
    const EVENT_URL  = 'https://mapi.fbot.me/v1/event/purchase';

    /**
     * Get valid Bearer token.
     */
    public static function get_token() {

        $logger = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        $auth_data = get_option( self::OPTION_KEY );

        if ( $auth_data && ! empty( $auth_data['token'] ) && ! empty( $auth_data['expires'] ) ) {

            $now = time();

            // renew 120 seconds before expiration
            if ( $now < ( $auth_data['expires'] - 120 ) ) {
                return $auth_data['token'];
            }

            $logger->info( 'Token expired or about to expire. Renewing...', $context );
        }

        return self::refresh_token();
    }

    /**
     * Request new token from Friendbuy.
     */
    private static function refresh_token() {

        $logger = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        $response = wp_remote_post( self::AUTH_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( [
                'key'    => FRIENDBUY_API_KEY,
                'secret' => FRIENDBUY_API_SECRET,
            ] ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            $logger->error( 'Auth request failed: ' . $response->get_error_message(), $context );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['token'] ) || empty( $body['expires'] ) ) {
            $logger->error( 'Invalid auth response: ' . print_r( $body, true ), $context );
            return false;
        }

        $expires_timestamp = strtotime( $body['expires'] );

        update_option( self::OPTION_KEY, [
            'token'   => $body['token'],
            'expires' => $expires_timestamp,
        ], false ); // autoload false (important)

        $logger->info( 'New Friendbuy token stored.', $context );

        return $body['token'];
    }

    /**
     * Send purchase event.
     */
    public static function send_purchase_event( $payload ) {

        $logger = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        $token = self::get_token();

        if ( ! $token ) {
            $logger->error( 'No valid token available.', $context );
            return false;
        }
        //$payload_print  =   print_r($payload, true);
        $payload_print  =   serialize($payload);

        $logger->info( "Purchase payload: {$payload_print}", $context );

        $response = wp_remote_post( self::EVENT_URL, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            $logger->error( 'Purchase request failed: ' . $response->get_error_message(), $context );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        $logger->info( "Purchase response ({$status}): {$body}", $context );

        return $status === 200;
    }
}
new AH_Friendbuy_API();