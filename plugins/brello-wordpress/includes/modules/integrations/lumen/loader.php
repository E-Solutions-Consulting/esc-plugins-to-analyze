<?php
/**
 * Lumen Integration - Bootstrap Loader
 *
 * Entry point for the Lumen Partners API integration.
 * Loaded by BH_Modules_Loader via safe_require().
 *
 * wp-config.php constants required:
 *   define( 'LUMEN_API_KEY_STAGING',    'sk_...' );
 *   define( 'LUMEN_API_KEY_PRODUCTION', 'sk_...' );
 *
 * @package BH_Features
 * @subpackage Integrations/Lumen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Lumen_Loader {

    public static function init() {
        $base = plugin_dir_path( __FILE__ );

        require_once $base . 'config.php';
        require_once $base . 'logger.php';
        require_once $base . 'api-client.php';
        require_once $base . 'events.php';

        AH_Lumen_Config::init();
        AH_Lumen_Events::init();
        AH_Lumen_Events::maybe_create_table();
    }
}
add_action( 'plugins_loaded', array( 'AH_Lumen_Loader', 'init' ), 100 );
