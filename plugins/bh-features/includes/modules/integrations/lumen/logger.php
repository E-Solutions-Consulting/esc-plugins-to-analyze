<?php
/**
 * Lumen Integration - Logger
 *
 * @package BH_Features
 * @subpackage Integrations/Lumen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'AH_Lumen_Logger' ) ) {
    return;
}

class AH_Lumen_Logger {

    const CHANNEL = 'lumen';

    /**
     * Write a log entry if logging is enabled.
     *
     * @param string $message
     * @param array  $context
     * @param string $level    debug|info|notice|warning|error|critical
     */
    public static function log( $message, $context = [], $level = 'info' ) {
        if ( AH_Lumen_Config::get( 'logging_enabled' ) !== 'yes' ) {
            return;
        }

        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        wc_get_logger()->log(
            $level,
            $message,
            array_merge( $context, [ 'source' => self::CHANNEL ] )
        );
    }

    public static function error( $message, $context = [] ) {
        self::log( $message, $context, 'error' );
    }

    public static function debug( $message, $context = [] ) {
        self::log( $message, $context, 'debug' );
    }
}
