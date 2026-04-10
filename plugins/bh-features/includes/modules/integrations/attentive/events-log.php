<?php
/**
 * BH Attentive Events Log
 *
 * Tracks all Attentive events in the external DB (bh_external).
 * Replaces order/subscription metas used for deduplication:
 *
 *   _attentive_payment_failed_sent
 *   _attentive_payment_failed_time
 *   _attentive_payment_failed_reason
 *   _attentive_payment_recovered_sent
 *   _attentive_card_expiring_notified_{year}{month}
 *   _attentive_questionnaire_triggered
 *
 * Table: bh_attentive_events (in bh_external DB)
 *
 * Usage:
 *   // Check before sending
 *   if ( BH_Attentive_Events_Log::was_triggered( $order_id, 'order', 'payment_failed' ) ) return;
 *
 *   // Record after sending
 *   BH_Attentive_Events_Log::mark_triggered( $order_id, 'order', 'payment_failed', [
 *       'failure_reason' => 'card_declined',
 *   ]);
 *
 *   // Resolve when no longer active
 *   BH_Attentive_Events_Log::mark_resolved( $order_id, 'order', 'payment_failed', 'recovered' );
 *
 * @package    BH_Features
 * @subpackage Integrations/Attentive
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Events_Log {

    /**
     * Table name in bh_external DB (no prefix — external DB is dedicated).
     */
    const TABLE = 'bh_attentive_events';

    /**
     * Valid object types — maps to wc_orders.type values.
     */
    const TYPE_ORDER        = 'order';
    const TYPE_SUBSCRIPTION = 'subscription';

    /**
     * Event types — one constant per use case.
     * Add new ones here as new modules adopt this class.
     */
    const EVENT_PAYMENT_FAILED        = 'payment_failed';
    const EVENT_PAYMENT_RECOVERED     = 'payment_recovered';
    const EVENT_CARD_EXPIRING         = 'card_expiring';
    const EVENT_QUESTIONNAIRE_PENDING = 'questionnaire_pending';

    /**
     * Resolved reason values.
     */
    const REASON_COMPLETED  = 'completed';
    const REASON_CANCELLED  = 'cancelled';
    const REASON_RECOVERED  = 'recovered';
    const REASON_EXPIRED    = 'expired';

    // =========================================================================
    // TABLE MANAGEMENT
    // =========================================================================

    /**
     * Create the table in the external DB if it does not exist.
     * Call this from your module's loader, after BH_ExtDB is initialized.
     *
     * @return bool
     */
    public static function maybe_create_table(): bool {
        if ( ! BH_ExtDB::is_available() ) {
            error_log( '[BH_Attentive_Events_Log] External DB not available — cannot create table.' );
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
            `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `object_id`       BIGINT UNSIGNED NOT NULL COMMENT 'wc_orders.id',
            `object_type`     ENUM('order','subscription') NOT NULL,
            `event_type`      VARCHAR(60) NOT NULL,
            `triggered_at`    DATETIME NOT NULL,
            `resolved_at`     DATETIME NULL DEFAULT NULL,
            `resolved_reason` VARCHAR(30) NULL DEFAULT NULL,
            `extra`           JSON NULL DEFAULT NULL COMMENT 'Event-specific metadata',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_object_event` (`object_id`, `object_type`, `event_type`),
            KEY `idx_event_type` (`event_type`),
            KEY `idx_triggered_at` (`triggered_at`),
            KEY `idx_resolved_at` (`resolved_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return BH_ExtDB::execute( $sql );
    }

    // =========================================================================
    // CORE API
    // =========================================================================

    /**
     * Check if an event has already been triggered for this object.
     * Use this BEFORE sending an event to Attentive to prevent duplicates.
     *
     * @param int    $object_id    WC order or subscription ID.
     * @param string $object_type  Use class constants: TYPE_ORDER, TYPE_SUBSCRIPTION.
     * @param string $event_type   Use class constants: EVENT_PAYMENT_FAILED, etc.
     * @return bool  True if already triggered (skip sending). False if safe to send.
     */
    public static function was_triggered( int $object_id, string $object_type, string $event_type ): bool {
        if ( ! BH_ExtDB::is_available() ) {
            // If external DB is down, fall back to "not triggered" to avoid silent failures.
            // The event may be sent twice in this edge case, which is safer than never sending.
            error_log( "[BH_Attentive_Events_Log] External DB unavailable — was_triggered({$object_id}, {$event_type}) defaulting to false." );
            return false;
        }

        $result = BH_ExtDB::get_var(
            "SELECT id FROM `" . self::TABLE . "` WHERE object_id = ? AND object_type = ? AND event_type = ? LIMIT 1",
            [ $object_id, $object_type, $event_type ]
        );

        return $result !== null;
    }

    /**
     * Check if an event is currently active (triggered but not yet resolved).
     * Useful for recovery logic: "was there a payment_failed that hasn't been recovered yet?"
     *
     * @param int    $object_id
     * @param string $object_type
     * @param string $event_type
     * @return bool
     */
    public static function is_active( int $object_id, string $object_type, string $event_type ): bool {
        if ( ! BH_ExtDB::is_available() ) {
            return false;
        }

        $result = BH_ExtDB::get_var(
            "SELECT id FROM `" . self::TABLE . "` WHERE object_id = ? AND object_type = ? AND event_type = ? AND resolved_at IS NULL LIMIT 1",
            [ $object_id, $object_type, $event_type ]
        );

        return $result !== null;
    }

    /**
     * Record that an event was triggered.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE so it's safe to call even
     * if the record already exists (idempotent).
     *
     * @param int    $object_id
     * @param string $object_type
     * @param string $event_type
     * @param array  $extra       Optional associative array stored as JSON.
     *                            Examples:
     *                              payment_failed:        [ 'failure_reason' => 'card_declined' ]
     *                              card_expiring:         [ 'exp_month' => '03', 'exp_year' => '2026' ]
     *                              questionnaire_pending: [ 'questionnaires_total' => 2, 'questionnaires_valid' => 0 ]
     * @return bool
     */
    public static function mark_triggered( int $object_id, string $object_type, string $event_type, array $extra = [] ): bool {
        if ( ! BH_ExtDB::is_available() ) {
            error_log( "[BH_Attentive_Events_Log] External DB unavailable — mark_triggered({$object_id}, {$event_type}) skipped." );
            return false;
        }

        $now        = current_time( 'mysql' );
        $extra_json = ! empty( $extra ) ? wp_json_encode( $extra ) : null;

        $result = BH_ExtDB::upsert(
            self::TABLE,
            [
                'object_id'    => $object_id,
                'object_type'  => $object_type,
                'event_type'   => $event_type,
                'triggered_at' => $now,
                'extra'        => $extra_json,
            ],
            [
                // On duplicate: update triggered_at and extra, but keep original record.
                // This handles the edge case where the same event fires twice.
                'triggered_at' => $now,
                'extra'        => $extra_json,
            ]
        );

        return $result !== false;
    }

    /**
     * Mark an event as resolved.
     * This stops Attentive journeys that check the active state.
     *
     * @param int    $object_id
     * @param string $object_type
     * @param string $event_type
     * @param string $reason  Use class constants: REASON_COMPLETED, REASON_CANCELLED, etc.
     * @return bool
     */
    public static function mark_resolved( int $object_id, string $object_type, string $event_type, string $reason ): bool {
        if ( ! BH_ExtDB::is_available() ) {
            error_log( "[BH_Attentive_Events_Log] External DB unavailable — mark_resolved({$object_id}, {$event_type}) skipped." );
            return false;
        }

        $result = BH_ExtDB::update(
            self::TABLE,
            [
                'resolved_at'     => current_time( 'mysql' ),
                'resolved_reason' => $reason,
            ],
            [
                'object_id'   => $object_id,
                'object_type' => $object_type,
                'event_type'  => $event_type,
            ]
        );

        // affected_rows = 0 means no row existed — not a failure per se,
        // but worth logging for debugging.
        if ( $result === 0 ) {
            error_log( "[BH_Attentive_Events_Log] mark_resolved({$object_id}, {$event_type}, {$reason}) — no row found to resolve." );
        }

        return $result !== false;
    }

    /**
     * Get the full event row for an object + event type combination.
     * Useful when you need to read extra data (e.g. original failure reason for recovery event).
     *
     * @param int    $object_id
     * @param string $object_type
     * @param string $event_type
     * @return array|null  Associative array with all columns, or null if not found.
     *                     'extra' is returned as decoded array (not raw JSON string).
     */
    public static function get_event( int $object_id, string $object_type, string $event_type ): ?array {
        if ( ! BH_ExtDB::is_available() ) {
            return null;
        }

        $row = BH_ExtDB::get_row(
            "SELECT * FROM `" . self::TABLE . "` WHERE object_id = ? AND object_type = ? AND event_type = ? LIMIT 1",
            [ $object_id, $object_type, $event_type ]
        );

        if ( $row === null ) {
            return null;
        }

        // Decode JSON extra field automatically.
        if ( ! empty( $row['extra'] ) ) {
            $row['extra'] = json_decode( $row['extra'], true ) ?? [];
        } else {
            $row['extra'] = [];
        }

        return $row;
    }

    /**
     * Get all active (unresolved) events for a specific object.
     * Useful for debugging or admin tools.
     *
     * @param int    $object_id
     * @param string $object_type
     * @return array[]
     */
    public static function get_active_events( int $object_id, string $object_type ): array {
        if ( ! BH_ExtDB::is_available() ) {
            return [];
        }

        $rows = BH_ExtDB::query(
            "SELECT * FROM `" . self::TABLE . "` WHERE object_id = ? AND object_type = ? AND resolved_at IS NULL",
            [ $object_id, $object_type ]
        );

        return array_map( function( $row ) {
            if ( ! empty( $row['extra'] ) ) {
                $row['extra'] = json_decode( $row['extra'], true ) ?? [];
            } else {
                $row['extra'] = [];
            }
            return $row;
        }, $rows );
    }

    // =========================================================================
    // CONVENIENCE SHORTCUTS
    // Thin wrappers that pass the object_type automatically.
    // Keeps call sites cleaner when you already know the type.
    // =========================================================================

    /**
     * Shortcut for order events.
     *
     * BH_Attentive_Events_Log::order_was_triggered( $order_id, 'payment_failed' )
     */
    public static function order_was_triggered( int $order_id, string $event_type ): bool {
        return self::was_triggered( $order_id, self::TYPE_ORDER, $event_type );
    }

    public static function order_is_active( int $order_id, string $event_type ): bool {
        return self::is_active( $order_id, self::TYPE_ORDER, $event_type );
    }

    public static function order_mark_triggered( int $order_id, string $event_type, array $extra = [] ): bool {
        return self::mark_triggered( $order_id, self::TYPE_ORDER, $event_type, $extra );
    }

    public static function order_mark_resolved( int $order_id, string $event_type, string $reason ): bool {
        return self::mark_resolved( $order_id, self::TYPE_ORDER, $event_type, $reason );
    }

    public static function order_get_event( int $order_id, string $event_type ): ?array {
        return self::get_event( $order_id, self::TYPE_ORDER, $event_type );
    }

    /**
     * Shortcut for subscription events.
     *
     * BH_Attentive_Events_Log::sub_was_triggered( $sub_id, 'card_expiring' )
     */
    public static function sub_was_triggered( int $sub_id, string $event_type ): bool {
        return self::was_triggered( $sub_id, self::TYPE_SUBSCRIPTION, $event_type );
    }

    public static function sub_is_active( int $sub_id, string $event_type ): bool {
        return self::is_active( $sub_id, self::TYPE_SUBSCRIPTION, $event_type );
    }

    public static function sub_mark_triggered( int $sub_id, string $event_type, array $extra = [] ): bool {
        return self::mark_triggered( $sub_id, self::TYPE_SUBSCRIPTION, $event_type, $extra );
    }

    public static function sub_mark_resolved( int $sub_id, string $event_type, string $reason ): bool {
        return self::mark_resolved( $sub_id, self::TYPE_SUBSCRIPTION, $event_type, $reason );
    }

    public static function sub_get_event( int $sub_id, string $event_type ): ?array {
        return self::get_event( $sub_id, self::TYPE_SUBSCRIPTION, $event_type );
    }

    // =========================================================================
    // PREVENT INSTANTIATION
    // =========================================================================

    private function __construct() {}
    private function __clone() {}
}