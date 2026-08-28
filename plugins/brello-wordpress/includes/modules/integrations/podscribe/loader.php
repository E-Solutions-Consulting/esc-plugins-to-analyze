<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/podscribe.php';

add_action( 'plugins_loaded', function() {
    if ( class_exists( 'AH_Podscribe' ) ) {
        new AH_Podscribe();
    }
});
