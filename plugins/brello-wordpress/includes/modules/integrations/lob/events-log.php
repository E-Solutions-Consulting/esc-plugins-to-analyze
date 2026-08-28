<?php
/**
 * LOB Events Log
 *
 * Deduplication ledger for LOB mailings, stored in the external DB
 * (bh_external.bh_lob_events) — NOT in wc_orders_meta / postmeta, per the
 * project rule that store flags live in the external database.
 *
 * One row per (object_id, object_type, event_type). Used to guarantee a given
 * subscription only triggers one mail piece per lifecycle status (e.g. a
 * subscription only mails once when it becomes `cancelled`, even if the status
 * is written more than once).
 *
 * @package    BH_Features
 * @subpackage Integrations/Lob
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_LOB_Events_Log {

    const TABLE = 'bh_lob_events';

    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_ORDER        = 'order';

    /**
     * Create the ledger table in the external DB if missing.
     *
     * @return bool
     */
    public static function maybe_create_table(): bool {
        if ( ! class_exists( 'BH_ExtDB' ) || ! BH_ExtDB::is_available() ) {
            error_log( '[BH_LOB_Events_Log] External DB unavailable — cannot create table.' );
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
            `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `object_id`    BIGINT UNSIGNED NOT NULL COMMENT 'wc_orders.id (order or subscription)',
            `object_type`  ENUM('order','subscription') NOT NULL,
            `event_type`   VARCHAR(60) NOT NULL COMMENT 'subscription status, e.g. cancelled',
            `lob_id`       VARCHAR(60) NULL DEFAULT NULL COMMENT 'LOB mail piece id (psc_/ltr_)',
            `status`       VARCHAR(20) NOT NULL DEFAULT 'sent' COMMENT 'sent | failed | skipped',
            `triggered_at` DATETIME NOT NULL,
            `extra`        JSON NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_object_event` (`object_id`, `object_type`, `event_type`),
            KEY `idx_event_type` (`event_type`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return BH_ExtDB::execute( $sql );
    }

    /**
     * Whether a mailing has already been triggered for this object + event.
     *
     * If the external DB is unavailable, returns false (fail open) so a missing
     * DB never silently blocks a mailing — the request-level logs will show it.
     *
     * @param int    $object_id
     * @param string $object_type
     * @param string $event_type
     * @return bool
     */
    public static function was_triggered( int $object_id, string $object_type, string $event_type ): bool {
        if ( ! class_exists( 'BH_ExtDB' ) || ! BH_ExtDB::is_available() ) {
            error_log( "[BH_LOB_Events_Log] External DB unavailable — was_triggered({$object_id}, {$event_type}) defaulting to false." );
            return false;
        }

        // Only a SUCCESSFUL send counts as "already triggered" — a prior failed
        // attempt should be retryable when the status changes again.
        $id = BH_ExtDB::get_var(
            "SELECT id FROM `" . self::TABLE . "` WHERE object_id = ? AND object_type = ? AND event_type = ? AND status = 'sent' LIMIT 1",
            [ $object_id, $object_type, $event_type ]
        );

        return $id !== null;
    }

    /**
     * Record a triggered mailing (idempotent via ON DUPLICATE KEY UPDATE).
     *
     * @param int    $object_id
     * @param string $object_type
     * @param string $event_type
     * @param string $status       sent | failed | skipped
     * @param string|null $lob_id
     * @param array  $extra
     * @return bool
     */
    public static function mark_triggered( int $object_id, string $object_type, string $event_type, string $status = 'sent', ?string $lob_id = null, array $extra = [] ): bool {
        if ( ! class_exists( 'BH_ExtDB' ) || ! BH_ExtDB::is_available() ) {
            error_log( "[BH_LOB_Events_Log] External DB unavailable — mark_triggered({$object_id}, {$event_type}) skipped." );
            return false;
        }

        $now        = current_time( 'mysql', true );
        $extra_json = ! empty( $extra ) ? wp_json_encode( $extra ) : null;

        $result = BH_ExtDB::upsert(
            self::TABLE,
            [
                'object_id'    => $object_id,
                'object_type'  => $object_type,
                'event_type'   => $event_type,
                'lob_id'       => $lob_id,
                'status'       => $status,
                'triggered_at' => $now,
                'extra'        => $extra_json,
            ],
            [
                'lob_id'       => $lob_id,
                'status'       => $status,
                'triggered_at' => $now,
                'extra'        => $extra_json,
            ]
        );

        return $result !== false;
    }

    /**
     * Subscription shortcut for was_triggered().
     */
    public static function sub_was_triggered( int $sub_id, string $event_type ): bool {
        return self::was_triggered( $sub_id, self::TYPE_SUBSCRIPTION, $event_type );
    }

    /**
     * Subscription shortcut for mark_triggered().
     */
    public static function sub_mark_triggered( int $sub_id, string $event_type, string $status = 'sent', ?string $lob_id = null, array $extra = [] ): bool {
        return self::mark_triggered( $sub_id, self::TYPE_SUBSCRIPTION, $event_type, $status, $lob_id, $extra );
    }

    private function __construct() {}
    private function __clone() {}
}
