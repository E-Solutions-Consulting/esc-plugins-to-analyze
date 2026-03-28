<?php
/**
 * Attentive Integration - Logger
 * 
 * Handles logging for webhook activity and API calls.
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Logger {

    private static $log_dir;
    private static $log_file;
    private static $max_file_size = 5242880; // 5MB

    /**
     * Initialize logger
     */
    public static function init() {
        $upload_dir = wp_upload_dir();
        self::$log_dir = $upload_dir['basedir'] . '/bh-logs';
        self::$log_file = self::$log_dir . '/attentive_webhooks.log';
        
        // Create log directory if not exists
        if ( ! file_exists( self::$log_dir ) ) {
            wp_mkdir_p( self::$log_dir );
            
            // Add .htaccess protection
            $htaccess = self::$log_dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, 'Deny from all' );
            }
        }
        
        // Schedule log cleanup
        if ( ! wp_next_scheduled( 'bh_attentive_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'bh_attentive_cleanup_logs' );
        }
        add_action( 'bh_attentive_cleanup_logs', array( __CLASS__, 'cleanup_old_logs' ) );
    }

    /**
     * Log a message
     */
    public static function log( $message, $data = null, $level = 'INFO' ) {
        $settings = BH_Attentive_Config::get_settings();
        
        if ( empty( $settings['logging_enabled'] ) || $settings['logging_enabled'] !== 'yes' ) {
            return;
        }
        
        // Rotate log if needed
        self::maybe_rotate_log();
        
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $log_entry = "[{$timestamp}] [{$level}] {$message}";
        
        if ( $data !== null ) {
            if ( is_array( $data ) || is_object( $data ) ) {
                $log_entry .= "\n" . print_r( $data, true );
            } else {
                $log_entry .= " | Data: {$data}";
            }
        }
        
        $log_entry .= "\n---\n";
        
        error_log( $log_entry, 3, self::$log_file );
    }

    /**
     * Log webhook received
     */
    public static function log_webhook( $form_id, $event_id, $status, $details = [] ) {
        $message = "WEBHOOK | Form: {$form_id} | Event: {$event_id} | Status: {$status}";
        self::log( $message, $details );
    }

    /**
     * Log API call
     */
    public static function log_api_call( $endpoint, $response_code, $response_body = null ) {
        $message = "API CALL | Endpoint: {$endpoint} | Response: {$response_code}";
        self::log( $message, $response_body );
    }

    /**
     * Log error
     */
    public static function log_error( $message, $error_data = null ) {
        self::log( $message, $error_data, 'ERROR' );
        error_log( '[BH Attentive] ' . $message );
    }

    /**
     * Rotate log file if too large
     */
    private static function maybe_rotate_log() {
        if ( ! file_exists( self::$log_file ) ) {
            return;
        }
        
        if ( filesize( self::$log_file ) >= self::$max_file_size ) {
            $archive_name = self::$log_dir . '/attentive_webhooks_' . date( 'Y-m-d_H-i-s' ) . '.log';
            rename( self::$log_file, $archive_name );
        }
    }

    /**
     * Cleanup logs older than 30 days
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        
        // Cleanup database logs
        $table_name = $wpdb->prefix . 'typeform_webhook_logs';
        $wpdb->query( "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)" );
        
        // Cleanup old archive files
        $files = glob( self::$log_dir . '/attentive_webhooks_*.log' );
        $threshold = strtotime( '-30 days' );
        
        foreach ( $files as $file ) {
            if ( filemtime( $file ) < $threshold ) {
                unlink( $file );
            }
        }
        
        self::log( 'Log cleanup completed' );
    }

    /**
     * Log to database
     */
    public static function log_to_database( $data ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'typeform_webhook_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'form_id'            => sanitize_text_field( $data['form_id'] ?? '' ),
                'event_id'           => sanitize_text_field( $data['event_id'] ?? '' ),
                'typeform_payload'   => wp_json_encode( $data['typeform_payload'] ?? [] ),
                'mapped_data'        => wp_json_encode( $data['mapped_data'] ?? [] ),
                'attentive_request'  => wp_json_encode( $data['attentive_request'] ?? [] ),
                'attentive_response' => wp_json_encode( $data['attentive_response'] ?? [] ),
                'status'             => sanitize_text_field( $data['status'] ?? 'pending' ),
                'error_message'      => sanitize_textarea_field( $data['error_message'] ?? '' ),
                'phone_number'       => sanitize_text_field( $data['phone_number'] ?? '' ),
                'email'              => sanitize_email( $data['email'] ?? '' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        
        return $wpdb->insert_id;
    }

    /**
     * Get recent logs from database
     */
    public static function get_recent_logs( $limit = 50 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'typeform_webhook_logs';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }
}
