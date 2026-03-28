<?php
/**
 * Performance module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '/elementor-editor-optimizer.php';

add_action( 'plugins_loaded', function() {
    if ( class_exists( 'AH_Elementor_Editor_Optimizer' ) ) {
        new AH_Elementor_Editor_Optimizer();
    }
});
