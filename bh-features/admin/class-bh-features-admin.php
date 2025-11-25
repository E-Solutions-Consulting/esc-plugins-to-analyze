<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Features
 * @subpackage Bh_Features/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Bh_Features
 * @subpackage Bh_Features/admin
 * @author     Jaime <jaime@solutionswebonline.com>
 */
class Bh_Features_Admin {

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
	private $common;
	private $secret_key	=	'bh-wcs-secret-key';
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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $common ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->common = $common;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bh_Features_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Features_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bh-features-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bh_Features_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Features_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bh-features-admin.js', array( 'jquery' ), $this->version, false );

	}
	
	/**
	 * Reorders the WooCommerce order status filter links 
	 * in the admin orders list for a custom display sequence.
	 */
	function custom_reorder_wc_order_statuses( $order_statuses ) {
		$new_order = array(
			'all' => 'All',
			'pending' => 'Pending payment',
			'processing' => 'Processing',
			'on-hold' => 'On hold',
			'waiting_room' => 'Waiting Room',
			'prerequisites' => 'Require Prerequisites',
			'provider_review' => 'Provider Review',
			'error_review' => 'Error - Review',
			'admin_review' => 'Admin Review',
			'collect_payment' => 'Collect Payment',
			'completed' => 'Completed',
			'cancelled' => 'Cancelled',
			'refunded' => 'Refunded',
			'failed' => 'Failed',
		);

		$reordered_statuses = array();
		foreach ( $new_order as $key => $label ) {
			$wc_key = ( $key === 'all' ) ? 'all' : 'wc-' . $key;

			if ( $wc_key === 'all' ) {
				continue;
			}

			if ( isset( $order_statuses[ $wc_key ] ) ) {
				$reordered_statuses[ $wc_key ] = $order_statuses[ $wc_key ];
				unset( $order_statuses[ $wc_key ] ); // lo eliminamos para no repetir después
			}
		}

		if ( ! empty( $order_statuses ) ) {
			foreach ( $order_statuses as $key => $label ) {
				$reordered_statuses[ $key ] = $label;
			}
		}

		return $reordered_statuses;
	}

	/*
	*	Add Role Customer Service
	*/
	public function add_customer_services_role() {
		try {
			$shop_manager = get_role( 'shop_manager' );
			if ( $shop_manager && ! get_role( 'customer_services' ) ) {
				add_role(
					'customer_services',
					__( 'Customer Services', 'woocommerce' ), 
					$shop_manager->capabilities
				);

				$role = get_role( 'customer_services' );
		    	if ( ! $role ) return;

				$role->remove_cap( 'edit_posts' );          // Posts
			    $role->remove_cap( 'edit_pages' );          // Pages
			    $role->remove_cap( 'edit_products' );          // Pages
			    
			    $role->remove_cap( 'upload_files' );        // Media
			    $role->remove_cap( 'edit_others_posts' );   // Posts de otros
			    $role->remove_cap( 'edit_others_pages' );   // Pages de otros

			    $role->remove_cap( 'edit_theme_options' );
			    $role->remove_cap( 'manage_categories' );
			    $role->remove_cap( 'manage_links' );
			    $role->remove_cap( 'export' );
			    $role->remove_cap( 'import' );
			    $role->remove_cap( 'view_woocommerce_reports' ); // Analytics
			}
		} catch (\Throwable $th) {
			// _print($th);
		}
	}
	function register_remove_menu_admin(){
		if ( ! is_admin() || ! current_user_can( 'customer_services' ) ) {
			return ;
		}
		global $menu, $submenu;
		$allowed_menus	=	[
				'wsal-auditlog',
				'email-log',
				'woocommerce',
				'users.php'
		];
		$summary=[];
		foreach ($menu as $key => $menuitem) {
			$_menu=$menuitem[2];
			if(!in_array($_menu, $allowed_menus)){
				remove_menu_page($_menu);
				$_menu	.= ' removed';				
			}
			$summary[]	=	$_menu;
		}

		$allowed_submenus = [
		    'woocommerce' => ['wc-orders', 'wc-orders--shop_subscription'],
		    'email-log'   => ['email-log'],
		    'wsal-auditlog'   => ['wsal-auditlog'],
		];

		foreach ( $submenu as $parent_slug => $submenus ) {
		    if ( ! isset( $allowed_submenus[ $parent_slug ] ) ) {
		        continue;
		    }

		    foreach ( $submenus as $submenu_item ) {
		        $submenu_slug = $submenu_item[2];
		        if ( ! in_array( $submenu_slug, $allowed_submenus[ $parent_slug ] ) ) {
		            remove_submenu_page( $parent_slug, $submenu_slug );
		        }
		    }
		}
	}

	/*
	*	Add Role Tracking_expert Master
	*/
	function add_tracking_master_role() {
	    $admin = get_role('administrator');
	    if (!$admin) return;

	    if (!get_role('tracking_master')) {
	        add_role('tracking_master', 'Tracking Master', $admin->capabilities);

	        $restricted_caps = [
		        'activate_plugins',
		        'delete_plugins',
		        'install_plugins',
		        'update_plugins',
		        'edit_plugins',
		        /*'resume_plugins',*/

				'create_users',
				'delete_users',
				'edit_users',
				'list_users',
				'promote_users',
				'remove_users',

				'delete_themes',
				'edit_themes',
				'install_themes',
				'resume_themes',
				'switch_themes',
				'update_themes',

		        'edit_theme_options',

		        'assign_shop_coupon_terms',
				'delete_others_shop_coupons',
				'delete_private_shop_coupons',
				'delete_published_shop_coupons',
				'delete_shop_coupon',
				'delete_shop_coupon_terms',
				'delete_shop_coupons',
				'edit_others_shop_coupons',
				'edit_private_shop_coupons',
				'edit_published_shop_coupons',
				'edit_shop_coupon',
				'edit_shop_coupon_terms',

				'delete_wpforms_forms',
				'edit_others_wpforms_forms',
				'edit_wpforms_forms',
				'publish_wpforms_forms',
				'read_private_wpforms_forms',

		        'export', 
		        'import',

		        'delete_yaymail_templates',
				'edit_others_yaymail_templates',
				'edit_yaymail_templates',
				'publish_yaymail_templates',
		    ];

		    $role = get_role('tracking_master');
		    foreach ($restricted_caps as $cap) {
		        $role->remove_cap($cap);
		    }

		    if ($role && !$role->has_cap('manage_pys')) {
		        $role->add_cap('manage_pys');
		    }
	    }
	}
	function tracking_master_remove_menu_admin(){
		if ( ! is_admin() || ! current_user_can( 'tracking_master' ) ) {
			return ;
		}
		global $menu, $submenu;

		$to_remove_menus	=	[
			/*'index.php removed',
			'separator1 removed',
			'upload.php removed',
			'edit-tags.php?taxonomy=link_category removed',*/
			'edit-comments.php',
			/*'edit.php removed',
			'edit.php?post_type=page removed',*/
			'edit.php?post_type=elementor_library',
			/*'edit.php?post_type=product removed',
			'separator2 removed',*/
			'themes.php',
			'plugins.php',
			'profile.php',
			'tools.php',
			/*'options-general.php',*/
			/*'separator-last',
			'woocommerce-marketing',*/
			'wpforms-overview',
			/*'separator-woocommerce',
			'woocommerce',*/
			'edit.php?post_type=acf-field-group',
			/*'autopilot_settings',*/
			'bh-features',
			/*'deadline-funnel-settings',
			'ib-ghlconnect',
			'pixelyoursite',*/
			'qsm_dashboard',
			/*'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM removed',*/
			'WP-Optimize',
			'wpclever',
			'eael-settings',
			'yaycommerce',
			'wpstg_clone',
			/*'checkout-upsell-woocommerce',*/
			'email-log',
			'hello-elementor',
			/*'wc-admin&path=/analytics/overview removed',*/
			'wp-mail-smtp',
			/*'separator-elementor removed',*/
			'elementor',
			'telemdnow',
			'brightplugins',
			'sg-cachepress',
			'sg-security',
			'fonts-plugin',
		];
		$summary=[];
		foreach ($menu as $key => $menuitem) {
			$_menu=$menuitem[2];
			if(in_array($_menu, $to_remove_menus)){
				remove_menu_page($_menu);
				$_menu	.= ' removed';
			}
			$summary[]	=	$_menu;
		}
		
		$to_remove_submenus = [
			'index.php'						=>	['update-core.php'],
		    'woocommerce' 					=>	['wc-status', 'arg-multistep-checkout', 'wc-admin&path=/customers', 'wc-settings', 'wc-status', 'wc-addons', 'wc-admin&path=/extensions', 'codemanas-woocommerce-preview-emails'],
		    'checkout-upsell-woocommerce' 	=>	['checkout-upsell-woocommerce&tab=settings', 'checkout-upsell-woocommerce&tab=addons'],
		    'options-general.php'			=>	[
		    	'options-general.php', 
		    	'options-writing.php', 
		    	'options-reading.php',
		    	'options-discussion.php',
		    	'options-media.php',
		    	'options-permalink.php',
		    	'options-privacy.php',
		    	'disable-emails',
		    	'duplicate_page_settings',
		    	'siteground_settings',
		    	'elecsp-admin-page',
		    	'uae',
		    	'settings-user-role-editor.php',
		    ],
		    'profile.php'					=>	['users-user-role-editor.php']
		];
		$summary	=	[];
		foreach ( $submenu as $parent_slug => $submenus ) {
		    if ( ! isset( $to_remove_submenus[ $parent_slug ] ) ) {
		        continue;
		    }

		    foreach ( $submenus as $submenu_item ) {
		        $submenu_slug = $submenu_item[2];
		        if ( in_array( $submenu_slug, $to_remove_submenus[ $parent_slug ] ) ) {
		            remove_submenu_page( $parent_slug, $submenu_slug );
		            $submenu_slug .= ' removed';
		        }
		        $summary[$parent_slug]	=	$submenu_slug;
		    }
		}
	}
	
	/**
	* Full restrictions for the tracking_master role
	* Can only view orders and subscriptions, not edit or delete
	*/

	// ---------- Hide action buttons in admin ----------
	function tracking_master_hide_action_buttons() {
	    $screen = get_current_screen();
	    $user = wp_get_current_user();

	    if (!in_array('tracking_master', (array) $user->roles, true)) {
	        return;
	    }

	    if ($screen && $screen->post_type === 'shop_order') {
	        echo '<style>
	            #order_data h3 > a.edit_address,
	            #woocommerce-order-actions,     /* Widget Order Actions */
	            #woocommerce-order-notes,
	            #woocommerce-order-downloads,
	            #woocommerce-order-items .button,
	            #woocommerce-order-items .wc-order-edit-line-item,
	            #order_custom,
	            .wrap .page-title-action,        /* Añadir orden */
	            #delete-action,                  /* Mover a papelera */
	            #publishing-action input[type=submit],            
	            #the-list .row-actions,
	            .bulkactions,                    /* Acciones masivas */
	            .row-actions .editinline          /* Quick Edit */
	            { display:none !important; }
	        </style>';
	    }

	    if ($screen && $screen->id === 'woocommerce_page_wc-orders--shop_subscription') {
	        echo '<style>
	            #order_data h3 > a.edit_address,
	            #woocommerce-order-actions,     /* Widget Order Actions */
	            #woocommerce-order-notes,
	            #woocommerce-order-downloads,
	            #woocommerce-order-items .button,
	            #woocommerce-order-items .wc-order-edit-line-item,
	            #order_custom,
	            .wrap .page-title-action,
	            #delete-action,
	            #publishing-action input[type=submit],
	            .bulkactions,
	            #the-list .row-actions,
	            .row-actions .editinline,
	            .row-actions .trash
	            { display:none !important; }
	        </style>';
	    }
	}
	// ---------- Prevent saving changes to Orders ----------
	function tracking_master_deny_save_order($post_id) {
	    $user = wp_get_current_user();
	    if (in_array('tracking_master', (array) $user->roles, true)) {
	        wp_die(__('You do not have permission to modify this order.', 'bh-features'));
	    }
	}
	// ---------- Prevent saving changes to Subscriptions ----------
	function tracking_master_deny_save_order_suscription($post_id) {
	    $user = wp_get_current_user();
	    if (in_array('tracking_master', (array) $user->roles, true)) {
	        wp_die(__('You do not have permission to modify this subscription.', 'bh-features'));
	    }
	}
	// ---------- Prevent deleting Orders or Subscriptions ----------
	function tracking_master_restric_edit_shop_order_subscription($post_id) {
	    $user = wp_get_current_user();
	    $type = get_post_type($post_id);

	    if (in_array('tracking_master', (array) $user->roles, true) &&
	        ($type === 'shop_order' || $type === 'shop_subscription')) {
	        wp_die(__('You do not have permission to delete this item.', 'bh-features'));
	    }
	}
	function force_redirect_for_customer_services( $redirect_to, $requested_redirect_to, $user ) {
	    if ( is_a( $user, 'WP_User' ) && in_array( 'customer_services', $user->roles ) ) {
	        return admin_url( 'admin.php?page=wc-orders' );
	    }
	    return $redirect_to;
	}

	/**
	 * Main Menu Brello
	 * */
	public function add_admin_menu() {
		$icon	=	'dashicons-admin-generic';
		add_menu_page(
			'Brello',
			'Brello',
			'manage_options',
			PARENT_MENU_SLUG,
			'',
			$icon,
			4
		);
		add_submenu_page(
			PARENT_MENU_SLUG,
			'Order Limits',
			'Order Limits',
			'manage_options',
			PARENT_MENU_SLUG . '--order-limits',
			[$this, 'render_order_limits_admin_page']
		);

		remove_submenu_page(PARENT_MENU_SLUG, PARENT_MENU_SLUG);
	}

	/*
	*	/my-account/  Remove Downloads Menu
	*/
	function hide_menu_downloads_my_account( $items ) {
	    unset( $items['downloads'] );
	    return $items;
	}
	function change_subscription_price_text($subscription_string, $subscription_details) {
		if (!function_exists('is_account_page') || !is_account_page())
			return $subscription_string;

		if(!isset($subscription_details['subscription_interval']))
			return $subscription_string;

		$subscription_interval	=	$subscription_details['subscription_interval'];
		$custom_text 			=	'';
		if ( $subscription_interval == 1 ) {
			$custom_text = '<br/><small>Renews every 25 days</small>';
			$subscription_string = str_replace('/ month', $custom_text, $subscription_string);
		} elseif ( $subscription_interval == 3 ) {
			$custom_text = '<br/><small>Renews every 10 weeks</small>';
			$subscription_string = str_replace('every 3 months', $custom_text, $subscription_string);
		}
		global $printed_new_line;
		$printed_new_line	=	true;
		
		return $subscription_string;
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
	
	/**
	 * Add Filter by State
	 */
	function state_filter_for_subscriptions($order_type) {
		if ('shop_subscription' !== $order_type) return;

		$states = get_transient('subscription_states_cache');
		
		if (false === $states) {
			global $wpdb;
			$states = $wpdb->get_col(
				"SELECT DISTINCT state 
				FROM mrb_wc_order_addresses 
				WHERE address_type = 'billing' 
				AND state != '' 
				ORDER BY state"
			);
			set_transient('subscription_states_cache', $states, 7 * DAY_IN_SECONDS);
		}

		$current_state 	=	isset($_GET['_subscription_state']) ? sanitize_text_field($_GET['_subscription_state']) : '';
		$base_country	=	WC()->countries->get_base_country();
		$country_states =	WC()->countries->get_states($base_country);

		if (empty($states)) return;

		echo '<select name="_subscription_state" id="filter_by_subscription_state" style="min-width:150px;margin-left:10px;">';
		echo '<option value="">' . esc_html__('All States', 'woocommerce') . '</option>';
		
		foreach ($states as $state_code) {
			$state_name = $country_states[$state_code] ?? $state_code;
			echo '<option value="' . esc_attr($state_code) . '" ' . selected($current_state, $state_code, false) . '>';
			echo esc_html($state_name);
			echo '</option>';
		}
		
		echo '</select>';
	}
	function filter_subscriptions_by_state($wp_query_args) {
		if (
			!is_admin() || $wp_query_args['type']!=='shop_subscription' || empty($_GET['_subscription_state'])
		)
			return $wp_query_args;

		global $wpdb;

		$subscription_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT order_id 
			FROM {$wpdb->prefix}wc_order_addresses 
			WHERE address_type = 'billing' 
			AND state = %s",
			$_GET['_subscription_state']
		));

		if (!empty($subscription_ids)) {
			$wp_query_args['id'] = $subscription_ids;
		} else {
			$wp_query_args['id'] = array(0);
		}

		return $wp_query_args;
	}

	/*
	* Put to on-hold subscription with State CT
	*/
	function cancel_subscription_renewal_if_state_is_ct($renewal_order, $subscription) {
		try {
			if (!is_a($renewal_order, 'WC_Order') || !is_a($subscription, 'WC_Subscription')) {
				return $renewal_order;
			}

			$renewal_order_state	=	$renewal_order->get_shipping_state();
			$subscription_state		=	$subscription->get_shipping_state();

			$excluded_subscription_states = ['CT', 'FL'];
			if (!empty($subscription_state) && !in_array($subscription_state, $excluded_subscription_states)) {
				return $renewal_order;
			}

			$message	=	'Renewal Order Cancelled: No license for the state Connecticut.';
			$renewal_order->update_status('wc-cancelled', $message);
			//$subscription->update_status('wc-cancelled');
			//$subscription->update_status('wc-pending-cancel');
			$subscription->update_status( 'on-hold' );
			//$subscription->update_status('wc-on-hold');

			//$subscription->update_meta_data('_cancelled_state_ct', 1);
			if(empty($subscription_state))
				$subscription_state	=	'empty';
			
			$meta_key = '_cancelled_state_' . strtolower($subscription_state);
    		$subscription->update_meta_data($meta_key, 1);

			$subscription->save();

			bh_plugins_log('cancel_subscription_renewal_if_state_is_ct(' . $subscription->get_id() . ', ' . $subscription->get_status() . ')', 'bh_plugins_state_ct');
			//$message	=	'⚠️ CT: Subscription #' . $subscription->get_id() . ' -> ' . $subscription->get_status();
			$message	=	'⚠️ ' . $subscription_state . ': Subscription #' . $subscription->get_id() . ' -> ' . $subscription->get_status();
			bh_send_slack_notification($message);
		} catch (\Throwable $th) {
			$message	.=	"\n" . $th->getMessage();
		}

		return $renewal_order;
	}

	/*
	*	Filter Order, Subscriptions by Date Range
	*/
	function date_range_filter_to_orders() {
		$current_screen = get_current_screen();		
		if ($current_screen && in_array($current_screen->id, ['woocommerce_page_wc-orders', 'woocommerce_page_wc-orders--shop_subscription']) ) {
			$start_date 	=	isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
			$end_date 		=	isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';			
			$utc_checked 	=	isset($_GET['utc']) ? ' checked' : '';			
			echo '
			<div class="order-date-filter">
				<input type="date" id="start_date" name="start_date" value="' . esc_attr($start_date) . '">
				<input type="date" id="end_date" name="end_date" value="' . esc_attr($end_date) . '">
				<input type="checkbox" id="utc" name="utc" value="yes"' . $utc_checked . '>UTC
			</div>
			<style>
			.order-date-filter {display: inline-block;margin-right: 10px;}
			.order-date-filter > input[type="date"] {max-width: 120px;height: 100% !important;}
			.order-date-filter > input[type="checkbox"] {height: 15px !important;}
			</style>';
		}
	}
	function handle_date_range_filter_to_orders($query) {
		if (!empty($_GET['start_date']) || !empty($_GET['end_date'])) {
			$date_query = array();
			$timezone = wp_timezone();
			$utc	=	isset($_GET['utc']);
			
			if (!empty($_GET['start_date'])) {
				if($utc)
					$date_query['after'] = $_GET['start_date'];
				else{					
					$start_date = DateTime::createFromFormat('Y-m-d', $_GET['start_date'], $timezone);
		            $start_date->setTime(0, 0, 0);
		            $start_date->setTimezone(new DateTimeZone('UTC'));
		            $date_query['after'] = $start_date->format('Y-m-d H:i:s');
				}
			}
			
			if (!empty($_GET['end_date'])) {
				if($utc)
					$date_query['before'] = $_GET['end_date'];
				else{
					$end_date = DateTime::createFromFormat('Y-m-d', $_GET['end_date'], $timezone);
		            $end_date->setTime(23, 59, 59);
		            $end_date->setTimezone(new DateTimeZone('UTC'));
		            $date_query['before'] = $end_date->format('Y-m-d H:i:s');
				}
			}
			
			$date_query['inclusive'] = true;			
			$query['date_query'] = array($date_query);
		}
		return $query;
	}

	/*
	*	Add Widget Telegra Info
	*/
	function add_widget_telegra_metabox() {
		$order_screen_id = wcs_get_page_screen_id( 'shop_order' );
	    $screen = get_current_screen();
	    if ($screen && $screen->post_type === 'shop_order') {
	        $post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            add_meta_box(
                'telegra_info_metabox',
                'Telegra Info',
                [$this, 'display_widget_telegra'],
                $order_screen_id,
                'side',
                'default'
            );
	    }
	}
	function display_widget_telegra($order) {
		$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if(empty($order_id)){
			echo 'No Info Available';
			return ;
		}
	    $entity_id = get_post_meta($order_id, 'telemdnow_entity_id', true);
	    echo '<p><a href="https://affiliate-admin.telegramd.com/orders/' . esc_html($entity_id) . '" target="_blank">Click to view order details at Telegra</a></p>';
	}

	/**
	 * ---------------------------
	 *  URL Edit subscription Page
	 * https://shop.brellohealth.com/wp-admin/admin.php?page=wc-orders--shop_subscription&action=edit&id=1234
	 * 
	 * add &hb-action=reactivate_subscription
	 * 
	 * ---------------------------
	 * URL: Subscriptions Page
	 * https://shop.brellohealth.com/wp-admin/admin.php?page=wc-orders--shop_subscription
	 * 
	 * add &hb-action=reactivate_subscription&subscription_id=SUBSCRIPTION_ID_HERE	 
	 * 
	 */
	function subscription_actions(){
		if(!isset($_GET['hb-action']) || empty($_GET['hb-action']))
			return ;

		global $hb_action;

		$hb_action	=	$_GET['hb-action'];
		switch ($hb_action) {
			case 'reactivate_subscription':
				$subscription_id	=	isset($_GET['subscription_id']) ? absint($_GET['subscription_id']) : 0;
				if(!$subscription_id)
					$subscription_id	=	isset($_GET['id']) ? absint($_GET['id']) : 0;	
				
				if($subscription_id)
					$this->reactivate_subscription($subscription_id);
				break;
			
			default:
				# code...
				break;
		}		
	}
	function woocommerce_can_subscription_be_updated_to_active( $can_be_updated, $subscription){
		global $hb_action;
		if($hb_action!=='reactivate_subscription'){
			return $can_be_updated;
		}
		return true;
	}
	function reactivate_subscription($subscription_id){
		$new_status			=	'wc-active';
		$subscription		=	wcs_get_subscription($subscription_id);
		if($subscription){
			$subscription->update_dates( array( 'cancelled' => 0, 'end' => 0 ) );
			$subscription->update_status($new_status);
		}
		$_url = add_query_arg( array( 
			'page' 	=>	'wc-orders--shop_subscription', 
			'action'=>	'edit', 
			'id'	=>	$subscription_id, 
		), admin_url( 'admin.php' ) );
		wp_admin_notice( 'Subscription #<a href="' .$_url . '" target="_blank">' .$subscription_id . '</a> Reactivated', array( 'type' => 'success' ) );
	}

	/**
	 * Switch Subscription Product
	 */	
	function generate_product_switch_link($subscription_id, $args=[]) {
		$key = $this->secret_key;
		$data = [
			'subscription_id' 		=>	$subscription_id,
		];
		if(isset($args['cancelled'])){
			$data['subscription_cancel']	=	'yes';
		}else{
			$data['product_id']		=	$args['product_id'];
			$data['variation_id']	=	$args['variation_id'];
		}
	
		$encrypted_data = base64_encode(openssl_encrypt(json_encode($data), 'aes-256-cbc', $key, 0, substr($key, 0, 16)));
		return add_query_arg(['switch_product' => $encrypted_data], home_url('/switch-product'));
	}
	function hb_init_switch_product_subscription() {
		if (isset($_GET['switch_product'])) {
			$key 			=	$this->secret_key;
			$encrypted_data =	sanitize_text_field($_GET['switch_product']);
			$data 			=	json_decode(openssl_decrypt(base64_decode($encrypted_data), 'aes-256-cbc', $key, 0, substr($key, 0, 16)), true);
			
			$message	=	'<div style="text-align:center;display: flex;flex-direction: column;justify-content: center;align-items: center;height: 50vh;"><h1>%s</h1></div>';

			if (!$data || !isset($data['subscription_id'])){
				$message	=	sprintf($message, 'Invalid Action!');
				die( $message );
			}
			$subscription_id	=	intval($data['subscription_id']);
			$subscription		=	wcs_get_subscription($subscription_id);
			
			//	Is a subscription? isActive?
			if ( (!$subscription instanceof WC_Subscription) || !$subscription->has_status('active') ) {
				$message	=	sprintf($message, 'Action Not Available!');
				die( $message );
			}
			//	The subscription was changed previously?			
			if ($subscription->get_meta('_subscription_switched') === 'yes') {
				$message	=	sprintf($message, 'Action Not Available!');
				die( $message );
			}
			
			if (isset($data['product_id']) && isset($data['variation_id'])) {
				$product_id		=	intval($data['product_id']);
				$variation_id 	=	intval($data['variation_id']);
				foreach ($subscription->get_items() as $item_id => $item) {
					$subscription->remove_item($item_id);
				}

				$product = wc_get_product($variation_id);
				if ($product && $product->is_type('variation')) {

					$parent_product_id = $product->get_parent_id();						
					$parent_product = wc_get_product($parent_product_id);
					$item_product_name	=	$parent_product->get_name() . ' - ' . $product->get_name();
					
					$item	=	new WC_Order_Item_Product();
					$item->set_product_id($product_id);
					$item->set_variation_id($variation_id);
					$item->set_name($item_product_name);					
					$item->set_quantity(1);
					$item->set_total($product->get_price());
					$attributes	=	$product->get_variation_attributes();
					foreach ($attributes as $key => $value) {
						$item->add_meta_data($key, $value);
					}
					$subscription->add_item($item);
				}						
				$subscription->calculate_totals();
				$subscription->update_meta_data('_subscription_switched', 'yes');
				$subscription->update_meta_data('_subscription_switched_action', 'switched');
				$subscription->update_meta_data('_subscription_switched_date', gmdate( 'Y-m-d H:i:s' ));

				$subscription->save();

				$message	=	sprintf($message, 'The Product of your subscription was updated Successfully!');
				die( $message );
			}elseif (isset($data['subscription_cancel'])) {
				try {
					as_unschedule_all_actions( '', [], $subscription_id );
					$subscription->update_meta_data('_subscription_switched', 'yes');
					$subscription->update_meta_data('_subscription_switched_action', 'cancel');
					$subscription->update_meta_data('_subscription_switched_date', gmdate( 'Y-m-d H:i:s' ));
					$subscription->update_dates( array( 'cancelled' => 0, 'end' => 0 ) );
					$subscription->update_status('on-hold');
					//$subscription->update_status(['cancelled']);
					$subscription->save();
					$message	=	sprintf($message, 'Subscription Cancelled!');
					die( $message );

				} catch (\Throwable $th) {
					$message	=	sprintf($message, 'Action Not Available!');
					die( $message );
				}
			}
		}
	}
	function hb_woocommerce_admin_order_data_after_order_details($subscription) {
		if (!is_a($subscription, 'WC_Subscription') ) {
			return ;
		}
		$output	=	'';
		if ( $subscription->get_meta('_subscription_switched') === 'yes') {
			$output	.=	'<li><strong>Updated from Email</strong></li>';
			$action	=	$subscription->get_meta('_subscription_switched_action');
			switch ($action) {
				case 'switched':
					$output	.=	'<li>Action: <strong>Product Changed</strong></li>';
					break;
				case 'cancel':
					$output	.=	'<li>Action: <strong>Subscription Cancelled</strong></li>';
					break;
			}			
		}
		
		if(!empty($output)){
			$date	=	$subscription->get_meta('_subscription_switched_date');
			$output	.=	'<li>Updated on <strong>' . $date . '</strong></li>';
			echo '<ul style="display:inline-block;margin-top:1rem">' . $output . '</ul>';
		}
	}
	function hb_current_screen() {
		global $hb_action;
		if(!empty($hb_action)){
			return ;
		}

		$screen = get_current_screen();
		if ( $screen && $screen->id === 'woocommerce_page_wc-orders--shop_subscription' && isset( $_GET['id'] ) ) {
			$subscription_id = intval( $_GET['id'] );	
			$subscription	=	wcs_get_subscription( $subscription_id );
			if ( $subscription && $subscription->has_status( 'active' ) && $subscription->get_meta('_subscription_switched_action') === 'cancel' ) {
				$subscription->update_status( 'cancelled' );	
				add_action( 'admin_notices', function() use ( $subscription_id ) {
					echo '<div class="notice notice-success is-dismissible">
							<p>Subscription was cancelled</p>
						  </div>';
				});
			}
		}
	}

	/*
	* Order Action for send email for Switch the subscription
	*/
	function hb_woocommerce_order_actions($actions) {
		global $theorder;
	
		if (is_a($theorder, 'WC_Subscription')) {
			$actions['send_switch_product_email'] = __('Send switch product email', 'text-domain');
		}
		/*
		if (is_a($theorder, 'WC_Order') && !is_a($theorder, 'WC_Subscription') && function_exists('send_order_to_telegra')) {
			$actions['resend_to_telegra'] = __('Resend order to Telegra', 'text-domain');
		}*/

		return $actions;
	}
	function hb_woocommerce_order_action_send_switch_product_email($subscription) {
		if (is_a($subscription, 'WC_Subscription')) {
			$customer_email	=	$subscription->get_billing_email();
			$args = array(
				'category' => array('optional-tirzepatide'),
			);
			
			$products = wc_get_products($args);
			$links = array_map(function ($product) use ($subscription) {
				$product_id = $product->get_id();			
				if ($product->is_type('variable')) {
					$variations = wc_get_products(array(
						'type'        => 'variation',
						'parent'      => $product_id,
						'return'      => 'objects',
						'status'      => 'publish',
					));
					$variation_links = array_map(function ($variation) use ($subscription, $product) {
						$variation_id = $variation->get_id();
						$variation_name = implode(', ', $variation->get_variation_attributes());
						$link	=	$this->generate_product_switch_link($subscription->get_id(), ['product_id'=>$product->get_id(), 'variation_id'=> $variation_id] );
						return '<a href="' . esc_url($link) . '">Select ' . $product->get_name() . ' - ' . $variation_name . '</a>';
					}, $variations);


					$output	= '';
					foreach ($variations as $variation) {
						$variation_id	=	$variation->get_id();
						$variation_name =	implode(', ', $variation->get_variation_attributes());

						$link	=	$this->generate_product_switch_link($subscription->get_id(), ['product_id'=>$product->get_id(), 'variation_id'=> $variation_id] );
						$output .= '<li><a href="' . esc_url($link) . '">' . $product->get_name() . ' - ' . $variation_name . '</a></li>';
					}
					//$output	.= '</ul>';
					return $output;
				}
			}, $products);

			$message	=	'<p>Hi</p>';
			$message	.=	'<p>Pellentesque mollis risus non diam aliquam volutpat. Vestibulum vitae lorem tortor. Morbi vehicula nunc vitae elementum pulvinar. Maecenas convallis libero in magna dignissim accumsan. Curabitur est lectus, pellentesque lobortis odio in, tempor pulvinar ligula. Vestibulum euismod velit eros, quis malesuada enim placerat a. Ut dui dolor, hendrerit ut dolor aliquam, suscipit vestibulum metus. Integer quis maximus massa.</p>';
			$message	.=	'<br>';
			$message	.=	'<p>You can change your current product to one of the following:</p>';
			$message	.=	'<br>';
			$message	.=	'<ul>' . implode('', $links) . '</ul>';
			$message	.=	'<br></br>';
			$message	.=	'<p>Thanks.</p>';

			wp_mail(
				$customer_email,
				'Attention, it is necessary to change the product of your subscription',
				$message,
				[
					'Content-Type: text/html; charset=UTF-8',
					'From: Brello Health <info@brellohealth.com>',
				]
			);
	
			// Notificar al administrador.
			wp_admin_notice( 'Email Sent successfully!', array( 'type' => 'success' ) );
		}
	}
	function hb_woocommerce_order_action_resend_to_telegra($order) {
		if (is_a($order, 'WC_Order') && !is_a($order, 'WC_Subscription') && function_exists('send_order_to_telegra')) {
			$order_id = $order->get_id();
			send_order_to_telegra($order_id);
		}
	}
	/*
	 *	Order Limit
	 */
	function render_order_limits_admin_page() {
		wp_enqueue_style( 'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css' );
		wp_enqueue_script( 'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true );

		$now 			=	current_time('timestamp');
		$now_formatted 	=	date('m/d/Y H:i:s', $now);
	    $limit_str		=	'';
		?>
		<div class="wrap">
			<h1>Order Limit</h1>
			<div class="order-limits-grid">
				<div class="postbox">
					<div class="inside">
						<h2>Schedule Restriction</h2>
						<form method="post" action="options.php">
							<?php
							settings_fields('order_limits_schedule_group');
							do_settings_sections('order_limits_schedule');
							submit_button('Save Schedule');
							?>
						</form>

						<div id="clock">Current Time: <?php echo $now_formatted ?></div>

					</div>
				</div>
			</div>
			<style>
				.order-limits-grid > .postbox {padding:2rem}
				.order-limits-day-selector {margin: 10px 0;}
				.order-limits-day-selector .select2-container {width: 100% !important;}
				.switch { position: relative; display: inline-block; width: 60px; height: 34px; }
				.switch input { opacity: 0; width: 0; height: 0; }
				.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
				.slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
				input:checked + .slider { background-color: #2271b1; }
				input:checked + .slider:before { transform: translateX(26px); }
				.input-time{max-width: 120px}
				#clock {font-size: 1rem;font-weight: bold;}
	        	#clock.expired {color: red;}
			</style>
			<script>
				jQuery(document).ready(function($) {
					$('input[name="enable_schedule_restriction"]').change(function() {
						$('select[name="operating_days"], input[name="start_time"], input[name="end_time"]')
							.prop('readonly', !this.checked);
					}).trigger('change');
					 $('.order-limits-day-select').select2({
						width:'resolve',
						placeholder: 'Select Day(s)',
						allowClear: true,
					});
					$('.order-limits-day-select').on('change', function() {
						var selectedDays = $(this).val();
						selectedDays.forEach(function(productId) {
							$('.order-limits-day-select option[data-state="' + productId + '"]').each(function() {
								$(this).prop('selected', true);
							});
						});
					}); 
				});
			</script>
			<script>
			    let currentTime = new Date("<?php echo $now_formatted; ?>");
			    <?php if(!empty($limit_str)) : ?>
			    const limitTime = new Date("<?php echo $limit_str; ?>");
				<?php endif; ?>
			    const clockEl = document.getElementById("clock");
			    function updateClock() {
			        const formatted = currentTime.toLocaleString("en-US", {
			            month: "2-digit",
			            day: "2-digit",
			            year: "numeric",
			            hour: "2-digit",
			            minute: "2-digit",
			            second: "2-digit",
			            hour12: false
			        });

			        clockEl.textContent = "Current Time: " + formatted;
			        <?php if(!empty($limit_str)) : ?>
			        if (currentTime >= limitTime) {
			            clockEl.classList.add("expired");
			        } else {
			            clockEl.classList.remove("expired");
			        }
			        <?php endif; ?>
			        currentTime.setSeconds(currentTime.getSeconds() + 1);
			    }
			    updateClock();
			    setInterval(updateClock, 1000);
			</script>
		</div>
		<?php
	}
	function register_order_limit_settings() {
		add_settings_section(
			'order_limits_schedule_section',
			'',
			[$this, 'render_schedule_section_desc'],
			'order_limits_schedule'
		);
		
		add_settings_field(
			'enable_schedule_restriction',
			'Enable Schedule Restriction',
			[$this, 'render_schedule_restriction_toggle'],
			'order_limits_schedule',
			'order_limits_schedule_section'
		);

		add_settings_field(
			'operating_day',
			'Active Day',
			[$this, 'render_operating_day_field'],
			'order_limits_schedule',
			'order_limits_schedule_section'
		);
		
		add_settings_field(
			'start_time',
			'Start Time (5:00 PM)',
			[$this, 'render_start_time_field'],
			'order_limits_schedule',
			'order_limits_schedule_section'
		);
		
		add_settings_field(
			'end_time',
			'End Time (6:00 AM)',
			[$this, 'render_end_time_field'],
			'order_limits_schedule',
			'order_limits_schedule_section'
		);	

		register_setting('order_limits_schedule_group', 'enable_schedule_restriction');
		register_setting('order_limits_schedule_group', 'operating_days', [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitize_operating_days']
		]);
		register_setting('order_limits_schedule_group', 'start_time');
		register_setting('order_limits_schedule_group', 'end_time');
	}
	function render_schedule_restriction_toggle() {
		$enabled = get_option('enable_schedule_restriction', 'no');
		echo '
		<label class="switch">
			<input type="checkbox" name="enable_schedule_restriction" value="yes" ' . checked('yes', $enabled, false) . '>
			<span class="slider round"></span>
		</label>';
	}
	/**
	 * Sanitize operating days selection
	 */
	function sanitize_operating_days($days) {
		$valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
		
		if (empty($days)) {
			return ['friday'];
		}
		
		return array_intersect($days, $valid_days);
	}
	/**
	* Render description for schedule section
	*/
	function render_schedule_section_desc() {
		echo '<p>Set a daily order limit during which no new requests will be accepted through Brellohealth.com/start.</p>';
	}
	/**
	 * Render operating days multi-select field with toggle
	 */
	function render_operating_day_field() {
		$all_days = [
			'monday'    => 'Monday',
			'tuesday'   => 'Tuesday',
			'wednesday' => 'Wednesday',
			'thursday'  => 'Thursday',
			'friday'    => 'Friday',
			'saturday'  => 'Saturday',
			'sunday'    => 'Sunday'
		];
		
		$selected_days = (array) get_option('operating_days', ['friday']);
		
		echo '<div class="order-limits-day-selector">';
		echo '<select name="operating_days[]" multiple="multiple" class="order-limits-day-select" style="height:auto;min-height:120px;width:200px;">';
		foreach ($all_days as $value => $label) {
			$is_selected = in_array($value, $selected_days) ? 'selected="selected"' : '';
			echo '<option value="' . esc_attr($value) . '" ' . $is_selected . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
	}
	/**
	 * Render start time field
	 */
	function render_start_time_field() {
		echo '<input type="time" name="start_time" value="' . esc_attr(get_option('start_time', '17:00')) . '" class="regular-text input-time" required>';
	}
	/**
	 * Render end time field
	 */
	function render_end_time_field() {
		echo '<input type="time" name="end_time" value="' . esc_attr(get_option('end_time', '06:00')) . '" class="regular-text input-time" required>';
		echo '<p class="description">Next day morning</p>';
	}
	function admin_notices_is_order_restricted() {
	    if (current_user_can('manage_options') && $this->common->get_restriction_status()['active']) {
	        echo '<div class="notice notice-warning">
	            <p>' . $this->common->get_restriction_status()['message'] . '</p>
	        </div>';
	    }
	}
	private function parse_time_to_hours_minutes($time_str) {
	    if (empty($time_str)) return [0, 0];
	    
	    $time = DateTime::createFromFormat('g:i A', $time_str);
	    if (!$time) $time = DateTime::createFromFormat('H:i', $time_str);
	    
	    return $time ? [$time->format('G'), $time->format('i')] : [0, 0];
	}
	/**
	 * Update File js in subdomain start
	 * 
	 * */
	function handle_schedule_settings_update(){
		try {
			$start_time   = get_option('start_time', 14);
			$end_time     = get_option('end_time', 6);

			list($start_hour, $start_minute) = $this->parse_time_to_hours_minutes($start_time);
    		list($end_hour, $end_minute) = $this->parse_time_to_hours_minutes($end_time);

    		$start_hour		=	intval($start_hour);
    		$start_minute	=	intval($start_minute);
    		$end_hour		=	intval($end_hour);
    		$end_minute		=	intval($end_minute);

		    $js_content = <<<JS
			document.addEventListener('DOMContentLoaded', function() {
			    const originalBtnSelector = '.product-info .toggle-planinfo-button .elementor-button';
			    const btnLink		= 'https://app.monstercampaigns.com/c/mitvjnbofev3rh12codp/';
			    const timeZone 		= "America/Chicago";
			    const startHour     = $start_hour;
			    const startMinute   = $start_minute;
			    const endHour       = $end_hour;
			    const endMinute     = $end_minute;

			    function replaceButtons() {
			        const originalButtons = document.querySelectorAll(originalBtnSelector);
			        
			        originalButtons.forEach(btn => {
			            if (!btn.nextElementSibling || !btn.nextElementSibling.classList.contains('restricted-btn')) {
			                const newBtn = createNewButton(btn);
			                btn.insertAdjacentElement('afterend', newBtn);
			                btn.style.display = 'none';
			            }
			        });
			    }

			    function createNewButton(originalBtn) {
			        const newBtn 		= document.createElement('a');
			        newBtn.href 		= btnLink;
			        newBtn.target 		= '_blank';
			        newBtn.rel 			= 'noopener noreferrer';
			        newBtn.className 	= 'restricted-btn elementor-button elementor-size-sm';
			        newBtn.textContent 	= 'Daily Order Limit Reached';
			        const computedStyle = window.getComputedStyle(originalBtn);
			        newBtn.style.cssText= `background-color: rgb(233, 230, 237);padding:1rem 5rem;color:inherit;`;
			        return newBtn;
			    }

			    function checkScheduleRestriction() {
			        const now = new Date();
			        const options = { timeZone, hour: '2-digit', minute: '2-digit', hour12: false };
			        const [hourStr, minuteStr] = new Intl.DateTimeFormat('en-US', options)
			            .format(now)
			            .split(":");
			        
			        const currentHour           = parseInt(hourStr, 10);
			        const currentMinute         = parseInt(minuteStr, 10);
			        const currentTotalMinutes   = currentHour * 60 + currentMinute;
			        
			        const startTotalMinutes     = startHour * 60 + startMinute;
			        const endTotalMinutes       = endHour * 60 + endMinute;

			        let restricted = false;

			        if (endTotalMinutes < startTotalMinutes) {
			            if (currentTotalMinutes >= startTotalMinutes || currentTotalMinutes < endTotalMinutes) {
			                restricted = true;
			            }
			        } else {
			            if (currentTotalMinutes >= startTotalMinutes && currentTotalMinutes < endTotalMinutes) {
			                restricted = true;
			            }
			        }

			        if (restricted) {
			            replaceButtons();
			        }
			    }

			    checkScheduleRestriction();
			});
			JS;
			$js_file_path	=	'/home/customer/www/start.brellohealth.com/public_html/public/widgets/cart-control.js';
			if (file_put_contents($js_file_path, $js_content) === false) {
		        bh_plugins_log('handle_schedule_settings_update: file not updated');
		    }

		    $user_info = '';
		    $current_user = wp_get_current_user();
			if ($current_user->exists()) {
			    $user_info = sprintf(
			        "👤 *Usuario:* %s (ID: %d)",
			        $current_user->display_name,
			        $current_user->ID,
			    );
			} else {
			    $user_info = "👤 System";
			}
		    //	#health-website
			$webhook_url = 'SLACK_WEBHOOK_URL_HERE';
		    $message	=	'⏰ Schedule Settings Updated: ' . $start_time . ' | ' . $end_time . ' | ' . $user_info;
			bh_send_slack_notification($message, $webhook_url);
		} catch (Exception $e) {
			bh_plugins_log('handle_schedule_settings_update: ' . $e->getMessage());
		}
	}

	/**
	 * Add custom Column to ListTable Coupon
	 * for display the subscription applied the coupon
	 * */
	function custom_coupon_add_column($columns) {
	    $columns['applied_to_subscription'] = __('Applied to', 'bh-features');
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
				$links[]	=	'<a href="'.esc_url($edit_url).'" target="_blank">' . esc_html__('#', 'bh-features'). $order->id .'</a>';
            }
			if($links) {
				echo implode(', ', $links);
			}
		}
	}

	/* 
	 * Ajax for send Shipping Change Request
	 */
	function send_shipping_request_ajax() {
		if (!is_user_logged_in()) wp_send_json_error('You must be logged in.');

		$message = sanitize_textarea_field($_POST['message'] ?? '');
		if (empty($message)) wp_send_json_error('Message cannot be empty.');

		$user = wp_get_current_user();

		$subject = 'Request to Change Shipping Address for Current Order';
		$to      = 'info@brellohealth.com';
		// $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: ' . $user->user_email];
		$headers = [
					'Content-Type: text/plain; charset=UTF-8',
					'From: ' . $user->user_email,
					'Reply-To: ' . $user->user_email
				];

		$sent = wp_mail($to, $subject, $message, $headers);

		if ($sent) {
			wp_send_json_success('✅ Your request has been sent successfully.');
		} else {
			wp_send_json_error('❌ Failed to send the request. Please try again.');
		}
	}

	/**
	 * Add metabox Access Restriction to pages
	 * */
	function add_logged_in_only_metabox() {
	    add_meta_box(
	        'logged_in_only_metabox',
	        'User Access Restriction',
	        [$this, 'display_logged_in_only_metabox'],
	        'page',
	        'side',
	        'high'
	    );
	}
	// Display the checkbox in metabox
	function display_logged_in_only_metabox($post) {
	    $logged_in_only = get_post_meta($post->ID, '_logged_in_only', true);
	    wp_nonce_field('save_logged_in_only', 'logged_in_only_nonce');
	    ?>
	    <label>
	        <input type="checkbox" name="logged_in_only" value="1" <?php checked($logged_in_only, '1'); ?> />
	        Show only for logged-in users
	    </label>
	    <p class="description">If checked, non-logged-in users will be redirected to home page.</p>
	    <?php
	}
	// Save the checkbox value
	function save_logged_in_only_metabox($post_id) {
	    if (!isset($_POST['logged_in_only_nonce']) || !wp_verify_nonce($_POST['logged_in_only_nonce'], 'save_logged_in_only')) {
	        return;
	    }
	    if (!current_user_can('edit_post', $post_id)) {
	        return;
	    }
	    if (isset($_POST['logged_in_only']) && $_POST['logged_in_only'] == '1') {
	        update_post_meta($post_id, '_logged_in_only', '1');
	    } else {
	        delete_post_meta($post_id, '_logged_in_only');
	    }
	}

	/**
	 * Show App Tracking Fields in User Profile
	 * */
	function show_app_tracking_profile_fields($user) {
		$uses_app 	=	get_user_meta($user->ID, '_uses_app', true);
		$last_opened = get_user_meta($user->ID, '_app_last_opened', true);
		//$last_used 	=	get_user_meta($user->ID, '_app_last_used', true);
		//$installed_datetime	=	get_user_meta($user->ID, '_app_installed_datetime', true);

		$uses_app_display = '❌ No';
		if($uses_app=='true')
			$uses_app_display =  '✅ Yes';

	    ?>
	    <br>
	    <hr>
	    <h3>📱 App Tracking Information</h3>
	    <table class="form-table">
	        <tr>
	            <th><label for="uses_app">Use the App?</label></th>
	            <td>
	                <?php 
	                echo '<strong>' . $uses_app_display . '</strong>';
	                ?>
	                <p class="description">Indicates whether the user has installed/is using the mobile app.</p>
	            </td>
	        </tr>
	         <tr>
	            <th><label for="app_installed_date">Installation Date</label></th>
	            <td>
	                <?php
	                $installed_date = get_user_meta($user->ID, '_app_installed_date', true);
	                echo $installed_date ? '<strong>' . $installed_date . '</strong>' : '-- Not registered --';
	                ?>
	            </td>
	        </tr>
	        <tr>
	            <th><label for="app_last_opened">Last App Use</label></th>
	            <td>
                <?php
	                if ($last_opened) {
	                    echo '<strong>' . $last_opened . '</strong>';
	                } else {
	                    echo '-- Never --';
	                }
				?>
	            </td>
	        </tr>
	    </table>
	    <hr>
	    <?php
	}

}
