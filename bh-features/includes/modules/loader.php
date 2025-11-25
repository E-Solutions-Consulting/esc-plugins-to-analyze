<?php
/**
 * Module Loader for BrelloHealth Features
 *
 * Recursively loads all PHP modules inside /includes/modules/
 * and automatically excludes any PHP file starting with "__".
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BH_ModuleLoader {

    /**
     * Base path for the modules directory.
     *
     * @var string
     */
    private $modules_dir;

    /**
     * List of excluded top-level module folders (optional).
     *
     * Example: "wc", "emails", "subscriptions".
     *
     * @var array
     */
    private $excluded_modules = [];

    /**
     * Constructor: initialize loader.
     */
    public function __construct() {

        // Set base directory
        $this->modules_dir = plugin_dir_path( __FILE__ );

        // Load modules early after plugins are ready
        add_action( 'plugins_loaded', [ $this, 'load_all_modules' ], 1 );
    }

    /**
     * Load all top-level modules inside /modules/
     *
     * @return void
     */
    public function load_all_modules() {

        if ( ! is_dir( $this->modules_dir ) ) {
            return;
        }

        $top_level_dirs = glob( $this->modules_dir . '*', GLOB_ONLYDIR );

        if ( empty( $top_level_dirs ) ) {
            return;
        }

        foreach ( $top_level_dirs as $dir ) {

            $module_slug = basename( $dir );

            // Skip excluded modules if explicitly set
            if ( in_array( $module_slug, $this->excluded_modules, true ) ) {
                continue;
            }

            $this->load_module_recursive( $dir );
        }
    }

    /**
     * Recursively load all PHP files inside a module and its subfolders.
     *
     * @param string $path
     * @return void
     */
    private function load_module_recursive( $path ) {

        // 1) Load PHP files in this folder
        $php_files = glob( trailingslashit( $path ) . '*.php' );

        if ( ! empty( $php_files ) ) {
            foreach ( $php_files as $file ) {

                $basename = basename( $file );

                // Skip files starting with "__"
                if ( strpos( $basename, '__' ) === 0 ) {
                    continue;
                }

                require_once $file;
            }
        }

        // 2) Load subdirectories (submodules)
        $subdirs = glob( trailingslashit( $path ) . '*', GLOB_ONLYDIR );

        if ( empty( $subdirs ) ) {
            return;
        }

        foreach ( $subdirs as $subdir ) {
            $this->load_module_recursive( $subdir );
        }
    }

    /**
     * Exclude a top-level module folder from being loaded.
     *
     * Example: $loader->exclude_module( 'wc' );
     *
     * @param string $slug
     * @return void
     */
    public function exclude_module( $slug ) {
        $this->excluded_modules[] = sanitize_key( $slug );
    }
}
new BH_ModuleLoader();