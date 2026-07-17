<?php
namespace YayMail\Abstracts;

/**
 * Base Rest API Controller
 */
abstract class BaseController {

    /**
     * Check if a user has permission to access a router
     *
     * @return bool
     */
    public function permission_callback() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Returns response when verify nonce failed
     *
     * @return \WP_REST_Response
     */
    public function nonce_failure_response() {
        return new \WP_REST_Response(
            [
                'success' => false,
                'code'    => 'nonce_failure',
                'message' => __( 'Verify nonce failed', 'yaymail' ),
            ]
        );
    }

    /**
     * Verify nonce
     *
     * @return int|false
     */
    public function verify_nonce( $request ) {
        $nonce = $request->get_header( 'x_wp_nonce' );
        return wp_verify_nonce( $nonce, 'wp_rest' );
    }

    /**
     * Function API exec
     *
     * @param  callable         $callable
     * @param  \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function exec( $callable, \WP_REST_Request $request ) {

        if ( ! $this->verify_nonce( $request ) ) {
            return rest_ensure_request( $this->nonce_failure_response() );
        }

        try {
            if ( is_callable( $callable ) ) {
                $response = $callable( $request );
            }
        } catch ( \Throwable $ex ) {
            return rest_ensure_response(
                [
                    'isError' => true,
                    'code'    => $ex->getCode(),
                    'message' => $ex->getMessage(),
                ]
            );
        }

        return rest_ensure_response( $response );
    }
}
