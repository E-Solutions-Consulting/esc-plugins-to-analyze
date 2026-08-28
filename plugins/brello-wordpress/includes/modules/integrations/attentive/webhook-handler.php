<?php
/**
 * Attentive Integration - Webhook Handler
 * 
 * REST API endpoint to receive Typeform webhooks.
 * Endpoint: /wp-json/bh/v1/typeform/webhook
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Webhook_Handler {

    /**
     * Initialize webhook handler
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route( 'bh/v1', '/typeform/webhook', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'handle_webhook' ),
            'permission_callback' => '__return_true', // Public endpoint, validated by signature
        ) );
    }

    /**
     * Handle incoming Typeform webhook
     */
    public static function handle_webhook( WP_REST_Request $request ) {
        $settings = BH_Attentive_Config::get_settings();
        
        // Check if integration is enabled
        if ( $settings['enabled'] !== 'yes' ) {
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => 'integration_disabled',
                'message' => 'Integration is not enabled',
            ), 503 );
        }
        
        // Get raw body for signature verification
        $raw_body = $request->get_body();
        $payload = json_decode( $raw_body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            BH_Attentive_Logger::log_error( 'Invalid JSON payload', json_last_error_msg() );
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => 'invalid_json',
                'message' => 'Invalid JSON payload',
            ), 400 );
        }
        
        $form_id = $payload['form_response']['form_id'] ?? 'unknown';
        $event_id = $payload['event_id'] ?? 'unknown';
        
        // Verify Typeform signature
        $signature = $request->get_header( 'Typeform-Signature' );
        if ( ! self::verify_signature( $raw_body, $signature, $settings['webhook_secret'] ) ) {
            BH_Attentive_Logger::log_webhook( $form_id, $event_id, 'failed', array( 'error' => 'Invalid signature' ) );
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => 'invalid_signature',
                'message' => 'Webhook signature verification failed',
            ), 401 );
        }
        
        // Check rate limit
        if ( ! self::check_rate_limit( $form_id ) ) {
            BH_Attentive_Logger::log_webhook( $form_id, $event_id, 'rate_limited' );
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => 'rate_limit_exceeded',
                'message' => 'Too many requests',
            ), 429 );
        }
        
        // Process the webhook
        $result = self::process_webhook( $payload, $settings );
        
        return $result;
    }

    /**
     * Verify Typeform signature (HMAC SHA-256)
     */
    private static function verify_signature( $payload, $signature, $secret ) {
        if ( empty( $secret ) ) {
            // If no secret configured, allow for testing
            return true;
        }
        
        if ( empty( $signature ) ) {
            return false;
        }
        
        $calculated = 'sha256=' . base64_encode( hash_hmac( 'sha256', $payload, $secret, true ) );
        
        return hash_equals( $calculated, $signature );
    }

    /**
     * Check rate limit (100 requests per form per hour)
     */
    private static function check_rate_limit( $form_id ) {
        $transient_key = 'bh_attentive_rate_' . md5( $form_id );
        $count = get_transient( $transient_key );
        
        if ( $count === false ) {
            set_transient( $transient_key, 1, HOUR_IN_SECONDS );
            return true;
        }
        
        if ( $count >= 100 ) {
            return false;
        }
        
        set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );
        return true;
    }

    /**
     * Process webhook payload
     */
    private static function process_webhook( $payload, $settings ) {
        $form_id = $payload['form_response']['form_id'] ?? 'unknown';
        $event_id = $payload['event_id'] ?? 'unknown';
        
        $log_entry = array(
            'form_id'           => $form_id,
            'event_id'          => $event_id,
            'typeform_payload'  => $payload,
            'status'            => 'processing',
        );
        
        try {
            // Map Typeform data to Attentive format
            $mapper = new BH_Attentive_Field_Mapper();
            $mapped_data = $mapper->map_typeform_to_attentive( $payload );
            
            $log_entry['mapped_data'] = $mapped_data;
            $log_entry['phone_number'] = $mapped_data['phone'];
            $log_entry['email'] = $mapped_data['email'];
            
            // Validate mapped data
            $validation = $mapper->validate_mapped_data( $mapped_data );
            if ( $validation !== true ) {
                $log_entry['status'] = 'failed';
                $log_entry['error_message'] = implode( '; ', $validation );
                BH_Attentive_Logger::log_to_database( $log_entry );
                
                return new WP_REST_Response( array(
                    'success' => false,
                    'error'   => 'validation_failed',
                    'message' => implode( '; ', $validation ),
                ), 400 );
            }
            
            // Initialize API client
            $api_client = new BH_Attentive_API_Client();
            
            // 1. Subscribe user
            $subscribe_result = $api_client->subscribe_user(
                $mapped_data['phone'],
                $mapped_data['email']
            );
            
            $log_entry['attentive_request']['subscribe'] = array(
                'phone' => $mapped_data['phone'],
                'email' => $mapped_data['email'],
            );
            $log_entry['attentive_response']['subscribe'] = $subscribe_result;
            
            if ( is_wp_error( $subscribe_result ) ) {
                // Log but continue - user might already be subscribed
                BH_Attentive_Logger::log( 'Subscribe returned error (may already exist)', $subscribe_result->get_error_message() );
            }
            
            // 2. Send custom event
            $event_name = $settings['default_event_name'] ?: 'survey_completed';
            $event_result = $api_client->send_custom_event(
                $mapped_data['phone'],
                $event_name,
                $mapped_data['event_properties'],
                $mapped_data['email']
            );
            
            $log_entry['attentive_request']['event'] = array(
                'type'       => $event_name,
                'properties' => $mapped_data['event_properties'],
            );
            $log_entry['attentive_response']['event'] = $event_result;
            
            // 3. Set custom attributes
            if ( ! empty( $mapped_data['custom_attributes'] ) ) {
                $attr_result = $api_client->set_custom_attributes(
                    $mapped_data['phone'],
                    $mapped_data['custom_attributes'],
                    $mapped_data['email']  // Include email for better user matching
                );
                
                $log_entry['attentive_request']['attributes'] = $mapped_data['custom_attributes'];
                $log_entry['attentive_response']['attributes'] = $attr_result;
            }

            
            // Check for errors
            if ( is_wp_error( $event_result ) ) {
                $log_entry['status'] = 'failed';
                $log_entry['error_message'] = $event_result->get_error_message();
                BH_Attentive_Logger::log_to_database( $log_entry );
                
                return new WP_REST_Response( array(
                    'success' => false,
                    'error'   => 'attentive_api_error',
                    'message' => $event_result->get_error_message(),
                ), 500 );
            }
            
            // Success
            $log_entry['status'] = 'success';
            BH_Attentive_Logger::log_to_database( $log_entry );
            BH_Attentive_Logger::log_webhook( $form_id, $event_id, 'success' );
            
            return new WP_REST_Response( array(
                'success'    => true,
                'message'    => 'Webhook processed successfully',
                'event_sent' => true,
            ), 200 );
            
        } catch ( Exception $e ) {
            $log_entry['status'] = 'failed';
            $log_entry['error_message'] = $e->getMessage();
            BH_Attentive_Logger::log_to_database( $log_entry );
            BH_Attentive_Logger::log_error( 'Exception processing webhook', $e->getMessage() );
            
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => 'processing_error',
                'message' => $e->getMessage(),
            ), 500 );
        }
    }
}
