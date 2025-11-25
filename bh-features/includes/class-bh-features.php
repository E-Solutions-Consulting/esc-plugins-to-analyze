<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Features
 * @subpackage Bh_Features/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Bh_Features
 * @subpackage Bh_Features/includes
 * @author     Jaime <jaime@solutionswebonline.com>
 */
class Bh_Features {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Bh_Features_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'BH_FEATURES_VERSION' ) ) {
			$this->version = BH_FEATURES_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'bh-features';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Bh_Features_Loader. Orchestrates the hooks of the plugin.
	 * - Bh_Features_i18n. Defines internationalization functionality.
	 * - Bh_Features_Admin. Defines all hooks for the admin area.
	 * - Bh_Features_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bh-features-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bh-features-i18n.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bh-features-common.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bh-features-emails.php';

		/**
		 * The class responsible for FriendBuy integration
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/friendbuy/class-bh-friendbuy.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/wc-checkout/bh-us-phone-standardization.php';
		
		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-bh-features-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-bh-features-public.php';

		$this->loader = new Bh_Features_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Bh_Features_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Bh_Features_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_common = new Bh_Features_Common();
		$plugin_emails	=	new Bh_Features_Emails($plugin_common);
		$plugin_admin = new Bh_Features_Admin( $this->get_plugin_name(), $this->get_version(), $plugin_common );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		/**
		 * Reorders the WooCommerce order status filter links 
		 */
		$this->loader->add_filter( 'wc_order_statuses', $plugin_admin, 'custom_reorder_wc_order_statuses', 100);

