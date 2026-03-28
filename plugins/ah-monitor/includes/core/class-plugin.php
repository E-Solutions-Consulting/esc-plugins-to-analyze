<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Plugin {

    /**
     * Initialize plugin hooks.
     */
    public static function init() {

        add_action( 'plugins_loaded', [ __CLASS__, 'load_modules' ], 20 );

    }

    /**
     * Load plugin modules in controlled order.
     */
    public static function load_modules() {

        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        self::load_slack_module();
        self::load_monitor_module();
        self::load_debug_module();

    }

    /**
     * Load Slack module.
     */
    private static function load_slack_module() {

        require_once AH_MONITOR_PATH . 'includes/modules/slack/loader.php';

    }

    /**
     * Load monitor module.
     */
    private static function load_monitor_module() {

        require_once AH_MONITOR_PATH . 'includes/modules/monitor/loader.php';

    }

    /**
     * Load monitor module.
     */
    private static function load_debug_module() {

        require_once AH_MONITOR_PATH . 'includes/modules/debug/loader.php';

    }

}