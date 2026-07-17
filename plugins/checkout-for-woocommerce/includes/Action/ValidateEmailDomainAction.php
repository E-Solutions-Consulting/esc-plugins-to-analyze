<?php

namespace Objectiv\Plugins\Checkout\Action;

/**
 * @link checkoutwc.com
 * @since 5.4.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class ValidateEmailDomainAction extends CFWAction {
	public function __construct() {
		parent::__construct( 'cfw_validate_email_domain' );
	}

	public function action() {
		if ( empty( $_POST['email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->out(
				array(
					'message' => 'Invalid email validation request. Must include email.',
				),
				418 // I'm a teapot
			);
		}

		$email_address = sanitize_email( wp_unslash( $_POST['email'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email_domain  = substr( $email_address, strpos( $email_address, '@' ) + 1 );

		/**
		 * Filters whether to validate email domain
		 *
		 * If you don't append dot to the domain, every domain will validate because
		 * it will fetch your local MX handler
		 *
		 * @since 7.2.3
		 * @param bool $valid Whether the email domain is valid
		 * @param string $email_domain The email domain
		 * @param string $email_address The email address
		 */
		$valid = apply_filters( 'cfw_email_domain_valid', checkdnsrr( $email_domain . '.', 'MX' ), $email_domain, $email_address );

		$this->out(
			array(
				// translators: %s is the postcode field label
				'message' => $valid ? '' : __( 'Email address contains invalid domain name.', 'checkout-wc' ),
			),
			$valid ? 200 : 400
		);
	}
}
