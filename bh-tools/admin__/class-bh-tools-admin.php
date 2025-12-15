<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Tools
 * @subpackage Bh_Tools/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Bh_Tools
 * @subpackage Bh_Tools/admin
 * @author     Jaime Isidro <jaime@solutionswebonline.com>
 */
class Bh_Tools_Admin {

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

	/**
	 * Interval of days for plan
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
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
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

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
		 * defined in Bh_Tools_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Tools_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bh-tools-admin.css', array(), $this->version, 'all' );

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
		 * defined in Bh_Tools_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Tools_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bh-tools-admin.js', array( 'jquery' ), $this->version, false );

	}


	function telegramd_getToken(){
		$token	=	get_transient('telemdnow_token');
		if($token){
			return $token;
		}
		$username			=	get_option('telemdnow_affiliate_username');
		$Password			=	get_option('telemdnow_affiliate_password');
		$authenticationToken=	base64_encode($username . ':' . $Password);
		$curl				=	curl_init();
		//$telemdnow_rest_url =	get_option('telemdnow_rest_url');		
		$telemdnow_rest_url	=	'https://telegramd-rest.telegramd.com';
		curl_setopt_array($curl, array(
			CURLOPT_URL => $telemdnow_rest_url . '/auth/client',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_HTTPHEADER => array(
			'Authorization: Basic ' . $authenticationToken
			),
		));
		$response = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$token	=	'';
		if (!empty($response)) {
			$res_data	=	json_decode($response);
			$token		=	$res_data->token;
			set_transient('telemdnow_token', $token, DAY_IN_SECONDS);
		}
		return $token;
	}
	function telegramd_search($email){
		///search?access_token=&q=nick+brello010@telegramd.com
		global $telegramd_token;
		if(empty($telegramd_token))
			$telegramd_token	=	$this->telegramd_getToken();

		$telemdnow_rest_url	=	'https://telegramd-rest.telegramd.com';
		//$api_url	=	$telemdnow_rest_url . '/orders/order::2220cca0-b6f5-4a87-9185-3a9e14038640';
		$api_url	=	$telemdnow_rest_url . '/search?access_token=' . $telegramd_token . '&q=' . $email;
		$response	=	wp_remote_get($api_url, [
			'timeout'	=>	120,
			'headers' => array(
							'Authorization' => 'Bearer ' . $telegramd_token
						),
					]);
		if(is_wp_error($response)){
			_print($response->get_error_message());
			return [];
		}
		$jsonData	=	json_decode(wp_remote_retrieve_body($response), true);
		$rs	=	'';
		if(isset($jsonData['patients'][0]['_id']))
			$rs	=	$jsonData['patients'][0]['_id'];
		return $rs;
		
	}
	function telegramd_getData($patient_id){
		global $telegramd_token;
		if(empty($telegramd_token))
			$telegramd_token	=	$this->telegramd_getToken();
		
		$telemdnow_rest_url	=	'https://telegramd-rest.telegramd.com';
		//https://affiliate-admin.telegramd.com/patients/pat::451aec68-fb57-4389-9d54-aac46588fa3e
		$api_url	=	$telemdnow_rest_url . '/patients/' . $patient_id;
		$response	=	wp_remote_get($api_url, [
			'timeout'	=>	120,
			'headers' => array(
							'Authorization' => 'Bearer ' . $telegramd_token
						),
					]);

		if(is_wp_error($response)){
			return [];
		}
		$jsonData	=	json_decode(wp_remote_retrieve_body($response), true);
		$questionnaireInstances		=	$jsonData['questionnaireInstances'];
		return $questionnaireInstances;
	}


	function telegramd_init(){
		if(!isset($_GET['display']) || $_GET['display']!=='questionaries')
			return ;

		global $telegramd_token;
		$telegramd_token	=	$this->telegramd_getToken();
	}
	function add_questionarie_info_column($columns){
		if(!isset($_GET['display']) || $_GET['display']!=='questionaries')
			return $columns;

		$new_cols=[];
		foreach ($columns as $key => $column) {
			$new_cols[$key]	=	$column;
			if($key==='order_status'){
				$new_cols['order_questionarie']	=	'Questionarie';
			}
		}
		return $new_cols;
	}
	function show_questionarie_info_column_content($column, $order){
		if(!isset($_GET['display']) || $_GET['display']!=='questionaries')
			return $column;
		
		if($column==='order_questionarie'){
			$sufix					=	'';
			$order_id				=	$order->get_id();
			$questionnaireInstances	=	$order->get_meta_data();

			$telegramd_data			=	get_post_meta($order_id, 'telegramd_data', true);
			//_print($telegramd_data);
			$questionnaireInstances	=	false;
			if(!$telegramd_data){
				$patient_email					=	$order->get_billing_email();
				$patient_id						=	$this->telegramd_search($patient_email);
				$questionnaireInstances			=	$this->telegramd_getData($patient_id);
				$data['patient']['id']			=	$patient_id;
				$data['patient']['email']		=	$patient_email;
				$data['questionnaireInstances']	=	$questionnaireInstances;
				update_post_meta($order_id, 'telegramd_data', $data);
				$sufix					=	'*** ';
			}else{
				$questionnaireInstances	=	$telegramd_data['questionnaireInstances'];
			}

			if($questionnaireInstances){
				/*
				$telemdnow_rest_url	=	'https://affiliate-admin.telegramd.com';
				echo '<ul>';
				echo '<li>' . $sufix . '<a href="' . $telemdnow_rest_url . '/patients/' . $patient_id . '" target="_blank">View in telegramd</a></li>';
				*/
				$items_completed	=	0;
				$output='';
				foreach ($questionnaireInstances as $item) {
					//$item	=	(array) $item;
					//echo '<li>' . ($item['valid']? '&#9989;':'&#10060;') . ' ' . wp_trim_words($item['questionnaire']['title'], 3, '...') . '</li>';					
					$output	.=	'<li>' . ($item['valid']? '&#9989;':'&#10060;') . ' ' . wp_trim_words($item['questionnaire']['title'], 3, '...') . '</li>';
					if($item['valid'])
						$items_completed++;
				}
				if(count($questionnaireInstances)!=$items_completed){
					$telemdnow_rest_url	=	'https://affiliate-admin.telegramd.com';
					echo '<ul>';
					echo '<li>' . $sufix . '<a href="' . $telemdnow_rest_url . '/patients/' . $patient_id . '" target="_blank">View in telegramd</a></li>';
					echo $output;
					echo '</ul>';
				}

			}
		}
	}
	function filter_orders_by_questionarie_status(&$query){
		global $pagenow;
		//_print($pagenow, '$pagenow');
		/*if(
			'admin.php'===$pagenow &&  
			isset($_GET['page']) && 
			'wc-orders'===$_GET['page']			
			){*/
			$meta_query	=	$query->get('meta_query');
			if(!is_array($meta_query))
				$meta_query	=	[];

			$meta_query[]	=	array(
				'key'	=>	'telegramd_data',
				'compare'=>	'NOT EXISTS'
			);
			$query->set('meta_query', $meta_query);
		//}
	}


	function add_order_notes_filter( string $order_type ){
		if ( 'shop_order' !== $order_type ) {
			return;
		}
		?>
		<select name="filter_order_notes" id="filter_order_notes">
			<option value="">Filter by Order Notes</option>
			<option value="has_notes" <?php selected($_GET['filter_order_notes']?? '', 'has_notes'); ?>>With relevant notes</option>
		</select>
		<?php
		
	}
	function add_order_notes_column($columns){
		if(!isset($_GET['display']) || $_GET['display']!=='notes')
			return $columns;

		$new_cols=[];
		foreach ($columns as $key => $column) {
			$new_cols[$key]	=	$column;
			if($key==='order_status'){
				$new_cols['order_notes']	=	'Order Notes';
			}
		}
		return $new_cols;
	}
	
	function show_order_notes_column_content($column, $order){
		if(!isset($_GET['display']) || $_GET['display']!=='notes')
			return $column;
		
		if($column==='order_notes'){
			$post_id	=	$order->get_id();
			$args = array( 'order_id' => $post_id );
			$notes = wc_get_order_notes( $args );

			if(!empty($notes)){



				$should_display	=	true;
				$filtered_notes	=	[];				
				foreach ($notes as $note) {
					$content	=	$note->content;
					if(strpos($content, 'was successfully sent over to Telegra') !== false){
						$should_display	=	false;
						break;
					}
					$filtered_notes[]	=	$content;
				}

				if($should_display && !empty($filtered_notes)){
					$comments='';
					foreach ($filtered_notes as $filtered_note) {
						$comments	.=	'<li>' . esc_html(wp_trim_words($filtered_note, 10, '...')) . '</li>';
					}
					echo '<ol class="list">' . $comments . '</ol>';

				}
			}
		}
	}
	function custom_order_notes_column_styles(){
		if(!isset($_GET['display']) || $_GET['display']!=='notes')
			return ;

		echo '<style>table.wp-list-table .column-order_notes {width: 30%;}table.wp-list-table .column-order_notes .list {text-align: left;font-size: 11px;color: #666;padding: 5px 10px;border-radius: 6px;background-color: #d7cad2;list-style-position: inside;margin: 0;}table.wp-list-table .column-order_notes .list >  li {margin: 0;}</style>';
	}

	function filter_orders_by_order($query){
		global $pagenow;
		/*
		_print($_GET);
		_print($pagenow);
		_print('$query->is_main_query() -> ' .  $query->is_main_query());
		*/
		if(
			'admin.php'===$pagenow &&  
			isset($_GET['page']) && 
			'wc-orders'===$_GET['page'] &&
			isset($_GET['filter_order_notes']) && 
			'has_notes'===$_GET['filter_order_notes']
			){
				error_log('filter_orders_by_order YES');
			//$query->set('post_type', 'shop_order');
			//$query->set('post_status', 'on-hold');
			$query->set('meta_query', [
				[
					'key'		=>	'_order_notes',
					'compare'	=>	'EXISTS'
				]
			]);
			//$query->set('suppress_filters', false);
			$query->set('post__in', $this->get_orders_with_conditions());
		}

	}
	/**
	 * 	
	 */
	function hb_restrict_manage_posts($order_type = '') {
		//_print($_REQUEST);
		if ( '' === $order_type ) {
			$order_type = isset( $GLOBALS['typenow'] ) ? $GLOBALS['typenow'] : '';
		}

		if ( 'shop_subscription' !== $order_type ) {
			return;
		}

		$options	=	[
			'changed'	=>	[
				'label'	=>	'Subscription Updated',
				'selected'	=>	''
			],
			'unchanged'	=>	[
				'label'	=>	'Subscription Not Updated',
				'selected'	=>	''
			],
		];
		$action	=	isset($_GET['updated_product_subscription'])? $_GET['updated_product_subscription']:'';
		?>
		
			<input type="date" name="next_payment_start" value="<?php echo esc_attr($_GET['next_payment_start'] ?? ''); ?>" placeholder="Start Date">
			<input type="date" name="next_payment_end" value="<?php echo esc_attr($_GET['next_payment_end'] ?? ''); ?>" placeholder="End Date">

			<select name="updated_product_subscription">
				<option value="">---</option>
				<?php
				foreach ($options as $key => $value) {
					$output	=	'<option value="' . $key . '"';
					if($action==$key){
						$output	.=	' selected="selected"';
						$options[$action]['selected']	=	'yes';
					}
					$output	.=	'>' . $options[$key]['label'] . '</option>';
					echo $output;
				}
				?>
			</select>

			<input type="hidden" action="bh-action" value="filter_subscriptions">
		

		<?php
	
	}
	
	function hb_request($vars) {
		global $typenow;
		error_log('hb_request: ' . $typenow . print_r($_GET, true));

		
		$meta_query =	$vars['meta_query'] ?? [];
		if ( isset($_GET['next_payment_start'], $_GET['next_payment_end'])) {
			$start_date =	sanitize_text_field($_GET['next_payment_start']);
			$end_date 	=	sanitize_text_field($_GET['next_payment_end']);
			
			if(!empty($start_date)){
				$meta_query[] = [
					'key' => '_schedule_next_payment',
					'value' => $start_date,
					'compare' => '>',
				];
			}
			if(!empty($end_date)){
				$meta_query[] = [
					'key' => '_schedule_next_payment',
					'value' => $end_date,
					'compare' => '<',
				];
			}
		}
		if ( isset($_GET['updated_product_subscription']) && !empty($_GET['updated_product_subscription'])) {
			$updated_product_subscription	=	$_GET['updated_product_subscription'];
		
			if($updated_product_subscription =='changed'){
				$meta_query[] = [
					'key' => '_subscription_switched',
					'value' => 'yes',
				];
			}

			if($updated_product_subscription =='unchanged'){
				$meta_query[] = [
					'key' 		=>	'_subscription_switched',
					'compare' 	=>	'NOT EXISTS',
				];
			}
	
		}

		$vars['meta_query'] = $meta_query;
		error_log('hb_request:vars-> ' . print_r($vars, true));
		return $vars;
	}



	/**
	 * Process bh actions
	 */
