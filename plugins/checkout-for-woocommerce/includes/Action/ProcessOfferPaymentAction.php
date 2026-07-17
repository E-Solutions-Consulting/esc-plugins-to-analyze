<?php
/**
 * Process One-Click Upsell Offer Payment
 *
 * AJAX handler for processing post-purchase bump payments after checkout completion.
 *
 * @package Objectiv\Plugins\Checkout\Action
 */

namespace Objectiv\Plugins\Checkout\Action;

use Objectiv\Plugins\Checkout\Features\OneClick\GatewayInterface;
use Objectiv\Plugins\Checkout\Features\OneClick\GatewayRegistry;
use Objectiv\Plugins\Checkout\Features\OneClick\OfferOrderManager;
use Objectiv\Plugins\Checkout\Features\OneClick\OrderStatusManager;
use Objectiv\Plugins\Checkout\Features\OrderBumps;
use WC_Order;

/**
 * Process Offer Payment Action
 *
 * @since 10.4.0
 */
class ProcessOfferPaymentAction extends CFWAction {
	private $order_bumps_feature;

	/**
	 * Constructor
	 *
	 * @param OrderBumps $order_bumps_feature The order bumps feature class.
	 * @since 10.4.0
	 */
	public function __construct( OrderBumps $order_bumps_feature ) {
		$this->order_bumps_feature = $order_bumps_feature;
		parent::__construct( 'cfw_process_offer_payment' );
	}

	/**
	 * Execute action
	 *
	 * Processes one-click upsell payment via registered gateway.
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function action() {
		// Get and sanitize POST data
		$order_id     = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_key    = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
		$bump_id      = isset( $_POST['bump_id'] ) ? absint( $_POST['bump_id'] ) : 0;
		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$qty          = isset( $_POST['qty'] ) ? absint( $_POST['qty'] ) : 1;
		$nonce        = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, 'cfw_process_offer_' . $bump_id ) ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'Security verification failed.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Validate order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'Invalid order.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Verify order key
		if ( $order->get_order_key() !== $order_key ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'Invalid order key.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Check order status - prevent processing on invalid statuses
		$restricted_statuses = array( 'refunded', 'cancelled', 'failed' );
		if ( in_array( $order->get_status(), $restricted_statuses, true ) ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'This order cannot accept offers.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Get payment method
		$payment_method = $order->get_payment_method();
		if ( empty( $payment_method ) ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'No payment method found.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Check if gateway supported
		$registry = GatewayRegistry::instance();
		if ( ! $registry->is_gateway_supported( $payment_method ) ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'Payment gateway does not support one-click upsells.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Load gateway
		$gateway = $registry->load_gateway( $payment_method );
		if ( ! $gateway ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'Failed to load payment gateway.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Get product
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'Invalid product.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Get the bump to calculate the correct offer price
		$bump = \Objectiv\Plugins\Checkout\Factories\BumpFactory::get( $bump_id );
		if ( ! $bump ) {
			$this->out(
				array(
					'success' => false,
					'message' => __( 'Invalid bump offer.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Use bump's existing price calculation method
		// Using 'view' context to ensure sale prices are applied before bump discount
		$offer_price = $bump->get_price( 'view', $variation_id );

		// Handle tax calculations
		$tax_enabled = get_option( 'woocommerce_calc_taxes' );

		// Default prices (no tax scenario)
		$price_for_gateway = $offer_price;
		$price_for_args    = $offer_price;

		if ( 'yes' === $tax_enabled && $product ) {
			if ( wc_prices_include_tax() ) {
				// Prices INCLUDE tax in store settings
				// $offer_price already includes tax (e.g., $3.00 with tax included)
				$price_excluding_tax = wc_get_price_excluding_tax( $product, array( 'price' => $offer_price ) );

				// For gateway: charge full amount with tax included
				$price_for_gateway = $offer_price; // e.g., $3.00 (tax included)

				// For order args: use tax-exclusive price so WC can calculate tax
				$price_for_args = $price_excluding_tax; // e.g., ~$2.61

			} else {
				// Prices EXCLUDE tax in store settings
				// $offer_price doesn't include tax (e.g., $3.00 without tax)
				$price_including_tax = wc_get_price_including_tax( $product, array( 'price' => $offer_price ) );

				// For gateway: charge amount including tax
				$price_for_gateway = $price_including_tax; // e.g., $3.15 ($3.00 + tax)

				// For order args: use tax-exclusive price
				$price_for_args = $offer_price; // e.g., $3.00
			}
		}

		// Build product data array (CartFlows format)
		$product_data = array(
			'id'           => $product_id,
			'variation_id' => $variation_id,
			'qty'          => $qty,
			'price'        => $price_for_gateway, // Tax-inclusive price for payment gateway
			'name'         => $product->get_name(),
			'step_id'      => $bump_id,
			'bump_id'      => $bump_id, // woo-stripe-payment gateway expects this key
			'action'       => 'cfw_offer_accepted',
			'shipping_fee' => 0,
			'args'         => array(
				'subtotal' => $price_for_args * $qty, // Tax-exclusive for order line item
				'total'    => $price_for_args * $qty, // Tax-exclusive for order line item
			),
		);

		// Debug logging for tax calculations
		cfw_debug_log( 'Offer Tax Calculation:' );
		cfw_debug_log( '- Base offer price: ' . $offer_price );
		cfw_debug_log( '- Tax enabled: ' . $tax_enabled );
		cfw_debug_log( '- Prices include tax: ' . ( wc_prices_include_tax() ? 'yes' : 'no' ) );
		cfw_debug_log( '- Gateway price (tax-inclusive): ' . $price_for_gateway );
		cfw_debug_log( '- Order args price (tax-exclusive): ' . $price_for_args );
		cfw_debug_log( '- Product data: ' . var_export( $product_data, true ) );

		/**
		 * Filter offer product data before payment processing
		 *
		 * @since 10.4.0
		 *
		 * @param array     $product_data Product data array.
		 * @param WC_Order $order Parent order.
		 */
		$product_data = apply_filters( 'cfw_offer_product_data', $product_data, $order );

