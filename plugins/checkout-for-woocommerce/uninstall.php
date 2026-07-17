<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

if ( get_option( '_cfw_allow_uninstall', false ) !== 'yes' ) {
	return;
}


// Get options
global $wpdb;
$options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_cfw_%'" );

// Remove options
foreach ( $options as $option ) {
	delete_option( $option->option_name );
}

// Remove some esoteric options
delete_option( 'cfw_db_version' );
delete_option( 'cfw_license_price_id' );
delete_option( 'cfw_v80_data_migrated' );
delete_option( 'cfw_license' );
delete_option( 'cfw_license_data' );
