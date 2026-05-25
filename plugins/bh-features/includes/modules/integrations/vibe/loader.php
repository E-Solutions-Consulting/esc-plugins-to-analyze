<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vibe.php';

add_action( 'woocommerce_loaded', function () {
    new AH_Vibe();
} );