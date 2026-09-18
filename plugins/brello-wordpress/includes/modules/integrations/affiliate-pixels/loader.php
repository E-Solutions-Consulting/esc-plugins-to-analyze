<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-bh-affiliate-pixels.php';

add_action(
    'plugins_loaded',
    static function () {
        new BH_Affiliate_Pixels();
    },
    20
);
