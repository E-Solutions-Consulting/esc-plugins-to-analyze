<?php
/**
 * AH Orders Telegra
 *
 * Custom WooCommerce order statuses and Telegra integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Orders_Telegra' ) ) {

    class AH_Orders_Telegra {

        /**
         * Constructor.
         */
        public function __construct() {

            /**
             * Register custom order statuses (post_status) on init.
             */
            add_action( 'init', [ $this, 'register_custom_post_statuses' ], 20 );

            /**
             * Add custom statuses to WooCommerce visible status list.
             */
            add_filter( 'wc_order_statuses', [ $this, 'add_custom_statuses_to_list' ], 20 );

            /**
             * Disable Telemdnow thank you redirect.
             */
            add_filter(
                'telemdnow_thankyou_redirect_enabled',
                [ $this, 'disable_thankyou_redirect' ],
                10,
                2
            );

            add_action('telemdnow_critical_api_error', [$this, 'notify_slack_on_critical_error'], 10, 6);
        }

        /**
         * Register custom post statuses for shop orders.
         *
         * IMPORTANT:
         * - Relies on constants:
         *   SEND_TO_TELEGRA
         *   CANCEL_CUSTOMER_REQUEST
         *   CANCEL_AUTHORIZATION_EXPIRED
         *   CANCEL_PATIENT_REJECTED
         * - These must be defined BEFORE this class is instantiated.
         */
        public function register_custom_post_statuses() {

            // Send to Telegra.
            register_post_status(
                'wc-' . SEND_TO_TELEGRA,
                [
                    'label'                     => _x( 'Send to Telegra', 'Order status', 'woocommerce' ),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop(
                        'Send to Telegra (%s)',
                        'Send to Telegra (%s)'
                    ),
                ]
            );

            // Cancelled – Customer Request.
            register_post_status(
                'wc-' . CANCEL_CUSTOMER_REQUEST,
                [
                    'label'                     => _x( 'Cancelled - Customer request', 'Order status', 'woocommerce' ),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop(
                        'Cancelled - Customer request (%s)',
                        'Cancelled - Customer request (%s)'
                    ),
                ]
            );

            // Cancelled – Authorization Expired.
            register_post_status(
                'wc-' . CANCEL_AUTHORIZATION_EXPIRED,
                [
                    'label'                     => _x( 'Cancelled - Authorization expired', 'Order status', 'woocommerce' ),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop(
                        'Cancelled - Authorization expired (%s)',
                        'Cancelled - Authorization expired (%s)'
                    ),
                ]
            );

            // Cancelled – Patient Rejected.
            register_post_status(
                'wc-' . CANCEL_PATIENT_REJECTED,
                [
                    'label'                     => _x( 'Cancelled - Patient rejected', 'Order status', 'woocommerce' ),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop(
                        'Cancelled - Patient rejected (%s)',
                        'Cancelled - Patient rejected (%s)'
                    ),
                ]
            );
        }

        /**
         * Add custom statuses to WooCommerce status list.
         *
         * - Insert "Send to Telegra" after "processing".
         * - Insert cancelled variants after "cancelled".
         *
         * @param array $order_statuses
         * @return array
         */
        public function add_custom_statuses_to_list( $order_statuses ) {

            $new_statuses = [];

            foreach ( $order_statuses as $key => $label ) {

                // Keep original status.
                $new_statuses[ $key ] = $label;

                // After processing → Send to Telegra.
                if ( 'wc-processing' === $key ) {
                    $new_statuses[ 'wc-' . SEND_TO_TELEGRA ] = _x(
                        'Send to Telegra',
                        'Order status',
                        'woocommerce'
                    );
                }

                // After cancelled → custom cancelled variants.
                if ( 'wc-cancelled' === $key ) {

                    $new_statuses[ 'wc-' . CANCEL_CUSTOMER_REQUEST ] = _x(
                        'Cancelled - Customer request',
                        'Order status',
                        'woocommerce'
                    );

                    $new_statuses[ 'wc-' . CANCEL_AUTHORIZATION_EXPIRED ] = _x(
                        'Cancelled - Authorization expired',
                        'Order status',
                        'woocommerce'
                    );

                    $new_statuses[ 'wc-' . CANCEL_PATIENT_REJECTED ] = _x(
                        'Cancelled - Patient rejected',
                        'Order status',
                        'woocommerce'
                    );
                }
            }

            return $new_statuses;
        }

        /**
         * Disable Telegra thank you redirect.
         *
         * @param bool   $should_redirect
         * @param string $url
         * @return bool
         */
        public function disable_thankyou_redirect( $should_redirect, $url ) {
            return false;
        }


        public function notify_slack_on_critical_error__original($status, $url, $type, $data_sent, $data_received, $inserted_id) {
            try {
                if ($status < 502) return;

                $sent_decoded = is_string($data_sent) ? json_decode($data_sent, true) : $data_sent;

                $message = "🚨 Error {$status} | URL: {$url} | Type: {$type} | Data: " . json_encode($sent_decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                bh_send_slack_notification($message, BH_SLACK_CHANNEL_TELEGRA_API_ERROR);

            } catch (Exception $e) {
                bh_plugins_error_log('notify_slack_on_critical_error: ' . $e->getMessage());
            }
        }
        function notify_slack_on_critical_error($status, $url, $type, $data_sent, $data_received, $inserted_id) {
            try {
                if ($status < 502) return;

                //if (strpos($url, '/patients/') !== false) return;
                $ignored = ['/patients/', '/auth/sso'];
                foreach ($ignored as $path) {
                    if (strpos($url, $path) !== false) return;
                }

                $sent_decoded = is_string($data_sent) ? json_decode($data_sent, true) : $data_sent;

                $log_url = function_exists('telemdnow_api_error_log_url') 
                        ? html_entity_decode(telemdnow_api_error_log_url($inserted_id), ENT_QUOTES, 'UTF-8')
                        : $inserted_id;

                $parsed     =   parse_url($url);
                $url_path   =   $parsed['path'] ?? '';

                $title = match(true) {
                    strpos($url, '/sendToPharmacyRecipients') !== false => "Send To Pharmacy",
                    strpos($url, '/getOrCreateOrder') !== false         => "Send To Telegra",
                    default                                             => $url_path,
                };
                $message = "🚨 `<{$log_url}|Error {$status}>` *{$title}*";
                if($sent_decoded)
                    $message .= " ```" . json_encode($sent_decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "```";

                bh_send_slack_notification($message, BH_SLACK_CHANNEL_TELEGRA_API_ERROR);

            } catch (Exception $e) {
                plugin_log('notify_slack_on_critical_error: ' . $e->getMessage());
            }
        }

    }

    /**
     * Instantiate the module after WooCommerce is loaded.
     */
    add_action( 'woocommerce_loaded', function() {
        new AH_Orders_Telegra();
    } );

}
