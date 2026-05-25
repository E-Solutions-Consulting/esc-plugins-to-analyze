<?php
/**
 * Lumen Integration - Order Events
 *
 * Async flow:
 *   1. woocommerce_order_status_completed → enqueue AS job (non-blocking)
 *   2. AS job → send report to Lumen API
 *   3. On retryable failure (timeout, 5xx) → re-enqueue with backoff
 *   4. On hard failure (4xx except 409) → log + order note, no retry
 *
 * Retryable:   cURL errors, HTTP 5xx, HTTP 429
 * Not retried: HTTP 400, 401, 409 (data/auth errors won't be fixed by retrying)
 *
 * @package BH_Features
 * @subpackage Integrations/Lumen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'AH_Lumen_Events' ) ) {
    return;
}

class AH_Lumen_Events {

    const TABLE     = 'lumen_purchase_log';
    const AS_HOOK   = 'ah_lumen_report_purchase';

    public static function init() {
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'on_order_completed' ], 20, 1 );
        add_action( self::AS_HOOK, [ __CLASS__, 'process_report' ], 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Step 1 — Enqueue async job (runs synchronously on order completion)
    // -------------------------------------------------------------------------

    /**
     * @param int $order_id
     */
    public static function on_order_completed( $order_id ) {

        if ( ! AH_Lumen_Config::is_configured() ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $matched_product_id = self::get_matching_product_id( $order );

        if ( ! $matched_product_id ) {
            return;
        }

        if ( self::already_reported( $order_id ) ) {
            AH_Lumen_Logger::log( 'Skipping — already reported', [ 'order_id' => $order_id ] );
            return;
        }

        $existing = as_get_scheduled_actions( [
            'hook'   => self::AS_HOOK,
            'args'   => [ 'order_id' => $order_id, 'attempt' => 1 ],
            'status' => \ActionScheduler_Store::STATUS_PENDING,
        ], 'ids' );

        if ( ! empty( $existing ) ) {
            AH_Lumen_Logger::log( 'Job already queued', [ 'order_id' => $order_id ] );
            return;
        }

        $scheduled_at = time() + DAY_IN_SECONDS;

        as_schedule_single_action( $scheduled_at, self::AS_HOOK, [
            'order_id' => $order_id,
            'attempt'  => 1,
        ] );

        AH_Lumen_Logger::log( 'Purchase report job scheduled for 24h', [
            'order_id'      => $order_id,
            'wc_product_id' => $matched_product_id,
            'scheduled_at'  => gmdate( 'Y-m-d H:i:s', $scheduled_at ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Step 2 — Process report (runs in background via Action Scheduler)
    // -------------------------------------------------------------------------

    /**
     * @param int $order_id
     * @param int $attempt   1-based attempt number
     */
    public static function process_report( $order_id, $attempt = 1 ) {

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            AH_Lumen_Logger::error( 'Order not found in process_report', [ 'order_id' => $order_id ] );
            return;
        }

        // Only report if order is still completed — it may have been cancelled/refunded
        // during the 24h window or between retry attempts.
        if ( $order->get_status() !== 'completed' ) {
            AH_Lumen_Logger::log( 'Order no longer completed — skipping report', [
                'order_id' => $order_id,
                'status'   => $order->get_status(),
            ] );
            $order->add_order_note( sprintf(
                'Lumen: purchase report skipped — order status changed to "%s" before report was sent.',
                $order->get_status()
            ) );
            return;
        }

        $matched_product_id = self::get_matching_product_id( $order );

        if ( ! $matched_product_id ) {
            AH_Lumen_Logger::error( 'No Lumen product found in order during retry', [ 'order_id' => $order_id ] );
            return;
        }

        if ( self::already_reported( $order_id ) ) {
            AH_Lumen_Logger::log( 'Skipping — already reported (caught in process_report)', [ 'order_id' => $order_id ] );
            return;
        }

        AH_Lumen_Logger::log( 'Sending purchase report', [
            'order_id' => $order_id,
            'attempt'  => $attempt,
        ] );

        $result = AH_Lumen_API_Client::report_purchase( $order );

        if ( $result['success'] ) {
            self::save_log( $order_id, $matched_product_id, $result, $attempt );
            $order->add_order_note( sprintf(
                'Lumen: purchase reported successfully (attempt %d). Lumen ID: %s',
                $attempt,
                $result['body']['data']['id'] ?? 'n/a'
            ), false, true );
            return;
        }

        $http_code = $result['http_code'];

        // 409 — Lumen already has it, treat as success
        if ( $http_code === 409 ) {
            self::save_log( $order_id, $matched_product_id, $result, $attempt );
            $order->add_order_note( 'Lumen: duplicate purchase (409) — already registered in Lumen.' );
            return;
        }

        // Retryable: timeout (http_code 0), 5xx, 429
        if ( self::is_retryable( $http_code ) ) {
            $max_retries = AH_Lumen_Config::get_max_retries();

            if ( $attempt < $max_retries ) {
                $delay = self::get_delay_for_attempt( $attempt );
                as_schedule_single_action( time() + $delay, self::AS_HOOK, [
                    'order_id' => $order_id,
                    'attempt'  => $attempt + 1,
                ] );

                $order->add_order_note( sprintf(
                    'Lumen: attempt %d failed (HTTP %d: %s). Retry %d of %d scheduled in %d min.',
                    $attempt,
                    $http_code,
                    $result['error'],
                    $attempt + 1,
                    $max_retries,
                    (int) ( $delay / 60 )
                ) );

                AH_Lumen_Logger::log( 'Retry scheduled', [
                    'order_id'   => $order_id,
                    'attempt'    => $attempt,
                    'next_in'    => $delay . 's',
                    'http_code'  => $http_code,
                ] );

            } else {
                // Max retries reached
                self::save_log( $order_id, $matched_product_id, $result, $attempt );
                $order->add_order_note( sprintf(
                    'Lumen: purchase report FAILED after %d attempts (HTTP %d: %s). Manual review required.',
                    $attempt,
                    $http_code,
                    $result['error']
                ) );
                AH_Lumen_Logger::error( 'Max retries reached', [
                    'order_id'  => $order_id,
                    'attempt'   => $attempt,
                    'http_code' => $http_code,
                    'error'     => $result['error'],
                ] );
            }

            return;
        }

        // Hard failure — 4xx (not 409), do not retry
        self::save_log( $order_id, $matched_product_id, $result, $attempt );
        $order->add_order_note( sprintf(
            'Lumen: purchase report FAILED (HTTP %d: %s). Not retrying.',
            $http_code,
            $result['error']
        ) );
        AH_Lumen_Logger::error( 'Hard failure — not retrying', [
            'order_id'  => $order_id,
            'http_code' => $http_code,
            'error'     => $result['error'],
        ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param int $http_code
     * @return bool
     */
    private static function is_retryable( int $http_code ): bool {
        return $http_code === 0       // cURL timeout or connection error
            || $http_code === 429     // rate limited
            || ( $http_code >= 500 && $http_code < 600 ); // server error
    }

    /**
     * Return delay in seconds for a given attempt number.
     * Uses configured delays, repeating the last value if attempts exceed the list.
     *
     * @param int $attempt  1-based (attempt 1 failed → schedule attempt 2 with delay index 0)
     * @return int seconds
     */
    private static function get_delay_for_attempt( int $attempt ): int {
        $delays = AH_Lumen_Config::get_retry_delays();

        if ( empty( $delays ) ) {
            return 5 * 60; // fallback: 5 minutes
        }

        $index = $attempt - 1; // attempt 1 failed → use delays[0]
        return $delays[ min( $index, count( $delays ) - 1 ) ];
    }

    /**
     * Check if the order contains any of the configured Lumen WC products.
     * Returns the matching WC product ID, or null if none found.
     *
     * @param WC_Order $order
     * @return int|null
     */
    private static function get_matching_product_id( WC_Order $order ) {
        $trigger_ids = AH_Lumen_Config::get_wc_product_ids();

        if ( empty( $trigger_ids ) ) {
            return null;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id   = (int) $item->get_product_id();
            $variation_id = (int) $item->get_variation_id();

            if ( in_array( $product_id, $trigger_ids, true ) ) {
                return $product_id;
            }

            if ( $variation_id && in_array( $variation_id, $trigger_ids, true ) ) {
                return $variation_id;
            }
        }

        return null;
    }

    /**
     * @param int $order_id
     * @return bool
     */
    private static function already_reported( int $order_id ): bool {
        if ( ! BH_ExtDB::is_available() ) {
            return false;
        }

        $result = BH_ExtDB::get_var(
            "SELECT id FROM `" . self::TABLE . "` WHERE order_id = ? AND status = 'success' LIMIT 1",
            [ $order_id ]
        );

        return $result !== null;
    }

    /**
     * @param int   $order_id
     * @param int   $wc_product_id
     * @param array $result
     * @param int   $attempt
     */
    private static function save_log( int $order_id, int $wc_product_id, array $result, int $attempt ): void {
        if ( ! BH_ExtDB::is_available() ) {
            AH_Lumen_Logger::error( 'External DB unavailable — log not saved', [ 'order_id' => $order_id ] );
            return;
        }

        $lumen_purchase_id = $result['body']['data']['id'] ?? null;
        $status            = $result['success'] ? 'success' : 'error';

        BH_ExtDB::upsert(
            self::TABLE,
            [
                'order_id'          => $order_id,
                'wc_product_id'     => $wc_product_id,
                'lumen_purchase_id' => $lumen_purchase_id,
                'status'            => $status,
                'http_code'         => $result['http_code'],
                'attempts'          => $attempt,
                'response_body'     => wp_json_encode( $result['body'] ),
                'mode'              => AH_Lumen_Config::get( 'mode', 'staging' ),
                'created_at'        => current_time( 'mysql' ),
            ],
            [
                'lumen_purchase_id' => $lumen_purchase_id,
                'status'            => $status,
                'http_code'         => $result['http_code'],
                'attempts'          => $attempt,
                'response_body'     => wp_json_encode( $result['body'] ),
                'mode'              => AH_Lumen_Config::get( 'mode', 'staging' ),
            ]
        );
    }

    // -------------------------------------------------------------------------
    // DB schema
    // -------------------------------------------------------------------------

    public static function maybe_create_table(): void {
        if ( ! BH_ExtDB::is_available() ) {
            error_log( '[AH_Lumen_Events] External DB not available — cannot create table.' );
            return;
        }

        BH_ExtDB::execute( "
            CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
                `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id`          BIGINT UNSIGNED NOT NULL,
                `wc_product_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `lumen_purchase_id` VARCHAR(64)     DEFAULT NULL,
                `status`            VARCHAR(16)     NOT NULL DEFAULT 'pending',
                `http_code`         SMALLINT        NOT NULL DEFAULT 0,
                `attempts`          TINYINT         NOT NULL DEFAULT 1,
                `response_body`     TEXT            DEFAULT NULL,
                `mode`              VARCHAR(16)     NOT NULL DEFAULT 'staging',
                `created_at`        DATETIME        NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_order_id` (`order_id`),
                INDEX `ix_wc_product` (`wc_product_id`),
                INDEX `ix_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        " );
    }
}