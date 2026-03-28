<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Integration
 * @subpackage Bh_Integration/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Bh_Integration
 * @subpackage Bh_Integration/public
 * @author     Jaime Isidro <jaime@solutionswebonline.com>
 */
class Bh_Integration_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	private $category_supplements;


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->category_supplements = 'supplements-external';

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bh_Integration_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Integration_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bh-integration-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bh_Integration_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Integration_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bh-integration-public.js', array( 'jquery' ), $this->version, false );

	}
	
	public function check_permissions($permission, $context, $object_id, $post_type) {	
		$user 		=	wp_get_current_user();
		$endpoint 	=	$_SERVER['REQUEST_URI'];
		$username	=	isset($user->ID)? $user->ID:0;
		$log		=	[$_SERVER['REMOTE_ADDR'], $username, $_SERVER['REQUEST_URI']];
		//bh_plugins_log(implode(' ', $log), 'bh_plugins-integration-permissions');
		if (in_array('api_vendor', $user->roles)) {
			$new_permission	=	false;			
			if (
				strpos($endpoint, '/wp-json/wc/v3/products') !== false || 
				strpos($endpoint, '/wp-json/wc/v3/orders') !== false 
			) {
				$new_permission	=	true;
			}
			return $new_permission;
		}
		return $permission;
	}

	public function restrict_product_query($args, $request) {
		$user = wp_get_current_user();
		if (in_array('api_vendor', $user->roles)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => [$this->category_supplements],
			);
		}
		return $args;
	}

	public function restrict_order_object_query($args, $request) {
		try {
			$user = wp_get_current_user();			
			if (!in_array('api_vendor', $user->roles))
				return $args;

			$product_ids = get_posts([
				'post_type' => ['product', 'product_variation'],
				'fields'    => 'ids',
				'numberposts' => -1,
				'tax_query' => [
					[
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => [$this->category_supplements], 
					],
				],
			]);

			if (empty($product_ids)) {
				$args['post__in'] = [0];
				return $args;
			}

			add_filter('woocommerce_rest_prepare_shop_order_object', function ($response, $object, $request) use ($product_ids) {
				$items = $object->get_items();
				$has_supplement = false;
		
				foreach ($items as $item) {
					$product_id = $item->get_product_id();
					if (in_array($product_id, $product_ids)) {
						$has_supplement = true;
						break;
					}
				}
				return $has_supplement ? $response : null;
			}, 10, 3);
			
		} catch (Exception $e) {
			//
		}
		return $args;
	}

	/**
	 * Limit Limiting Rate for Rest API
	 * */
	public function limit_request_to_rest_api($is_rest_api_request){
		try {
		    if ($is_rest_api_request) {
		    	//bh_plugins_log(['limit_request_to_rest_api', $_SERVER], 'bh_plugins-integration-limit');
		    	$log	=	[$_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']];
				//bh_plugins_log(implode(' ', $log), 'bh_plugins-integration-limit');
		        $ip 			=	$_SERVER['REMOTE_ADDR'];
		        $transient_key 	=	'wc_rest_rate_limit_' . $ip;
		        $requests		=	get_transient($transient_key) ?: 0;
		        
		        if ($requests > 100) {
		            status_header(429);
		            exit('Too Many Requests');
		        }	        
		        set_transient($transient_key, $requests + 1, MINUTE_IN_SECONDS);
		    }
	    } catch (Exception $e) {
			
		}
	    return $is_rest_api_request;
	}

}
