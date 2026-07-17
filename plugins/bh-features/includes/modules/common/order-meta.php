<?php
/**
 * AH Order Meta
 *
 * Stores per-order tracking flags in bh_external.bh_order_meta instead of
 * wc_orders_meta, keeping the 7.5 GB HPOS table from growing further.
 *
 * API mirrors the shape of BH_ExtDB — all static, fails silently with logging.
 *
 * Table DDL (run once in phpMyAdmin before deploying this file):
 *
 *   ALTER TABLE bh_order_meta
 *     MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 *     ADD PRIMARY KEY (id),
 *     ADD UNIQUE KEY uq_order_meta (order_id, meta_key);
 *
 * Usage:
 *   AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_status', 'success' );
 *   AH_Order_Meta::get( $order_id, '_bh_marketing_subscription_status' );
 *   AH_Order_Meta::get_all( $order_id );
 *   AH_Order_Meta::delete( $order_id, '_bh_marketing_subscription_status' );
 *
 * @package    BH_Features
 * @subpackage Common
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Order_Meta {

    const TABLE = 'bh_order_meta';

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Insert or update a single meta value for an order.
     *
     * Uses ON DUPLICATE KEY UPDATE against the (order_id, meta_key) unique index.
     * Returns true on success, false on failure or when BH_ExtDB is unavailable.
     *
     * @param int    $order_id
     * @param string $meta_key
     * @param mixed  $meta_value  Scalar or array/object (arrays serialized automatically).
     * @return bool
     */
    public static function set( int $order_id, string $meta_key, mixed $meta_value ): bool {
        if ( ! self::is_available() ) {
            return false;
        }

        $value = is_scalar( $meta_value ) ? (string) $meta_value : serialize( $meta_value );

        $result = BH_ExtDB::upsert(
            self::TABLE,
            [
                'order_id'   => $order_id,
                'meta_key'   => $meta_key,
                'meta_value' => $value,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [
                'meta_value' => $value,
                'updated_at' => current_time( 'mysql', true ),
            ]
        );

        return $result !== false;
    }

    /**
     * Retrieve a single meta value for an order.
     *
     * Returns null when no row is found, BH_ExtDB is unavailable, or on error.
     * Serialized values are unserialized automatically.
     *
     * @param int    $order_id
     * @param string $meta_key
     * @return mixed|null
     */
    public static function get( int $order_id, string $meta_key ): mixed {
        if ( ! self::is_available() ) {
            return null;
        }

        $value = BH_ExtDB::get_var(
            'SELECT meta_value FROM ' . self::TABLE . ' WHERE order_id = ? AND meta_key = ? LIMIT 1',
            [ $order_id, $meta_key ]
        );

        if ( $value === null ) {
            return null;
        }

        return self::maybe_unserialize( $value );
    }

    /**
     * Retrieve all meta entries for an order as an associative array.
     *
     * Returns an empty array when no rows are found or on failure.
     * Keys are meta_key strings; values are unserialized.
     *
     * @param int $order_id
     * @return array<string, mixed>
     */
    public static function get_all( int $order_id ): array {
        if ( ! self::is_available() ) {
            return [];
        }

        $rows = BH_ExtDB::query(
            'SELECT meta_key, meta_value FROM ' . self::TABLE . ' WHERE order_id = ?',
            [ $order_id ]
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $result = [];
        foreach ( $rows as $row ) {
            $result[ $row['meta_key'] ] = self::maybe_unserialize( $row['meta_value'] );
        }

        return $result;
    }

    /**
     * Delete a single meta entry for an order.
     *
     * Returns true on success (including when no row existed), false on failure.
     *
     * @param int    $order_id
     * @param string $meta_key
     * @return bool
     */
    public static function delete( int $order_id, string $meta_key ): bool {
        if ( ! self::is_available() ) {
            return false;
        }

        $conn = self::raw_connection();
        if ( $conn === null ) {
            return false;
        }

        $stmt = $conn->prepare(
            'DELETE FROM `' . self::TABLE . '` WHERE `order_id` = ? AND `meta_key` = ?'
        );

        if ( $stmt === false ) {
            error_log( '[AH_Order_Meta] prepare() failed for delete: ' . $conn->error );
            return false;
        }

        $stmt->bind_param( 'is', $order_id, $meta_key );

        if ( ! $stmt->execute() ) {
            error_log( '[AH_Order_Meta] delete execute() failed: ' . $stmt->error );
            $stmt->close();
            return false;
        }

        $stmt->close();

        return true;
    }

    /**
     * Ensure the bh_order_meta table has the required schema.
     *
     * Safe to call on every plugin activation — the ALTER statements use
     * IF NOT EXISTS / conditional checks to avoid errors on repeat runs.
     * Should be called from the plugin activator, not on every request.
     *
     * @return bool  True if table is ready, false if BH_ExtDB is unavailable.
     */
    public static function maybe_create_table(): bool {
        if ( ! BH_ExtDB::is_available() ) {
            error_log( '[AH_Order_Meta] maybe_create_table: BH_ExtDB not available.' );
            return false;
        }

        BH_ExtDB::execute(
            'CREATE TABLE IF NOT EXISTS `bh_order_meta` (
                `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id`   BIGINT UNSIGNED NOT NULL,
                `meta_key`   VARCHAR(191)    NOT NULL,
                `meta_value` LONGTEXT,
                `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_order_meta` (`order_id`, `meta_key`),
                KEY `idx_order_id` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        return true;
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * @return bool
     */
    private static function is_available(): bool {
        return BH_ExtDB::is_available();
    }

    /**
     * Obtain the raw mysqli connection via BH_ExtDB by triggering is_available().
     *
     * This is a controlled escape hatch for operations (DELETE) that BH_ExtDB
     * does not expose directly. It works because BH_ExtDB stores the connection
     * as a static property and a reflection call is the cleanest way to reuse
     * it without duplicating connection logic.
     *
     * @return \mysqli|null
     */
    private static function raw_connection(): ?\mysqli {
        if ( ! BH_ExtDB::is_available() ) {
            return null;
        }

        try {
            $ref  = new ReflectionProperty( BH_ExtDB::class, 'conn' );
            $ref->setAccessible( true );
            return $ref->getValue( null );
        } catch ( ReflectionException $e ) {
            error_log( '[AH_Order_Meta] Could not reflect BH_ExtDB::$conn — ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Unserialize a value only if it is a serialized string.
     *
     * @param string $value
     * @return mixed
     */
    private static function maybe_unserialize( string $value ): mixed {
        if ( is_serialized( $value ) ) {
            return @unserialize( $value );
        }
        return $value;
    }

    private function __construct() {}
    private function __clone() {}
}