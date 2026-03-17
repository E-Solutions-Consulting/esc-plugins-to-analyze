<?php
/**
 * Attentive Integration - API Client
 * 
 * Handles all Attentive API communications.
 * Based on OpenAPI spec: https://api.attentivemobile.com/v1
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_API_Client {

    private $api_key;
    private $base_url;
    private $max_retries = 3;

    public function __construct() {
        $settings = BH_Attentive_Config::get_settings();
        $this->api_key = $settings['api_key'];
        $this->base_url = rtrim( $settings['api_base_url'], '/' );
    }

    /**
     * Test API connection (GET /me)
     */
    public function test_connection() {
        $response = $this->make_get_request( '/me' );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return array( 'success' => true, 'data' => $response );
    }

    /**
     * Subscribe user (POST /subscriptions)
     * Returns 202 Accepted (async processing)
     */
    public function subscribe_user( $phone, $email = null, $sign_up_source_id = null ) {
        if ( empty( $sign_up_source_id ) ) {
            $settings = BH_Attentive_Config::get_settings();
            $sign_up_source_id = $settings['sign_up_source_id'];
        }
        
        $data = array(
            'user' => array(
                'phone' => $phone,
            ),
        );
        
        // Include signUpSourceId if provided
        if ( ! empty( $sign_up_source_id ) ) {
            $data['signUpSourceId'] = $sign_up_source_id;
        } else {
            // Attentive requires either signUpSourceId OR locale
            // Using locale as fallback
            $data['locale'] = 'en_US';  // Changed from en-US to en_US format
            
            // Also add external identifier for tracking
            $data['externalIdentifiers'] = array(
                'clientUserId' => md5( $phone )
            );
        }
        
        if ( ! empty( $email ) ) {
            $data['user']['email'] = $email;
        }
        
        $response = $this->make_post_request( '/subscriptions', $data );
        
        BH_Attentive_Logger::log_api_call( '/subscriptions', is_wp_error( $response ) ? 'error' : '202', $response );
        
        return $response;
    }

    /**
     * Send custom event (POST /events/custom)
     * Correct format: flat object, NOT nested in events array
     */
    public function send_custom_event( $phone, $event_type, $properties = [], $email = null ) {
        $user = array( 'phone' => $phone );
        if ( ! empty( $email ) ) {
            $user['email'] = $email;
        }
        
        // Correct format per Attentive docs: flat object with type, properties, occurredAt, user
        $data = array(
            'type'       => sanitize_text_field( $event_type ),
            'properties' => ! empty( $properties ) ? $properties : new stdClass(),
            'occurredAt' => gmdate( 'Y-m-d\TH:i:sO' ),  // ISO 8601 format
            'user'       => $user,
        );
        
        $response = $this->make_post_request( '/events/custom', $data );
        
        BH_Attentive_Logger::log_api_call( '/events/custom', is_wp_error( $response ) ? 'error' : '200', $response );
        
        return $response;
    }

    /**
     * Set custom attributes (POST /attributes/custom)
     * Correct format: uses "properties" key, not "customAttributes"
     */
    public function set_custom_attributes( $phone, $attributes = [], $email = null ) {
        if ( empty( $attributes ) ) {
            return true; // Nothing to do
        }
        
        // Attentive doesn't allow arrays in attributes
        // Also, keys cannot contain special characters
        $sanitized_attrs = array();
        foreach ( $attributes as $key => $value ) {
            if ( ! is_array( $value ) ) {
                // Clean key: replace underscores with spaces for readability
                $clean_key = str_replace( '_', ' ', $key );
                $sanitized_attrs[ $clean_key ] = sanitize_text_field( (string) $value );
            }
        }
        
        // Limit to 100 attributes
        $sanitized_attrs = array_slice( $sanitized_attrs, 0, 100 );
        
        // Build user object
        $user = array( 'phone' => $phone );
        if ( ! empty( $email ) ) {
            $user['email'] = $email;
        }
        
        // Correct format per docs: "properties" not "customAttributes"
        $data = array(
            'properties' => $sanitized_attrs,
            'user'       => $user,
        );
        
        $response = $this->make_post_request( '/attributes/custom', $data );
        
        BH_Attentive_Logger::log_api_call( '/attributes/custom', is_wp_error( $response ) ? 'error' : '200', $response );
        
        return $response;
    }

    /**
     * Get subscription status (GET /subscriptions)
     */
    public function get_subscription_status( $phone ) {
        // Phone must be URL encoded with + as %2B
        $encoded_phone = rawurlencode( $phone );
        
        return $this->make_get_request( '/subscriptions?phone=' . $encoded_phone );
    }

    /**
     * Make POST request to Attentive API
     */
    private function make_post_request( $endpoint, $data ) {
        $url = $this->base_url . $endpoint;
        
        $json_body = wp_json_encode( $data );
        
        // Debug: Log the exact JSON being sent
        BH_Attentive_Logger::log( "Sending to {$endpoint}", array(
            'url' => $url,
            'data' => $data,
            'json' => $json_body
        ) );
        
        $args = array(
            'method'      => 'POST',
            'timeout'     => 30,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'        => $json_body,
        );
        
        return $this->execute_request( $url, $args );
    }

    /**
     * Make GET request to Attentive API
     */
    private function make_get_request( $endpoint, $params = [] ) {
        $url = $this->base_url . $endpoint;
        
        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }
        
        $args = array(
            'method'  => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
        );
        
        return $this->execute_request( $url, $args );
    }

    /**
     * Execute request with retry logic
     */
    private function execute_request( $url, $args ) {
        $attempt = 0;
        $last_error = null;
        
        while ( $attempt < $this->max_retries ) {
            $attempt++;
            
            $response = wp_remote_request( $url, $args );
            
            if ( is_wp_error( $response ) ) {
                $last_error = $response;
                BH_Attentive_Logger::log_error( "API request failed (attempt {$attempt})", $response->get_error_message() );
                
                // Exponential backoff
                if ( $attempt < $this->max_retries ) {
                    sleep( pow( 2, $attempt - 1 ) );
                }
                continue;
            }
            
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            
            // Success codes: 200, 201, 202
            if ( $code >= 200 && $code < 300 ) {
                $decoded = json_decode( $body, true );
                return $decoded ?: true;
            }
            
            // Rate limited - wait and retry
            if ( $code === 429 ) {
                BH_Attentive_Logger::log( 'Rate limited, waiting before retry', array( 'attempt' => $attempt ) );
                sleep( pow( 2, $attempt ) );
                continue;
            }
            
            // Server error - retry
            if ( $code >= 500 ) {
                BH_Attentive_Logger::log_error( "Server error {$code}", $body );
                sleep( pow( 2, $attempt - 1 ) );
                continue;
            }
            
            // Client error - don't retry
            $decoded = json_decode( $body, true );
            $error_message = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$code}";
            
            BH_Attentive_Logger::log_error( "API error: {$error_message}", $body );
            
            return new WP_Error( 'attentive_api_error', $error_message, array( 'code' => $code, 'body' => $body ) );
        }
        
        return $last_error ?: new WP_Error( 'attentive_api_error', 'Max retries exceeded' );
    }
}
