<?php
/**
 * Plugin Name: CheckoutWC
 * Plugin URI:  https://www.checkoutwc.com
 * Description: Beautiful conversion optimized checkout templates for WooCommerce.
 * Author: Kestrel
 * Author URI: https://kestrelwp.com/
 * Text Domain: checkout-wc
 * Domain Path: /i18n/languages
 * Version: 11.1.2
 * Requires Plugins: woocommerce
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Tested up to: 7.0
 * WC requires at least: 8.2
 * WC tested up to: 10.8.1
 * Build: BwMIUwpQAgJXAVIMAVAEXABVAFcHAQIAUFRWWQVQXlAXAgkeAkhW
 * Copyright: (c) 2018-2026 Kestrel Commerce LLC [support@kestrelwp.com]
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author    Kestrel
 * @copyright Copyright (c) 2018-2026 Kestrel Commerce LLC [support@kestrelwp.com]
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// This is safe even with the unified version because:
// 1. The distributed lite version will always have the directory slug checkoutwc-lite
// 2. So this code only applies when using a distributed pro version with the lite version installed
add_action(
	'activate_checkout-for-woocommerce/checkout-for-woocommerce.php',
	function () {
		deactivate_plugins( 'checkoutwc-lite/checkout-for-woocommerce.php' );
	}
);

if ( defined( 'CFW_VERSION' ) ) {
	return;
}

define( 'CFW_NAME', 'Checkout for WooCommerce' );
define( 'CFW_UPDATE_URL', 'https://www.checkoutwc.com' );
define( 'CFW_VERSION', '11.1.2' );
define( 'CFW_PATH', __DIR__ );
define( 'CFW_URL', plugins_url( '/', __FILE__ ) );
define( 'CFW_MAIN_FILE', __FILE__ );
define( 'CFW_PATH_BASE', plugin_dir_path( __FILE__ ) );
define( 'CFW_PATH_URL_BASE', plugin_dir_url( __FILE__ ) );
define( 'CFW_PATH_MAIN_FILE', CFW_PATH_BASE . __FILE__ );
define( 'CFW_PATH_ASSETS', CFW_PATH_URL_BASE . 'assets/dist' );
define( 'CFW_PATH_PLUGIN_TEMPLATE', CFW_PATH_BASE . 'templates' );
define( 'CFW_PATH_THEME_TEMPLATE', get_stylesheet_directory() . '/checkout-wc' );

/**
 * Our hook function wrappers that we only use for external hooks
 */
require_once CFW_PATH . '/functions/hook-wrapper-functions.php';


/**
 * Handle chunk loading
 */
require_once CFW_PATH . '/assets/dist/wordpressEnqueueChunksPlugin.php';

// Preprocess wordpressEnqueueChunksPlugin dependencies and marry them to @wordpress/scripts dependencies
// Also handles some edge cases for the main pages
add_filter(
	'wpecp/register',
	function ( $args, $chunk_name ) {
		if ( substr( $chunk_name, - 7 ) === '-styles' ) {
			return $args;
		}

		$front    = CFW_PATH_ASSETS;
		$filename = basename( $args['src'] );

		// Use default handle 'woocommerce' for main pages since other plugins look for
		// a script registered with that handle
		if ( in_array( $chunk_name, array( 'checkout', 'order-pay', 'thank-you' ), true ) ) {
			$args['handle'] = 'woocommerce';
			array_push( $args['deps'], 'jquery-blockui', 'js-cookie' );
		}

		if ( 'selectwoo' === $chunk_name ) {
			$args['handle'] = 'selectWoo';
		}

		// Remove any deps that end with -styles
		$args['deps'] = array_filter(
			$args['deps'],
			function ( $dep ) {
				return substr( $dep, - 7 ) !== '-styles';
			}
		);

		// Load Dependency Extraction Webpack Plugin files
		$deps_file = CFW_PATH . '/assets/dist/js/' . str_replace( '.js', '.asset.php', $filename );

		// If the file can be found, use it to set the dependencies array.
		if ( file_exists( $deps_file ) ) {
			$deps_file = require $deps_file;

			array_push( $args['deps'], ...$deps_file['dependencies'] ?? array() );
		}

		// Remove duplicate dependencies
		$args['deps'] = array_unique( $args['deps'] );
		$args['src']  = "{$front}/js/" . $filename;

		return $args;
	},
	10,
	2
);

/*
 * Protect our gentle, out of date users from our fancy modern code
 */
