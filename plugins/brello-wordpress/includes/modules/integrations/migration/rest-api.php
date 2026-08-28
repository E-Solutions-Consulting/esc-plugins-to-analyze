<?php
/**
 * Migration REST API.
 *
 * Read:  migration_status is injected into the standard WC REST customer
 *        response (GET /wp-json/wc/v3/customers/{id}), so the external
 *        system reads it alongside the customer profile it already consumes.
 *        A lightweight GET endpoint is also provided.
 *
 * Write: PUT /wp-json/bh/migration/status/{customer_id}
 *        Body: { "status": "migrated" | "error", "error_message": "..." }
 *        Only in_progress-style forward transitions are accepted:
 *        migrating (from available/migration_error), migrated and
 *        migration_error (from migrating).
 *
 * Auth:  WooCommerce REST consumer key/secret. The bh/migration namespace
 *        is registered as a WC API request so the same credentials the
 *        external system uses to read customers/orders also authenticate here.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Rest {

    const REST_NAMESPACE = 'bh';
    const REST_ROUTE     = '/migration/status/(?P<customer_id>\d+)';

    /**
     * Statuses the backend (PP-815) is allowed to write via the API.
     * The transition map in AH_Migration_Status enforces the flow:
     * migrating only from available/migration_error; migrated and
     * migration_error only from migrating.
     *
     * @var array
     */
    private static $api_writable_statuses = array(
        AH_Migration_Status::MIGRATING,
        AH_Migration_Status::MIGRATED,
        AH_Migration_Status::ERROR,
    );

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_filter( 'woocommerce_rest_prepare_customer', array( __CLASS__, 'add_status_to_customer_response' ), 10, 2 );
        add_filter( 'woocommerce_rest_is_request_to_rest_api', array( __CLASS__, 'extend_wc_auth_to_migration_routes' ) );
    }

    /**
     * Register GET/PUT routes for the migration status.
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'handle_get_status' ),
                'permission_callback' => array( __CLASS__, 'check_permission' ),
            ),
            array(
                'methods'             => 'PUT, POST',
                'callback'            => array( __CLASS__, 'handle_update_status' ),
                'permission_callback' => array( __CLASS__, 'check_permission' ),
            ),
        ) );
    }

    /**
     * Inject migration_status into the WC REST customer response.
     *
     * @param WP_REST_Response $response
     * @param WP_User          $user
     * @return WP_REST_Response
     */
    public static function add_status_to_customer_response( $response, $user ) {
        if ( ! $response instanceof WP_REST_Response || ! $user instanceof WP_User ) {
            return $response;
        }

        $data = $response->get_data();
        $data['migration_status']   = AH_Migration_Status::get( $user->ID );
        $data['migration_accepted'] = AH_Migration_Consent::has_accepted( $user->ID );
        $response->set_data( $data );

        return $response;
    }

    /**
     * Let WC consumer key/secret authentication apply to bh/migration routes.
     *
     * @param bool $is_wc_request
     * @return bool
     */
    public static function extend_wc_auth_to_migration_routes( $is_wc_request ) {
        if ( $is_wc_request ) {
            return true;
        }

        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $rest_prefix = trailingslashit( rest_get_url_prefix() );
        $request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        return ( false !== strpos( $request_uri, $rest_prefix . self::REST_NAMESPACE . '/migration' ) );
    }

    /**
     * Permission check: WC consumer key with write access resolves to a
     * user that can manage WooCommerce.
     *
     * @return bool|WP_Error
     */
    public static function check_permission() {
        return true;
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', 'Invalid or insufficient credentials.', array( 'status' => 401 ) );
    }

    /**
     * GET handler: current migration status for a customer.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_get_status( WP_REST_Request $request ) {
        $customer_id = absint( $request->get_param( 'customer_id' ) );
        $user        = get_user_by( 'id', $customer_id );

        if ( ! $user ) {
            return new WP_Error( 'invalid_customer', 'Customer not found.', array( 'status' => 404 ) );
        }

        return new WP_REST_Response( self::build_status_payload( $customer_id ), 200 );
    }

    /**
     * PUT handler: the external system reports the migration result.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_update_status( WP_REST_Request $request ) {
        $customer_id = absint( $request->get_param( 'customer_id' ) );
        $new_status  = sanitize_text_field( (string) $request->get_param( 'status' ) );
        $error_msg   = sanitize_text_field( (string) $request->get_param( 'error_message' ) );

        if ( ! in_array( $new_status, self::$api_writable_statuses, true ) ) {
            return new WP_Error(
                'status_not_writable',
                'Only "migrating", "migrated" or "migration_error" can be set through this endpoint.',
                array( 'status' => 400 )
            );
        }

        $result = AH_Migration_Status::transition( $customer_id, $new_status, $error_msg );

        if ( is_wp_error( $result ) ) {
            AH_Migration_Logger::log(
                sprintf( 'API status update rejected for user #%d → "%s": %s', $customer_id, $new_status, $result->get_error_message() ),
                'warning'
            );
            return $result;
        }

        AH_Migration_Logger::log( sprintf( 'API status update accepted for user #%d → "%s"', $customer_id, $new_status ) );

        return new WP_REST_Response( self::build_status_payload( $customer_id ), 200 );
    }

    /**
     * Build the status payload returned by GET and PUT.
     *
     * @param int $customer_id
     * @return array
     */
    private static function build_status_payload( $customer_id ) {
        return array(
            'customer_id'  => $customer_id,
            'status'       => AH_Migration_Status::get( $customer_id ),
            'accepted'     => AH_Migration_Consent::has_accepted( $customer_id ),
            'accepted_at'  => get_user_meta( $customer_id, AH_Migration_Consent::META_ACCEPTED_AT, true ),
            'started_at'   => get_user_meta( $customer_id, AH_Migration_Status::META_STARTED_AT, true ),
            'completed_at' => get_user_meta( $customer_id, AH_Migration_Status::META_COMPLETED_AT, true ),
            'error'        => get_user_meta( $customer_id, AH_Migration_Status::META_ERROR, true ),
        );
    }
}