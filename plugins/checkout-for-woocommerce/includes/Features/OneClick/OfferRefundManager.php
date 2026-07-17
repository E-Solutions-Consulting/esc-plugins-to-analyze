<?php
/**
 * One-Click Upsell Offer Refund Manager
 *
 * Handles refunding accepted one-click offers via WooCommerce admin metabox.
 * Mirrors CartFlows Pro's refund implementation.
 *
 * @package Objectiv\Plugins\Checkout\Features\OneClick
 */

namespace Objectiv\Plugins\Checkout\Features\OneClick;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Internal\DependencyManagement\ContainerException;

/**
 * Offer Refund Manager Class
 *
 * @since 10.4.0
 */
class OfferRefundManager {
	/**
	 * Singleton instance
	 *
	 * @since 10.4.0
	 * @var OfferRefundManager
	 */
	private static $instance;

	/**
	 * Get singleton instance
	 *
	 * @since 10.4.0
	 * @return OfferRefundManager
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 10.4.0
	 */
	private function __construct() {
		// Register metabox
		add_action( 'add_meta_boxes', array( $this, 'add_offer_refund_meta_box' ) );

		// Register AJAX handler
		add_action( 'wp_ajax_cfw_admin_refund_offer', array( $this, 'process_offer_refund' ) );

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add offer refund metabox
	 *
	 * Registers metabox on order edit screen if order has accepted offers.
	 *
	 * @param string $post_type Current post type.
	 *
	 * @return void
	 * @throws ContainerException The exception.
	 * @since 10.4.0
	 */
	public function add_offer_refund_meta_box( $post_type ) {
		if ( 'shop_order' !== $post_type && 'woocommerce_page_wc-orders' !== $post_type ) {
			return;
		}

		global $post;

		// Get order ID (handles both post-based and HPOS orders)
		$order_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : ( $post ? $post->ID : 0 ); // phpcs:ignore

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if order has offers
		if ( ! $this->order_has_offers( $order ) ) {
			return;
		}

		// Determine the correct screen for HPOS vs legacy
		$screen = wc_get_container()->get( CustomOrdersTableController::class )
			->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'cfw-offer-refund',
			__( 'CheckoutWC: Refund Post Purchase One-Click Order Bumps', 'checkout-wc' ),
			array( $this, 'render_metabox' ),
			$screen,
			'normal',
			'high'
		);
	}

	/**
	 * Check if order has offers
	 *
	 * @since 10.4.0
	 *
	 * @param \WC_Order $order Order object.
	 * @return bool True if order has offers, false otherwise.
	 */
	protected function order_has_offers( $order ) {
		return (bool) $order->get_meta( '_cfw_has_offer', true );
	}

	/**
	 * Render metabox
	 *
	 * Displays offer items with refund buttons.
	 *
	 * @since 10.4.0
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post object or Order object (HPOS).
	 * @return void
	 */
	public function render_metabox( $post_or_order ) {
		// Get order (handles both post-based and HPOS orders)
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		// Include view template
		$view_file = dirname( __DIR__, 2 ) . '/Admin/Views/html-refund-offer.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * Process offer refund
	 *
	 * AJAX handler for refunding offers.
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function process_offer_refund() {
		// Verify nonce
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfw_admin_refund_offer' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'checkout-wc' ) ) );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'checkout-wc' ) ) );
			return;
		}

		// Get parameters
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$bump_id  = isset( $_POST['bump_id'] ) ? absint( $_POST['bump_id'] ) : 0;
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		// Validate order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'checkout-wc' ) ) );
			return;
		}

		// Get order item
		$item = $order->get_item( $item_id );
		if ( ! $item ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order item.', 'checkout-wc' ) ) );
			return;
		}

		// Check if already refunded
		if ( wc_get_order_item_meta( $item_id, '_cfw_refunded', true ) === 'yes' ) {
			wp_send_json_error( array( 'message' => __( 'This offer has already been refunded.', 'checkout-wc' ) ) );
			return;
		}

