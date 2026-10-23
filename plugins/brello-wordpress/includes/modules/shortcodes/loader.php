<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcodes module loader.
 *
 * Home for new shortcodes: one file per shortcode, named bh_<name>. A shortcode
 * that needs an editable value registers its field in BH_Settings (Brello >
 * Settings) instead of creating its own admin page.
 */
require_once __DIR__ . '/delivery-time.php';
