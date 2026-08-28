<?php
/**
 * Helper methods for Uscreen customer + access management.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Uscreen_Client_Helper {

    /**
     * Find customer by email.
     *
     * @param string $email
     * @return array|false|WP_Error
     */
    public static function find_customer_by_email( $email ) {

        $response = AH_Uscreen_Client::request(
            'GET',
            'customers/' . $email,
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            return false;
        }

        if ( is_array( $body ) && ! empty( $body ) && isset( $body[0]['id'] ) ) {
            return $body[0]; // Uscreen returns an array of customer(s).
        }

        return false;
    }


    /**
     * Create a new customer.
     *
     * @param array $mapper → from AH_Uscreen_Subscription_Mapper
     * @return int|WP_Error  customer_id
     */
    public static function create_customer( array $mapper ) {

        $mode = $mapper['creation_mode']; // sso_only | password_silent | password_email

        // Determine password creation behavior.
        $password = null;

        if ( 'password_silent' === $mode || 'password_email' === $mode ) {
            $password = wp_generate_password( 12, true, true );
        }

        $payload = array(
            'email' => $mapper['email'],
            'name'  => trim( $mapper['first_name'] . ' ' . $mapper['last_name'] ),
        );

        if ( $password ) {
            $payload['password'] = $password;
        }

        // Some accounts require explicit "send_welcome_email".
        if ( 'password_email' === $mode ) {
            $payload['send_welcome_email'] = true;
        }

        $response = AH_Uscreen_Client::request(
            'POST',
            'customers',
            array( 'body' => $payload )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code > 299 ) {
            return new WP_Error(
                'uscreen_create_failed',
                'Failed to create Uscreen customer: ' . wp_json_encode( $body )
            );
        }

        if ( isset( $body['id'] ) ) {
            return (int) $body['id'];
        }

        return new WP_Error(
            'uscreen_create_missing_id',
            'Uscreen did not return a customer id.'
        );
    }


    /**
     * Ensure customer exists (create if needed).
     *
     * @param array $mapper
     * @return int|WP_Error
     */
    public static function ensure_customer_exists( array $mapper ) {

        $user_id   = $mapper['wp_user_id'];
        $email     = $mapper['email'];

        // Check if already stored locally
        $stored = get_user_meta( $user_id, '_ah_uscreen_customer_id', true );
        if ( $stored ) {
            return (int) $stored;
        }

        // Check in Uscreen by email.
        $found = self::find_customer_by_email( $email );

        if ( is_wp_error( $found ) ) {
            return $found;
        }

        if ( is_array( $found ) && isset( $found['id'] ) ) {
            $customer_id = (int) $found['id'];
            update_user_meta( $user_id, '_ah_uscreen_customer_id', $customer_id );
            return $customer_id;
        }

        // Not found → create
        $created = self::create_customer( $mapper );
        if ( is_wp_error( $created ) ) {
            return $created;
        }

        update_user_meta( $user_id, '_ah_uscreen_customer_id', $created );

        return $created;
    }


    /**
     * Assign access (offer).
     *
     * @param int    $customer_id
     * @param string $offer_id
     * @return array|WP_Error
     */
    public static function assign_access( $customer_id, $offer_id ) {

        $payload = array(
            'product_id'   => (int) $offer_id,
            'product_type' => 'offer',

            // Important: marks access as created by Publisher API
            // Uscreen handles origin = assigned_from_publisher_api automatically,
            // so metadata is optional, but we can still enhance clarity.
            'metadata' => array(
                'origin' => 'woocommerce',
            ),
        );

        $endpoint = "customers/{$customer_id}/accesses";

        $response = AH_Uscreen_Client::request(
            'POST',
            $endpoint,
            array( 'body' => $payload )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code > 299 ) {
            return new WP_Error(
                'uscreen_assign_failed',
                'Failed to assign access: ' . wp_json_encode( $body )
            );
        }

        return $body;
    }


    /**
     * List all accesses for a customer.
     *
     * @param int $customer_id
     * @return array|WP_Error
     */
    public static function list_accesses( $customer_id ) {

        $endpoint = "customers/{$customer_id}/accesses";

        $response = AH_Uscreen_Client::request(
            'GET',
            $endpoint
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code > 299 ) {
            return new WP_Error(
                'uscreen_list_accesses_failed',
                'Failed to list accesses: ' . wp_json_encode( $body )
            );
        }

        if ( ! is_array( $body ) ) {
            return array();
        }

        return $body;
    }


    /**
     * Delete a single access entry.
     *
     * @param int $customer_id
     * @param int $access_id
     * @return true|WP_Error
     */
    public static function delete_access( $customer_id, $access_id ) {

        $endpoint = "customers/{$customer_id}/accesses/{$access_id}";

        $response = AH_Uscreen_Client::request(
            'DELETE',
            $endpoint
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code < 200 || $code > 299 ) {
            return new WP_Error(
                'uscreen_delete_access_failed',
                "Failed to delete access {$access_id} for customer {$customer_id}"
            );
        }

        return true;
    }


    /**
     * SMART revocation:
     * Remove ONLY accesses created from WooCommerce via Publisher API.
     *
     * Rule: access.origin === "assigned_from_publisher_api"
     *
     * @param int $customer_id
     * @return void
     */
    public static function revoke_wc_accesses( $customer_id ) {

        $accesses = self::list_accesses( $customer_id );

        if ( is_wp_error( $accesses ) ) {
            AH_Uscreen_Client::log(
                'Unable to list accesses for revocation: ' . $accesses->get_error_message(),
                'error'
            );
            return;
        }

        foreach ( $accesses as $access ) {

            // Safeguard: ensure structure
            if ( ! isset( $access['id'], $access['origin'] ) ) {
                continue;
            }

            // Only revoke WooCommerce-provisioned access
            if ( isset( $access['origin'] ) && in_array( $access['origin'], [ 'assigned_from_publisher_api', 'automatically_assigned' ], true ) ) {

                AH_Uscreen_Client::log(
                    "Revoking WC access {$access['id']} for customer {$customer_id}",
                    'info'
                );

                self::delete_access( $customer_id, $access['id'] );
            }
        }
    }


    /**
     * Generate SSO tokenized URL.
     *
     * @param int $customer_id
     * @return string|WP_Error
     */
    public static function generate_tokenized_url( $customer_id ) {

        $response = AH_Uscreen_Client::request(
            'POST',
            '/customers/' . $customer_id . '/tokenized_url'            
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code > 299 ) {
            return new WP_Error(
                'uscreen_sso_failed',
                'Failed to generate SSO URL: ' . wp_json_encode( $body )
            );
        }

        if ( isset( $body['url'] ) ) {
            return $body['url'];
        }

        return new WP_Error(
            'uscreen_missing_sso_url',
            'Uscreen did not return a tokenized URL'
        );
    }

    /**
     * Check if customer already has WC-provisioned access in Uscreen.
     *
     * Rule: any access with origin === "assigned_from_publisher_api" or "automatically_assigned".
     *
     * @param int $customer_id
     * @return bool|WP_Error
     */
    public static function customer_has_wc_access( $customer_id ) {

        $accesses = self::list_accesses( $customer_id );

        if ( is_wp_error( $accesses ) ) {
            return $accesses;
        }

        foreach ( $accesses as $access ) {
            if ( isset( $access['origin'] ) && in_array( $access['origin'], [ 'assigned_from_publisher_api', 'automatically_assigned' ], true ) ) {
                return true;
            }
        }

        return false;
    }


}
