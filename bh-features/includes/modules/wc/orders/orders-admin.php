<?php
/**
 * AH Orders Admin
 * Admin-specific functionalities for WooCommerce Orders.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Orders_Admin' ) ) {

class AH_Orders_Admin {

    public function __construct() {

		/**
		 * Reorders the WooCommerce order status filter links 
		 */
		add_filter( 'wc_order_statuses', [$this, 'custom_reorder_wc_order_statuses'], 100);

        add_action('admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts']);

		/**
		 * Add product categories to order line items in the admin order view.
		 */
		add_action( 'woocommerce_after_order_itemmeta', [$this, 'add_product_category_to_order_item_meta'], 10, 2 );

		// add_action( 'woocommerce_order_list_table_restrict_manage_orders', [ 'AH_Date_Range_Filter', 'render_filter_ui' ]);
        // add_action( 'woocommerce_shop_order_list_table_prepare_items_query_args', [ 'AH_Date_Range_Filter', 'apply_query_args' ]);

    }

	/**
	 * Reorders the WooCommerce order status filter links 
	 * in the admin orders list for a custom display sequence.
	 */
	function custom_reorder_wc_order_statuses( $order_statuses ) {
		$lock_svg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="#555"><path d="M12 1C9.243 1 7 3.243 7 6v4H5v13h14V10h-2V6c0-2.757-2.243-5-5-5zm3 9H9V6c0-1.654 1.346-3 3-3s3 1.346 3 3v4z"/></svg>';

		$new_order = array(
			'all' => 'All',
			'pending' => 'Pending payment',
			'processing' => 'Processing',
			'send_to_telegra' => 'Send to Telegra',
			'waiting_room' => 'Waiting Room',
			'prerequisites' => 'Require Prerequisites',
			'error_review' => 'Error - Review',
			'admin_review' => 'Admin Review',
			'provider_review' => 'Provider Review ' . $lock_svg,
			'collect_payment' => 'Collect Payment',
			'fulfillment' => 'Fulfillment Required',
			'completed' => 'Completed',
			'on-hold' => 'On hold',
			'failed' => 'Failed',
			'refunded' => 'Refunded',
			'cancelled' => 'Cancelled',
		);

		$reordered_statuses = array();
		foreach ( $new_order as $key => $label ) {
			$wc_key = ( $key === 'all' ) ? 'all' : 'wc-' . $key;

			if ( $wc_key === 'all' ) {
				continue;
			}

			if ( isset( $order_statuses[ $wc_key ] ) ) {
				$reordered_statuses[ $wc_key ] = $order_statuses[ $wc_key ];
				unset( $order_statuses[ $wc_key ] );
			}
		}

		if ( ! empty( $order_statuses ) ) {
			foreach ( $order_statuses as $key => $label ) {
				$reordered_statuses[ $key ] = $label;
			}
		}

		return $reordered_statuses;
	}

	/**
	 * 
	 */
	function admin_enqueue_scripts() {
		wp_enqueue_style( 'admin-order-css', plugins_url('assets/css/admin.css', __FILE__), array(), BH_FEATURES_VERSION, 'all' );
	}

	/**
	 * Add product categories to order line items in the admin order view.
	 */
	function add_product_category_to_order_item_meta( $item_id, $item ) {
	    if ( ! $item instanceof WC_Order_Item_Product )
	        return;

	    $product = $item->get_product();
	    if ( ! $product )
	        return;

	    $product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	    $categories = get_the_terms( $product_id, 'product_cat' );
	    if ( is_wp_error( $categories ) || empty( $categories ) )
	        return;

	    $category_names = wp_list_pluck( $categories, 'name' );
	    echo '<div class="wc-order-item-categories" style="color:#888"><strong>' . esc_html__( 'Categories:', 'your-textdomain' ) . '</strong> ' . esc_html( implode( ', ', $category_names ) ) . '</div>';
	}

}
/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new AH_Orders_Admin();
});

}