if ( version_compare( phpversion(), '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php echo wp_kses_post( __( 'Your site is running an <strong>insecure version</strong> of PHP that is no longer supported. Please contact your web hosting provider to update your PHP version.', 'checkout-wc' ) ); ?>
					<br><br>
					<?php
					printf(
						wp_kses(
							/* translators: %s - checkoutwc.com URL for documentation with more details. */
							__( '<strong>Note:</strong> CheckoutWC is disabled on your site until you fix the issue. <a href="%s" target="_blank" rel="noopener noreferrer">Need help? Click here.</a>', 'checkout-wc' ),
							array(
								'a'      => array(
									'href'   => array(),
									'target' => array(),
									'rel'    => array(),
								),
								'strong' => array(),
							)
						),
						'https://www.checkoutwc.com/documentation/installation-requirements/'
					);
					?>
				</p>
			</div>

			<?php
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}
	);

	// Abort!
	return;
}

// Require WP 5.2+
if ( version_compare( $GLOBALS['wp_version'], '5.2', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: %s - WordPress version. */
						esc_html__( 'CheckoutWC requires WordPress %s or later.', 'checkout-wc' ),
						'5.2'
					);
					?>
				</p>
			</div>

			<?php
			// In case this is on plugin activation.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}
	);

	// Do not process the plugin code further.
	return;
}

// Test to see if WooCommerce is active (including network activated).
$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

if (
	! in_array( $plugin_path, wp_get_active_and_valid_plugins(), true )
	&& ( ! function_exists( 'wp_get_active_network_plugins' ) || ! in_array( $plugin_path, wp_get_active_network_plugins(), true ) )
) {
	add_action(
		'admin_notices',
		function () {

			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: %s - WordPress version. */
						esc_html__( 'CheckoutWC requires WooCommerce %s or later.', 'checkout-wc' ),
						'5.6'
					);
					?>
				</p>
			</div>

			<?php
			// In case this is on plugin activation.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}
	);

	// Do not process the plugin code further.
	return;
}

/**
 * Auto-loader (composer)
 *
 * Aviary emits scoped dependencies to vendor-scoped/ and generates
 * aviary-autoload.php, which loads the main Composer autoloader
 * (vendor/autoload.php) and the scoped autoloader together.
 */
require_once CFW_PATH . '/vendor-scoped/aviary-autoload.php';
require_once CFW_PATH . '/lib/sendwp-sdk/sendwp-init.php';

// ensure CFW_DEV_MODE is defined
if ( ! defined( 'CFW_DEV_MODE' ) ) {
	define( 'CFW_DEV_MODE', getenv( 'CFW_DEV_MODE' ) === 'true' );
}

require_once CFW_PATH . '/functions/api.php';
require_once CFW_PATH . '/functions/ab-testing-api.php';
require_once CFW_PATH . '/functions/functions.php';
require_once CFW_PATH . '/functions/admin-template-functions.php';
require_once CFW_PATH . '/functions/template-functions.php';
require_once CFW_PATH . '/functions/template-hooks.php';

/**
 * Debugging - Kint disabled by default. Enable by enabling developer mode (see docs)
 */
if ( class_exists( '\Kint' ) && property_exists( '\Kint', 'enabled_mode' ) ) {
	Kint::$enabled_mode = defined( 'CFW_DEV_MODE' ) && CFW_DEV_MODE;
}

// Declare compatibility with High-Performance Order Storage.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Activation hook must run from the main plugin file so it fires on first install
 * (init.php is only loaded on plugins_loaded, which does not run during activation).
 * Load init (and premium-init) here so cfw_do_plugin_activation listeners are registered.
 */
register_activation_hook(
	CFW_MAIN_FILE,
	function () {
		set_transient( '_cfw_welcome_screen_activation_redirect', true, 30 );

		if ( file_exists( CFW_PATH . '/functions/premium-init.php' ) && ! defined( 'CFW_FORCE_FREE_VERSION' ) ) {
			require CFW_PATH . '/functions/premium-init.php';
		}
		require CFW_PATH . '/functions/init.php';

		/**
		 * Fires after plugin activation.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cfw_do_plugin_activation' );
	}
);

// Load premium-init and init on plugins_loaded so we can apply editor preview overrides first (pluggable.php is loaded after plugins, so we need this hook).
add_action(
	'plugins_loaded',
	function () {
		// Apply editor preview setting overrides before premium-init so features that read settings at construction get preview values.
		if ( ! is_admin() && isset( $_GET['cfw-editor-preview'] ) && $_GET['cfw-editor-preview'] === '1' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['_cfw_preview_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_cfw_preview_nonce'] ) ), 'cfw-editor-preview' ) && current_user_can( 'cfw_manage_pages' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$user_id  = get_current_user_id();
				$settings = get_transient( '_cfw_editor_preview_' . $user_id );
				if ( is_array( $settings ) && ! empty( $settings ) ) {
					( new \Objectiv\Plugins\Checkout\EditorPreviewSettingsOverride() )->apply_preview_overrides_early( $settings );
				}
			}
		}

		if ( file_exists( CFW_PATH . '/functions/premium-init.php' ) && ! defined( 'CFW_FORCE_FREE_VERSION' ) ) {
			require CFW_PATH . '/functions/premium-init.php';
		}

		require CFW_PATH . '/functions/init.php';
	},
	0
);
