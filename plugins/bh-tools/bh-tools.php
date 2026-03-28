<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://solutionswebonline.com
 * @since             1.0.0
 * @package           Bh_Tools
 *
 * @wordpress-plugin
 * Plugin Name:       Brello Tools
 * Plugin URI:        https://solutionswebonline.com
 * Description:       Brello tools.
 * Version:           1.0.0
 * Author:            Jaime Isidro
 * Author URI:        https://solutionswebonline.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-tools
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BH_TOOLS_VERSION', '1.0.0' );

if ( ! defined( 'PARENT_MENU_SLUG' ) ) {
	define( 'PARENT_MENU_SLUG', 'bh-features' );
}
if (!function_exists('bh_plugins_log')) {
	function bh_plugins_log($entry, $file = 'bh_plugins', $mode = 'a') {
		try {
			$upload_dir = wp_upload_dir();
			$upload_dir = $upload_dir['basedir'];
			if (!is_string($entry)) {
			  $entry = print_r($entry, true);
			}
			$file  = $upload_dir . '/' . $file . '.log';
			$file  = fopen($file, $mode);
			date_default_timezone_set('UTC');
			$now	=	date('d-M-Y H:i:s T');
			$bytes = fwrite($file, '[' . $now . '] ' . $entry . "\n");
			fclose($file);
		} catch (\Throwable $th) {
			error_log(print_r($th, true));
		}	  
	  	return $bytes;
	}
}
if (!function_exists('bh_plugins_error_log')) {
	function bh_plugins_error_log($entry, $file = 'bh_plugins_errors', $mode = 'a') {
		bh_plugins_log($entry, $file, $mode);
	}
}
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-bh-tools-activator.php
 */
function activate_bh_tools() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-tools-activator.php';
	Bh_Tools_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bh-tools-deactivator.php
 */
function deactivate_bh_tools() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-tools-deactivator.php';
	Bh_Tools_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bh_tools' );
register_deactivation_hook( __FILE__, 'deactivate_bh_tools' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bh-tools.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_bh_tools() {

	$plugin = new Bh_Tools();
	$plugin->run();

}
run_bh_tools();
