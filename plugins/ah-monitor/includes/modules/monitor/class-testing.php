<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Testing {

    /**
     * Register testing hooks.
     */
    public static function init() {

        add_action( 'admin_init', [ __CLASS__, 'handle_monitor_test' ] );

    }

    /**
     * Trigger monitor manually via admin URL parameter.
     * /wp-admin/?ah_monitor_test=1
     */
    function handle_monitor_test() {

        if ( empty( $_GET['ah_monitor_test'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        try {

            AH_Hourly_Orders_Monitor::run();

            wp_die( 'AH Monitor executed successfully.' );

        } catch ( Throwable $e ) {

            AH_Error_Handler::handle_exception( $e );

            wp_die( 'AH Monitor execution failed.' );

        }

    }

}