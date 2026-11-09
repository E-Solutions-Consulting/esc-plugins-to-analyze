<?php
/**
 * My Account: customers cannot edit their addresses directly.
 *
 * Address changes go through the "Request Shipping Address Update" form in
 * the my-address.php template override, so support can review them. The
 * template no longer prints "Edit" links, but WooCommerce and WooCommerce
 * Subscriptions still expose the edit form by other routes:
 *  - the "Change address" button on each subscription (My Account >
 *    Subscriptions) points to /my-account/edit-address/shipping/?subscription=ID;
 *  - the edit-address/billing and edit-address/shipping URLs can be typed in;
 *  - WC_Form_Handler::save_address() saves any POST that carries the nonce.
 *
 * This module removes the button, sends the edit URLs back to the read-only
 * address page and unhooks the save handler on every URL. The read-only page
 * itself (/my-account/edit-address/) is kept.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_Block_Address_Editing' ) ) {

class AH_Block_Address_Editing {

	public static function init() {
		add_filter( 'wcs_view_subscription_actions', array( __CLASS__, 'remove_change_address_action' ), 99 );
		// Before WC_Form_Handler::save_address() (priority 10) and the
		// Subscriptions endpoint check (priority 10).
		add_action( 'template_redirect', array( __CLASS__, 'block_edit_endpoint' ), 1 );
	}

	/**
	 * Drop the "Change address" button from the subscription actions.
	 *
	 * @param array $actions
	 * @return array
	 */
	public static function remove_change_address_action( $actions ) {
		unset( $actions['change_address'] );
		return $actions;
	}

	/**
	 * Redirect edit-address/billing|shipping, and refuse address saves.
	 *
	 * @return void
	 */
	public static function block_edit_endpoint() {
		// Refuse address saves on every URL: the handler does not check the
		// endpoint and falls back to billing when edit-address is not set.
		remove_action( 'template_redirect', array( 'WC_Form_Handler', 'save_address' ) );

		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
			return;
		}

		$type = isset( $GLOBALS['wp']->query_vars['edit-address'] ) ? $GLOBALS['wp']->query_vars['edit-address'] : '';
		if ( '' === $type ) {
			return; // Read-only address page.
		}

		wc_add_notice( __( 'To change an address, please use the "Request Shipping Address Update" form below.', 'bh-features' ), 'notice' );
		wp_safe_redirect( wc_get_account_endpoint_url( 'edit-address' ) );
		exit;
	}
}

add_action( 'woocommerce_loaded', array( 'AH_Block_Address_Editing', 'init' ) );

}
