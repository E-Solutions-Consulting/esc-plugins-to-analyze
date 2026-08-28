<?php
/**
 * Friendbuy integration module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '/products-admin.php';
require_once plugin_dir_path( __FILE__ ) . '/product-export-admin.php';