		// Process payment via gateway
		$is_successful = $gateway->process_offer_payment( $order, $product_data );

		// Handle payment failure early
		if ( ! $is_successful ) {
			/**
			 * Fires when offer payment failed
			 *
			 * @since 10.4.0
			 *
			 * @param WC_Order                                             $order Parent order.
			 * @param array                                                 $product_data Product data.
			 * @param GatewayInterface $gateway Gateway instance.
			 */
			do_action( 'cfw_offer_payment_failed', $order, $product_data, $gateway );

			$this->out(
				array(
					'success' => false,
					'message' => __( 'Payment failed. Please try again.', 'checkout-wc' ),
				)
			);
			return;
		}

		// Payment successful - Add offer to order
		$result = OfferOrderManager::instance()->add_offer_to_order( $order, $product_data );

		if ( false === $result ) {
			/**
			 * Fires when offer payment succeeded but order update failed
			 *
			 * @since 10.4.0
			 *
			 * @param WC_Order $order Parent order.
			 * @param array     $product_data Product data.
			 */
			do_action( 'cfw_offer_payment_succeeded_order_update_failed', $order, $product_data );

			$this->out(
				array(
					'success' => false,
					'message' => __( 'Payment successful but failed to update order.', 'checkout-wc' ),
				)
			);
			return;
		}

		/**
		 * Fires after successful offer acceptance
		 *
		 * @since 10.4.0
		 *
		 * @param WC_Order $order Parent order.
		 * @param array     $product_data Product data.
		 */
		do_action( 'cfw_offer_accepted', $order, $product_data );

		// Mark bump as accepted using the OrderBumps method
		$this->order_bumps_feature->mark_bump_handled( $bump_id, 'accepted' );

		// Re-evaluate bumps
		$this->order_bumps_feature->update_pending_bumps( $order );

		// Complete the offer session if no more offers remain
		$next_bump = $this->order_bumps_feature->get_next_pending_bump();

		if ( ! $next_bump && $order && $order->get_status() === 'cfw-pending-offer' ) {
			OrderStatusManager::instance()->complete_offer_session( $order_id );
		}

		$this->out(
			array(
				'success' => true,
				'message' => __( 'Offer accepted successfully.', 'checkout-wc' ),
				'reload'  => true, // Signal page reload to show next bump or updated order
			)
		);
	}
}
