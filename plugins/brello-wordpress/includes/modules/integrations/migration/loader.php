<?php
/**
 * Patient Platform migration module loader.
 *
 * WooCommerce is the source system for a controlled migration to the new
 * Patient Platform. The external system reads all customer data through the
 * WC REST API and reports the result back through this module's endpoint.
 * WC does not orchestrate anything: it exposes/reacts to a single attribute
 * (_ah_migration_status).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'logger.php';
require_once plugin_dir_path( __FILE__ ) . 'config.php';
require_once plugin_dir_path( __FILE__ ) . 'status.php';
require_once plugin_dir_path( __FILE__ ) . 'rest-api.php';
require_once plugin_dir_path( __FILE__ ) . 'pp-client.php';
require_once plugin_dir_path( __FILE__ ) . 'unknown-user-check.php';
require_once plugin_dir_path( __FILE__ ) . 'login-gate.php';
require_once plugin_dir_path( __FILE__ ) . 'consent.php';
require_once plugin_dir_path( __FILE__ ) . 'renewal-blocker.php';
require_once plugin_dir_path( __FILE__ ) . 'admin.php';
require_once plugin_dir_path( __FILE__ ) . 'cli.php';

if ( ! class_exists( 'AH_Migration_Loader' ) ) {

class AH_Migration_Loader {

    /**
     * Initialize the module.
     *
     * @return void
     */
    public static function init() {
        AH_Migration_Rest::init();
        AH_Migration_Login_Gate::init();
        AH_Migration_Consent::init();
        AH_Migration_Unknown_User_Check::init();
        AH_Migration_Admin::init();

        add_action( 'woocommerce_loaded', array( 'AH_Migration_Renewal_Blocker', 'init' ) );

        if ( did_action( 'woocommerce_loaded' ) ) {
            AH_Migration_Renewal_Blocker::init();
        }
    }
}

AH_Migration_Loader::init();

}