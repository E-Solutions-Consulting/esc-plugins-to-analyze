<?php
/**
 * Attentive Integration - Bootstrap Loader
 * 
 * Initializes the Typeform x Attentive webhook integration.
 * This file loads all required components and registers WordPress hooks.
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Main loader class for Attentive integration
 */
class BH_Attentive_Loader {

    /**
     * Initialize the Attentive integration
     * 
     * @return void
     */
    public static function init() {
        
        // Define base path
        $base_path = plugin_dir_path( __FILE__ );
        
        // Load required files
        self::load_dependencies( $base_path );
        
        // Initialize components
        self::initialize_components();
        
        // Create database table if needed (runs once)
        self::maybe_create_table();
    }
    
    /**
     * Load all required dependency files
     * 
     * @param string $base_path Base directory path
     * @return void
     */
    private static function load_dependencies( $base_path ) {
        
        // Core components
        require_once $base_path . 'config.php';
        require_once $base_path . 'webhook-handler.php';
        require_once $base_path . 'api-client.php';
        require_once $base_path . 'field-mapper.php';
        require_once $base_path . 'logger.php';
        
        // Shared helper functions (must load before event handlers)
        require_once $base_path . 'helper.php';
        
        // Frontend components
        require_once $base_path . 'frontend-trigger.php';
        
        // UNIFIED event handler - handles ALL events (orders + subscriptions)
        require_once $base_path . 'unified-events.php';
        
        // STRIPE event handler - handles payment_failed, card_expiring, payment_recovered
        require_once $base_path . 'stripe-events.php';
        
        // OLD event trackers - DISABLED (unified-events.php handles everything)
        // require_once $base_path . 'subscription-events.php';
        // require_once $base_path . 'order-events.php';
        
        // Debug admin page - DISABLED (remove file in production)
        // require_once $base_path . 'admin-debug-code.php';
    }
    
    /**
     * Initialize all component classes
     * 
     * @return void
     */
    private static function initialize_components() {
        
        // Initialize configuration and admin page
        if ( class_exists( 'BH_Attentive_Config' ) ) {
            BH_Attentive_Config::init();
        }
        
        // Initialize webhook handler (REST API endpoint)
        if ( class_exists( 'BH_Attentive_Webhook_Handler' ) ) {
            BH_Attentive_Webhook_Handler::init();
        }
        
        // Initialize logger
        if ( class_exists( 'BH_Attentive_Logger' ) ) {
            BH_Attentive_Logger::init();
        }
        
        // Initialize UNIFIED event handler (handles ALL events)
        if ( class_exists( 'BH_Attentive_Unified_Events' ) ) {
            new BH_Attentive_Unified_Events();
        }
        
        // Initialize STRIPE event handler (Priority #2: payment_failed, card_expiring, payment_recovered)
        if ( class_exists( 'BH_Attentive_Stripe_Events' ) ) {
            new BH_Attentive_Stripe_Events();
        }
        
        // OLD event trackers - DISABLED (unified-events.php handles everything)
        // if ( class_exists( 'BH_Attentive_Subscription_Events' ) ) {
        //     new BH_Attentive_Subscription_Events();
        // }
        // 
        // if ( class_exists( 'BH_Attentive_Order_Events' ) ) {
        //     new BH_Attentive_Order_Events();
        // }
    }
    
    /**
     * Create database table if not exists
     * Called during init instead of activation hook
     */
    private static function maybe_create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'typeform_webhook_logs';
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id VARCHAR(100) NOT NULL,
            event_id VARCHAR(100) NOT NULL,
            typeform_payload LONGTEXT,
            mapped_data LONGTEXT,
            attentive_request LONGTEXT,
            attentive_response LONGTEXT,
            status VARCHAR(20) NOT NULL,
            error_message TEXT,
            phone_number VARCHAR(20),
            email VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_form_id (form_id),
            INDEX idx_status (status),
            INDEX idx_phone (phone_number),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

// Initialize the integration when WordPress loads
add_action( 'plugins_loaded', array( 'BH_Attentive_Loader', 'init' ), 20 );
