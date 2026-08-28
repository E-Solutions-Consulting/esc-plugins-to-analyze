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

        // Handle US numbers: remove country code if present
        if ( strlen( $phone ) === 11 && str_starts_with( $phone, '1' ) ) {
            // Remove the leading 1 (US country code)
            $phone = substr( $phone, 1 );
        }
        
        // Remove any additional leading 1s that might be duplicated
        while ( strlen( $phone ) > 10 && str_starts_with( $phone, '1' ) ) {
            $phone = substr( $phone, 1 );
        }

        // At this point, we should have exactly 10 digits for US number
        if ( strlen( $phone ) === 10 && ctype_digit( $phone ) ) {
            return '+1' . $phone;
        }

        // If we have other lengths, force US format
        return '+1' . $phone;
    }

    /**
     * Check whether a user is already opted in to Attentive.
     *
     * Calls Attentive's "Get Subscription Eligibility" endpoint
     * (GET /subscriptions) so we can avoid re-running a Subscribers API
     * (POST /subscriptions) call for someone who is already subscribed — that
     * is what makes Attentive auto-reply with the "You are already subscribed"
     * SMS that customers were receiving alongside our custom events.
     *
     * @param string $phone Normalized phone (preferred lookup key).
     * @param string $email Email (used if no phone is available).
     * @return bool|null  true  = already subscribed (skip the subscribe call)
     *                    false = not subscribed (safe to subscribe)
     *                    null  = unknown (not configured / API error) — caller should fail open
     */
    public static function is_subscribed( $phone, $email = '' ) {

        if ( empty( $phone ) && empty( $email ) ) {
            return null;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return null;
        }

        $base  = trailingslashit( $settings['api_base_url'] ?? 'https://api.attentivemobile.com/v1' );
        $query = ! empty( $phone )
            ? 'phone=' . rawurlencode( $phone )
            : 'email=' . rawurlencode( $email );

        $response = wp_remote_get( $base . 'subscriptions?' . $query, [
            'headers'  => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout'  => 10,
            'blocking' => true, // runs inside an AS worker — safe to block.
        ] );

        if ( is_wp_error( $response ) ) {
            self::log( 'Eligibility check failed', [ 'error' => $response->get_error_message() ] );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        // 404 = Attentive has no subscriptions on file for this user
        // ("Subscriptions not found ...") → they are not subscribed → safe to subscribe.
        if ( $code === 404 ) {
            self::log( 'Eligibility check: no subscriptions found — not subscribed', [
                'phone' => $phone,
                'email' => $email,
            ] );
            return false;
        }

        if ( $code < 200 || $code >= 300 ) {
            self::log( 'Eligibility check non-2xx', [ 'code' => $code, 'body' => $body ] );
            return null;
        }

        $data = json_decode( $body, true );

        // Attentive's Get Subscription Eligibility lists the subscriptions the
        // user already HAS (a 404 "Subscriptions not found" is returned when they
        // have none), each with an eligibility flag. Shape:
        //   { "subscriptionEligibilities": [
        //       { "subscription": { "type": "...", "channel": "TEXT" },
        //         "eligibility": { "isEligible": true } } ] }
        // An entry that is eligible (isEligible === true) on the TEXT (SMS)
        // channel means the user is already an active SMS subscriber — re-running
        // POST /subscriptions for them is exactly what triggers Attentive's
        // "You are already subscribed" auto-reply, so we treat that as
        // already-subscribed and skip the subscribe call.
        $eligibilities = ( is_array( $data ) && ! empty( $data['subscriptionEligibilities'] ) && is_array( $data['subscriptionEligibilities'] ) )
            ? $data['subscriptionEligibilities']
            : [];

        $already_subscribed = false;
        foreach ( $eligibilities as $entry ) {
            $channel     = $entry['subscription']['channel'] ?? '';
            $is_eligible = $entry['eligibility']['isEligible'] ?? null;

            if ( $channel === 'TEXT' && $is_eligible === true ) {
                $already_subscribed = true;
                break;
            }
        }

        // Log the parsed outcome (with a trimmed raw body) so any future response
        // shape change is diagnosable from a single log line.
        self::log( 'Eligibility check result', [
            'phone'              => $phone,
            'email'              => $email,
            'already_subscribed' => $already_subscribed,
            'eligibility_count'  => count( $eligibilities ),
            'raw'                => mb_substr( (string) $body, 0, 500 ),
        ] );

        return $already_subscribed;
    }

    /**
     * Subscribe user via Integration source (no signUpSourceId).
     * Use this when the configured signUpSourceId is not of type INTEGRATION.
     * Safe to call even if user already exists in Attentive.
     *
     * @param string $phone
     * @param string $email
     * @param bool   $blocking
     * @return array|WP_Error|null
     */
    public static function subscribe_user_integration( string $phone, string $email, bool $blocking = false ) {

        if ( empty( $phone ) && empty( $email ) ) {
            self::log( 'subscribe_user_integration: no phone or email provided' );
            return null;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'];

        if ( empty( $api_key ) ) {
            self::log( 'subscribe_user_integration: API key not configured' );
            return null;
        }

        // Skip if already opted in — avoids the "You are already subscribed"
        // auto-reply. Only skip on a confirmed subscription; fail open otherwise.
        if ( self::is_subscribed( $phone, $email ) === true ) {
            self::log( 'subscribe_user_integration: already subscribed — skipping', [
                'phone' => $phone,
                'email' => $email,
            ] );
            return null;
        }

        $data = [
            'user'   => [ 'phone' => $phone ],
            'locale' => 'en_US',
            'externalIdentifiers' => [
                'clientUserId' => md5( $phone . $email ),
            ],
        ];

        if ( ! empty( $email ) ) {
            $data['user']['email'] = $email;
        }

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
            $code = wp_remote_retrieve_response_code( $response );
            self::log( 'subscribe_user_integration response: ' . $code . ' | ' . wp_remote_retrieve_body( $response ) );
        }

        return $response;
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

        // Check eligibility first (Attentive recommendation): don't re-run the
        // Subscribers API for someone already opted in, otherwise Attentive
        // auto-replies with the "You are already subscribed" SMS. Only skip on a
        // confirmed subscription; on an unknown/error result we fail open and
        // subscribe as before.
        if ( self::is_subscribed( $phone, $email ) === true ) {
            self::log( 'User already subscribed — skipping subscribe', [
                'phone' => $phone,
                'email' => $email,
            ] );
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
    public static function send_event( $event_type, $phone, $email, $properties = [], $blocking = false ) {
        
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
    public static function set_attributes( $phone, $email, $attributes, $blocking = false ) {
        
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