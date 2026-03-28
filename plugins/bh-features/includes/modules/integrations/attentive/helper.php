<?php
/**
 * Attentive Helper Class
 * 
 * Shared utility functions for all Attentive event handlers
 * Eliminates code duplication across unified-events.php and stripe-events.php
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Helper {

    /**
     * Normalize phone number to E.164 format for Attentive
     * Removes spaces, dashes, parentheses, and ensures proper format
     * 
     * @param string $phone Raw phone number
     * @return string Normalized phone in +1XXXXXXXXXX format
     */
    public static function normalize_phone( $phone ) {
        
        if ( empty( $phone ) ) {
            return '';
        }

        // Remove all non-digit characters except +
        $phone = preg_replace( '/[^\d+]/', '', $phone );

        // Remove leading + to work with just digits
        $phone = ltrim( $phone, '+' );

        // Remove leading 1s (country code) - handle multiple 1s
        while ( strlen( $phone ) > 10 && str_starts_with( $phone, '1' ) ) {
            $phone = substr( $phone, 1 );
        }

        // At this point, we should have exactly 10 digits for US number
        if ( strlen( $phone ) === 10 && ctype_digit( $phone ) ) {
            return '+1' . $phone;
        }

        // If we still have 11 digits starting with 1, it's already country code + number
        if ( strlen( $phone ) === 11 && str_starts_with( $phone, '1' ) ) {
            return '+' . $phone;
        }

        // If less than 10 digits or invalid, return as-is with + prefix
        return '+' . $phone;
    }

    /**
     * Subscribe user to Attentive
     * Creates subscriber profile before sending events
     * 
     * @param string $phone Normalized phone number
     * @param string $email Email address
     * @param bool $blocking Whether to wait for response (default: false for async)
     * @return array|WP_Error|null Response or null if skipped
     */
    public static function subscribe_user( $phone, $email, $blocking = false ) {
        
        if ( empty( $phone ) && empty( $email ) ) {
            self::log( 'Cannot subscribe - no phone or email provided' );
            return null;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key = $settings['api_key'];
        $sign_up_source_id = $settings['sign_up_source_id'] ?? '';

        if ( empty( $api_key ) ) {
            self::log( 'API key not configured - skipping subscription' );
            return null;
        }

        // Build subscription payload
        $data = [
            'user' => [
                'phone' => $phone,
            ],
        ];

        // Add signUpSourceId or locale (required by Attentive)
        if ( ! empty( $sign_up_source_id ) ) {
            $data['signUpSourceId'] = $sign_up_source_id;
        } else {
            $data['locale'] = 'en_US';
            $data['externalIdentifiers'] = [
                'clientUserId' => md5( $phone . $email )
            ];
        }

        if ( ! empty( $email ) ) {
            $data['user']['email'] = $email;
        }

        self::log( 'Subscribing user', [
            'phone' => $phone,
            'email' => $email,
            'has_signUpSourceId' => ! empty( $sign_up_source_id ),
        ] );

        $response = wp_remote_post(
            'https://api.attentivemobile.com/v1/subscriptions',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $data ),
                'blocking' => $blocking,
                'timeout'  => 10,
            ]
        );

        if ( $blocking ) {
            if ( is_wp_error( $response ) ) {
                self::log( 'Subscribe error', [ 'error' => $response->get_error_message() ] );
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                self::log( 'Subscribe response', [ 'code' => $code ] );
            }
        }

        return $response;
    }

    /**
     * Send custom event to Attentive
     * 
     * @param string $event_type Event type (e.g., 'OrderStatus_Completed')
     * @param string $phone Normalized phone number
     * @param string $email Email address
     * @param array $properties Event properties
     * @param bool $blocking Whether to wait for response (default: true for debugging)
     * @return array|WP_Error Response
     */
    public static function send_event( $event_type, $phone, $email, $properties = [], $blocking = true ) {
        
        $settings = BH_Attentive_Config::get_settings();
        $api_key = $settings['api_key'];

        if ( empty( $api_key ) ) {
            self::log( 'API key not configured - skipping event' );
            return new WP_Error( 'no_api_key', 'API key not configured' );
        }

        // Build payload
        $payload = [
            'type'            => $event_type,
            'externalEventId' => wp_generate_uuid4(),
            'occurredAt'      => gmdate( 'c' ),
            'user'            => [
                'email' => $email,
                'phone' => $phone,
            ],
            'properties'      => $properties,
        ];

        self::log( "Sending event: {$event_type}", [ 'payload' => $payload ] );

        $response = wp_remote_post(
            'https://api.attentivemobile.com/v1/events/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $payload ),
                'blocking' => $blocking,
                'timeout'  => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            self::log( "Event error: {$event_type}", [ 'error' => $response->get_error_message() ] );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            self::log( "Event response: {$event_type}", [ 
                'code' => $code, 
                'body' => $body,
                'success' => ( $code === 200 || $code === 202 ),
            ] );
        }

        return $response;
    }

    /**
     * Set custom attributes for a user
     * 
     * @param string $phone Normalized phone number
     * @param string $email Email address
     * @param array $attributes Key-value pairs of attributes
     * @param bool $blocking Whether to wait for response
     * @return array|WP_Error Response
     */
    public static function set_attributes( $phone, $email, $attributes, $blocking = true ) {
        
        $settings = BH_Attentive_Config::get_settings();
        $api_key = $settings['api_key'];

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'API key not configured' );
        }

        $data = [
            'user' => [
                'phone' => $phone,
                'email' => $email,
            ],
            'properties' => $attributes,
        ];

        self::log( 'Setting attributes', [ 'attributes' => $attributes ] );

        $response = wp_remote_post(
            'https://api.attentivemobile.com/v1/attributes/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $data ),
                'blocking' => $blocking,
                'timeout'  => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            self::log( 'Attribute error', [ 'error' => $response->get_error_message() ] );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            self::log( 'Attribute response', [ 'code' => $code ] );
        }

        return $response;
    }

    /**
     * Log message using Attentive Logger
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function log( $message, $context = [] ) {
        if ( class_exists( 'BH_Attentive_Logger' ) ) {
            BH_Attentive_Logger::log( $message, $context );
        }
    }

    /**
     * Get API key from settings
     * 
     * @return string API key or empty string
     */
    public static function get_api_key() {
        $settings = BH_Attentive_Config::get_settings();
        return $settings['api_key'] ?? '';
    }

    /**
     * Check if API is configured
     * 
     * @return bool True if API key exists
     */
    public static function is_configured() {
        return ! empty( self::get_api_key() );
    }
}
