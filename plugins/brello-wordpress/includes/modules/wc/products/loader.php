<?php
/**
 * Friendbuy integration module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '/products-admin.php';
require_once plugin_dir_path( __FILE__ ) . '/state-replacement-admin.php';
require_once plugin_dir_path( __FILE__ ) . '/product-export-admin.php';
require_once plugin_dir_path( __FILE__ ) . '/telegra-mapping-admin.php';
