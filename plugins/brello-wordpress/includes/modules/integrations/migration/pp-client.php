<?php
/**
 * Patient Platform API client.
 *
 * Consumes the PP-839 migration-status endpoint to check whether an email
 * belongs to a Patient Platform user.
 *
 * Contract (confirmed with PP team, differs from docs/PatientAPI.md):
 *   POST {pp_api_base}/migration-status
 *   Headers: x-tenant-slug, Authorization: Bearer <md5 hash provided by PP team>
 *   No apikey header.
 *   Body: { "email": "..." }
 *   Response: { "data": { "migration": { "isMigrated": bool, "status": "...", "label": "..." } } }
 *   Errors:   { "error": { "code": "...", "message": "..." } }
 *   Not-found is HTTP 200 with isMigrated=false, status="not_migrated".
 *   The settings field accepts either the hash verbatim (32 hex chars)
 *   or a raw key to be hashed at request time.
 *   Mock mode: define AH_PP_MOCK in wp-config.php as 'migrated',
 *   'not_migrated' or 'random' to simulate lookups without API calls.
 *
 * Behavior:
 *   - Results cached in transients (positive 1h, negative 10min).
 *   - 3s timeout, fail-open: any failure returns null (unknown).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_PP_Client {

    const CACHE_PREFIX       = 'ah_pp_lookup_';
    const CACHE_TTL_POSITIVE = HOUR_IN_SECONDS;
    const CACHE_TTL_NEGATIVE = 600;
    const TIMEOUT            = 3;

    /**
     * Whether the email belongs to a Patient Platform user.
     *
     * @param string $email
     * @return bool|null True/false when verified, null when it could not be determined.
     */
    public static function is_platform_user( $email ) {
        $email = sanitize_email( $email );

        if ( empty( $email ) || ! is_email( $email ) ) {
            AH_Migration_Logger::log( 'PP lookup skipped: input is not a valid email.', 'debug' );
            return null;
        }

        if ( defined( 'AH_PP_MOCK' ) && AH_PP_MOCK ) {
            $mocked = self::mock_lookup( $email );
            AH_Migration_Logger::log( sprintf( 'PP lookup MOCK (%s) for %s: %s', AH_PP_MOCK, $email, $mocked ? 'IS platform user' : 'NOT a platform user' ), 'notice' );
            return $mocked;
        }

        if ( ! self::is_configured() ) {
            AH_Migration_Logger::log( 'PP lookup skipped: client not configured (check pp_api_base, pp_publishable_key, pp_tenant_slug).', 'warning' );
            return null;
        }

        $cache_key = self::CACHE_PREFIX . md5( strtolower( $email ) );
        $cached    = get_transient( $cache_key );

        if ( 'yes' === $cached ) {
            AH_Migration_Logger::log( sprintf( 'PP lookup cache HIT (positive) for %s', $email ), 'debug' );
            return true;
        }

        if ( 'no' === $cached ) {
            AH_Migration_Logger::log( sprintf( 'PP lookup cache HIT (negative) for %s', $email ), 'debug' );
            return false;
        }

        AH_Migration_Logger::log( sprintf( 'PP lookup cache MISS for %s — querying API.', $email ), 'debug' );

        $result = self::query_api( $email );

        if ( null === $result ) {
            AH_Migration_Logger::log( sprintf( 'PP lookup for %s could not be determined (fail-open).', $email ), 'warning' );
            return null;
        }

        AH_Migration_Logger::log( sprintf( 'PP lookup result for %s: %s', $email, $result ? 'IS platform user' : 'NOT a platform user' ) );

        set_transient( $cache_key, $result ? 'yes' : 'no', $result ? self::CACHE_TTL_POSITIVE : self::CACHE_TTL_NEGATIVE );

        return $result;
    }

    /**
     * Mock lookup driven by the AH_PP_MOCK constant (wp-config.php).
     * Values: 'migrated' (always true), 'not_migrated' (always false),
     * 'random' (deterministic per email: same email always same result).
     *
     * @param string $email
     * @return bool
     */
    private static function mock_lookup( $email ) {
        $mode = strtolower( (string) AH_PP_MOCK );

        if ( 'migrated' === $mode ) {
            return true;
        }

        if ( 'not_migrated' === $mode ) {
            return false;
        }

        return 0 === ( crc32( strtolower( $email ) ) % 2 );
    }

    /**
     * Whether the client has the required settings.
     *
     * @return bool
     */
    public static function is_configured() {
        $base   = AH_Migration_Config::get( 'pp_api_base' );
        $key    = AH_Migration_Config::get( 'pp_publishable_key' );
        $tenant = AH_Migration_Config::get( 'pp_tenant_slug' );

        return ! empty( $base ) && ! empty( $key ) && ! empty( $tenant );
    }

    /**
     * Resolve the bearer token from settings. If the stored value is
     * already a 32-char hex MD5 hash, it is used verbatim; otherwise
     * it is treated as a raw key and hashed.
     *
     * @return string
     */
    private static function get_bearer_hash() {
        $value = trim( (string) AH_Migration_Config::get( 'pp_publishable_key' ) );

        if ( preg_match( '/^[a-f0-9]{32}$/i', $value ) ) {
            return strtolower( $value );
        }

        return md5( $value );
    }

    /**
     * Perform the migration-status lookup against the PP API.
     *
     * @param string $email
     * @return bool|null
     */
    private static function query_api( $email ) {
        $url = trailingslashit( AH_Migration_Config::get( 'pp_api_base' ) ) . 'migration-status';

        AH_Migration_Logger::log( sprintf( 'PP request: POST %s | tenant=%s', $url, AH_Migration_Config::get( 'pp_tenant_slug' ) ), 'debug' );

        $response = wp_remote_post( $url, array(
            'timeout' => self::TIMEOUT,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'x-tenant-slug' => AH_Migration_Config::get( 'pp_tenant_slug' ),
                'Authorization' => 'Bearer ' . self::get_bearer_hash(),
            ),
            'body'    => wp_json_encode( array( 'email' => $email ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            AH_Migration_Logger::log( 'PP lookup failed: ' . $response->get_error_message(), 'warning' );
            return null;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );

        AH_Migration_Logger::log( sprintf( 'PP response: HTTP %d | body: %s', $code, substr( $raw_body, 0, 500 ) ), 'debug' );

        if ( 200 !== $code ) {
            AH_Migration_Logger::log( 'PP lookup unexpected HTTP ' . $code, 'warning' );
            return null;
        }

        $body = json_decode( $raw_body, true );

        if ( ! is_array( $body ) ) {
            AH_Migration_Logger::log( 'PP lookup returned invalid JSON.', 'warning' );
            return null;
        }

        if ( isset( $body['error'] ) ) {
            AH_Migration_Logger::log( 'PP lookup API error: ' . wp_json_encode( $body['error'] ), 'warning' );
            return null;
        }

        $migration = isset( $body['data']['migration'] ) && is_array( $body['data']['migration'] )
            ? $body['data']['migration']
            : $body;

        if ( isset( $migration['isMigrated'] ) ) {
            return (bool) $migration['isMigrated'];
        }

        if ( isset( $migration['status'] ) ) {
            return 'migrated' === strtolower( (string) $migration['status'] );
        }

        AH_Migration_Logger::log( 'PP response has neither isMigrated nor status fields.', 'warning' );

        return null;
    }
}