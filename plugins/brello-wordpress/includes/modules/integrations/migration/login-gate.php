<?php
/**
 * Migration login gate.
 *
 * The opt-in button lives in the Brello app (PP-814) and the migration is
 * triggered by the backend (PP-815), so WooCommerce only blocks access:
 *
 * migrating        → login rejected, active sessions force-logged-out
 * migrated         → login permanently rejected, message links to the app
 * migration_error  → access restored, no blocking (per PP-819)
 *
 * Users with manage_woocommerce are never blocked.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Login_Gate {

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_login_for_blocked_statuses' ), 10, 1 );
        add_action( 'template_redirect', array( __CLASS__, 'force_logout_blocked_sessions' ), 1 );
        add_action( 'ah_migration_status_changed', array( __CLASS__, 'destroy_sessions_on_block' ), 10, 2 );
    }

    /**
     * Destroy all sessions the moment a user enters a blocked status,
     * regardless of who wrote it (REST API, admin override, CLI).
     *
     * @param int    $user_id
     * @param string $new_status
     * @return void
     */
    public static function destroy_sessions_on_block( $user_id, $new_status ) {
        if ( ! in_array( $new_status, array( AH_Migration_Status::MIGRATING, AH_Migration_Status::MIGRATED ), true ) ) {
            return;
        }

        WP_Session_Tokens::get_instance( $user_id )->destroy_all();

        AH_Migration_Logger::log( sprintf( 'Destroyed all sessions for user #%d on transition to "%s".', $user_id, $new_status ) );
    }

    /**
     * Reject authentication for users in a blocked migration status.
     *
     * @param WP_User|WP_Error $user
     * @return WP_User|WP_Error
     */
    public static function block_login_for_blocked_statuses( $user ) {
        if ( ! AH_Migration_Config::is_enabled() ) {
            return $user;
        }

        if ( ! $user instanceof WP_User ) {
            return $user;
        }

        if ( user_can( $user, 'manage_woocommerce' ) ) {
            return $user;
        }

        $status = AH_Migration_Status::get( $user->ID );

        if ( AH_Migration_Status::MIGRATED === $status ) {
            return new WP_Error( 'ah_migrated', self::get_blocked_login_message( AH_Migration_Status::MIGRATED ) );
        }

        if ( AH_Migration_Status::MIGRATING === $status ) {
            return new WP_Error( 'ah_migrating', self::get_blocked_login_message( AH_Migration_Status::MIGRATING ) );
        }

        return $user;
    }

    /**
     * Safety net: log out any active session whose user became blocked
     * after logging in (e.g. migration started while session was open).
     *
     * @return void
     */
    public static function force_logout_blocked_sessions() {
        if ( ! AH_Migration_Config::is_enabled() ) {
            return;
        }

        if ( ! is_user_logged_in() || current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! AH_Migration_Status::is_blocked( $user_id ) ) {
            return;
        }

        WP_Session_Tokens::get_instance( $user_id )->destroy_all();
        wp_logout();

        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    /**
     * Message shown on the login form for blocked statuses.
     *
     * @param string $status
     * @return string
     */
    private static function get_blocked_login_message( $status ) {
        $message = esc_html( AH_Migration_Config::get( 'blocked_message' ) );

        if ( AH_Migration_Status::MIGRATED === $status ) {
            $app_url = AH_Migration_Config::get( 'app_login_url' );

            if ( ! empty( $app_url ) ) {
                $message .= ' Please log in at: <a href="' . esc_url( $app_url ) . '">' . esc_html( $app_url ) . '</a>';
            }
        }

        return $message;
    }
}