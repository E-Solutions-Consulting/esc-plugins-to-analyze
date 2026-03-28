<?php
/**
 * Plugin Name: AH Monitor
 * Description: Monitoring utilities for WooCommerce operations and reporting.
 * Version: 1.0.0
 * Author: Jaime Isidro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AH_MONITOR_VERSION', '1.0.0' );
define( 'AH_MONITOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'AH_MONITOR_URL', plugin_dir_url( __FILE__ ) );

require_once AH_MONITOR_PATH . 'includes/core/loader.php';