<?php
/**
 * Migration status state machine.
 *
 * Single source of truth for the user migration status.
 * Absence of meta means NOT_AVAILABLE (no meta rows written for
 * users that will never migrate).
 *
 * Allowed transitions:
 *   not_available   → available            (bulk tool / admin)
 *   available       → migrating            (Raj's backend, PP-815)
 *   migrating       → migrated | migration_error  (Raj's backend, PP-815)
 *   migration_error → migrating | available (retry via app / admin)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Status {

    const META_KEY          = '_ah_migration_status';
    const META_STARTED_AT   = '_ah_migration_started_at';
    const META_COMPLETED_AT = '_ah_migration_completed_at';
    const META_ERROR        = '_ah_migration_error';

    const NOT_AVAILABLE = 'not_available';
    const AVAILABLE     = 'available';
    const MIGRATING     = 'migrating';
    const MIGRATED      = 'migrated';
    const ERROR         = 'migration_error';

    /**
     * Map of allowed transitions: current status => list of next statuses.
     *
     * @var array
     */
    private static $transitions = array(
        self::NOT_AVAILABLE => array( self::AVAILABLE ),
        self::AVAILABLE     => array( self::MIGRATING, self::NOT_AVAILABLE ),
        self::MIGRATING     => array( self::MIGRATED, self::ERROR ),
        self::ERROR         => array( self::MIGRATING, self::AVAILABLE ),
        self::MIGRATED      => array(),
    );

    /**
     * Get all valid status values.
     *
     * @return array
     */
    public static function get_valid_statuses() {
        return array(
            self::NOT_AVAILABLE,
            self::AVAILABLE,
            self::MIGRATING,
            self::MIGRATED,
            self::ERROR,
        );
    }

    /**
     * Get the current migration status for a user.
     *
     * @param int $user_id
     * @return string
     */
    public static function get( $user_id ) {
        $status = get_user_meta( $user_id, self::META_KEY, true );

        if ( empty( $status ) || ! in_array( $status, self::get_valid_statuses(), true ) ) {
            return self::NOT_AVAILABLE;
        }

        return $status;
    }

    /**
     * Whether the user has been migrated.
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_migrated( $user_id ) {
        return self::MIGRATED === self::get( $user_id );
    }

    /**
     * Whether the user is blocked from using WooCommerce.
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_blocked( $user_id ) {
        return in_array( self::get( $user_id ), array( self::MIGRATING, self::MIGRATED ), true );
    }

    /**
     * Whether a transition from the user's current status to $new_status is allowed.
     *
     * @param int    $user_id
     * @param string $new_status
     * @return bool
     */
    public static function can_transition( $user_id, $new_status ) {
        $current = self::get( $user_id );

        if ( ! isset( self::$transitions[ $current ] ) ) {
            return false;
        }

        return in_array( $new_status, self::$transitions[ $current ], true );
    }

    /**
     * Transition a user to a new status, enforcing the state machine.
     *
     * @param int    $user_id
     * @param string $new_status
     * @param string $error_message Optional, stored when transitioning to ERROR.
     * @return true|WP_Error
     */
    public static function transition( $user_id, $new_status, $error_message = '' ) {
        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new WP_Error( 'invalid_customer', 'Customer not found.', array( 'status' => 404 ) );
        }

        if ( ! in_array( $new_status, self::get_valid_statuses(), true ) ) {
            return new WP_Error( 'invalid_status', 'Invalid status value.', array( 'status' => 400 ) );
        }

        $current = self::get( $user_id );

        if ( ! self::can_transition( $user_id, $new_status ) ) {
            return new WP_Error(
                'invalid_transition',
                sprintf( 'Transition from "%s" to "%s" is not allowed.', $current, $new_status ),
                array( 'status' => 409, 'current_status' => $current )
            );
        }

        self::write( $user_id, $new_status, $error_message );

        do_action( 'ah_migration_status_changed', $user_id, $new_status, $current );

        return true;
    }

    /**
     * Force-set a status without transition validation. Admin use only.
     *
     * @param int    $user_id
     * @param string $new_status
     * @return bool
     */
    public static function force_set( $user_id, $new_status ) {
        if ( ! in_array( $new_status, self::get_valid_statuses(), true ) ) {
            return false;
        }

        $current = self::get( $user_id );
        self::write( $user_id, $new_status );

        do_action( 'ah_migration_status_changed', $user_id, $new_status, $current );

        return true;
    }

    /**
     * Persist status and side metas.
     *
     * @param int    $user_id
     * @param string $new_status
     * @param string $error_message
     * @return void
     */
    private static function write( $user_id, $new_status, $error_message = '' ) {
        if ( self::NOT_AVAILABLE === $new_status ) {
            delete_user_meta( $user_id, self::META_KEY );
        } else {
            update_user_meta( $user_id, self::META_KEY, $new_status );
        }

        $now = current_time( 'mysql', true );

        if ( self::MIGRATING === $new_status ) {
            update_user_meta( $user_id, self::META_STARTED_AT, $now );
            delete_user_meta( $user_id, self::META_COMPLETED_AT );
            delete_user_meta( $user_id, self::META_ERROR );
        }

        if ( self::MIGRATED === $new_status ) {
            update_user_meta( $user_id, self::META_COMPLETED_AT, $now );
            delete_user_meta( $user_id, self::META_ERROR );
        }

        if ( self::ERROR === $new_status ) {
            update_user_meta( $user_id, self::META_COMPLETED_AT, $now );
            update_user_meta( $user_id, self::META_ERROR, sanitize_text_field( $error_message ) );
        }

        AH_Migration_Logger::log( sprintf(
            'User #%d status set to "%s"%s',
            $user_id,
            $new_status,
            $error_message ? ' — error: ' . $error_message : ''
        ) );
    }
}