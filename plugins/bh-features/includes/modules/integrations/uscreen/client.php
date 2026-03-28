<?php
/**
 * Uscreen API client.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Uscreen_Client {

    /**
     * Perform a test call to validate connectivity and credentials.
     *
     * @return array|WP_Error
     */
    public static function test_connection() {
        $settings = AH_Uscreen_Config::get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return new WP_Error(
                'ah_uscreen_missing_api_key',
                __( 'API key is empty. Please configure the Uscreen API key before testing the connection.', 'ah-uscreen' )
            );
        }

        $response = self::request(
            'GET',
            'customers',
            array(
                'query_args' => array(
                    'per_page' => 1,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code >= 200 && $status_code < 300 ) {
            self::log( 'Test connection successful. Status: ' . $status_code . ' Body: ' . substr( $body, 0, 500 ) );
            return array(
                'success' => true,
                'code'    => $status_code,
            );
        }

        if ( 401 === $status_code || 403 === $status_code ) {
            $message = __( 'Unauthorized response from Uscreen. Please check the API key.', 'ah-uscreen' );
        } else {
            $message = sprintf(
                __( 'Unexpected response from Uscreen API. HTTP status: %d', 'ah-uscreen' ),
                $status_code
            );
        }

        self::log(
            sprintf(
                'Test connection failed. Status: %d Body: %s',
                $status_code,
                substr( $body, 0, 1000 )
            ),
            'error'
        );

        return array(
            'success' => false,
            'code'    => $status_code,
            'message' => $message,
        );
    }

    /**
     * Generic request wrapper for Uscreen API.
     *
     * @param string $method HTTP method (GET, POST, etc.).
     * @param string $path   Relative API path (e.g. "customers").
     * @param array  $args   Extra arguments:
     *                       - query_args (array)
     *                       - body (array)
     *                       - headers (array)
     *
     * @return array|WP_Error Response from wp_remote_request.
     */
    public static function request( $method, $path, array $args = array() ) {
        $settings = AH_Uscreen_Config::get_settings();

        $base_url = rtrim( (string) $settings['base_url'], '/' ) . '/';
        $url      = $base_url . ltrim( $path, '/' );

        $query_args = isset( $args['query_args'] ) && is_array( $args['query_args'] )
            ? $args['query_args']
            : array();

        if ( ! empty( $query_args ) ) {
            $url = add_query_arg( $query_args, $url );
        }

        $headers = array(
            'Authorization' => $settings['api_key'],
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        );

        if ( ! empty( $args['headers'] ) && is_array( $args['headers'] ) ) {
            $headers = array_merge( $headers, $args['headers'] );
        }

        $request_args = array(
            'method'  => strtoupper( $method ),
            'headers' => $headers,
            'timeout' => 20,
        );

        if ( isset( $args['body'] ) ) {
            if ( is_array( $args['body'] ) ) {
                $request_args['body'] = wp_json_encode( $args['body'] );
            } else {
                $request_args['body'] = $args['body'];
            }
        }

        self::log(
            sprintf(
                'Request: %s %s Body: %s',
                $request_args['method'],
                $path,
                isset( $request_args['body'] ) ? substr( (string) $request_args['body'], 0, 1000 ) : ''
            )
        );

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            self::log( 'HTTP error: ' . $response->get_error_message(), 'error' );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        self::log(
            sprintf(
                'Response: HTTP %d Body: %s',
                $status_code,
                substr( (string) $body, 0, 1000 )
            )
        );

        return $response;
    }

    /**
     * Conditional logger using WooCommerce logger if available.
     *
     * @param string $message
     * @param string $level   log|info|notice|warning|error|critical|alert|emergency
     */
    public static function log( $message, $level = 'info' ) {
        $settings = AH_Uscreen_Config::get_settings();

        if ( ! isset( $settings['logging_enabled'] ) || 'yes' !== $settings['logging_enabled'] ) {
            return;
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $context = array( 'source' => 'uscreen' );
            $logger->log( $level, $message, $context );
        }
    }
}
