<?php
// phpcs:ignoreFile
/**
 * Define your partner ID as and integer
 */
define( 'SENDWP_SDK_PARTNER_ID', 78440 );

/**
 * Read the remote installer action hook
 *
 * dirname(__FILE__) can be replaced by a constant with the path if defined and the path to the remote-install.php file
 */
require_once __DIR__ . '/remote-install.php';

/**
 * Enqueue the SendWP JS SDK.
 *
 * !!! Adapt cfw_ and textdomain  !!!
 */
function cfw_enqueue_sendwp_installer() {
	wp_enqueue_script( 'cfw_sendwp_installer', plugins_url( 'remote-install.js', __FILE__ ), array(), CFW_VERSION, true );
	wp_localize_script(
		'cfw_sendwp_installer',
		'sendwp_vars',
		array(
			'nonce'                    => wp_create_nonce( 'sendwp_install_nonce' ),
			'security_failed_message'  => esc_html__( 'Security failed to check sendwp_install_nonce', 'checkout-wc' ),
			'user_capability_message'  => esc_html__( 'Ask an administrator for install_plugins capability', 'checkout-wc' ),
			'sendwp_connected_message' => esc_html__( 'SendWP is already connected.', 'checkout-wc' ),
		)
	);
}

add_action( 'admin_enqueue_scripts', 'cfw_enqueue_sendwp_installer' );
