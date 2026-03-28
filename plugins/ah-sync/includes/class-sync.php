<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Sync {

    private $loader;
    private $version;

    public function __construct() {
        $this->version = AH_SYNC_VERSION;
        $this->loader  = new AH_Sync_Loader();
        $this->define_admin_hooks();
    }

    private function define_admin_hooks() {
        $admin = new AH_Sync_Admin( $this->version );

        // Menu
        $this->loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );

        // Enqueue
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );

        // ── Telegra WC Sync AJAX ───────────────────────────────
        $this->loader->add_action( 'wp_ajax_ah_sync_telegra_wc_preview',      $admin, 'ajax_telegra_wc_preview' );
        $this->loader->add_action( 'wp_ajax_ah_sync_telegra_wc_apply',        $admin, 'ajax_telegra_wc_apply' );
        $this->loader->add_action( 'wp_ajax_ah_sync_telegra_wc_export',       $admin, 'ajax_telegra_wc_export' );
        $this->loader->add_action( 'wp_ajax_ah_sync_telegra_wc_reset',        $admin, 'ajax_telegra_wc_reset' );
    }

    public function run() {
        $this->loader->run();
    }

    public function get_version() {
        return $this->version;
    }
}
