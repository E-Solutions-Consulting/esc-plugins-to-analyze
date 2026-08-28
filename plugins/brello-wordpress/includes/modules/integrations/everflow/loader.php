<?php
/**
 * Everflow integration module loader.
 *
 * Load order matters: click tracking must register even if S2S sender fails.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dir = plugin_dir_path( __FILE__ );

$everflow_files = [
    'everflow-helper.php',       // constants + TID helper (required first)
    'everflow-tracking.php',     // EF.click — load before S2S so clicks survive sender errors
    'everflow-conversion.php',   // thank-you Event 2
    'everflow-funnel.php',       // Event 8 Begin Checkout (Woo checkout JS)
    'everflow-async-sender.php', // S2S events 5/6/8/9/Base
];

foreach ( $everflow_files as $file ) {
    $path = $dir . $file;
    if ( ! file_exists( $path ) ) {
        error_log( '[BH Everflow] Missing file: ' . $path );
        continue;
    }
    try {
        require_once $path;
    } catch ( Throwable $e ) {
        error_log( '[BH Everflow] Failed loading ' . $file . ': ' . $e->getMessage() );
    }
}
