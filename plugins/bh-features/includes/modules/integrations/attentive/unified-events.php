<?php
/**
 * Attentive Unified Events Handler
 * 
 * SIMPLIFIED approach: ONE hook for ALL events
 * Handles both regular orders and subscription orders
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Unified_Events {

    /**
     * Initialize unified events handler
     */
    public function __construct() {
        
        // NEW ORDER: fires after successful checkout
        add_action(
            'woocommerce_thankyou',
            [ $this, 'handle_order_success' ],
            20,
            1
        );

        // STATUS CHANGE: fires when order status changes (from admin or anywhere)
        add_action(
            'woocommerce_order_status_changed',
            [ $this, 'handle_order_status_change' ],
            20,
            4
        );

        $this->log( 'Unified events handler initialized' );
    }

    /**
     * Handle successful order/checkout
     * This fires AFTER payment is complete
     */
    public function handle_order_success( $order_id ) {
        
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }

        // Prevent duplicate processing
        if ( $order->get_meta( '_attentive_unified_processed', true ) ) {
            $this->log( 'Order already processed - skipping', [
                'order_id' => $order_id,
            ] );
            return;
        }

        $this->log( 'Processing order success', [
            'order_id' => $order_id,
            'status' => $order->get_status(),
        ] );

        $phone = $this->normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        $this->log( 'Phone normalized', [
            'original' => $order->get_billing_phone(),
            'normalized' => $phone,
        ] );

        // Step 1: Subscribe user to Attentive (CRITICAL!)
        $this->subscribe_user( $phone, $email );

        // Step 2: Check if this is a subscription order
        $is_subscription = $this->is_subscription_order( $order );

        if ( $is_subscription ) {
            // Send subscription activated event
            $this->send_subscription_event( $order );
        }

        // Step 3: Send order status event
        $this->send_order_event( $order );

        // Mark as processed
        $order->update_meta_data( '_attentive_unified_processed', 'yes' );
        $order->save();

        $this->log( 'Order processing complete', [
            'order_id' => $order_id,
            'is_subscription' => $is_subscription,
        ] );
    }

    /**
     * Handle order status change (from admin or anywhere)
     * This sends events when status changes AFTER initial checkout
     */
    public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
        
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        // Skip if same status
        if ( $old_status === $new_status ) {
            return;
        }

        $this->log( 'Order status changed', [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ] );

        // Build unique meta key for this status
        $meta_key = '_attentive_status_fired_' . $new_status;

        // Check if already fired for this status
        if ( $order->get_meta( $meta_key, true ) ) {
            $this->log( 'Status event already fired - skipping', [
                'order_id' => $order_id,
                'status' => $new_status,
            ] );
            return;
        }

        // Mark as fired
        $order->update_meta_data( $meta_key, 'yes' );
        $order->save();

        // Get user details
        $phone = $this->normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        // Subscribe user (CRITICAL - ensures user exists)
        $this->subscribe_user( $phone, $email );

        // Send status change event
        $this->send_status_change_event( $order, $old_status, $new_status );

        $this->log( 'Status change event complete', [
            'order_id' => $order_id,
            'new_status' => $new_status,
        ] );
    }

    /**
     * Send status change event to Attentive
     */
    private function send_status_change_event( WC_Order $order, $old_status, $new_status ) {
        
        $settings = BH_Attentive_Config::get_settings();
        $api_key = $settings['api_key'];

        if ( empty( $api_key ) ) {
            return;
        }

        $phone = $this->normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        // Build event type (e.g., OrderStatus_Processing, OrderStatus_Cancelled)
        $event_type = 'OrderStatus_' . ucfirst( str_replace( '-', '_', $new_status ) );

        // Build properties
        $properties = [
            'order_id' => (string) $order->get_id(),
            'order_number' => $order->get_order_number(),
            'old_status' => $old_status,
            'new_status' => $new_status,
            'order_total' => $order->get_total(),
            'currency' => $order->get_currency(),
        ];

        // Add product info
        $items = $order->get_items();
        if ( ! empty( $items ) ) {
            $product_names = [];
            foreach ( $items as $item ) {
                $product_names[] = $item->get_name();
            }
            $properties['products'] = implode( ', ', array_slice( $product_names, 0, 3 ) );
            $properties['items_count'] = count( $items );
        }

        // Add subscription info
        $properties['is_subscription_order'] = $this->is_subscription_order( $order );

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

        $this->log( '=== STATUS CHANGE EVENT DEBUG ===', [
            'event_type' => $event_type,
            'payload' => $payload,
            'order_id' => $order->get_id(),
        ] );

        // Send event with BLOCKING for debugging
        $response = wp_remote_post(
            'https://api.attentivemobile.com/v1/events/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $payload ),
                'blocking' => true,  // BLOCKING for debugging
                'timeout'  => 10,
            ]
        );

        // Log response
        if ( is_wp_error( $response ) ) {
            $this->log( '=== STATUS CHANGE EVENT ERROR ===', [
                'error_message' => $response->get_error_message(),
            ] );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            $this->log( '=== STATUS CHANGE EVENT RESPONSE ===', [
                'response_code' => $response_code,
                'response_body' => $response_body,
                'is_success' => ( $response_code === 200 || $response_code === 202 ),
            ] );
        }
    }

    /**
     * Normalize phone number to E.164 format for Attentive
     * Uses shared BH_Attentive_Helper
     */
    private function normalize_phone( $phone ) {
        return BH_Attentive_Helper::normalize_phone( $phone );
    }

    /**
     * Subscribe user to Attentive
     * Creates subscriber profile before sending events
     */
    private function subscribe_user( $phone, $email ) {
        
        if ( empty( $phone ) && empty( $email ) ) {
            $this->log( 'Cannot subscribe - no phone or email provided' );
            return;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key = $settings['api_key'];
        $sign_up_source_id = $settings['sign_up_source_id'] ?? '';

        if ( empty( $api_key ) ) {
            $this->log( 'API key not configured - skipping subscription' );
            return;
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

        // DEBUG: Log exact payload being sent
        $this->log( '=== SUBSCRIBE USER DEBUG ===', [
            'phone' => $phone,
            'email' => $email,
            'payload' => $data,
            'has_signUpSourceId' => ! empty( $sign_up_source_id ),
            'api_key_first_10' => substr( $api_key, 0, 10 ) . '...',
        ] );

        // Send with BLOCKING to see actual API response
        $response = wp_remote_post(
            'https://api.attentivemobile.com/v1/subscriptions',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $data ),
                'blocking' => true,  // BLOCKING for debugging
                'timeout'  => 10,
            ]
        );

        // DEBUG: Log full response
        if ( is_wp_error( $response ) ) {
            $this->log( '=== API ERROR ===', [
                'error_message' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
            ] );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            $response_headers = wp_remote_retrieve_headers( $response );
            
            $this->log( '=== API RESPONSE ===', [
                'response_code' => $response_code,
                'response_body' => $response_body,
                'response_headers' => $response_headers->getAll(),
                'is_success' => ( $response_code === 200 || $response_code === 202 ),
            ] );
        }
    }

    /**
     * Send subscription activated event
     */
    private function send_subscription_event( WC_Order $order ) {
        
        // Get subscription from order
        $subscriptions = wcs_get_subscriptions_for_order( $order );
        
        if ( empty( $subscriptions ) ) {
            return;
        }

        $subscription = reset( $subscriptions );
        
        if ( ! $subscription instanceof WC_Subscription ) {
            return;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key = $settings['api_key'];

        if ( empty( $api_key ) ) {
            return;
        }

        $phone = $this->normalize_phone( $subscription->get_billing_phone() );
        $email = $subscription->get_billing_email();

        // Determine cycle stage based on renewal count
        $renewal_count = $subscription->get_completed_payment_count() - 1;
        
        if ( $renewal_count === 0 ) {
            $cycle_stage = 'cycle_stage_1';
        } elseif ( $renewal_count === 1 ) {
            $cycle_stage = 'cycle_stage_2';
        } else {
            $cycle_stage = 'cycle_stage_3';
        }

        // Build subscription event payload
        $payload = [
            'type'            => 'SubscriptionStatus_Activated',
            'externalEventId' => wp_generate_uuid4(),
            'occurredAt'      => gmdate( 'c' ),
            'user'            => [
                'email' => $email,
                'phone' => $phone,
            ],
            'properties'      => [
                'subscription_id' => (string) $subscription->get_id(),
                'status' => 'active',
                'cycle_stage' => $cycle_stage,
                'order_id' => (string) $order->get_id(),
            ],
        ];

        // DEBUG: Log event payload
        $this->log( '=== SUBSCRIPTION EVENT DEBUG ===', [
            'event_type' => 'SubscriptionStatus_Activated',
            'payload' => $payload,
            'subscription_id' => $subscription->get_id(),
        ] );

        // Send event with BLOCKING to see response
        $response = wp_remote_post(
            'https://api.attentivemobile.com/v1/events/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $payload ),
                'blocking' => true,  // BLOCKING for debugging
                'timeout'  => 10,
            ]
        );

        // DEBUG: Log response
        if ( is_wp_error( $response ) ) {
            $this->log( '=== SUBSCRIPTION EVENT ERROR ===', [
                'error_message' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
            ] );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            $this->log( '=== SUBSCRIPTION EVENT RESPONSE ===', [
                'response_code' => $response_code,
                'response_body' => $response_body,
                'is_success' => ( $response_code === 200 || $response_code === 202 ),
            ] );
        }

        // Also send cycle stage as custom attribute
        $this->log( '=== CUSTOM ATTRIBUTE DEBUG ===', [
            'attribute' => $cycle_stage,
            'user_phone' => $phone,
        ] );

        $attribute_data = [
            'user' => [
                'phone' => $phone,
                'email' => $email,
            ],
            'properties' => [
                $cycle_stage => true,
            ],
        ];

        $attribute_response = wp_remote_post(
            'https://api.attentivemobile.com/v1/attributes/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $attribute_data ),
                'blocking' => true,  // BLOCKING for debugging
                'timeout'  => 10,
            ]
        );

        // DEBUG: Log attribute response
        if ( is_wp_error( $attribute_response ) ) {
            $this->log( '=== ATTRIBUTE ERROR ===', [
                'error_message' => $attribute_response->get_error_message(),
                'error_code' => $attribute_response->get_error_code(),
            ] );
        } else {
            $attr_code = wp_remote_retrieve_response_code( $attribute_response );
            $attr_body = wp_remote_retrieve_body( $attribute_response );
            
            $this->log( '=== ATTRIBUTE RESPONSE ===', [
                'response_code' => $attr_code,
                'response_body' => $attr_body,
                'is_success' => ( $attr_code === 200 || $attr_code === 202 ),
            ] );
        }

        $this->log( 'Subscription event complete', [
            'subscription_id' => $subscription->get_id(),
            'cycle_stage' => $cycle_stage,
        ] );
    }

    /**
     * Send order status event
     */
    private function send_order_event( WC_Order $order ) {
        
        $settings = BH_Attentive_Config::get_settings();
        $api_key = $settings['api_key'];

        if ( empty( $api_key ) ) {
            return;
        }

        $phone = $this->normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();
        $status = $order->get_status();

        // Build event type (e.g., OrderStatus_Processing)
        $event_type = 'OrderStatus_' . ucfirst( str_replace( '-', '_', $status ) );

        // Build properties
        $properties = [
            'order_id' => (string) $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $status,
            'order_total' => $order->get_total(),
            'currency' => $order->get_currency(),
        ];

        // Add product info
        $items = $order->get_items();
        if ( ! empty( $items ) ) {
            $product_names = [];
            foreach ( $items as $item ) {
                $product_names[] = $item->get_name();
            }
            $properties['products'] = implode( ', ', array_slice( $product_names, 0, 3 ) );
            $properties['items_count'] = count( $items );
        }

        // Add subscription flag
        $properties['is_subscription_order'] = $this->is_subscription_order( $order );

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

        // DEBUG: Log event payload
        $this->log( '=== ORDER EVENT DEBUG ===', [
            'event_type' => $event_type,
            'payload' => $payload,
            'order_id' => $order->get_id(),
        ] );

        // Send event with BLOCKING
        $response = wp_remote_post(
            'https://api.attentivemobile.com/v1/events/custom',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $payload ),
                'blocking' => true,  // BLOCKING for debugging
                'timeout'  => 10,
            ]
        );

        // DEBUG: Log response
        if ( is_wp_error( $response ) ) {
            $this->log( '=== ORDER EVENT ERROR ===', [
                'error_message' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
            ] );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            $this->log( '=== ORDER EVENT RESPONSE ===', [
                'response_code' => $response_code,
                'response_body' => $response_body,
                'is_success' => ( $response_code === 200 || $response_code === 202 ),
            ] );
        }

        $this->log( 'Order event complete', [
            'order_id' => $order->get_id(),
            'event_type' => $event_type,
        ] );
    }

   /**
     * Check if order contains subscription
     */
    private function is_subscription_order( WC_Order $order ) {
        
        if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
            return false;
        }

        return wcs_order_contains_subscription( $order );
    }

    /**
     * Log message using shared BH_Attentive_Helper
     */
    private function log( $message, $context = [] ) {
        BH_Attentive_Helper::log( $message, $context );
    }
}
