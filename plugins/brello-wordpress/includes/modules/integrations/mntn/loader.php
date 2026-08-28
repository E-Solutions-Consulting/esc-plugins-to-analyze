<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path( __FILE__ ) . '/mntn.php';

add_action( 'plugins_loaded', function() {
    new AH_MNTN();
}, 20 );