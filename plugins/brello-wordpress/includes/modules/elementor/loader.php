<?php
/**
 * Elementor module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$elementor_base = plugin_dir_path( __FILE__ );

$this->safe_require( $elementor_base . 'specific-product-condition.php' );
