<?php

namespace Objectiv\Plugins\Checkout\Action;

use Exception;
use Objectiv\Plugins\Checkout\Features\OrderBumps;
use Objectiv\Plugins\Checkout\Managers\AssetManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.4.0
 * @package Objectiv\Plugins\Checkout\Action
 */
class UpdateSideCart extends CFWAction {
	protected $order_bumps_controller;

	public function __construct( OrderBumps $order_bumps_controller ) {
		parent::__construct( 'update_side_cart' );

		$this->order_bumps_controller = $order_bumps_controller;
	}

	/**
	 * @throws Exception Exception getting data.
	 */
	public function action() {
		parse_str( wp_unslash( $_POST['cart_data'] ?? '' ), $cart_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

		if ( ! empty( $cart_data['cfw-promo-code'] ) ) {
			WC()->cart->add_discount( wc_format_coupon_code( wc_clean( wp_unslash( $cart_data['cfw-promo-code'] ) ) ) );
		}

		/**
		 * Fires before updating the side cart.
		 *
		 * @param array $cart_data The cart data.
		 *
		 * @since 6.0.6
		 */
		do_action( 'cfw_before_update_side_cart_action', $cart_data );

		$result = false;

		if ( SettingsManager::instance()->get_setting( 'enable_order_bumps_on_side_cart' ) === 'yes' ) {
			$result = $this->order_bumps_controller->handle_adding_order_bump_to_cart( $cart_data );
		}

		$this->out(
			array(
				'result'    => $result ? $result : cfw_update_cart( $cart_data['cart'] ?? array() ),
				'data'      => AssetManager::get_data(),
				'cart_hash' => WC()->cart->get_cart_hash(),
			)
		);
	}
}
