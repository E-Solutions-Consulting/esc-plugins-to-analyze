<?php

namespace Objectiv\Plugins\Checkout\Action;

/**
 * Class ApplyCouponAction
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class RemoveCouponAction extends CFWAction {
	public function __construct() {
		parent::__construct( 'cfw_remove_coupon' );
	}

	public function action() {
		check_ajax_referer( 'remove-coupon', 'security' );

		$coupon = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result = false;

		ob_start();

		if ( empty( $coupon ) ) {
			wc_add_notice( __( 'Sorry there was a problem removing this coupon.', 'woocommerce' ), 'error' );
		} else {
			WC()->cart->remove_coupon( $coupon );
			wc_add_notice( __( 'Coupon has been removed.', 'woocommerce' ) );
			$result = true;
		}

		$output = ob_get_clean();

		$this->out(
			/**
			 * Filters remove coupon action response object
			 *
			 * @since 3.14.0
			 *
			 * @param array $response The response object
			 */
			apply_filters(
				'cfw_remove_coupon_response',
				array(
					'result' => $result,
					'html'   => $output,
					'coupon' => $coupon,
				)
			)
		);
	}
}
