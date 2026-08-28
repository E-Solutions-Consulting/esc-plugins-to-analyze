<?php
/**
 * Subscription mapper for Uscreen.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Uscreen_Subscription_Mapper {

    /**
     * Map WC_Subscription to Uscreen-ready payload.
     *
     * @param WC_Subscription $subscription
     *
     * @return array {
     *   'email'       => string,
     *   'first_name'  => string,
     *   'last_name'   => string,
     *   'wp_user_id'  => int,
     *   'offer_id'    => string,
     *   'customer_meta'=> array,
     * }
     */
    public static function map_subscription( $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return new WP_Error(
                'invalid_subscription',
                'Mapper received a non-subscription object.'
            );
        }

        $settings = AH_Uscreen_Config::get_settings();
        $offer_id = $settings['offer_id'];

        $user_id  = $subscription->get_user_id();
        $user     = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new WP_Error(
                'missing_user',
                'Subscription has no valid WP user.'
            );
        }

        // Split names (best effort).
        $first_name = $user->first_name ?: '';
        $last_name  = $user->last_name ?: '';

        if ( empty( $first_name ) && empty( $last_name ) ) {
            // Try fallback from billing.
            $first_name = $subscription->get_billing_first_name();
            $last_name  = $subscription->get_billing_last_name();
        }

        $email = $user->user_email;

        // Check if we have stored Uscreen customer ID previously.
        $uscreen_customer_id = get_user_meta( $user_id, '_ah_uscreen_customer_id', true );
        $uscreen_customer_id = $uscreen_customer_id ?: null;

        return array(
            'email'              => $email,
            'first_name'         => $first_name ?: '',
            'last_name'          => $last_name ?: '',
            'wp_user_id'         => $user_id,
            'offer_id'           => $offer_id,
            'existing_customer'  => $uscreen_customer_id,
            'creation_mode'      => $settings['account_creation_mode'],
        );
    }
}