		// Get transaction ID
		$transaction_id = $order->get_meta( 'cfw_offer_txn_resp_' . $bump_id, true );
		if ( empty( $transaction_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No transaction ID found for this offer.', 'checkout-wc' ) ) );
			return;
		}

		// Calculate refund amount
		$refund_amount = $item->get_total() + $item->get_total_tax();

		// Get payment gateway
		$payment_method = $order->get_payment_method();
		$registry       = GatewayRegistry::instance();

		if ( ! $registry->is_gateway_supported( $payment_method ) ) {
			wp_send_json_error( array( 'message' => __( 'Payment gateway does not support one-click upsell refunds.', 'checkout-wc' ) ) );
			return;
		}

		$gateway = $registry->load_gateway( $payment_method );
		if ( ! $gateway ) {
			wp_send_json_error( array( 'message' => __( 'Failed to load payment gateway.', 'checkout-wc' ) ) );
			return;
		}

		// Process API refund if supported
		$refund_txn_id = false;
		if ( $gateway->is_api_refund() ) {
			$offer_data = array(
				'transaction_id' => $transaction_id,
				'refund_amount'  => $refund_amount,
				'bump_id'        => $bump_id,
			);

			$refund_txn_id = $gateway->process_offer_refund( $order, $offer_data );

			if ( false === $refund_txn_id ) {
				wp_send_json_error( array( 'message' => __( 'Gateway refund failed.', 'checkout-wc' ) ) );
				return;
			}
		}

		// Build line items for WooCommerce refund
		$line_items = array(
			$item_id => array(
				'qty'          => $item->get_quantity(),
				'refund_total' => $item->get_total(),
				'refund_tax'   => array_sum( $item->get_taxes()['total'] ),
			),
		);

		// Create WooCommerce refund
		$refund = wc_create_refund(
			array(
				'amount'         => $refund_amount,
				'reason'         => sprintf( __( 'Order Bump Refund: %s', 'checkout-wc' ), $item->get_name() ),
				'order_id'       => $order_id,
				'refund_payment' => false, // Already processed via gateway
				'line_items'     => $line_items,
				'restock_items'  => true,
			)
		);

		if ( is_wp_error( $refund ) ) {
			wp_send_json_error( array( 'message' => $refund->get_error_message() ) );
			return;
		}

		// Mark item as refunded
		wc_update_order_item_meta( $item_id, '_cfw_refunded', 'yes' );

		// Add order note
		$order->add_order_note(
			sprintf(
				__( 'Order Bump Refunded: %1$s - Amount: %2$s - Transaction ID: %3$s', 'checkout-wc' ),
				$item->get_name(),
				wc_price( $refund_amount ),
				$refund_txn_id ?: $transaction_id
			)
		);

		/**
		 * Fires after offer refunded successfully
		 *
		 * @since 10.4.0
		 *
		 * @param \WC_Order       $order Order object.
		 * @param \WC_Order_Item  $item Order item.
		 * @param int             $bump_id Bump ID.
		 * @param string          $refund_txn_id Refund transaction ID.
		 */
		do_action( 'cfw_offer_refunded', $order, $item, $bump_id, $refund_txn_id );

		wp_send_json_success(
			array(
				'message' => __( 'Offer refunded successfully.', 'checkout-wc' ),
			)
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads CSS and JS for refund metabox.
	 *
	 * @since 10.4.0
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on order edit screen
		if ( 'post.php' !== $hook && 'woocommerce_page_wc-orders' !== $hook ) {
			return;
		}

		global $post;

		// Get order ID (handles both post-based and HPOS orders)
		$order_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : ( $post ? $post->ID : 0 );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->order_has_offers( $order ) ) {
			return;
		}

		// Register and enqueue scripts
		cfw_register_scripts( array( 'admin-offer-refund' ) );
		wp_enqueue_script( 'cfw-admin-offer-refund' );

		// Localize script
		wp_localize_script(
			'cfw-admin-offer-refund',
			'cfwOfferRefund',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cfw_admin_refund_offer' ),
				'i18n'    => array(
					'confirmRefund' => __( 'Are you sure you want to refund this offer? This action cannot be undone.', 'checkout-wc' ),
					'processing'    => __( 'Processing refund...', 'checkout-wc' ),
					'refunded'      => __( 'Refunded', 'checkout-wc' ),
				),
			)
		);
	}
}
