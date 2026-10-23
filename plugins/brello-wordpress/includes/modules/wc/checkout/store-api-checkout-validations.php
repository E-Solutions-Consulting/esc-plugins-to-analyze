<?php
/**
 * Store API checkout validations.
 *
 * Stripe express checkout (Apple Pay, Google Pay, Link) places the order through
 * POST /wc/store/v1/checkout, which never fires the classic checkout hooks, so
 * none of our checkout rules ran there. This runs the shared validators
 * (bh_checkout_shared_validators()) on that path and refuses the order before
 * the draft order is created and before payment.
 *
 * Stripe prints that refusal in the first .woocommerce-notices-wrapper of the page;
 * on product pages that wrapper spans the full width, so it is centered here.
 */

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bh_Store_Api_Checkout_Validations' ) ) {

	class Bh_Store_Api_Checkout_Validations {

		const LOG_SOURCE = 'bh-store-api-checkout';
		const ERROR_CODE = 'bh_checkout_validation_failed';

		/** Stylesheet of the Stripe gateway's express checkout buttons. */
		const STRIPE_STYLE = 'wc_stripe_express_checkout_style';

		public function __construct() {
			// Fires only when the order is placed (POST), after the customer is filled from the request
			// and before the draft order is built from the cart (WC 11 Checkout::process_order).
			// Priority 5: before Subscriptions saves the submitted addresses on the subscription of a
			// renewal cart (WCS_Cart_Renewal, priority 10), so a refused address is never stored.
			add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'validate' ), 5, 2 );

			// After the Stripe gateway enqueues its express checkout style (wp_enqueue_scripts, priority 10).
			add_action( 'wp_enqueue_scripts', array( $this, 'add_notice_style' ), 20 );
		}

		/**
		 * @param WC_Customer     $customer Customer filled from the request.
		 * @param WP_REST_Request $request  Checkout request.
		 * @throws RouteException When a shared validator reports an error.
		 */
		public function validate( $customer, $request ) {
			/**
			 * Kill switch for the shared validators on the Store API checkout.
			 *
			 * @param bool $enabled Default true.
			 */
			if ( ! apply_filters( 'bh_store_api_checkout_validations_enabled', true ) ) {
				return;
			}
			if ( ! $customer instanceof WC_Customer ) {
				return;
			}
			// Paying an existing order (/checkout/{id}) does not use the cart these rules read; the
			// classic order-pay page does not run them either: the order was checked when it was placed.
			if ( $request instanceof WP_REST_Request && preg_match( '#/checkout/\d+/?$#', $request->get_route() ) ) {
				return;
			}

			$errors = bh_checkout_collect_validation_errors( bh_checkout_fields_from_customer( $customer ) );
			if ( empty( $errors ) ) {
				return;
			}

			$product_ids = array();
			if ( WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $item ) {
					$product_ids[] = (int) ( $item['variation_id'] ?: $item['product_id'] );
				}
			}
			wc_get_logger()->info(
				'Store API checkout refused by shared validators.',
				array(
					'source'      => self::LOG_SOURCE,
					'user_id'     => get_current_user_id(),
					'product_ids' => $product_ids,
					'validators'  => array_values( array_unique( array_column( $errors, 'source' ) ) ),
				)
			);

			throw new RouteException(
				self::ERROR_CODE,
				implode( ' ', array_values( array_unique( array_column( $errors, 'message' ) ) ) ),
				400
			);
		}

		/**
		 * Centers the notices wrapper on product pages, where express checkout errors are printed.
		 */
		public function add_notice_style() {
			if ( ! is_product() || ! wp_style_is( self::STRIPE_STYLE, 'enqueued' ) ) {
				return;
			}

			wp_add_inline_style(
				self::STRIPE_STYLE,
				'.single-product .woocommerce-notices-wrapper:not(:empty){box-sizing:border-box;max-width:1200px;margin:1.5rem auto 0;padding:0 1rem;}'
			);
		}
	}

	new Bh_Store_Api_Checkout_Validations();
}