		$this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'add_widget_telegra_metabox', 1 );

		$this->loader->add_action( 'init', $plugin_admin, 'add_customer_services_role' );
		$this->loader->add_action( 'init', $plugin_admin, 'add_tracking_master_role' );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'register_remove_menu_admin', 1100 );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'tracking_master_remove_menu_admin', 1100 );
		$this->loader->add_action( 'admin_head', $plugin_admin, 'tracking_master_hide_action_buttons', 999 );
		$this->loader->add_action( 'save_post_shop_order', $plugin_admin, 'tracking_master_deny_save_order', 0 );
		$this->loader->add_action( 'save_post_shop_subscription', $plugin_admin, 'tracking_master_deny_save_order_suscription', 0 );
		$this->loader->add_action( 'before_delete_post', $plugin_admin, 'tracking_master_restric_edit_shop_order_subscription');
		
		$this->loader->add_filter( 'login_redirect', $plugin_admin, 'force_redirect_for_customer_services', 999, 3 );

		$this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu' );

		/*
		*	My Account Menu - remove downloads menu
		*/
		$this->loader->add_action( 'woocommerce_account_menu_items', $plugin_admin, 'hide_menu_downloads_my_account' );

		/**
		 * Add Custom Field Checkbox: Apply for Subscription Renewals
		 * 
		 */
		$this->loader->add_action( 'woocommerce_coupon_options', $plugin_admin, 'add_custom_field_coupon_apply_to_renewal_subscription', 10, 2 );
		$this->loader->add_action( 'woocommerce_coupon_options_save', $plugin_admin, 'add_custom_field_coupon_apply_to_renewal_subscription_save', 10, 2 );

		/**
		 * Add Subscription Filter by State
		 */
		$this->loader->add_action( 'woocommerce_order_list_table_restrict_manage_orders', $plugin_admin, 'state_filter_for_subscriptions', 1000, 1 );
		$this->loader->add_filter( 'woocommerce_shop_subscription_list_table_prepare_items_query_args', $plugin_admin, 'filter_subscriptions_by_state', 11 );

		/**
		 * Add Order, Subscription Filter by Date Range
		 */
		$this->loader->add_action( 'woocommerce_order_list_table_restrict_manage_orders', $plugin_admin, 'date_range_filter_to_orders', 1000, 1 );
		$this->loader->add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', $plugin_admin, 'handle_date_range_filter_to_orders', 11 );
		$this->loader->add_filter( 'woocommerce_shop_subscription_list_table_prepare_items_query_args', $plugin_admin, 'handle_date_range_filter_to_orders', 11 );

		/**
		 * Cancel Subscription if the state is CT
		 */
		add_filter('wcs_renewal_order_created', [$plugin_admin, 'cancel_subscription_renewal_if_state_is_ct'], 10, 2);

		add_filter( 'woocommerce_can_subscription_be_updated_to_active', [ $plugin_admin, 'woocommerce_can_subscription_be_updated_to_active' ], 99, 2 );
		add_action('admin_init', [ $plugin_admin, 'subscription_actions']);
		/**
		 * Send email notification to customer
		 * to select a new product for their subscription
		 */
		add_action('init', [$plugin_admin, 'hb_init_switch_product_subscription']);
		add_action('woocommerce_admin_order_data_after_order_details', [$plugin_admin, 'hb_woocommerce_admin_order_data_after_order_details']);
		add_action( 'current_screen',[$plugin_admin, 'hb_current_screen'] );
		/*
		 * Add Option to Subscription Actions to 
		 * Send email notification to customer
		 * to select a new product for their subscription
	 	 */
		add_filter('woocommerce_order_actions', [$plugin_admin, 'hb_woocommerce_order_actions']);
		add_action('woocommerce_order_action_send_switch_product_email', [$plugin_admin, 'hb_woocommerce_order_action_send_switch_product_email']);
		add_action('woocommerce_order_action_resend_to_telegra', [$plugin_admin, 'hb_woocommerce_order_action_resend_to_telegra']);

		/**
		 * Change the text of Interval Renewals
		 */
		$this->loader->add_filter( 'woocommerce_subscription_price_string', $plugin_admin, 'change_subscription_price_text', 10, 2 );

		/**
		 * Order Limit
		 */
		$this->loader->add_action('admin_init', $plugin_admin, 'register_order_limit_settings' );
		$this->loader->add_action('admin_notices', $plugin_admin, 'admin_notices_is_order_restricted' );
		
		/**
		 * Schedule Settings
		 */
		$this->loader->add_action('update_option_start_time', $plugin_admin, 'handle_schedule_settings_update', 10, 3);
    	$this->loader->add_action('update_option_end_time', $plugin_admin, 'handle_schedule_settings_update', 10, 3);

		/**
		 * Add custom Column to ListTable Coupon
		 */
		$this->loader->add_filter('manage_edit-shop_coupon_columns', $plugin_admin, 'custom_coupon_add_column');
		$this->loader->add_action('manage_shop_coupon_posts_custom_column', $plugin_admin, 'custom_coupon_show_column_value', 10, 2);
		
		/*	
		 * Ajax for send Shipping Address Update Request
		 */
		$this->loader->add_action('wp_ajax_send_shipping_request', $plugin_admin, 'send_shipping_request_ajax');

		/**
		 * Add Metabox for display page only for logged in
		 * */
		$this->loader->add_action('add_meta_boxes', $plugin_admin, 'add_logged_in_only_metabox');
		$this->loader->add_action('save_post', $plugin_admin, 'save_logged_in_only_metabox');
		
		/**
		 * Show App Tracking Fields in User Profile
		 * */
		$this->loader->add_action('show_user_profile', $plugin_admin, 'show_app_tracking_profile_fields');
		$this->loader->add_action('edit_user_profile', $plugin_admin, 'show_app_tracking_profile_fields');

		

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_common = new Bh_Features_Common();
		$plugin_public = new Bh_Features_Public( $this->get_plugin_name(), $this->get_version(), $plugin_common );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		$this->loader->add_action( 'init', $plugin_public, 'disable_password_change_admin_notification' );

		$this->loader->add_action( 'login_enqueue_scripts', $plugin_public, 'custom_login_styles_and_layout' );
		$this->loader->add_action( 'login_header', $plugin_public, 'custom_login_start' );
		$this->loader->add_action( 'login_footer', $plugin_public, 'custom_login_end' );
		
		remove_action('woocommerce_add_to_cart_redirect', 'wc_add_to_cart_message', 10);
		
		add_action( 'woocommerce_checkout_before_order_review', [ $plugin_public, 'add_billing_shipping_summary' ], 20 );

		$this->loader->add_action( 'woocommerce_order_review', $plugin_public, 'woocommerce_review_order_before_cart_contents', 1 );
		add_filter('woocommerce_cart_item_name', [ $plugin_public, 'bh_woocommerce_cart_item_name'], 10, 3);

		add_filter('woocommerce_add_to_cart_redirect', [ $plugin_public, 'bh_woocommerce_add_to_cart_redirect']);

		add_filter('woocommerce_states', [ $plugin_public, 'restrict_us_states']);
		add_filter('gettext', [ $plugin_public, 'change_ship_to_different_address_text'], 20, 3);
		
		add_shortcode('modal_single_product', [ $plugin_public, 'hb_modal_single_product']);

		add_action('woocommerce_before_checkout_process', [ $plugin_public, 'associate_existing_customer_checkout'], 20);
		add_action('woocommerce_checkout_process', [ $plugin_public, 'validate_logged_in_user_restrictions']);

		add_action('woocommerce_checkout_process', [ $plugin_public, 'restrict_one_product_per_email']);
		add_action('woocommerce_checkout_process', [ $plugin_public, 'restrict_one_product_per_phone']);
		add_action('woocommerce_after_checkout_validation', [ $plugin_public, 'restrict_po_boxes_in_checkout'], 10, 2);

		add_action( 'woocommerce_product_after_variable_attributes', [ $plugin_public, 'variation_settings'], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $plugin_public, 'save_variation_settings'], 10, 2 );

		add_filter('woocommerce_is_sold_individually', [ $plugin_public, 'force_individual_products_cart'], 10, 2);

		add_action('woocommerce_after_checkout_validation', [ $plugin_public, 'restrict_shipping_states'], 10, 2);
		add_action('wp_enqueue_scripts', [ $plugin_public, 'enqueue_google_places_and_states']);

		add_action('wp_footer', [ $plugin_public, 'enqueue_quiz_styles']);
		add_shortcode('print_graphic', [ $plugin_public, 'print_graphic_shortcode']);
		add_filter( 'qsm_display_before_form', [$plugin_public, 'hb_qsm_display_before_form'], 100, 3);

		add_action('woocommerce_admin_order_data_after_order_details', [ $plugin_public, 'render_pause_subscription_metabox'], 10, 1);
		add_action('woocommerce_process_shop_order_meta', [ $plugin_public, 'save_pause_subscription_status']);
		add_action('woocommerce_order_status_completed', [ $plugin_public, 'remove_status_pause_subscription_renewal_payment_completed'], 10, 2);
		
		add_filter('woocommerce_checkout_fields', [ $plugin_public, 'bh_woocommerce_checkout_fields_phone_validation']);
		add_action('woocommerce_checkout_process', [ $plugin_public, 'bh_woocommerce_checkout_process_field_phone_validation']);

		add_action('woocommerce_order_status_changed', [ $plugin_public, 'order_renewal_payment_completed_send_to_telegram'], 10, 4);

		add_filter('woocommerce_checkout_fields', [ $plugin_public, 'bh_woocommerce_checkout_fields_kl_newsletter_checkbox'], 99999);

		$this->loader->add_action( 'woocommerce_subscription_renewal_payment_failed', $plugin_public, 'woocommerce_subscription_renewal_payment_failed', 10, 2 );
		
		add_filter('wcs_renewal_order_created', [$plugin_public, 'validate_previous_order_status_before_renewal'], 10, 2);

		add_filter( 'woocommerce_order_item_name', [$plugin_public, 'hb_woocommerce_order_item_name'], 10, 3 );
		add_filter( 'woocommerce_display_item_meta', [$plugin_public, 'hb_woocommerce_display_item_meta'], 10, 3 );
		/**
		 * Add product categories to order line items in the admin order view.
		 */
		$this->loader->add_action( 'woocommerce_after_order_itemmeta', $plugin_public, 'add_product_category_to_order_item_meta', 10, 2 );

		/**
		 * Remove the Error Messages from top of checkout pages
		 */
		add_action('init', [ $plugin_public, 'hb_init_remove_wc_hooks'], 999999);

		/**
		 * Add Terms & Conditions to Tab Checkout
		 */
		add_filter('arg-mc-init-options', [ $plugin_public, 'bh_arg_mc_init_options_add_step_terms_conditions']);
		add_action('arg-mc-checkout-step', [ $plugin_public, 'bh_arg_mc_checkout_step_add_content_terms_conditions']);
		add_action('woocommerce_checkout_process', [ $plugin_public, 'bh_woocommerce_checkout_process_field_accept_terms']);
		
		/**
		 * Disable custom Variation Name
		 */
		add_filter( 'woovr_variation_get_name', [$plugin_public, 'hb_woovr_variation_get_name'], 100 );
		
		add_action('admin_enqueue_scripts', [ $plugin_public, 'custom_script_order_edit']);
		add_action('woocommerce_admin_order_data_after_billing_address', [ $plugin_public, 'admin_warning_if_billing_state_restricted']);
		
		/**
		 * Allow Free Orders when use a coupon
		 */
		add_filter( 'woocommerce_cart_needs_payment', [ $plugin_public, 'disable_payment_for_free_orders'], 10000, 2 );
		add_action('woocommerce_order_status_changed', [ $plugin_public, 'change_to_on_hold_free_orders'], 10, 3);
			
		/**
		 * Rename the Product name of a new Order 
		 * When a new Renewal order is created
		 */
		add_filter( 'wcs_new_order_created', [ $plugin_public, 'wcs_new_order_created_update_product_name'], 100, 3 );
		/**
		 * Rename the Product name of Subscription
		 * When a renewal order is completed
		 */
		add_action('woocommerce_order_status_completed', [ $plugin_public, 'edited_product_item_name_in_subscription_renewal_payment_completed']);

		/**
		 * Add webhook for listen events
		 * - /wp-json/bh/stripe/webhook
		 * 
		 */
		add_action('rest_api_init', [$plugin_public, 'register_api_routes'], 15);

		/**
		 *	Remove Notice about success add to cart product
		 */
		add_filter( 'woocommerce_notice_types', [$plugin_public, 'woocommerce_notice_types'], 100);
		
		/**
		 *	Add tracking code to Thankyou Page
		 */
		//add_filter('telemd_thankyou_redirect_enabled', '__return_false');
		add_filter('telemd_thankyou_redirect_enabled', [$plugin_public, 'disable_thankyou_redirect'], 10, 2);
		add_action('woocommerce_thankyou', [$plugin_public, 'execute_tracking_and_redirect'], 999);
		add_action('wp_footer', [$plugin_public, 'insert_katalys_tracking_script_footer'], 999);

		/*
		*	Print Tracking Order Number
		*/
		add_shortcode('bh_display_shipment_info', [ $plugin_public, 'display_shipment_info_shortcode']);

		/*
		*	Coupons
		*	- Set coupon for Renewal Order Created
		*	- Apply coupon for Renewal Order
		*/
		$this->loader->add_action( 'woocommerce_checkout_subscription_created', $plugin_public, 'set_coupon_for_subscription_renewal_created', 10, 2 );
		$this->loader->add_filter( 'wcs_renewal_order_created', $plugin_public, 'apply_coupon_for_subscription_renewal_order_created', 10, 2 );

		/**
		 * Next Payment Date
		 *	-	Update Subscriptin Next Payment Date when a Renewal Order is completed
		 *	-	Edit Next Payment Date when the subscription is created from Checkout
		 */
		$this->loader->add_action('woocommerce_order_status_completed', $plugin_public, 'update_subscription_next_payment_date', 10, 2);		
		$this->loader->add_action('woocommerce_checkout_subscription_created', $plugin_public, 'set_plan_days_to_new_subscription', 10, 3);

		/**
		 *	Add tracking code Gtag to Thankyou Page
		 */
		$this->loader->add_action('wp_footer', $plugin_public, 'wp_footer_print_tracking_thankyou', 100);

		/**
		 *	Add tracking code fbq to Thankyou Page
		 */
		$this->loader->add_action('wp_footer', $plugin_public, 'insert_fbq_tracking_script_footer', 100);
		/**
		 *	Add tracking code vibeq to Thankyou Page
		 */
		$this->loader->add_action('wp_footer', $plugin_public, 'insert_vibe_pixel_tracking', 100);
		

		/*
		*	Rules Add To Cart
		*/
		$this->loader->add_filter( 'woocommerce_add_to_cart_validation', $plugin_public, 'strict_cart_restrictions', 30, 6);

		/*
		*	Print Custom Text depend of Subscription Variation
		*/
		$this->loader->add_filter( 'arg-mc-init-options', $plugin_public, 'arg_mc_init_options' );
		add_shortcode('_bh_disclaimer_plan_selected', [ $plugin_public, 'disclaimer_plan_selected_shortcode']);

		/*
		*	Updates the status of an upsell order to "processing" when its associated parent order is marked as "completed." 
		*/
		$this->loader->add_action('woocommerce_order_status_completed', $plugin_public, 'process_upsell_when_main_order_completed');

		/**
		 * Filters the formatted line subtotal in WooCommerce orders to append custom renewal text 
		 * for subscription products based on their billing interval.
		 */
		$this->loader->add_filter( 'woocommerce_order_formatted_line_subtotal', $plugin_public, 'woocommerce_order_formatted_line_subtotal', 10, 3 );

		/**
		 * Close session after purchase
		 */
		$this->loader->add_action('woocommerce_checkout_order_processed', $plugin_public, 'custom_checkout_session_validation', 999, 3);
		$this->loader->add_action('wp_footer', $plugin_public, 'custom_post_purchase_logout_handler', 100);

		/**
		 * Edit Upsell template for add product content
		 * 
		 */
		$this->loader->add_filter('cuw_offer_processed_data', $plugin_public, 'cuw_offer_processed_data', 10, 3);
		add_shortcode('bh_upsell_content', [ $plugin_public, 'shortcode_bh_upsell_content']);

		/**
		 * Modify the subscription renewal text in the subscription item totals display.
		 */
		$this->loader->add_filter( 'woocommerce_get_subscription_item_totals', $plugin_public, 'modify_subscription_renewal_text', 10, 3 );

		/**
		 * Shortcodes
		 */
		add_shortcode('bh_order_limit_is_activated', [ $plugin_public, 'shortcode_order_limit_is_activated']);
		add_shortcode('bh_product_variations', [ $plugin_public, 'brello_product_variations_form_shortcode']);

		/**
		 *	Add tracking code Northbeam to Thankyou Page
		 */
		$this->loader->add_action('wp_head', $plugin_public, 'insert_friendbuy_tracking_customer', 999);
		$this->loader->add_action('wp_footer', $plugin_public, 'insert_friendbuy_tracking', 100);
		
		/**
		 * Page Restriction for non-logged-in users
		 * */
		$this->loader->add_action('template_redirect', $plugin_public, 'check_page_access');

		/**
		 * US Phone Number Standardization
		 * */
		/*
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts_phone_validations');
        $this->loader->add_filter('woocommerce_billing_fields', $plugin_public, 'modify_billing_phone_field');
        $this->loader->add_action('woocommerce_checkout_process', $plugin_public, 'validate_phone_number');
        $this->loader->add_filter('woocommerce_process_myaccount_field_billing_phone', $plugin_public, 'format_phone_before_save');
        $this->loader->add_action('woocommerce_after_checkout_validation', $plugin_public, 'validate_checkout_phone', 10, 2);


		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts_mobile_phone_validation');
        $this->loader->add_filter('woocommerce_billing_fields', $plugin_public, 'modify_billing_mobile_phone_field');
        $this->loader->add_filter('woocommerce_checkout_fields', $plugin_public, 'add_mobile_phone_to_checkout');
        $this->loader->add_action('woocommerce_checkout_process', $plugin_public, 'validate_mobile_phone_number');
        $this->loader->add_action('woocommerce_after_checkout_validation', $plugin_public, 'validate_checkout_mobile_phone', 10, 2);
        $this->loader->add_action('woocommerce_checkout_update_order_meta', $plugin_public, 'save_mobile_phone_field');
        $this->loader->add_action('woocommerce_customer_save_address', $plugin_public, 'save_mobile_phone_customer', 10, 2);
        
        // Mostrar campo en admin
        $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $plugin_public, 'display_mobile_phone_in_admin', 10, 1);
		*/

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Bh_Features_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
