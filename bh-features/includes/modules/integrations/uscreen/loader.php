<?php
/**
 * Uscreen integration module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '/config.php';
require_once plugin_dir_path( __FILE__ ) . '/client.php';
require_once plugin_dir_path( __FILE__ ) . '/client-helper.php';
require_once plugin_dir_path( __FILE__ ) . '/subscription-mapper.php';
require_once plugin_dir_path( __FILE__ ) . 'subscription-sync.php';

/**
 * Bootstrap for Uscreen module.
 */
function ah_uscreen_bootstrap_module() {
    // Settings & admin.
    if ( class_exists( 'AH_Uscreen_Config' ) ) {
        AH_Uscreen_Config::init();
    }

    // Sync de suscripciones (ya implementado en fase anterior).
    if ( class_exists( 'AH_Uscreen_Subscription_Sync' ) ) {
        AH_Uscreen_Subscription_Sync::init();
    }

    // SSO / My Account endpoint + shortcode.
    // if ( class_exists( 'AH_Uscreen_SSO_Controller' ) ) {
    //     AH_Uscreen_SSO_Controller::init();
    // }
}
add_action( 'plugins_loaded', 'ah_uscreen_bootstrap_module', 20 );
