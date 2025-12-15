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
 * @package    Bh_Tools
 * @subpackage Bh_Tools/includes
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
 * @package    Bh_Tools
 * @subpackage Bh_Tools/includes
 * @author     Jaime Isidro <jaime@solutionswebonline.com>
 */
class Bh_Tools {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Bh_Tools_Loader    $loader    Maintains and registers all hooks for the plugin.
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
	private $plan	=	[
		'monthly'	=>	[
					'days'		=>	25,
					'interval'	=>	1
				],
		'3-month'	=>	[
					'days'		=>	70,
					'interval'	=>	3
				]
	];

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
		if ( defined( 'BH_TOOLS_VERSION' ) ) {
			$this->version = BH_TOOLS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'bh-tools';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		$this->loader->display_admin_notices();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Bh_Tools_Loader. Orchestrates the hooks of the plugin.
	 * - Bh_Tools_i18n. Defines internationalization functionality.
	 * - Bh_Tools_Admin. Defines all hooks for the admin area.
	 * - Bh_Tools_Public. Defines all hooks for the public side of the site.
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
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bh-tools-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bh-tools-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-bh-tools-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-bh-tools-public.php';

		$this->loader = new Bh_Tools_Loader();
		
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Bh_Tools_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Bh_Tools_i18n();

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

		$plugin_admin = new Bh_Tools_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		/**
		 * Extend WP Activity Log Search
		 */
		$this->loader->add_action( 'admin_init', $plugin_admin, 'wp_activity_log_extend_init' );
		$this->loader->add_action( 'admin_footer', $plugin_admin, 'wp_activity_log_extend_search' );
		$this->loader->add_filter( 'bh_column_names_filter', $plugin_admin, 'wp_activity_log_filter_column_names' );
		$this->loader->add_filter( 'bh_prepare_column_name', $plugin_admin, 'wp_activity_log_prepare_column_name', 10, 3 );
		$this->loader->add_filter( 'bh_prepare_column_name_where_key', $plugin_admin, 'wp_activity_log_prepare_column_name_where_key', 10, 3 );
		$this->loader->add_filter( 'bh_prepare_column_name_where_value', $plugin_admin, 'wp_activity_log_prepare_column_name_where_value', 10, 2 );

		/**
		 * Add Subscription Export Menu
		 */
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'wp_ajax_process_export_subscriptions_batch', $plugin_admin, 'process_export_subscriptions_batch' );
		$this->loader->add_action( 'wp_ajax_check_export_file', $plugin_admin, 'check_export_file' );
		$this->loader->add_action('wp_ajax_pprocess_subscriptions_batch', $plugin_admin, 'pprocess_subscriptions_batch');
		$this->loader->add_action('wp_ajax_ccheck_export_file', $plugin_admin, 'ccheck_export_file');

		$this->loader->add_action( 'wp_ajax_process_check_payment_subscriptions_batch', $plugin_admin, 'process_check_payment_subscriptions_batch' );
		$this->loader->add_action( 'wp_ajax_process_check_payment_subscriptions_export_file', $plugin_admin, 'process_check_payment_subscriptions_export_file');

		$this->loader->add_action( 'wp_ajax_process_send_notifications_to_complete_subscription_batch', $plugin_admin, 'process_send_notifications_to_complete_subscription_batch' );
		$this->loader->add_action( 'wp_ajax_process_send_notifications_to_complete_subscription_export_file', $plugin_admin, 'process_send_notifications_to_complete_subscription_export_file');

		$this->loader->add_action( 'wp_ajax_process_order_inspector_batch', $plugin_admin, 'process_order_inspector_batch' );
		$this->loader->add_action( 'wp_ajax_process_order_inspector_export_file', $plugin_admin, 'process_order_inspector_export_file');

		$this->loader->add_action( 'wp_ajax_process_prepare_order_to_northbeam_batch', $plugin_admin, 'process_prepare_order_to_northbeam_batch' );
		$this->loader->add_action( 'wp_ajax_process_prepare_order_to_northbeam_export_file', $plugin_admin, 'process_prepare_order_to_northbeam_export_file');

		/**
		 * Get Questionarie Summary for Each Subscription from Telegrmd
		 */
		// $this->loader->add_action( 'admin_init', $plugin_admin, 'telegramd_init' );
		// $this->loader->add_filter( 'woocommerce_shop_order_list_table_columns', $plugin_admin, 'add_questionarie_info_column' );
		// $this->loader->add_action( 'woocommerce_shop_order_list_table_custom_column', $plugin_admin, 'show_questionarie_info_column_content', 10, 2 );
		// $this->loader->add_action( 'pre_get_posts', $plugin_admin, 'filter_orders_by_questionarie_status', 10000 );

		// $this->loader->add_filter( 'woocommerce_shop_order_list_table_columns', $plugin_admin, 'add_order_notes_column' );
		// $this->loader->add_action( 'woocommerce_shop_order_list_table_custom_column', $plugin_admin, 'show_order_notes_column_content', 10, 2 );
		// $this->loader->add_action( 'admin_head', $plugin_admin, 'custom_order_notes_column_styles', 10, 2 );
		
		// $this->loader->add_action( 'woocommerce_order_list_table_restrict_manage_orders', $plugin_admin, 'add_order_notes_filter', 1000 );
		// $this->loader->add_action( 'pre_get_posts', $plugin_admin, 'filter_orders_by_order', 1000 );

		/**
		 * Custom Filter Subscription by "subscription switched"
		 */
		// add_action('woocommerce_order_list_table_restrict_manage_orders', [$plugin_admin, 'hb_restrict_manage_posts'], 999999 );
		// add_filter('woocommerce_shop_subscription_list_table_prepare_items_query_args', [$plugin_admin, 'hb_request'], 999 );

		/**
		 * Process bh actions
		 */
		
		add_action('admin_init', [$plugin_admin, 'process_pending_subscription_renewals']);
		add_action('admin_init', [$plugin_admin, 'process_update_next_payment_renewals']);
		// add_action('admin_init', [$plugin_admin, 'process_cancel_subscriptions_from_mississipi']);
		// add_action('admin_init', [$plugin_admin, 'process_verify_order_status']);
		// add_action('admin_init', [$plugin_admin, 'process_pause_subscriptions']);
		

		/**
		 * Edit Next Payment Date when the subscription is created from Checkout
		 */
		// add_action('woocommerce_checkout_subscription_created', [$plugin_admin, 'bh_woocommerce_checkout_subscription_created'], 10, 3);
		/**
		 * Edit Next Payment Date when the Order completed is a Renewal
		 */
		// add_action('woocommerce_order_status_completed', [ $plugin_admin, 'bh_woocommerce_order_status_completed'], 10, 2);

		$this->loader->add_action( 'admin_init', $plugin_admin, 'update_status_wc_order_from_telegra' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Bh_Tools_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

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
	 * @return    Bh_Tools_Loader    Orchestrates the hooks of the plugin.
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
