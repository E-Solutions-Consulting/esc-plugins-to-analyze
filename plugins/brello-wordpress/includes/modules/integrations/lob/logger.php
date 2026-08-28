<?php
/**
 * LOB Integration - Logger
 *
 * Thin wrapper over the WooCommerce logger (WC → Status → Logs, source
 * `bh-lob`). Mirrors the Attentive logger so all integrations log the same way.
 * Respects the "Enable Logging" toggle in the LOB settings.
 *
 * @package    BH_Features
 * @subpackage Integrations/Lob
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_LOB_Logger {

    const SOURCE = 'bh-lob';

    /**
     * Log a message (compact JSON for any structured context).
     *
     * @param string $message
     * @param array  $context
     * @param string $level    'info' | 'error'
     */
    public static function log( $message, $context = [], $level = 'info' ) {

        $settings = BH_LOB_Config::get_settings();

        if ( empty( $settings['logging_enabled'] ) || $settings['logging_enabled'] !== 'yes' ) {
            return;
        }

        if ( ! empty( $context ) ) {
            $message .= ' ' . wp_json_encode( $context );
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            $wc_level = ( strtolower( $level ) === 'error' ) ? 'error' : 'info';
            wc_get_logger()->log( $wc_level, $message, [ 'source' => self::SOURCE ] );
            return;
        }

        error_log( '[BH_LOB] ' . $message );
    }

    /**
     * Convenience error logger.
     *
     * @param string $message
     * @param array  $context
     */
    public static function error( $message, $context = [] ) {
        self::log( $message, $context, 'error' );
    }
}
