<?php

namespace Objectiv\Plugins\Checkout\Action;

/**
 * @link checkoutwc.com
 * @since 5.4.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class ValidatePostcodeAction extends CFWAction {
	public function __construct() {
		parent::__construct( 'cfw_validate_postcode' );
	}

	public function action() {
		if ( empty( $_POST['postcode'] ) || empty( $_POST['country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->out(
				array(
					'message' => 'Invalid postcode validation request. Must include postcode and country.',
				),
				202 // I was a teapot, but now I'm just a noncommittal response
			);
		}

		$postcode = wc_clean( wp_unslash( $_POST['postcode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$country  = wc_clean( wp_unslash( $_POST['country'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$valid = \WC_Validation::is_postcode( trim( $postcode ), $country );

		$this->out(
			array(
				// translators: %s is the postcode field label
				'message' => $valid ? '' : __( 'Please enter a valid %s.', 'checkout-wc' ),
			),
			$valid ? 200 : 400
		);
	}
}
