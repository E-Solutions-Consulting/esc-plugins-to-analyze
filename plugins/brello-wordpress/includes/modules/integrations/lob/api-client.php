<?php
/**
 * LOB Integration - API Client
 *
 * Thin client for the LOB Print & Mail REST API (https://api.lob.com/v1).
 * Auth is HTTP Basic with the API key as the username and an empty password
 * (LOB's standard scheme). Test keys (test_...) never produce real mail or
 * charges; live keys (live_...) do.
 *
 * Docs: https://docs.lob.com/
 *
 * @package    BH_Features
 * @subpackage Integrations/Lob
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_LOB_API_Client {

    const BASE_URL = 'https://api.lob.com/v1';

    /** @var string */
    private $api_key;

    /**
     * @param string|null $api_key  Overrides the configured key when provided.
     */
    public function __construct( $api_key = null ) {
        $this->api_key = $api_key !== null ? $api_key : BH_LOB_Config::get_active_api_key();
    }

    /**
     * Lightweight connectivity/auth check.
     * GET /addresses?limit=1 — returns 200 with a (possibly empty) list when the
     * key is valid, 401 when it is not.
     *
     * @return array|WP_Error  ['success'=>true,'mode'=>'test|live'] or WP_Error.
     */
    public function test_connection() {
        $response = $this->request( 'GET', '/addresses', [ 'limit' => 1 ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return [
            'success' => true,
            'mode'    => ( strpos( (string) $this->api_key, 'live_' ) === 0 ) ? 'live' : 'test',
        ];
    }

    /**
     * List saved templates (GET /templates).
     *
     * @param int $limit  Max templates to fetch (LOB caps at 100 per page).
     * @return array|WP_Error  List of [ 'id' => 'tmpl_...', 'description' => '...' ].
     */
    public function list_templates( $limit = 100 ) {
        $response = $this->request( 'GET', '/templates', [ 'limit' => $limit ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $out  = [];
        $data = $response['data'] ?? [];

        foreach ( (array) $data as $tmpl ) {
            if ( empty( $tmpl['id'] ) ) {
                continue;
            }
            $out[] = [
                'id'          => $tmpl['id'],
                'description' => $tmpl['description'] ?? $tmpl['id'],
            ];
        }

        return $out;
    }

    /**
     * Create a postcard.
     *
     * @param array $to        LOB address object (inline) or address id string.
     * @param array $from      LOB address object (inline) or address id string.
     * @param array $args      front, back (template ids or HTML), size, description, merge_variables, metadata.
     * @return array|WP_Error  Decoded LOB response (contains 'id' = psc_...).
     */
    public function create_postcard( $to, $from, array $args = [] ) {

        $body = [
            'to'          => $to,
            'from'        => $from,
            'front'       => $args['front'] ?? '',
            'back'        => $args['back'] ?? '',
            'size'        => $args['size'] ?? '4x6',
            // Required by LOB: 'marketing' or 'operational'.
            'use_type'    => $args['use_type'] ?? 'marketing',
            'description' => $args['description'] ?? 'Brello subscription mailer',
        ];

        if ( ! empty( $args['merge_variables'] ) ) {
            $body['merge_variables'] = $args['merge_variables'];
        }
        if ( ! empty( $args['metadata'] ) ) {
            $body['metadata'] = $args['metadata'];
        }

        return $this->request( 'POST', '/postcards', $body );
    }

    /**
     * Create a letter.
     *
     * @param array $to
     * @param array $from
     * @param array $args   file (template id or HTML), color, address_placement, description, merge_variables, metadata.
     * @return array|WP_Error
     */
    public function create_letter( $to, $from, array $args = [] ) {

        $body = [
            'to'                => $to,
            'from'              => $from,
            'file'              => $args['file'] ?? '',
            'color'             => isset( $args['color'] ) ? (bool) $args['color'] : false,
            // Required by LOB: 'marketing' or 'operational'.
            'use_type'          => $args['use_type'] ?? 'marketing',
            'address_placement' => $args['address_placement'] ?? 'top_first_page',
            'description'       => $args['description'] ?? 'Brello subscription mailer',
        ];
        if ( ! empty( $args['merge_variables'] ) ) {
            $body['merge_variables'] = $args['merge_variables'];
        }
        if ( ! empty( $args['metadata'] ) ) {
            $body['metadata'] = $args['metadata'];
        }

        return $this->request( 'POST', '/letters', $body );
    }

    /**
     * Perform an authenticated request against the LOB API.
     *
     * @param string $method  GET | POST
     * @param string $path    e.g. /postcards
     * @param array  $data    Query params (GET) or body params (POST).
     * @return array|WP_Error Decoded JSON array on 2xx, WP_Error otherwise.
     */
    private function request( $method, $path, array $data = [] ) {

        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'lob_no_key', 'LOB API key is not configured.' );
        }

        $url = self::BASE_URL . $path;

        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => [
                // Basic auth: base64( "API_KEY:" ) — key as username, blank password.
                'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' ),
                'Accept'        => 'application/json',
            ],
        ];

        if ( 'GET' === $method ) {
            if ( ! empty( $data ) ) {
                $url = add_query_arg( $data, $url );
            }
        } else {
            // LOB accepts form-encoded bodies; nested arrays (to/from/metadata)
            // are expanded by http_build_query into to[name], metadata[key], etc.
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body']                    = http_build_query( $data );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            BH_LOB_Logger::error( 'LOB request transport error', [
                'path'  => $path,
                'error' => $response->get_error_message(),
            ] );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );

        if ( $code >= 200 && $code < 300 ) {
            return is_array( $json ) ? $json : [];
        }

        $message = $json['error']['message'] ?? ( 'HTTP ' . $code );

        BH_LOB_Logger::error( 'LOB API error', [
            'path' => $path,
            'code' => $code,
            'body' => mb_substr( (string) $body, 0, 500 ),
        ] );

        return new WP_Error( 'lob_api_error', $message, [ 'code' => $code ] );
    }
}
