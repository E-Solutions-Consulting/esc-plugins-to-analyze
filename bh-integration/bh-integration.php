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
 * @package           Bh_Integration
 *
 * @wordpress-plugin
 * Plugin Name:       Brello Integration
 * Plugin URI:        https://solutionswebonline.com
 * Description:       Third-Party Integration Plugin for WooCommerce. Allows external systems to connect to your WooCommerce store to securely and automatically send and receive product and order data.
 * Version:           1.0.0
 * Author:            Jaime Isidro
 * Author URI:        https://solutionswebonline.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-integration
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
define( 'BH_INTEGRATION_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-bh-integration-activator.php
 */
function activate_bh_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-integration-activator.php';
	Bh_Integration_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bh-integration-deactivator.php
 */
function deactivate_bh_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-integration-deactivator.php';
	Bh_Integration_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bh_integration' );
register_deactivation_hook( __FILE__, 'deactivate_bh_integration' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bh-integration.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_bh_integration() {

	$plugin = new Bh_Integration();
	$plugin->run();

}
run_bh_integration();
