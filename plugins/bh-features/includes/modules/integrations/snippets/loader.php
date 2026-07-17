<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/snippets.php';

add_action( 'plugins_loaded', function() {
    new AH_Tracking_Snippets();
});