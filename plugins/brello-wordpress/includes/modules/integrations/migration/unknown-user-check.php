<?php
/**
 * Unknown user login check.
 *
 * When a login attempt fails because the user does not exist in WordPress
 * and the input looks like an email, the Patient Platform is queried.
 * If PP confirms the email belongs to a platform user, the generic
 * "unknown user" error is replaced with a message linking to the new app.
 *
 * Fail-open: if PP cannot be reached, the native WP error is left untouched.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Unknown_User_Check {

    /**
     * WP error codes that mean the user does not exist.
     *
     * @var array
     */
    private static $unknown_user_codes = array( 'invalid_username', 'invalid_email' );

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_filter( 'authenticate', array( __CLASS__, 'check_unknown_user_against_platform' ), 30, 2 );
    }

    /**
     * Replace the unknown-user error when the email belongs to a PP user.
     *
     * @param WP_User|WP_Error|null $user
     * @param string                $username
     * @return WP_User|WP_Error|null
     */
    public static function check_unknown_user_against_platform( $user, $username ) {
        if ( ! AH_Migration_Config::is_enabled() ) {
            return $user;
        }

        if ( ! is_wp_error( $user ) ) {
            return $user;
        }

        AH_Migration_Logger::log( sprintf( 'Login failed for "%s" with codes: %s', $username, implode( ',', $user->get_error_codes() ) ), 'debug' );

        if ( ! array_intersect( $user->get_error_codes(), self::$unknown_user_codes ) ) {
            AH_Migration_Logger::log( 'Unknown-user check skipped: error is not an unknown-user code.', 'debug' );
            return $user;
        }

        $email = sanitize_email( $username );

        if ( empty( $email ) || ! is_email( $email ) ) {
            AH_Migration_Logger::log( 'Unknown-user check skipped: input is not an email.', 'debug' );
            return $user;
        }

        $lookup = AH_Migration_PP_Client::is_platform_user( $email );

        if ( true !== $lookup ) {
            AH_Migration_Logger::log( sprintf( 'Unknown-user check: no message shown for "%s" (lookup: %s).', $email, var_export( $lookup, true ) ), 'debug' );
            return $user;
        }

        AH_Migration_Logger::log( sprintf( 'Unknown WP login "%s" identified as Patient Platform user — showing redirect message.', $email ) );

        return new WP_Error( 'ah_pp_user', self::get_platform_user_message() );
    }

    /**
     * Message shown to Patient Platform users trying to log in on WC.
     *
     * @return string
     */
    private static function get_platform_user_message() {
        $app_url = AH_Migration_Config::get( 'app_login_url' );
        $message = '<strong>Account found on our new Patient Platform.</strong> An account with this email exists on our new platform.';

        if ( ! empty( $app_url ) ) {
            $message .= ' Please log in at: <a href="' . esc_url( $app_url ) . '">' . esc_html( $app_url ) . '</a>';
        }

        return $message;
    }
}