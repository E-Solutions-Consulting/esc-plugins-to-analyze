<?php
/**
 * Attentive Integration - Admin Retry Functionality
 *
 * Provides retry buttons and manual resubmission capabilities for failed Attentive API calls.
 * Accessible from customer profiles and subscription pages.
 *
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Admin_Retry {

    public function __construct() {
        // Hook into customer profile and subscription actions
        add_action( 'show_user_profile', [ $this, 'display_customer_attentive_status' ] );
        add_action( 'edit_user_profile', [ $this, 'display_customer_attentive_status' ] );
        add_action( 'woocommerce_subscription_details_after_customer_details', [ $this, 'add_subscription_retry_section' ] );
        
        // AJAX handlers for retry functionality
        add_action( 'wp_ajax_bh_attentive_retry_subscription', [ $this, 'handle_retry_subscription' ] );
        add_action( 'wp_ajax_bh_attentive_retry_customer', [ $this, 'handle_retry_customer' ] );
        
        // Admin scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Add Attentive section to customer profile in admin
     */
    public function add_customer_profile_section( $fields ) {
        $fields['attentive'] = [
            'title' => __( 'Attentive Marketing', 'textdomain' ),
            'fields' => [
                'attentive_subscription_status' => [
                    'label' => __( 'Marketing Subscription', 'textdomain' ),
                    'description' => __( 'Current Attentive subscription status', 'textdomain' ),
                    'type' => 'text',
                    'custom_attributes' => [ 'readonly' => 'readonly' ],
                ],
                'attentive_last_attempt' => [
                    'label' => __( 'Last Attempt', 'textdomain' ),
                    'description' => __( 'Last Attentive API attempt', 'textdomain' ),
                    'type' => 'text',
                    'custom_attributes' => [ 'readonly' => 'readonly' ],
                ],
                'attentive_retry_button' => [
                    'label' => __( 'Manual Retry', 'textdomain' ),
                    'description' => __( 'Retry Attentive subscription manually', 'textdomain' ),
                    'type' => 'button',
                ],
            ],
        ];
        
        return $fields;
    }

    /**
     * Display customer's Attentive status and retry button
     */
    public function display_customer_attentive_status( $user ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $user_id = $user->ID;
        if ( ! $user_id ) {
            return;
        }

        $phone = get_user_meta( $user_id, 'billing_phone', true );
        $email = $user->user_email;

        // Check if user has recent failed attempts
        $last_failure = get_user_meta( $user_id, '_attentive_last_failure', true );
        $last_success = get_user_meta( $user_id, '_attentive_last_success', true );

        echo '<div class="attentive-customer-section" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
        echo '<h3>' . __( 'Attentive Marketing', 'textdomain' ) . '</h3>';
        
        echo '<p><strong>Phone:</strong> ' . esc_html( $phone ) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html( $email ) . '</p>';
        
        if ( $last_failure ) {
            echo '<p style="color: #d63638;"><strong>Last Failure:</strong> ' . esc_html( $last_failure ) . '</p>';
        }
        
        if ( $last_success ) {
            echo '<p style="color: #00a32a;"><strong>Last Success:</strong> ' . esc_html( $last_success ) . '</p>';
        }

        if ( ! empty( $phone ) ) {
            echo '<button type="button" class="button button-secondary bh-attentive-retry-customer" 
                    data-user-id="' . esc_attr( $user_id ) . '"
                    data-phone="' . esc_attr( $phone ) . '"
                    data-email="' . esc_attr( $email ) . '">
                    ' . __( 'Retry Attentive Subscription', 'textdomain' ) . '
                  </button>';
        } else {
            echo '<p style="color: #d63638;">No phone number available for retry</p>';
        }
        
        echo '<div id="attentive-retry-result-' . $user_id . '" style="margin-top: 10px;"></div>';
        echo '</div>';
    }

    /**
     * Add retry section to subscription edit page
     */
    public function add_subscription_retry_section( $subscription ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $subscription_id = $subscription->get_id();
        $user_id = $subscription->get_user_id();
        $phone = $subscription->get_billing_phone();
        $email = $subscription->get_billing_email();

        // Check subscription's Attentive status
        $last_failure = get_post_meta( $subscription_id, '_attentive_last_failure', true );
        $last_success = get_post_meta( $subscription_id, '_attentive_last_success', true );

        echo '<div class="attentive-subscription-section" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
        echo '<h3>' . __( 'Attentive Marketing Status', 'textdomain' ) . '</h3>';
        
        echo '<p><strong>Phone:</strong> ' . esc_html( $phone ) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html( $email ) . '</p>';
        
        if ( $last_failure ) {
            echo '<p style="color: #d63638;"><strong>Last Failure:</strong> ' . esc_html( $last_failure ) . '</p>';
        }
        
        if ( $last_success ) {
            echo '<p style="color: #00a32a;"><strong>Last Success:</strong> ' . esc_html( $last_success ) . '</p>';
        }

        if ( ! empty( $phone ) ) {
            echo '<button type="button" class="button button-secondary bh-attentive-retry-subscription" 
                    data-subscription-id="' . esc_attr( $subscription_id ) . '"
                    data-user-id="' . esc_attr( $user_id ) . '"
                    data-phone="' . esc_attr( $phone ) . '"
                    data-email="' . esc_attr( $email ) . '">
                    ' . __( 'Retry Attentive Subscription', 'textdomain' ) . '
                  </button>';
        } else {
            echo '<p style="color: #d63638;">No phone number available for retry</p>';
        }
        
        echo '<div id="attentive-retry-result-sub-' . $subscription_id . '" style="margin-top: 10px;"></div>';
        echo '</div>';
    }

    /**
     * Handle AJAX retry for customer profile
     */
    public function handle_retry_customer() {
        check_ajax_referer( 'bh_attentive_retry', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Insufficient permissions', 'textdomain' ) );
        }

        $user_id = intval( $_POST['user_id'] );
        $phone = sanitize_text_field( $_POST['phone'] );
        $email = sanitize_email( $_POST['email'] );

        if ( ! $user_id || ! $phone ) {
            wp_send_json_error( __( 'Missing required data', 'textdomain' ) );
        }

        $result = $this->retry_attentive_subscription( $phone, $email, $user_id );
        
        if ( is_wp_error( $result ) ) {
            // Log failure
            update_user_meta( $user_id, '_attentive_last_failure', current_time( 'mysql' ) . ' - ' . $result->get_error_message() );
            wp_send_json_error( $result->get_error_message() );
        } else {
            // Log success
            update_user_meta( $user_id, '_attentive_last_success', current_time( 'mysql' ) );
            wp_send_json_success( __( 'Attentive subscription retry successful!', 'textdomain' ) );
        }
    }

    /**
     * Handle AJAX retry for subscription
     */
    public function handle_retry_subscription() {
        check_ajax_referer( 'bh_attentive_retry', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Insufficient permissions', 'textdomain' ) );
        }

        $subscription_id = intval( $_POST['subscription_id'] );
        $user_id = intval( $_POST['user_id'] );
        $phone = sanitize_text_field( $_POST['phone'] );
        $email = sanitize_email( $_POST['email'] );

        if ( ! $subscription_id || ! $phone ) {
            wp_send_json_error( __( 'Missing required data', 'textdomain' ) );
        }

        $result = $this->retry_attentive_subscription( $phone, $email, $user_id, $subscription_id );
        
        if ( is_wp_error( $result ) ) {
            // Log failure
            update_post_meta( $subscription_id, '_attentive_last_failure', current_time( 'mysql' ) . ' - ' . $result->get_error_message() );
            wp_send_json_error( $result->get_error_message() );
        } else {
            // Log success
            update_post_meta( $subscription_id, '_attentive_last_success', current_time( 'mysql' ) );
            wp_send_json_success( __( 'Attentive subscription retry successful!', 'textdomain' ) );
        }
    }

    /**
     * Perform the actual Attentive subscription retry
     */
    private function retry_attentive_subscription( $phone, $email, $user_id = null, $subscription_id = null ) {
        $logger = wc_get_logger();
        $context = [ 'source' => 'bh_marketing_retry' ];
        
        // Normalize phone number using the same function as the main flow
        $original_phone = $phone;
        $phone = BH_Attentive_Helper::normalize_phone( $phone );
        
        $logger->info( 
            "Manual Attentive retry initiated - Original Phone: {$original_phone}, Normalized Phone: {$phone}, Email: {$email}, User: {$user_id}, Subscription: {$subscription_id}", 
            $context 
        );

        // Initialize API client
        $api_client = new BH_Attentive_API_Client();
        
        // Get sign up source from config
        $settings = BH_Attentive_Config::get_settings();
        $sign_up_source_id = $settings['sign_up_source_id'];
        
        // Log the payload that will be sent
        $payload_preview = [
            'user' => [ 'phone' => $phone ],
            'signUpSourceId' => $sign_up_source_id
        ];
        if ( ! empty( $email ) ) {
            $payload_preview['user']['email'] = $email;
        }
        
        $logger->info( 
            "Manual retry payload (with normalized phone): " . wp_json_encode( $payload_preview ), 
            $context 
        );

        // Attempt subscription
        $result = $api_client->subscribe_user( $phone, $email, $sign_up_source_id );
        
        if ( is_wp_error( $result ) ) {
            $logger->error( 
                "Manual retry failed - Original Phone: {$original_phone}, Normalized Phone: {$phone}, Error: " . $result->get_error_message(), 
                $context 
            );
            return $result;
        }
        
        $logger->info( 
            "Manual retry successful - Normalized Phone: {$phone}", 
            $context 
        );
        
        return $result;
    }

    /**
     * Enqueue admin scripts for retry functionality
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on user edit and subscription edit pages
        if ( ! in_array( $hook, [ 'user-edit.php', 'post.php', 'user-new.php', 'profile.php' ] ) ) {
            return;
        }

        wp_enqueue_script(
            'bh-attentive-retry',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin-retry.js',
            [ 'jquery' ],
            '1.0.3',
            true
        );

        wp_localize_script( 'bh-attentive-retry', 'bhAttentiveRetry', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'bh_attentive_retry' ),
            'strings' => [
                'processing' => __( 'Processing...', 'textdomain' ),
                'success' => __( 'Success!', 'textdomain' ),
                'error' => __( 'Error:', 'textdomain' ),
            ],
        ] );
    }
}

// Initialize the retry functionality
new BH_Attentive_Admin_Retry();