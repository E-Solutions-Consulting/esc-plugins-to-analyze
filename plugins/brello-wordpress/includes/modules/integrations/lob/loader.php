<?php
/**
 * LOB Integration - Bootstrap Loader
 *
 * Loads the WooCommerce → LOB direct-mail integration: config/admin page,
 * API client, external-DB dedup ledger, logger and the subscription event
 * handler. Follows the same shape as the Attentive loader.
 *
 * @package    BH_Features
 * @subpackage Integrations/Lob
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_LOB_Loader {

    /** Option flag so the external table is only created once. */
    const TABLE_FLAG = 'bh_lob_events_table_ready';

    public static function init() {
        $base = plugin_dir_path( __FILE__ );

        require_once $base . 'logger.php';
        require_once $base . 'config.php';
        require_once $base . 'events-log.php';
        require_once $base . 'api-client.php';
        require_once $base . 'subscription-events.php';

        if ( class_exists( 'BH_LOB_Config' ) ) {
            BH_LOB_Config::init();
        }

        if ( class_exists( 'BH_LOB_Subscription_Events' ) ) {
            new BH_LOB_Subscription_Events();
        }

        self::maybe_create_table();
    }

    /**
     * Create the external-DB dedup table once (guarded by an option so we
     * don't hit the external DB on every request).
     */
    private static function maybe_create_table() {
        if ( get_option( self::TABLE_FLAG ) === 'yes' ) {
            return;
        }

        if ( class_exists( 'BH_LOB_Events_Log' ) && BH_LOB_Events_Log::maybe_create_table() ) {
            update_option( self::TABLE_FLAG, 'yes', false );
        }
    }
}

add_action( 'plugins_loaded', [ 'BH_LOB_Loader', 'init' ], 20 );
