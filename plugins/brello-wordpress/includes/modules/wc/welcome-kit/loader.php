<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Welcome Kit module loader.
 *
 * Creates a free "welcome kit" order for each new (non-renewal) order that
 * reaches "completed", tagged with a custom status an external API polls.
 */

/**
 * Status slug used across the module.
 *
 * Defined here defensively so the module is self-contained. It can be moved to
 * common/constants.php (alongside SEND_TO_TELEGRA and friends); the guard
 * prevents a redefinition conflict if it is.
 */
if ( ! defined( 'KIT_READY_TO_SEND' ) ) {
    define( 'KIT_READY_TO_SEND', 'kit_ready_to_send' );
}

require_once __DIR__ . '/order-status.php';
require_once __DIR__ . '/eligibility.php';
require_once __DIR__ . '/order-factory.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/welcome-kit.php';

add_action( 'woocommerce_loaded', function() {
    if ( class_exists( 'AH_Welcome_Kit' ) ) {
        new AH_Welcome_Kit();
    }
} );
