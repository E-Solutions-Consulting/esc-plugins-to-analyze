<?php

namespace Objectiv\Plugins\Checkout\Action;

/**
 * Class AccountExistsAction
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class AccountExistsAction extends CFWAction {
	public function __construct() {
		parent::__construct( 'account_exists' );
	}

	public function action() {
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->out(
			array(
				/**
				 * Filters whether an email address has an account
				 *
				 * @since 1.0.0
				 *
				 * @param bool $exists Whether an email exists or not
				 * @param string $email The email address we are checking
				 */
				'account_exists' => (bool) apply_filters( 'cfw_email_exists', email_exists( $email ), $email ),
			)
		);
	}
}
