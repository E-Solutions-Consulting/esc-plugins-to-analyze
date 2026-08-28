<?php
/**
 * Performance module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$performance_base = plugin_dir_path( __FILE__ );

require_once plugin_dir_path( __FILE__ ) . '/elementor-editor-optimizer.php';
$this->safe_require( $performance_base . '/comments-disabler.php' );

add_action( 'plugins_loaded', function() {

    if ( class_exists( 'AH_Elementor_Editor_Optimizer' ) ) {
        new AH_Elementor_Editor_Optimizer();
    }

    if ( class_exists( 'AH_Comments_Disabler' ) ) {
        AH_Comments_Disabler::init();
    }

});
