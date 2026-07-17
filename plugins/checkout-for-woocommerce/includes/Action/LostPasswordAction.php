<?php

namespace Objectiv\Plugins\Checkout\Action;

/**
 * Class LostPasswordAction
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class LostPasswordAction extends CFWAction {
	public function __construct() {
		parent::__construct( 'cfw_lost_password' );
	}

	public function action() {
		$nonce_value = wc_get_var( $_POST['woocommerce-lost-password-nonce'] ); // @codingStandardsIgnoreLine.
		$error       = array(
			'result'  => false,
			'message' => 'An error occurred. Please contact site administrator.',
		);

		if ( ! wp_verify_nonce( $nonce_value, 'lost_password' ) ) {
			$this->out( $error );
		}

		$success = \WC_Shortcode_My_Account::retrieve_password();

		if ( ! $success ) {
			$all_notices = WC()->session->get( 'wc_notices', array() );

			$notice_type = 'error';
			$notices     = array();

			if ( wc_notice_count( $notice_type ) > 0 && isset( $all_notices[ $notice_type ] ) ) {
				// In WooCommerce 3.9+, messages can be an array with two properties:
				// - notice
				// - data
				foreach ( $all_notices[ $notice_type ] as $index => $notice ) {
					$notices[] = $notice['notice'] ?? $notice;
					unset( $all_notices[ $notice_type ][ $index ] );
				}
			}

			WC()->session->set( 'wc_notices', $all_notices );

			$this->out(
				array(
					'result'  => false,
					'message' => join( ' ', $notices ),
				)
			);
		}

		$this->out(
			array(
				'result'  => true,
				'message' => esc_html( cfw_apply_filters( 'woocommerce_lost_password_confirmation_message', esc_html__( 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.', 'woocommerce' ) ) ),
			)
		);
	}
}
