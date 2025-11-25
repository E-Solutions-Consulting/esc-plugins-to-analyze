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
 * @package           Bh_Features
 *
 * @wordpress-plugin
 * Plugin Name:       Brello Features
 * Plugin URI:        https://solutionswebonline.com
 * Description:       Add custom functionalities to process in WooCommerce pages with validations and summaries.
 * Version:           1.0.0
 * Author:            Jaime Isidro
 * Author URI:        https://solutionswebonline.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-features
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
define( 'BH_FEATURES_VERSION', '1.0.0' );
if ( ! defined( 'PARENT_MENU_SLUG' ) ) {
	define( 'PARENT_MENU_SLUG', 'bh-features' );
}

// Define plugin constants
if (!defined('BH_FEATURES_PLUGIN_URL')) {
    define('BH_FEATURES_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Plan Days
define( 'BH_DAYS_MONTHLY_PLAN', 25 );
define( 'BH_DAYS_THREE_MONTH_PLAN', 70 );



if(!function_exists('_print'))    :
function _print($data, $title=''){
    $output =   '';
    if(!empty($title))
        $output .=   '<h3>' . $title . '</h3>';

    $output   .=   print_r($data, true);
    echo '<pre>' . $output . '</pre>';
}
endif;
if (!function_exists('bh_plugins_log')) {
	function bh_plugins_log($entry, $file = 'bh_plugins', $mode = 'a') {
		try {
			$upload_dir = wp_upload_dir();
			$upload_dir = $upload_dir['basedir'];
			if (!is_string($entry)) {
			  $entry = print_r($entry, true);
			}
			$file  = $upload_dir . '/bh-logs/' . $file . '.log';
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
if (!function_exists('bh_send_slack_notification')) {
	/**
	 * Send Notifications to Slack Channel
	 */
	function bh_send_slack_notification($message, $webhook_url='') {
		if(empty($webhook_url))
			$webhook_url = BH_SLACK_CHANNEL_TEST_DEVELOPMENT;

		$payload = array(
			'text' => $message,
			'username' => 'WooCommerce Notifier',
			'icon_emoji' => ':warning:'
		);
		$response	=	wp_remote_post($webhook_url, array(
			'body' => json_encode($payload),
			'headers' => array('Content-Type' => 'application/json')
		));
	}
}
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-bh-features-activator.php
 */
function activate_bh_features() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-features-activator.php';
	Bh_Features_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bh-features-deactivator.php
 */
function deactivate_bh_features() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-features-deactivator.php';
	Bh_Features_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bh_features' );
register_deactivation_hook( __FILE__, 'deactivate_bh_features' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bh-features.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_bh_features() {

	$plugin = new Bh_Features();
	$plugin->run();

}
run_bh_features();
