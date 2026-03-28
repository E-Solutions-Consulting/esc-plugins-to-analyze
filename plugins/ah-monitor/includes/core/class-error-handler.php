<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Error_Handler {

    /**
     * Register global error handlers.
     */
    public static function init() {

        set_exception_handler( [ __CLASS__, 'handle_exception' ] );
        set_error_handler( [ __CLASS__, 'handle_error' ] );
        register_shutdown_function( [ __CLASS__, 'handle_shutdown' ] );

    }

    /**
     * Handle uncaught exceptions.
     */
    public static function handle_exception( $exception ) {

        error_log( '[AH Monitor Exception] ' . $exception->getMessage() );

    }

    /**
     * Handle PHP errors.
     */
    public static function handle_error( $severity, $message, $file, $line ) {

        error_log( "[AH Monitor Error] {$message} in {$file}:{$line}" );

    }

    /**
     * Handle fatal shutdown errors.
     */
    public static function handle_shutdown() {

        $error = error_get_last();

        if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {

            error_log( "[AH Monitor Fatal] {$error['message']} in {$error['file']}:{$error['line']}" );

        }

    }

}