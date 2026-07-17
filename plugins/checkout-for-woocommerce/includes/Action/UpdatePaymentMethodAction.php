<?php

namespace Objectiv\Plugins\Checkout\Action;

/**
 * Class UpdatePaymentMethodAction
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class UpdatePaymentMethodAction extends CFWAction {
	public function __construct() {
		parent::__construct( 'update_payment_method' );
	}

	public function action() {
		WC()->session->set( 'chosen_payment_method', wc_clean( wp_unslash( $_POST['paymentMethod'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->out(
			array(
				'payment_method' => WC()->session->get( 'chosen_payment_method' ),
			)
		);
	}
}