/**
	 * Process pending subscription Renewal
	 */
	private function get_subscriptions_renewal_pending($args){
		$defaults	=	[
				'plan'	=>	'',
				'limit'	=>	'',
		];
		$args	=	wp_parse_args($args, $defaults);
		global $wpdb;

		$sql['from']	=	$wpdb->prefix . 'wc_orders o';
		$sql['from']	.=	' INNER JOIN ' . $wpdb->prefix . 'wc_orders_meta om_payment ON o.id = om_payment.order_id AND om_payment.meta_key = \'_schedule_next_payment\'';
		$sql['where']	=	'o.type = \'shop_subscription\' AND o.status = \'wc-active\'' ;
		$sql['where']	.=	' AND STR_TO_DATE(om_payment.meta_value, \'%Y-%m-%d %H:%i:%s\') <= NOW()' ;
				
		if(!empty($args['plan'])){						
			$sql['from']	.=	' INNER JOIN ' . $wpdb->prefix . 'wc_orders_meta om_billing ON o.id = om_billing.order_id AND om_billing.meta_key = \'_billing_interval\' ';
			$sql['where']	.=	' AND om_billing.meta_value = ' . $this->plan[$args['plan']]['interval'];
		}
		$sql['select']	=	'o.id AS subscription_id';
		$sql	=	'SELECT ' . $sql['select'] . ' FROM ' . $sql['from'] . ' WHERE ' . $sql['where'];

		if(!empty($args['limit']) ){
			$sql	.=	' limit ' . $args['limit'];
		}		
		// _print($sql);
		$subscriptions	=	$wpdb->get_results( $sql );
		return $subscriptions;
	}

	private function get_edit_link_wc_order($order_id, $cpt='order'){
		$page	=	'wc-orders';
		if(!empty($cpt) && $cpt=='subscription')
			$page	.=	'--shop_subscription';

		$url	=	admin_url('admin.php?page=' . $page . '&action=edit&id=' . $order_id);
		if(isset($_GET['linked']) && $_GET['linked']=='live')
			$url	=	'https://shop.brellohealth.com/wp-admin/admin.php?page=' . $page . '&action=edit&id=' . $order_id;

		$link	=	sprintf(
			'<a href="%s" target="_blank">%s</a>', 
			$url,
			$order_id
		);
		return $link;
	}
	function process_pending_subscription_renewals(){
		if(!isset($_GET['bh-action']) || $_GET['bh-action']!='pending_renewal')
			return ;

		$args		=	[];
		$plan_days	=	0;
		if(isset($_GET['plan']) && in_array($_GET['plan'], array_keys($this->plan))){
			$args['plan']	=	$_GET['plan'];
			$plan_days		=	$this->plan[$_GET['plan']]['days'];
		}
		if(isset($_GET['limit']) && !empty($_GET['limit']) ){
			$limit	=	intval($_GET['limit']);
			if($limit > 0){
				$args['limit']	=	$limit;
			}
		}

		$subscriptions	=	$this->get_subscriptions_renewal_pending( $args );
		// _print($subscriptions);
		if(!$subscriptions){
			error_log('No Subscriptions pending for renew today');
			return ;
		}

		$count_initial		=	count($subscriptions);
		$vars['action']		=	$_GET['bh-action'];

		$execute	=	false;
		$dry_run	=	false;

		$mode		=	'view';

		if(isset($_GET['mode']) && !empty($_GET['mode'])){
			$vars['mode']		=	$_GET['mode'];
			switch ($_GET['mode']) {
				case 'run':
					$execute	=	true;
					break;
				case 'dry_run':
					$dry_run	=	true;
					break;
			}
		}

		$filter_status	=	'';
		if(isset($_GET['status']) && !empty($_GET['status']) ){
			$filter_status	=	trim($_GET['status']);
		}

		$output		=	[];
		$today		=	current_time( 'mysql', true );
		$date_now 	=	new DateTime();
		foreach ($subscriptions as $subscription_post) {
			$subscription			=	wcs_get_subscription($subscription_post->subscription_id);
			$subscription_id		=	$subscription->get_id();
			/*
			if(!empty($filter_status) && !$last_renewal_order)
				continue ;
			*/
			$print					=	true;
			$days_since_last_payment=	0;
			$pending				=	false;
			
			$is_paused						=	$subscription->get_meta('_is_paused');
			$row['is_paused']				=	$is_paused;
			$row['parent']					=	'';
			$row['date_now']				=	$today;
			$order_parent_date_completed	=	false;

			$last_order_completed			=	false;
			$last_order_date_completed		=	false;
			$last_order_status				=	'';


			$row							=	[];
			$order_parent					=	$subscription->get_parent();
			if($order_parent){
				$row['parent']					=	$this->get_edit_link_wc_order($order_parent->get_id());			
				$order_parent_date_completed	=	$order_parent->get_date_completed();	
				if($order_parent_date_completed){
					$row['parent']				.=	' ' . wc_format_datetime( $order_parent_date_completed, 'Y-m-d H:i:s' );
					$last_order_date_completed	=	$order_parent_date_completed;
					$last_order_completed		=	$order_parent;
				}
			}

			$row['status']			=	$subscription->get_status();
			$row['plan']			=	$subscription->get_billing_interval() . ' ' . $subscription->get_billing_period();
			$current_next_payment	=	$subscription->get_date('next_payment');
			$row['next_payment']	=	$current_next_payment;
			$row['trial_end']		=	$subscription->get_date('trial_end');

			
			$last_renewal_order 	=	$subscription->get_last_order( 'all', 'renewal' );
			if($last_renewal_order){
				$last_renewal_order_status	=	$last_renewal_order->get_status();
				if(empty($filter_status) && $last_renewal_order_status!='completed'){
					continue ;
				}

				if(!empty($filter_status) && $filter_status<>$last_renewal_order_status){
					$print	=	false;
					continue ;
				}

				if($last_renewal_order_status == 'pending' && ($execute || $dry_run)){
					$pending	=	true;
					continue ;
				}

				$date_completed	=	$last_renewal_order->get_date_completed();
				if($date_completed){
					$last_order_date_completed	=	$date_completed;
					$last_order_completed		=	$last_renewal_order;
					
					$last_payment_date_completed=	new DateTime($last_renewal_order->get_date_completed());
					$date_diff					=	$date_now->diff($last_payment_date_completed);
					$days_since_last_payment 	=	$date_diff->format('%a');
				}
				$row['days']	=	$days_since_last_payment;

				$row['latest_renewal_order']['lro_id']		=	$last_renewal_order->get_id();
				$row['latest_renewal_order']['lro_status']	=	$last_renewal_order->get_status();
				$row['latest_renewal_order']['lro_date_created'] 	=	wc_format_datetime( $last_renewal_order->get_date_created(), 'Y-m-d H:i:s' );
				$row['latest_renewal_order']['lro_date_paid'] 		=	wc_format_datetime( $last_renewal_order->get_date_paid(), 'Y-m-d H:i:s' );
				$row['latest_renewal_order']['lro_date_completed']	=	wc_format_datetime( $last_renewal_order->get_date_completed(), 'Y-m-d H:i:s' );
			}else{
				$row['latest_renewal_order']	=	'NONE';
				if($order_parent_date_completed){
					$payment_date_completed		=	new DateTime($order_parent_date_completed);
					$date_diff					=	$date_now->diff($payment_date_completed);
					$days_since_last_payment 	=	$date_diff->format('%a');
				}
				$row['days']	=	$days_since_last_payment;
			}
			
			if(!$last_order_completed)
				continue ;

			$plan_days		=	0;
			$row['items']	=	[];
			if($last_order_completed){
				$last_order_status	=	$last_order_completed->get_status();;
				$items			=	$last_order_completed->get_items();
				foreach ($items as $item) {
					$product_id		=	$item->get_product_id();
					$variation_id	=	$item->get_variation_id();
					$product		=	wc_get_product($variation_id? $variation_id:$product_id);
					if(!$product)
						continue;
		
					$billing_interval	=	get_post_meta($product->get_id(), '_subscription_period_interval', true);
					$billing_period		=	get_post_meta($product->get_id(), '_subscription_period', true);
					$row['last_order_plan']			=	$billing_interval . ' ' . $billing_period;
		
					if($billing_interval!=$subscription->get_billing_interval())
						$row['items'][]	=	'Interval: ' . $billing_interval . '!=' . $subscription->get_billing_interval();
					if($billing_period!=$subscription->get_billing_period())
						$row['items'][]	=	'Period: ' . $billing_period . '!=' . $subscription->get_billing_period();
		
					$plan_days	=	$this->plan['monthly']['days'];
					if($billing_interval==3)
						$plan_days	=	$this->plan['3-month']['days'];

				}
			}
			$row['plan_days']	=	$plan_days;

			// $row['days_test']	=	$days_since_last_payment . ' > ' . $plan_days . ': ' . intval($days_since_last_payment > $plan_days);

			$row['items'][]		=	$days_since_last_payment . ' > ' . $plan_days . ': ' . intval($days_since_last_payment > $plan_days);

			// if($days_since_last_payment > $plan_days)
			// 	continue ;
			
			$row['link'] = $this->get_edit_link_wc_order($subscription_id, 'subscription');

			try {				
				// $row['items'][]		=	'if(!' . $pending .' && (' . $execute . '||' . $dry_run . ')) ' . intval(!$pending && ($execute || $dry_run));
				//_print('if(!$pending && ($execute || $dry_run)) ' . intval(!$pending && ($execute || $dry_run)));
				if(!$pending && ($execute || $dry_run)){
					// $row['items'][]		=	'if(' . $days_since_last_payment . '>=' .$plan_days . ') && ' . $last_order_status . '==completed) ' . intval(($days_since_last_payment >= $plan_days ) && $last_renewal_order_status=='completed');
					//_print('if(' . $days_since_last_payment . '>=' .$plan_days . ') && ' . $last_renewal_order_status . '==completed) ' . intval(($days_since_last_payment >= $plan_days ) && $last_renewal_order_status=='completed'));
					if((intval($days_since_last_payment)>=intval($plan_days) ) && $last_order_status=='completed'){
						// $output[$subscription_id]	=	$row;
						if($execute){
							WCS_Admin_Meta_Boxes::process_renewal_action_request( $subscription );
							$subscription->update_meta_data('_subscription_hb_action', 'process_renewal_action_request');
							$subscription->save();
							$row['items'][]	=	'Processed';
						}
						$output[$subscription_id]	=	$row;
					}
				}else{
					$output[$subscription_id]	=	$row;
				}

			} catch (Exception $e) {
				// _print('error al procesar la renovacion de la suscripcion ' . $subscription_id . ': ' . $e->getMessage());
				$row['items'][]	=	'<span class="failed">ERROR: ' . $e->getMessage() . '</span>';
				$output[$subscription_id]	=	$row;
			}
		}

		
		uasort($output, function($a, $b) {
			return $a['days'] <=> $b['days'];
		});
		
		_print($_GET);

		_print('Subscriptions Reviewed: ' . $count_initial);
		_print('Subscriptions Processed: ' . count($output) . ' subscriptions');
		//_print($output);
		$html	=	'';
		$i		=	1;
		foreach ($output as $subscription_id=>$row) {
			$html	.=	'<tr>';
			$html	.=	'<td>' . $i . '</td>';
			$html	.=	'<td>' . $row['link'] . '</td>';
			$html	.=	'<td>' . $row['parent'] . '</td>';
			$html	.=	'<td>' . $row['plan'] . '</td>';
			$html	.=	'<td>' . $row['trial_end'] . '</td>';
			$html	.=	'<td>' . $row['next_payment'] . '</td>';

			if(isset($row['latest_renewal_order']) && is_array($row['latest_renewal_order'])){
				$html	.=	'<td>';				
				$html	.=	$row['latest_renewal_order']['lro_id'];
				$html	.=	' (<span  class="' . $row['latest_renewal_order']['lro_status'] . '">' . $row['latest_renewal_order']['lro_status'] . '</span>)';
				$html	.=	' - ' . $row['latest_renewal_order']['lro_date_completed'];
				$html	.=	'</td>';
			}else{
				$html	.=	'<td>&nbsp;</td>';
			}

			$html	.=	'<td>' . intval($row['is_paused']) . '</td>';
			$html	.=	'<td>' . $row['date_now'] . '</td>';
			$html	.=	'<td>' . (isset($row['last_order_plan'])? $row['last_order_plan']: '') . '</td>';
			$html	.=	'<td>' . $row['days'] . '</td>';
			//$html	.=	'<td>' . $row['days_test'] . '</td>';
			$items   =	implode('<br>', $row['items']);
			$html	.=	'<td>' . $items . '</td>';
			$html	.=	'</tr>';
			$i++;
		}
		if(!empty($html)){
			echo '<table class="table widefat">
			<thead>
			<tr>
				<th>&nbsp;</th>
				<th colspan="7">Subscription</th>
				<th colspan="3">Last Order Completed</th>
			</tr>
			<tr>
				<th>#</th>
				<th>ID</th>
				<th>Order Parent</th>
				<th>Plan</th>
				<th>Trial End</th>
				<th>Next Payment</th>
				<th>Last Renewal Order</th>
				<th>Is Paused</th>
				<th>Date Now</th>

				<th>Plan</th>
				<th>Days Ago</th>
				<th>Observations</th>
			</tr>
			</thead>
			<tbody>';
			echo $html;
			echo '</tbody></table>';
			echo '<style>
				body {
					color: #3c434a;
					font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
					font-size: 14px;
					line-height: 1.4em;
					min-width: 600px;
				}

				.completed{color:green}
				.cancelled{color:#666}
				.pending{color:#777}
				.failed{color:#eba3a3}
				.on-hold{color:#f8dda7}
				.error_review{color::#eba3a3}

				.widefat td, .widefat th {
					border: 1px solid #c3c4c7;
				}

				.widefat {
					border-spacing: 0;
					width: 100%;
					clear: both;
					margin: 0
				}

				.widefat * {
					word-wrap: break-word
				}

				.widefat a,.widefat button.button-link {
					text-decoration: none
				}

				.widefat td,.widefat th {
					padding: 8px 10px
				}

				.widefat thead td,.widefat thead th {
					border-bottom: 1px solid #c3c4c7
				}

				.widefat tfoot td,.widefat tfoot th {
					border-top: 1px solid #c3c4c7;
					border-bottom: none
				}

				.widefat .no-items td {
					border-bottom-width: 0
				}

				.widefat td {
					vertical-align: top
				}

				.widefat td,.widefat td ol,.widefat td p,.widefat td ul {
					font-size: 13px;
					line-height: 1.5em
				}

				.widefat tfoot td,.widefat th,.widefat thead td {
					text-align: left;
					line-height: 1.3em;
					font-size: 14px
				}

				.updates-table td input,.widefat tfoot td input,.widefat th input,.widefat thead td input {margin: 0 0 0 8px;padding: 0;vertical-align: text-top}
				.widefat .check-column {width: 2.2em;padding: 6px 0 25px;vertical-align: top}
				.widefat tbody th.check-column {padding: 9px 0 22px}
				</style>';
		}
		die('Development Environment');
	}

	/**
	 * Process Update Next Payment Dates
	 */
	private function get_subscriptions($args){
		$defaults	=	[
				'plan'		=>	'',
				'limit'		=>	'',
				'offset'	=>	'',
				'product_id'=>	'',
		];
		$args	=	wp_parse_args($args, $defaults);
		//$product_id	=	252;

		$fn_args	=	[
			'subscription_status'	=> array( 'active' )
		];
		if(!empty($args['limit']) ){
			$fn_args['limit']	=	$args['limit'];
		}
		if(!empty($args['offset']) ){
			$fn_args['offset']	=	$args['offset'];
		}

		$subscriptions = wcs_get_subscriptions_for_product( $args['product_id'],
			'fields', 
			$fn_args
			 );
		//_print($subscriptions);
		return $subscriptions;
	}
	function process_update_next_payment_renewals(){
		if(!isset($_GET['bh-action']) || $_GET['bh-action']!='update_next_payment')
			return ;

		if(!isset($_GET['product_id']) || !is_numeric($_GET['product_id'])){
			_print('product_id is required');
			return ;
		}
		
		$args		=	[];
		$args['product_id']	=	intval($_GET['product_id']);

	
		if(isset($_GET['plan']) && in_array($_GET['plan'], array_keys($this->plan))){
			$args['plan']	=	$_GET['plan'];
		}
		if(isset($_GET['limit']) && !empty($_GET['limit']) ){
			$limit	=	intval($_GET['limit']);
			if($limit > 0){
				$args['limit']	=	$limit;

				if(isset($_GET['offset']) && !empty($_GET['offset']) )
					$args['offset']	=	$_GET['offset'];
			}
		}

		$subscriptions	=	$this->get_subscriptions( $args );
		if(!$subscriptions){
			_print('No Subscriptions pending for renew today');
			return ;
		}
		
		$execute	=	false;
		$dry_run	=	false;
		$mode		=	'view';
		if(isset($_GET['mode']) && !empty($_GET['mode'])){
			$vars['mode']		=	$_GET['mode'];
			switch ($_GET['mode']) {
				case 'run':
					$execute	=	true;
					break;
				case 'dry_run':
					$dry_run	=	true;
					break;
			}
		}
		$filter_status	=	'';
		if(isset($_GET['status']) && !empty($_GET['status']) ){
			$filter_status	=	trim($_GET['status']);
		}

		$exclude	=	'';
		if(isset($_GET['exclude']) && !empty($_GET['exclude']) ){
			$exclude	=	trim($_GET['exclude']);
		}
		
		$eval_status_next_payment	=	[];
	
		$output		=	[];
		$date_now 	=	new DateTime();
		foreach ($subscriptions as $key=>$subscription) {
			if($subscription->get_meta('_is_paused'))
				continue ;

			// if($subscription->get_meta('_bh_action_update_payment'))
			// 	continue ;
			$current_next_payment		=	$subscription->get_date('next_payment');
			if(!$current_next_payment)
				continue ;
			
			$row	=	[];

			$row['next_payment']		=	$current_next_payment;
			$row['hb_action_update_payment']			=	$subscription->get_meta('_bh_action_update_payment');
			$subscription_id		=	$subscription->get_id();
			
			$print					=	true;
			$days_since_last_payment=	0;
			$pending				=	false;
			
			$row['parent']					=	'';
			$last_order_completed			=	false;
			$last_order_date_completed		=	false;
			
			$order_parent					=	$subscription->get_parent();
			if($order_parent){
				$row['parent']					=	$this->get_edit_link_wc_order($order_parent->get_id());			
				$order_parent_date_completed	=	$order_parent->get_date_completed();
				if($order_parent_date_completed){
					$row['parent']				.=	' ' . wc_format_datetime( $order_parent_date_completed, 'Y-m-d H:i:s' );
					$last_order_date_completed	=	$order_parent_date_completed;
					$last_order_completed		=	$order_parent;
				}
				$row['parent_order']['id']				=	$this->get_edit_link_wc_order($order_parent->get_id());
				$row['parent_order']['status']			=	$order_parent->get_status();
				$row['parent_order']['date_created'] 	=	wc_format_datetime( $order_parent->get_date_created(), 'Y-m-d H:i:s' );
				$row['parent_order']['date_paid'] 		=	wc_format_datetime( $order_parent->get_date_paid(), 'Y-m-d H:i:s' );
				$row['parent_order']['date_completed']	=	wc_format_datetime( $order_parent->get_date_completed(), 'Y-m-d H:i:s' );
				
			}
	
			$row['status']				=	$subscription->get_status();
			$row['schedule_interval']	=	$subscription->get_billing_interval() . ' ' . $subscription->get_billing_period();
			
			$subscription_trial_end		=	$subscription->get_date('trial_end');
			$row['trial_end']			=	$subscription_trial_end;

			$last_renewal_order 	=	$subscription->get_last_order( 'all', 'renewal' );
			if($last_renewal_order){
				$last_renewal_order_status	=	$last_renewal_order->get_status();
				if(!empty($filter_status) && $filter_status<>$last_renewal_order_status){
					$print	=	false;
					continue ;
				}
	
				if($last_renewal_order_status == 'pending' && ($execute || $dry_run)){
					$pending	=	true;
					continue ;
				}
	
				$date_completed	=	$last_renewal_order->get_date_completed();
				
				if($date_completed){
					$last_order_date_completed	=	$date_completed;
					$last_order_completed		=	$last_renewal_order;
	
					$last_payment_date_completed=	new DateTime($last_renewal_order->get_date_completed());
					$date_diff					=	$date_now->diff($last_payment_date_completed);
					$days_since_last_payment 	=	$date_diff->format('%a');
				}
				$row['days']	=	$days_since_last_payment;
	
				$row['latest_renewal_order']['lro_id']		=	$last_renewal_order->get_id();
				$row['latest_renewal_order']['lro_status']	=	$last_renewal_order->get_status();
				$row['latest_renewal_order']['lro_date_created'] 	=	wc_format_datetime( $last_renewal_order->get_date_created(), 'Y-m-d H:i:s' );
				$row['latest_renewal_order']['lro_date_paid'] 		=	wc_format_datetime( $last_renewal_order->get_date_paid(), 'Y-m-d H:i:s' );
				$row['latest_renewal_order']['lro_date_completed']	=	wc_format_datetime( $last_renewal_order->get_date_completed(), 'Y-m-d H:i:s' );
	
				$subscription_trial_end	=	0;
				
			}else{
				$row['latest_renewal_order']	=	'NONE';
				
				// $order_parent_date_completed	=	$order_parent->get_date_completed();
				if($order_parent_date_completed){
					$payment_date_completed		=	new DateTime($order_parent_date_completed);
					$date_diff					=	$date_now->diff($payment_date_completed);
					$days_since_last_payment 	=	$date_diff->format('%a');
				}
				$row['days']	=	$days_since_last_payment;
			}

			if(!$last_order_completed)
				continue ;
	
			$plan_days		=	0;			
			$row['items']	=	[];
			if($last_order_completed){
				$items			=	$last_order_completed->get_items();
				foreach ($items as $item) {
					$product_id		=	$item->get_product_id();
					$variation_id	=	$item->get_variation_id();
					$product		=	wc_get_product($variation_id? $variation_id:$product_id);
					if(!$product)
						continue;
		
					$billing_interval	=	get_post_meta($product->get_id(), '_subscription_period_interval', true);
					$billing_period		=	get_post_meta($product->get_id(), '_subscription_period', true);
		
					if($billing_interval!=$subscription->get_billing_interval())
						$row['items'][]	=	'Interval: ' . $billing_interval . '!=' . $subscription->get_billing_interval();
					if($billing_period!=$subscription->get_billing_period())
						$row['items'][]	=	'Period: ' . $billing_period . '!=' . $subscription->get_billing_period();
		
					$plan_days	=	$this->plan['monthly']['days'];
					if($billing_interval==3)
						$plan_days	=	$this->plan['3-month']['days'];
		
					
				}
			}
			
			$row['last_order_date_completed']	=	wc_format_datetime( $last_order_date_completed, 'Y-m-d H:i:s' );
			$row['plan_days']	=	$plan_days;
			$new_next_payment		=	date('Y-m-d H:i:s', strtotime("+{$plan_days} days", strtotime($last_order_date_completed)));
			//$row['next_payment_new']=	$new_next_payment;

			if (strtotime($new_next_payment) <= time()) {
				//$new_next_payment = '<span style="color:red;font-weight:700">'.$new_next_payment.'</span><br>' . date('Y-m-d H:i:s', strtotime('+5 minutes'));
				$new_next_payment = date('Y-m-d H:i:s', strtotime('+5 minutes'));
			}
			
			$row['next_payment_new'] = $new_next_payment;

			// $row['items'][]	=	($current_next_payment==$new_next_payment)? 'equals':'differents';


			// $status_next_payment_date	=	($current_next_payment==$new_next_payment)? 'equals':'differents';
			$only_date1 = date('Y-m-d', strtotime($current_next_payment));
			$only_date2 = date('Y-m-d', strtotime($new_next_payment));

			$status_next_payment_date	=	($only_date1==$only_date2)? 'equals':'differents';

			if(!empty($exclude) && $status_next_payment_date==$exclude){
				continue ;
			}

			$row['equals_or_different']	=	$status_next_payment_date;

			$eval_status_next_payment[$status_next_payment_date]	=	!isset($eval_status_next_payment[$status_next_payment_date])? 1: $eval_status_next_payment[$status_next_payment_date] + 1;
			

			$row['items'][]	=	$status_next_payment_date;					
			$row['link'] = $this->get_edit_link_wc_order($subscription_id, 'subscription');
	
			try {
				if(!$pending && ($execute || $dry_run)){	
					//$output[$subscription_id]	=	$row;
					$dates	=	[
						'next_payment'	=>	$new_next_payment
					];
					if($subscription_trial_end){
						$dates['trial_end']	=	$new_next_payment;
					}
					$row['items'][]	=	print_r($dates, true);
					if($execute){
						$subscription->update_meta_data('_bh_action_update_payment', $current_next_payment);
						$subscription->update_dates($dates);
						$subscription->save();
						$row['items'][]	=	'Updated';
					}
					$output[$subscription_id]	=	$row;
				}
				else{
					$output[$subscription_id]	=	$row;
				}
								
			} catch (Exception $e) {
				$row['items'][]	=	'<span class="failed">ERROR: ' . $e->getMessage() . '</span>';
				$output[$subscription_id]	=	$row;
			}
			
		}	

		//$row['items']		=	implode(', ', $row['items']);
		_print($_GET);
		_print($eval_status_next_payment);
		_print('Process completed:');
		_print( count($subscriptions) . ' subscriptions reviewed');					
		_print( count($output) . ' subscriptions ' . ($execute? 'Processed':'Ready to process' ));
		//_print($output);
		$html	=	'';
		$i		=	1;
		foreach ($output as $subscription_id=>$row) {
			$class='';
			if($row['equals_or_different']==='differents')
				$class	=' class="highlight"';
			$html	.=	'<tr' . $class . '>';
			$html	.=	'<td>' . $i . '</td>';
			$html	.=	'<td>' . $row['link'] . '</td>';
			// $html	.=	'<td>' . ($row['paused']? 'paused':'') . '</td>';
			$html	.=	'<td>' . $row['hb_action_update_payment'] . '</td>';

			// $html	.=	'<td>' . $row['parent'] . '</td>';
	
			if(isset($row['parent_order']) && is_array($row['parent_order'])){
				$html	.=	'<td>';				
				$html	.=	$row['parent_order']['id'];
				$html	.=	' (<span  class="' . $row['parent_order']['status'] . '">' . $row['parent_order']['status'] . '</span>)';
				// $html	.=	' - ' . $row['parent_order']['date_completed'];
				$html	.=	'</td>';
			}else{
				$html	.=	'<td>&nbsp;</td>';
			}
			if(isset($row['latest_renewal_order']) && is_array($row['latest_renewal_order'])){
				$html	.=	'<td>';				
				$html	.=	$row['latest_renewal_order']['lro_id'];
				$html	.=	' (<span  class="' . $row['latest_renewal_order']['lro_status'] . '">' . $row['latest_renewal_order']['lro_status'] . '</span>)';
				// $html	.=	' - ' . $row['latest_renewal_order']['lro_date_completed'];
				$html	.=	'</td>';
			}else{
				$html	.=	'<td>&nbsp;</td>';
			}
			

			$html	.=	'<td>' . $row['last_order_date_completed'] . '</td>';	
			$html	.=	'<td>' . $row['days'] . '</td>';	
			$html	.=	'<td>' . $row['schedule_interval'] . '</td>';
			$html	.=	'<td>' . $row['plan_days'] . '</td>';
			$html	.=	'<td>' . $row['trial_end'] . '</td>';
			$html	.=	'<td>Cur ' . $row['next_payment'] . '</td>';
			$html	.=	'<td>New ' . $row['next_payment_new'] . '</td>';
			$items   =	implode('<br>', $row['items']);
			$html	.=	'<td>' . $items . '</td>';
			$html	.=	'</tr>';
			$i++;
		}
		if(!empty($html)){
			echo '<table class="table widefat">
			<thead>
			<tr>
				<th>#</th>
				<th>ID</th>
				<th>hb_action</th>
				<th>Parent</th>
				<th>Last Renewal Order</th>
				<th>Last Date Order Completed</th>
				<th>Days Last Payment</th>
				<th colspan="2">Schedule Payment | Days</th>
				<th>Trial End</th>
				<th>Next Payment</th>
				<th>Next Payment New</th>
				<th>Observations</th>
			</tr>
			</thead>
			<tbody>';
			echo $html;
			echo '</tbody></table>';
			echo '<style>
				body {
					color: #3c434a;
					font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
					font-size: 14px;
					line-height: 1.4em;
					min-width: 600px;
				}
				tr.highlight td {background: #ffe2e2;border: 1px solid #b90303;}
				.completed{color:green}
				.cancelled{color:#666}
				.pending{color:#777}
				.failed{color:#eba3a3}
				.on-hold{color:#f8dda7}
				.error_review{color::#eba3a3}
	
				.widefat td, .widefat th {
					border: 1px solid #c3c4c7;
				}
	
				.widefat {
					border-spacing: 0;
					width: 100%;
					clear: both;
					margin: 0
				}
	
				.widefat * {
					word-wrap: break-word
				}
	
				.widefat a,.widefat button.button-link {
					text-decoration: none
				}
	
				.widefat td,.widefat th {
					padding: 8px 10px
				}
	
				.widefat thead td,.widefat thead th {
					border-bottom: 1px solid #c3c4c7
				}
	
				.widefat tfoot td,.widefat tfoot th {
					border-top: 1px solid #c3c4c7;
					border-bottom: none
				}
	
				.widefat .no-items td {
					border-bottom-width: 0
				}
	
				.widefat td {
					vertical-align: top
				}
	
				.widefat td,.widefat td ol,.widefat td p,.widefat td ul {
					font-size: 13px;
					line-height: 1.5em
				}
	
				.widefat tfoot td,.widefat th,.widefat thead td {
					text-align: left;
					line-height: 1.3em;
					font-size: 14px
				}
	
				.updates-table td input,.widefat tfoot td input,.widefat th input,.widefat thead td input {margin: 0 0 0 8px;padding: 0;vertical-align: text-top}
				.widefat .check-column {width: 2.2em;padding: 6px 0 25px;vertical-align: top}
				.widefat tbody th.check-column {padding: 9px 0 22px}
				</style>';
		}
		die('Development Environment');
	
	}

	/**
	 * Cancel Subscriptions from Mississipi
	 */
	private function get_subscriptions_from_mississipi($args){
		$defaults	=	[
				'limit'	=>	'',
				'offset'=>	'',
		];
		$args	=	wp_parse_args($args, $defaults);
		global $wpdb;
		$sql	=	'SELECT wcoa.id, wcoa.order_id, wcoa.first_name, wcoa.last_name, wcoa.email, wcoa.phone, wco.id, wco.customer_id, wco.billing_email ';
		$sql	.=	'FROM mrb_wc_order_addresses wcoa INNER JOIN mrb_wc_orders wco ON wco.id=wcoa.order_id ';
		$sql	.=	'WHERE wcoa.state = \'MS\' AND wcoa.address_type = \'billing\' AND wco.type=\'shop_subscription\' AND wco.status=\'wc-active\'';

		if(!empty($args['limit']) ){
			// $sql	.=	' limit ' . $offset . $args['limit'];
			$sql	.=	' limit ';
			if(!empty($args['offset']) ){
				$sql	.=	$args['offset'] . ', ';
			}
			$sql	.=	$args['limit'];
		}	
		// _print($sql);
		$subscriptions	=	$wpdb->get_results( $sql );
		return $subscriptions;
	}
	function process_cancel_subscriptions_from_mississipi(){
		if(!isset($_GET['bh-action']) || $_GET['bh-action']!='cancel_subscriptions_ms')
			return ;

		$args		=	[];
		if(isset($_GET['limit']) && !empty($_GET['limit']) ){
			$limit	=	intval($_GET['limit']);
			if($limit > 0){
				$args['limit']	=	$limit;

				if(isset($_GET['offset']) && !empty($_GET['offset']) )
					$args['offset']	=	$_GET['offset'];
			}
		}
		
		
		$subscriptions	=	$this->get_subscriptions_from_mississipi( $args );
		if(!$subscriptions){
			_print('No Subscriptions from Mississipi');
			return ;
		}
		
		$execute	=	false;
		$dry_run	=	false;
		$mode		=	'view';
		if(isset($_GET['mode']) && !empty($_GET['mode'])){
			$vars['mode']		=	$_GET['mode'];
			switch ($_GET['mode']) {
				case 'run':
					$execute	=	true;
					break;
				case 'dry_run':
					$dry_run	=	true;
					break;
			}
		}
		try {
			$output		=	[];
			$date_now 	=	new DateTime();
			foreach ($subscriptions as $row_item_obj) {
				$subscription_id		=	$row_item_obj->order_id;
				//$subscription			=	wcs_get_subscription( $subscription_id );
				$row					=	[];
				$row['link']			=	$this->get_edit_link_wc_order($subscription_id, 'subscription');
				$row['order_id']		=	$row_item_obj->order_id;
				$row['first_name']		=	$row_item_obj->first_name;
				$row['last_name']		=	$row_item_obj->last_name;
				$row['email']			=	$row_item_obj->email;
				$row['phone']			=	$row_item_obj->phone;
				$row['billing_email']	=	$row_item_obj->billing_email;
				$row['parent']			=	'';
				
				$order_parent_date_completed	=	false;
				$last_order_completed			=	false;
				$last_order_date_completed		=	false;
				$last_order_status				=	'';

				$row['next_payment']	=	$subscription->get_date('next_payment');
				$order_parent			=	$subscription->get_parent();

				if($order_parent){
					$row['parent']					=	$this->get_edit_link_wc_order($order_parent->get_id()) . '(' . $order_parent->get_status() . ')';
					$order_parent_date_completed	=	$order_parent->get_date_completed();	
					if($order_parent_date_completed){
						$row['parent']				.=	' ' . wc_format_datetime( $order_parent_date_completed, 'Y-m-d H:i:s' );
						$last_order_date_completed	=	$order_parent_date_completed;
						$last_order_completed		=	$order_parent;
					}

					$row['parent_order']['id']				=	$this->get_edit_link_wc_order($order_parent->get_id());
					$row['parent_order']['status']			=	$order_parent->get_status();
					$row['parent_order']['date_created'] 	=	wc_format_datetime( $order_parent->get_date_created(), 'Y-m-d H:i:s' );
					$row['parent_order']['date_paid'] 		=	wc_format_datetime( $order_parent->get_date_paid(), 'Y-m-d H:i:s' );
					$row['parent_order']['date_completed']	=	wc_format_datetime( $order_parent->get_date_completed(), 'Y-m-d H:i:s' );
				}

				$last_renewal_order 	=	$subscription->get_last_order( 'all', 'renewal' );
				if($last_renewal_order){
					$last_renewal_order_status	=	$last_renewal_order->get_status();

					$date_completed	=	$last_renewal_order->get_date_completed();
					if($date_completed){
						$last_order_date_completed	=	$date_completed;
						$last_order_completed		=	$last_renewal_order;
					}

					$row['latest_renewal_order']['lro_id']		=	$last_renewal_order->get_id();
					$row['latest_renewal_order']['lro_status']	=	$last_renewal_order->get_status();
					$row['latest_renewal_order']['lro_date_created'] 	=	wc_format_datetime( $last_renewal_order->get_date_created(), 'Y-m-d H:i:s' );
					$row['latest_renewal_order']['lro_date_paid'] 		=	wc_format_datetime( $last_renewal_order->get_date_paid(), 'Y-m-d H:i:s' );
					$row['latest_renewal_order']['lro_date_completed']	=	wc_format_datetime( $last_renewal_order->get_date_completed(), 'Y-m-d H:i:s' );
				}else{
					$row['latest_renewal_order']	=	'NONE';
				}
				// if(!$last_order_completed)
				// 	continue ;

				if($execute || $dry_run){
					if($execute){
						
						$subscription_status			=	$subscription->get_status();
						$subscription->update_status( 'cancelled' );
						$subscription->add_order_note('Status changed from ' . $subscription_status . ' to Cancelled. State Restriction');
						$subscription->update_meta_data('_subscription_hb_action_cancel_state_ms', gmdate( 'Y-m-d H:i:s' ));
						$subscription->save();
						$row['items'][]	=	'Cancelled';
					}
					$output[$subscription_id]	=	$row;
				}
				else{
					$output[$subscription_id]	=	$row;
				}				
			}
		} catch (\Throwable $th) {
			_print($th);
		}
		_print($_GET);

		// _print('Subscriptions Reviewed: ' . $count_initial);
		 _print('Subscriptions Processed: ' . count($output) . ' subscriptions');
		//_print($output);
		$html	=	'';
		$i		=	1;
		$headers	=	['ID', 'Email', 'Phone', 'Billing Email', 'Observations'];
		
		foreach ($output as $subscription_id=>$row) {
			$html	.=	'<tr>';
			$html	.=	'<td>' . $i . '</td>';
			$html	.=	'<td>' . $row['link'] . '</td>';
			// $html	.=	'<td>' . $row['first_name'] . ' ' . $row['last_name'] . '</td>';
			// $html	.=	'<td>' . $row['email'] . '</td>';
			// $html	.=	'<td>' . $row['phone'] . '</td>';
			// $html	.=	'<td>' . $row['parent'] . '</td>';


			if(isset($row['parent_order']) && is_array($row['parent_order'])){
				$html	.=	'<td>';				
				$html	.=	$row['parent_order']['id'];
				$html	.=	' (<span  class="' . $row['parent_order']['status'] . '">' . $row['parent_order']['status'] . '</span>)';
				$html	.=	' - ' . $row['parent_order']['date_completed'];
				$html	.=	'</td>';
			}else{
				$html	.=	'<td>&nbsp;</td>';
			}
			
			if(isset($row['latest_renewal_order']) && is_array($row['latest_renewal_order'])){
				$html	.=	'<td>';				
				$html	.=	$row['latest_renewal_order']['lro_id'];
				$html	.=	' (<span  class="' . $row['latest_renewal_order']['lro_status'] . '">' . $row['latest_renewal_order']['lro_status'] . '</span>)';
				if(!empty($row['latest_renewal_order']['lro_date_completed']))
					$html	.=	' - ' . $row['latest_renewal_order']['lro_date_completed'];
				$html	.=	'</td>';
			}else{
				$html	.=	'<td>&nbsp;</td>';
			}

			$html	.=	'<td>' . $row['next_payment'] . '</td>';
			$items   =	isset($row['items'])?? implode('<br>', $row['items']);
			$html	.=	'<td>' . $items . '</td>';
			$html	.=	'</tr>';
			$i++;
		}
		if(!empty($html)){
			echo '<table class="table widefat">
			<thead>
			<tr>
				<th>#</th>
				<th>ID</th>
				<th>Parent</th>
				<th>Last Renewal Order</th>
				<th>Next Payment</th>
				<th>Observations</th>
			</tr>
			</thead>
			<tbody>';
			echo $html;
			echo '</tbody></table>';
			echo '<style>
				body {
					color: #3c434a;
					font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
					font-size: 14px;
					line-height: 1.4em;
					min-width: 600px;
				}

				.completed{color:green}
				.cancelled{color:#666}
				.pending{color:#777}
				.failed{color:#eba3a3}
				.on-hold{color:#f8dda7}
				.error_review{color::#eba3a3}

				.widefat td, .widefat th {
					border: 1px solid #c3c4c7;
				}

				.widefat {
					border-spacing: 0;
					width: 100%;
					clear: both;
					margin: 0
				}

				.widefat * {
					word-wrap: break-word
				}

				.widefat a,.widefat button.button-link {
					text-decoration: none
				}

				.widefat td,.widefat th {
					padding: 8px 10px
				}

				.widefat thead td,.widefat thead th {
					border-bottom: 1px solid #c3c4c7
				}

				.widefat tfoot td,.widefat tfoot th {
					border-top: 1px solid #c3c4c7;
					border-bottom: none
				}

				.widefat .no-items td {
					border-bottom-width: 0
				}

				.widefat td {
					vertical-align: top
				}

				.widefat td,.widefat td ol,.widefat td p,.widefat td ul {
					font-size: 13px;
					line-height: 1.5em
				}

				.widefat tfoot td,.widefat th,.widefat thead td {
					text-align: left;
					line-height: 1.3em;
					font-size: 14px
				}

				.updates-table td input,.widefat tfoot td input,.widefat th input,.widefat thead td input {margin: 0 0 0 8px;padding: 0;vertical-align: text-top}
				.widefat .check-column {width: 2.2em;padding: 6px 0 25px;vertical-align: top}
				.widefat tbody th.check-column {padding: 9px 0 22px}
				</style>';
		}
		die('Development Environment');
	}
	/**
	 *  */	
	function process_verify_order_status(){
		if(!isset($_GET['bh-action']) || $_GET['bh-action']!='verify_order_status')
			return ;

		$exclude	=	[];	
		if(isset($_GET['exclude']) && !empty($_GET['exclude'])){
			$exclude	=	trim($_GET['exclude']);
			$exclude	=	explode(',', $exclude);
		}

		$args		=	[];
		$upload_dir		=	wp_upload_dir();
		$csv_file_path	=	$upload_dir['basedir'] . '/csv/brello-completed-orders.csv';
		if(!file_exists($csv_file_path)){
			_print('File Not exist!');
			return ;
		}
		$html		=	'';
		$statuses	=	[];
		if(($handle=fopen($csv_file_path, 'r'))!==false){			
			$headers	=	fgetcsv($handle, 1000, ',');
			$html  	   .=	'<thead>';
			$html  	   .=	'<tr>';
			$html  	   .=	'<th>#</th>';
			foreach ($headers as $header) {
				$html	.=	'<th>' . esc_html($header) . '</th>';
			}
			$html  	   .=	'<th>Status</th>';
			$html	.=	'</tr></thead>';
			$html	.=	'<tbody>';
			$i=1;
			$last	=	count($headers)-1;
			while(($data=fgetcsv($handle, 1000, ','))!==false){
				$table_tr	=	'<tr>';
				$table_tr	.=	'<td>' . $i . '</td>';
				$c	=	0;
				$exclude_row = false;
				foreach ($data as $value) {
					if($c==$last){
						$link	=	$this->get_edit_link_wc_order($value);	
						$table_tr	.=	'<td>' . $link . '</td>';
						$table_tr	.=	'<td>';
						if(!empty($value)){
							$order	=	wc_get_order($value);
							if($order){
								$status		=	$order->get_status();
								if(in_array($status, $exclude)){
									$exclude_row = true;
									break;
									// continue ;
								}
								$table_tr	.=	$status;
								$statuses[$status]	=	!isset($statuses[$status])? 1:$statuses[$status]+1;
							}else{
								if(in_array('empty', $exclude)){
									$exclude_row = true;
									break;
								}
								$statuses['empty']	=	!isset($statuses['empty'])? 1:$statuses['empty']+1;
							}
						}else{
							if(in_array('empty', $exclude)){
								$exclude_row = true;
								break;
							}
							$statuses['empty']	=	!isset($statuses['empty'])? 1:$statuses['empty']+1;
						}

						$table_tr	.=	'</td>';
					}
					else{
						$table_tr	.=	'<td>' . $value . '</td>';
					}
					$c++;
				}
				$table_tr	.=	'</tr>';
				if (!$exclude_row) {
					$html .= $table_tr;
					$i++;
				}
				// $html	.=	$table_tr;
			}
			$html	.=	'</tbody>';
			fclose($handle);
		}else {
			_print('Cannot open the csv file!');
			return ;
		}
		if(!empty($html)){
			_print($statuses);
			_print($exclude);
			_print(($i - 1 ) . ' records');
			echo '<table class="table" border="1" style="width:100%;border-collapse:collapse">' . $html . '</table>';
		}
	}

	/**
	 * Read Files from csv
	 */
	function csvToArray($filename, $headers_first_row=true){
		$upload_dir		=	wp_upload_dir();
		$csv_file_path	=	$upload_dir['basedir'] . '/csv/' . $filename;
		$array	=	[];
		if(!file_exists($csv_file_path)){
			_print('File Not exist!');
			return $array;
		}
		if(($handle	= fopen($csv_file_path, 'r'))!==false){
			if($headers_first_row)
				$array['headers']	=	fgetcsv($handle, 1000, ',');
			// foreach ($headers as $header) {
			// 	$html	.=	'<th>' . esc_html($header) . '</th>';
			// }
			while(($row	= fgetcsv($handle, 1000, ','))!==false){
				$array['rows'][]	=	$row;
				// foreach ($data as $value) {}
			}
			fclose($handle);
		}else {
			_print('Cannot open the csv file!');
		}
		return $array;
	}
	function process_pause_subscriptions(){
		if(!isset($_GET['bh-action']) || $_GET['bh-action']!='pause_subscriptions')
			return ;

		$args		=	[];		
		$html		=	'';
		$statuses	=	[];
		$data		=	$this->csvToArray('pause-subscription-sheet.csv');

		$count_headers	=	0;
		if(isset($data['headers'])){
			$count_headers	=	count($data['headers']);
			$html  	   .=	'<thead>';
			$html  	   .=	'<tr>';
			$html  	   .=	'<th>#</th>';
			foreach ($data['headers'] as $header) {
				$html	.=	'<th>' . $header . '</th>';
			}
			// $html  	   .=	'<th>Susbscription ID</th>';
			$html	.=	'</tr></thead>';
		}
		if(isset($data['rows'])){			
			$html	.=	'<tbody>';
			$i=1;
			$last	=	$count_headers-1;
			foreach ($data['rows'] as $row) {
				$html	.=	'<tr>';
				$html	.=	'<td>' . $i . '</td>';
				$email	=	$row[0];
				$html	.=	'<td>' . $email . '</td>';
				$html	.=	'<td>';
				$user_id	=	email_exists($email);
				if($user_id){
					$subscriptions	=	wcs_get_subscriptions([
						'customer_id'	=>	$user_id,
						'subscription_status'    => array( 'active' ),
					]);
					if($subscriptions){
						foreach ($subscriptions as $subscription) {
							$link	=	$this->get_edit_link_wc_order_live($subscription->get_id(), 'subscription');
							$html	.=	$link . ' [' . $subscription->get_status() . ']' . '<br>';
						}
					}
				}				
				$html	.=	'</td>';
				$html	.=	'</tr>';
				$i++;
			}			
			$html	.=	'</tbody>';
		}

		if(!empty($html)){
			_print($statuses);
			echo '<table class="table" border="1" style="width:60%;border-collapse:collapse">' . $html . '</table>';
		}
	}

	/**
	 * Edit Next Payment Date when the subscription is created from Checkout
	 */
	function bh_woocommerce_checkout_subscription_created( $subscription, $order, $recurring_cart ){
		try {
			bh_plugin_dev_log('hb_woocommerce_checkout_subscription_created(' . $subscription->get_id() . ')');
			$subscription_billing_period	=	$subscription->get_billing_period();
			$subscription_billing_interval	=	$subscription->get_billing_interval();
			$subscription_next_payment		=	$subscription->get_date('next_payment');
			$subscription_trial_end			=	$subscription->get_date('trial_end');
			
			$current_next_payment			=	wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );		

			$plan_days	=	$this->plan['monthly']['days'];
			if($subscription_billing_interval==3)
				$plan_days	=	$this->plan['3-month']['days'];

			$new_next_payment = date('Y-m-d H:i:s', strtotime("+{$plan_days} days", strtotime($current_next_payment)));

			bh_plugin_dev_log('$plan_days->' . $plan_days);

			$dates	=	[
						'next_payment'	=>	$new_next_payment
					];
			if($subscription_trial_end){
				$dates['trial_end']	=	$new_next_payment;
			}
			$subscription->update_dates( $dates );

			$subscription->update_meta_data(
				'_hb_action_update_payment_dates', 
				[
					'original_data'	=>	[
						'billing_period'	=>	$subscription_billing_period, 
						'billing_interval'	=>	$subscription_billing_interval, 
						'trial_end'			=>	$subscription_trial_end, 
						'next_payment'		=>	$subscription_next_payment
					],
					'days_plan'	=>	$plan_days,				
				]);

			$subscription->save();

		} catch (\Throwable $th) {
			bh_plugin_dev_log($th);
		}
	}
	/**
	 * Edit Next Payment Date when the Order completed is a Renewal
	 */
	function bh_woocommerce_order_status_completed($order_id) {
		try {
			bh_plugin_dev_log('link_manual_order_and_update_subscription #' . $order_id . ' - BEGIN');
			$order = wc_get_order($order_id);
			if (!$order) {
				return;
			}
			bh_plugin_dev_log('wcs_order_contains_renewal(' . $order_id . ')-> ' . wcs_order_contains_renewal($order_id));
			//if(wcs_order_contains_renewal($order)){
			if(wcs_order_contains_renewal($order_id)){
				
				$subscriptions	=	wcs_get_subscriptions_for_renewal_order($order);
				foreach ($subscriptions as $subscription) {
					if ($subscription->get_status() !== 'active') {
						continue;
					}				
					$items	=	$subscription->get_items();
					foreach ($items as $item) {
						$product_id		=	$item->get_product_id();
						$variation_id	=	$item->get_variation_id();
						$product	=	wc_get_product($variation_id? $variation_id:$product_id);
						if(!$product)
							continue;

						$billing_interval	=	get_post_meta($product->get_id(), '_subscription_period_interval', true);
						$billing_period		=	get_post_meta($product->get_id(), '_subscription_period', true);

						bh_plugin_dev_log('Product Payment: ' . $billing_interval . ' ' . $billing_period);

						$billing_interval	=	!empty($billing_interval)? (int)$billing_interval:1;
						$billing_period		=	!empty($billing_period)? $billing_period:'month';

						bh_plugin_dev_log('Product Payment After: ' . $billing_interval . ' ' . $billing_period);

						$log['order']['payment']		=	$billing_interval . ' ' . $billing_period;
					}				
					$subscription_billing_period		=	$subscription->get_billing_period();
					$subscription_billing_interval		=	$subscription->get_billing_interval();
					$subscription_next_payment			=	$subscription->get_date('next_payment');
					$subscription_trial_end				=	$subscription->get_date('trial_end');
					
					$log['subscription']['payment']		=	$subscription_billing_interval . ' ' . $subscription_billing_period;
					$log['subscription']['next_payment']=	$subscription_next_payment;			
					$log['subscription']['next_payment']=	$subscription_trial_end;			

					$order_created_date					=	wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );
					$order_date_completed				=	$order->get_date_completed();

					$log['order']['created_date']		=	$order_created_date;
					$log['order']['date_completed']		=	$order_date_completed;
					//$log['return']['data']['next_payment']	=	$order_created_date;					
					$days	=	$this->plan['3-month']['days'];
					if($billing_interval==1)
						$days	=	$this->plan['monthly']['days'];

					$new_next_payment = date('Y-m-d H:i:s', strtotime("+{$days} days", strtotime($order_date_completed)));

					$log['return']['days']			=	$days;
					$log['return']['next_payment']	=	$new_next_payment;
					bh_plugin_dev_log ($log);
					$subscription->update_dates([
						'next_payment' => $new_next_payment,
					]);
					if($subscription_billing_period!=$billing_period){
						bh_plugin_dev_log('updating billing period');
						$subscription->set_billing_period($billing_period);
					}
					if($subscription_billing_interval!=$billing_interval){
						bh_plugin_dev_log('updating billing interval');
						$subscription->set_billing_interval($billing_interval);
					}
					$subscription->save();
				}
			}

		} catch (\Throwable $th) {
			bh_plugin_dev_log($th);
		}
	}

}
