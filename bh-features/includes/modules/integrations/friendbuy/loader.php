<?php
/**
 * Friendbuy integration module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//
// update_option( 'ah_friendbuy_beta_emails', [
//     'test123@gmail.com',
//     'jaime+qa_150126@gmail.com'
// ] );

require_once plugin_dir_path( __FILE__ ) . '/friendbuy-webhook-handler.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-myaccount.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-api.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-admin.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-coupon-generator.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-events.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-feature-gate.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy.php';
