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

		add_filter( 'woocommerce_json_search_found_products', [ $this, 'filter_published_products_only' ] );
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


	/**
	* When searching for products while editing an order:
	* - Only shows published products (checks the parent product for variations).
	* - If a variable product has variations in the results, hides the parent product.
	*/
	public function filter_published_products_only( array $products ): array {
		$action = $_REQUEST['action'] ?? '';
		if ( ! in_array( $action, [
			'woocommerce_json_search_products_and_variations',
			'woocommerce_json_search_products',
		], true ) ) {
			return $products;
		}

		$filtered       = [];
		$parent_ids_with_variations = [];

		foreach ( $products as $id => $name ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				$parent    = wc_get_product( $parent_id );

				if ( $parent && $parent->get_status() === 'publish' ) {
					$filtered[ $id ] = $name;
					$parent_ids_with_variations[ $parent_id ] = true;
				}
			} else {
				if ( $product->get_status() === 'publish' ) {
					$filtered[ $id ] = $name;
				}
			}
		}

		foreach ( $parent_ids_with_variations as $parent_id => $_ ) {
			unset( $filtered[ $parent_id ] );
		}

		return $filtered;
	}

}
/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new AH_Orders_Admin();
});

}