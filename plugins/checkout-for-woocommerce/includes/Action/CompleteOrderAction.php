<?php

namespace Objectiv\Plugins\Checkout\Action;

use Exception;

/**
 * Class CompleteOrderAction
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class CompleteOrderAction extends CFWAction {

	/**
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_cfw_meta_data' ) );

		parent::__construct( 'checkout' );
	}

	/**
	 * Takes in the information from the order form and hands it off to Woocommerce.
	 *
	 * @throws Exception If the nonce is invalid.
	 * @since 1.0.0
	 */
	public function action() {
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		// If the user is logged in don't try and get the user from the front end, just get it on the back before we checkout
		if ( empty( $_POST['billing_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$current_user = wp_get_current_user();

			if ( $current_user ) {
				$_POST['billing_email'] = $current_user->user_email;
			}
		}

		// Since we allow disabling the billing_country in WP Admin > CheckoutWC > Checkout
		// we should set a default billing country for proper VAT handling
		// Specifically this fixes an issue with VismaPay
		if ( empty( $_POST['billing_country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Set country to store default country
			$_POST['billing_country'] = wc_get_base_location()['country'];
		}

		// Mark orders through CFW as being orders from CFW.
		$_POST['_cfw'] = true;

		/**
		 * Fires before checkout is processed in complete order action
		 *
		 * @since 3.0.0
		 */
		do_action( 'cfw_before_process_checkout' );

		WC()->checkout()->process_checkout();
		wp_die( 0 );
	}

	public function add_cfw_meta_data( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( ! empty( $_POST['_cfw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->add_meta_data( '_cfw', 'true', true );
		}

		if ( ! empty( $_POST['billing_full_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->add_meta_data( '_billing_full_name', sanitize_text_field( wp_unslash( $_POST['billing_full_name'] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( ! empty( $_POST['shipping_full_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->add_meta_data( '_shipping_full_name', sanitize_text_field( wp_unslash( $_POST['shipping_full_name'] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$order->save();
	}
}
