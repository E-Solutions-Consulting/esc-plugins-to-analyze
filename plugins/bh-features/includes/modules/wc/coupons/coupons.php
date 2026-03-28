<?php
/**
 * AH_Coupons
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Coupons' ) ) {

class AH_Coupons {

    /**
     * Constructor.
     */
    public function __construct() {
        /**
		 * Add custom Column to ListTable Coupon
		 */
		add_filter('manage_edit-shop_coupon_columns', [$this, 'custom_coupon_add_column']);
		add_action('manage_shop_coupon_posts_custom_column', [$this, 'custom_coupon_show_column_value'], 10, 2);

        /**
		 * Add Custom Field Checkbox: Apply for Subscription Renewals
		 */
		// add_action( 'woocommerce_coupon_options', [$this, 'add_custom_field_coupon_apply_to_renewal_subscription'], 10, 2 );
		// add_action( 'woocommerce_coupon_options_save', [$this, 'add_custom_field_coupon_apply_to_renewal_subscription_save'], 10, 2 );

		/*
		*	Coupons
		*	- Set coupon for Renewal Order Created
		*	- Apply coupon for Renewal Order
		*/
		// add_action( 'woocommerce_checkout_subscription_created', [$this, 'set_coupon_for_subscription_renewal_created'], 10, 2 );
		// add_filter( 'wcs_renewal_order_created', [$this, 'apply_coupon_for_subscription_renewal_order_created'], 10, 2 );

	}

    /**
	 * Add custom Column to ListTable Coupon
	 * for display the subscription applied the coupon
	 * */
	function custom_coupon_add_column($columns) {
	    $columns['applied_to_subscription'] = __('Applied to', 'ah-features');
	    return $columns;
	}

	function custom_coupon_show_column_value($column, $post_id) {
	    if ($column !== 'applied_to_subscription')
	    	return ;

	    $coupon_code = wc_get_coupon_code_by_id($post_id);
	    if(!$coupon_code)
	    	return ;

		global $wpdb;

		$sql	=	"SELECT DISTINCT order_items.order_id as id, o.type
			FROM {$wpdb->prefix}woocommerce_order_items as order_items
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_itemmeta             
			ON order_items.order_item_id = order_itemmeta.order_item_id
            INNER JOIN {$wpdb->prefix}wc_orders as o 
            ON o.id	=	order_items.order_id             
			WHERE order_items.order_item_type = 'coupon' 
			AND order_itemmeta.meta_value LIKE '%$coupon_code%' 
			 ORDER BY o.type DESC";

		$orders = $wpdb->get_results( $sql );

        if (!empty($orders)) {
			$links = [];
            foreach ($orders as $order) {
            	$page	=	'wc-orders';
            	if($order->type=='shop_subscription')
            		$page	.=	'--shop_subscription';

				$edit_url = add_query_arg( array( 
							'page' 	=>	$page, 
							'action'=>	'edit', 
							'id'	=>	$order->id
						), admin_url( 'admin.php' ) );
				$links[]	=	'<a href="'.esc_url($edit_url).'" target="_blank">' . esc_html__('#', 'ah-features'). $order->id .'</a>';
            }
			if($links) {
				echo implode(', ', $links);
			}
		}
	}

    /**
	 * Add Custom Field Checkbox: Apply for Subscription Renewals
	 * This coupon will be used for apply in subscription renewals
	 */
	function add_custom_field_coupon_apply_to_renewal_subscription($coupon_id, $coupon){
		woocommerce_wp_checkbox([
			'id'			=>	'_apply_to_subscriptions',
			'label'			=>	'Apply for Subscription Renewals',
			'description'	=>	'Check this box if the coupon will be applied to subscription renewals.',
			'value'			=>	get_post_meta($coupon_id, '_apply_to_subscriptions', true)
		]);
	}

	/**
	 * Store Custom Field: Apply for Subscription Renewals
	 * 
	 */
	function add_custom_field_coupon_apply_to_renewal_subscription_save($post_id, $coupon){
		$apply	=	isset($_POST['_apply_to_subscriptions']) ? 'yes' : 'no';
		update_post_meta($post_id, '_apply_to_subscriptions', $apply);
	}

	/*
	*	Set coupon for Renewal Order Created
	*/
	function set_coupon_for_subscription_renewal_created($subscription, $order){
		try {
			$used_coupons	=	$order->get_coupon_codes();
			if ( empty($used_coupons) )
				return ;

			$valid_coupons	=	[];
			foreach ($used_coupons as $code) {
				$coupon	=	new WC_Coupon($code);
				if(get_post_meta($coupon->get_id(), '_apply_to_subscriptions', true)==='yes'){
					$valid_coupons[]	=	$code;
				}
			}
			if(!empty($valid_coupons)){
				$subscription->update_meta_data('_custom_subscription_coupon', implode(',', $valid_coupons));
				$subscription->save();
			}

		} catch (\Throwable $th) {
			$data	=	[
				'error'		=>	$th->getMessage(),
				'function'	=>	'public:set_coupon_for_subscription_renewal_created',
				'args'		=>	func_get_args()
			];
			bh_plugins_error_log($data);
		}
	}
	/*
	*	Apply coupon for Renewal Order
	*/
	function apply_coupon_for_subscription_renewal_order_created($renewal_order, $subscription){
		try {			
			$coupon_codes	=	$subscription->get_meta('_custom_subscription_coupon');
			if(!$coupon_codes)
				return $renewal_order;

			$coupon_codes	=	explode(',', $coupon_codes);
			$applied	=	[];
			foreach ($coupon_codes as $code) {
				$coupon	=	new WC_Coupon(trim($code));
				if($coupon->get_id()){
					$renewal_order->apply_coupon($coupon);
					$applied[]	=	$code;
				}
			}
			$renewal_order->calculate_totals();

			if(!empty($applied)){
				$renewal_order->add_order_note(
					sprintf(
						'Coupon%s [%s] applied automatically from Subscription',
						count($applied) > 1? 's':'', 
						implode(', ', $applied)
					)
				);
			}


		} catch (\Throwable $th) {
			$data	=	[
				'error'		=>	$th->getMessage(),
				'function'	=>	'public:apply_coupon_for_subscription_renewal_order_created',
				'args'		=>	func_get_args()
			];
			bh_plugins_error_log($data);
		}
		return $renewal_order;
	}

}

/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new AH_Coupons();
});

}
