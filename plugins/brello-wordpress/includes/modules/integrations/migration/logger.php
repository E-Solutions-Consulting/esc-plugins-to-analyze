<?php
/**
 * Migration module logger.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Logger {

    const SOURCE = 'ah-migration';

    /**
     * Write a log entry.
     *
     * @param string $message
     * @param string $level   One of: debug, info, notice, warning, error, critical.
     * @param array  $context
     * @return void
     */
    public static function log( $message, $level = 'info', array $context = array() ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $context['source'] = self::SOURCE;

        wc_get_logger()->log( $level, $message, $context );
    }
}