<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Debug {

    /**
     * Register debug hooks.
     */
    public static function init() {

        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_init', [ __CLASS__, 'handle_debug_request' ], 20 );
    }

    /**
     * Handle debug URL actions.
     *
     * Example:
     * /wp-admin/?ah_debug=monitor
     * /wp-admin/?ah_debug=monitor&dry_run=1
     * /wp-admin/?ah_debug=monitor&dry_run=0&send_slack=1
     * /wp-admin/?ah_debug=monitor&start=2026-03-12 10:00:00&end=2026-03-12 10:59:59
     */
    public static function handle_debug_request() {

        if ( empty( $_GET['ah_debug'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_GET['ah_debug'] ) );

        try {

            switch ( $action ) {

                case 'monitor':

                    $options = self::get_monitor_options();

                    $monitor = new AH_Hourly_Orders_Monitor( $options );
                    $result  = $monitor->run();

                    self::render_result( $result );

                break;

            }

        } catch ( Throwable $e ) {

            AH_Error_Handler::handle_exception( $e );

            wp_die( 'Debug execution failed.' );
        }
    }

    /**
     * Build monitor options from URL params.
     */
    private static function get_monitor_options() {

        $dry_run = true;
        if ( isset( $_GET['dry_run'] ) ) {
            $dry_run = (int) $_GET['dry_run'] === 1;
        }

        $send_slack = false;
        if ( isset( $_GET['send_slack'] ) ) {
            $send_slack = (int) $_GET['send_slack'] === 1;
        }

        $start = null;
        if ( ! empty( $_GET['start'] ) ) {
            $start = sanitize_text_field( wp_unslash( $_GET['start'] ) );
        }

        $end = null;
        if ( ! empty( $_GET['end'] ) ) {
            $end = sanitize_text_field( wp_unslash( $_GET['end'] ) );
        }

        return [
            'dry_run'    => $dry_run,
            'send_slack' => $send_slack,
            'start'      => $start,
            'end'        => $end,
        ];
    }

    /**
     * Render debug result in admin.
     */
    private static function render_result( $result ) {

        echo '<div class="wrap">';
        echo '<h1>AH Monitor Debug</h1>';
        echo '<pre style="background:#fff;padding:16px;border:1px solid #ccd0d4;overflow:auto;">';
        print_r( $result );
        echo '</pre>';
        echo '</div>';

        exit;
    }

}