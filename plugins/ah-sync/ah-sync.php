<?php
/**
 * Plugin Name:       AH Sync
 * Plugin URI:        https://brellohealth.com
 * Description:       Automated synchronization engine for the Brello ecosystem. Handles Telegra ↔ WooCommerce status sync and order creation retries.
 * Version:           1.0.0
 * Author:            Jaime Isidro
 * Author URI:        https://solutionswebonline.com
 * License:           GPL-2.0+
 * Text Domain:       ah-sync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'AH_SYNC_VERSION',     '1.0.0' );
define( 'AH_SYNC_PLUGIN_FILE', __FILE__ );
define( 'AH_SYNC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'AH_SYNC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ── Autoloader ─────────────────────────────────────────────────────────────────
require_once AH_SYNC_PLUGIN_DIR . 'includes/class-sync-loader.php';
require_once AH_SYNC_PLUGIN_DIR . 'includes/class-sync-logger.php';
require_once AH_SYNC_PLUGIN_DIR . 'includes/class-sync-notifier.php';
require_once AH_SYNC_PLUGIN_DIR . 'includes/class-sync.php';
require_once AH_SYNC_PLUGIN_DIR . 'modules/telegra-wc-sync/class-telegra-wc-evaluator.php';
require_once AH_SYNC_PLUGIN_DIR . 'modules/telegra-wc-sync/class-telegra-wc-sync.php';
require_once AH_SYNC_PLUGIN_DIR . 'admin/class-sync-admin.php';

// ── WP-CLI ─────────────────────────────────────────────────────────────────────
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once AH_SYNC_PLUGIN_DIR . 'includes/class-sync-cli.php';
    WP_CLI::add_command( 'ah-sync', 'AH_Sync_CLI' );
}

// ── Boot ───────────────────────────────────────────────────────────────────────
function ah_sync_run() {
    $plugin = new AH_Sync();
    $plugin->run();
}
ah_sync_run();
