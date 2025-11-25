<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Features
 * @subpackage Bh_Features/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Bh_Features
 * @subpackage Bh_Features/public
 * @author     Jaime <jaime@solutionswebonline.com>
 */
class Bh_Features_Public {

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

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	
	 private $licensed_states = [
		'AK' => 'Alaska',
		'AZ' => 'Arizona',
		'CO' => 'Colorado',
		'DE' => 'Delaware',
		'GA' => 'Georgia',
		'HI' => 'Hawaii',
		'ID' => 'Idaho',
		'IL' => 'Illinois',
		'IN' => 'Indiana',
		'IA' => 'Iowa',
		'KS' => 'Kansas',
		'KY' => 'Kentucky',
		'LA' => 'Louisiana',
		'ME' => 'Maine',
		'MI' => 'Michigan',
		'MN' => 'Minnesota',
		'MS' => 'Mississippi',
		'MO' => 'Missouri',
		'MT' => 'Montana',
		'NH' => 'New Hampshire',
		'NM' => 'New Mexico',
		'NY' => 'New York',
		'ND' => 'North Dakota',
		'OH' => 'Ohio',
		'OK' => 'Oklahoma',
		'OR' => 'Oregon',
		'PA' => 'Pennsylvania',
		'RI' => 'Rhode Island',
		'SD' => 'South Dakota',
		'TN' => 'Tennessee',
		'TX' => 'Texas',
		'UT' => 'Utah',
		'VT' => 'Vermont',
		'VA' => 'Virginia',
		'WA' => 'Washington',
		'DC' => 'Washington DC',
		'WV' => 'West Virginia',
		'WI' => 'Wisconsin',
		'WY' => 'Wyoming',
	];

	private $northbeam	=	[
		'api_key'	=>	'6b2c6295-6866-4c75-92a1-20d6308046fb',
		'client_id'	=>	'fb54c9cf-f976-42e3-b42d-a68062210854',
		'api_url'	=>	[
			'production'=>	'https://api.northbeam.io',
			'test'		=>	'https://api-uat.northbeam.io',
		]
	];

	public function __construct( $plugin_name, $version, $common ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->common = $common;

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
		 * defined in Bh_Features_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Features_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bh-features-public.css', array(), $this->version, 'all' );

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
		 * defined in Bh_Features_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Features_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if (!is_checkout()) {
            return;
        }
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bh-features-public.js', array( 'jquery' ), $this->version, false );

	}
	
	/**
	 * Disable Email Notification when a Password Reset is requested
	 * */
	function disable_password_change_admin_notification() {
	    remove_action('after_password_reset', 'wp_password_change_notification');
	}

	/**
	 * Login Customization
	 * */
	function custom_login_styles_and_layout() {
		?>
		<style>
			body.login {background: url(https://www.brellohealth.com/wp-content/uploads/2025/07/BG-1-scaled.png) no-repeat bottom left transparent;background-size: cover;}
			body.login:before{background-color:#f9f8a2;content:"";width:100%;height:15px;position:absolute;}
			form#loginform {background: transparent;display: flex;flex-direction: column;}
			body.login h1.wp-login-logo {display: none;}
			body.login .button-primary {float: none;background: #e9e6ed;color: #000;border: none;}
			body.login #nav {padding: 0;color: transparent;}
			body.login #nav a.wp-login-register {display: none;}
			body.login #backtoblog, .login #nav {padding: 0;}
			@media(min-width:768px){
				body.login {display:flex;}
				body.login:before{height:50px;}
				div#custom-login-wrapper {max-width: 900px;margin: 0 auto;width: 100%;padding: 0;display: grid;position:relative;align-content: center;}
				div#custom-login-wrapper:after {content:"";position:absolute;right:0;width: 50%;height:100%;background-size: 475px !important;background:url(https://www.brellohealth.com/wp-content/uploads/2025/07/account-login-700x606.webp) no-repeat center center transparent;}
				.login-form-container {width: 50%;display: flex;justify-content: flex-start;flex-direction: row;flex-wrap: wrap;}
			}
		</style>
		<?php
	}
	function custom_login_start() {
	    echo '<div id="custom-login-wrapper">';
	    echo '<div class="login-form-container">';
	}	
	function custom_login_end() {
	    echo '</div>';
	    echo '</div>';
	}

	/**
	 * Redirecto to Checkout Page
	 * */
	function bh_woocommerce_add_to_cart_redirect(){
		return wc_get_checkout_url();
	}

	function add_billing_shipping_summary() {
		if (!is_checkout()) {
			return;
		}
	
		$billing_address = WC()->customer->get_billing();
		$shipping_address = WC()->customer->get_shipping();
	
		echo '<div id="billing-shipping-summary" class="checkout-summary">';
		echo '<h3>' . __('Billing & Shipping Address', 'woocommerce') . '</h3>';
	
		echo '<div class="summary-section">';
		echo '<ul>';
		echo '<li><strong>' . __('Billing Address', 'woocommerce') . '</strong>';
		echo '<div id="formatted-billing-address">';
		if ($billing_address) {
			echo WC()->countries->get_formatted_address($billing_address);
		} else {
			echo __('No billing address provided.', 'woocommerce');
		}
		echo '</div>';
		echo '<a href="#" id="billing_" class="edit-address-button">' . __('Edit Addresses', 'woocommerce') . '</a>';
		echo '</li>';
		echo '<li>';
		echo '<strong>' . __('Shipping Address', 'woocommerce') . '</strong>';
		echo '<div id="formatted-shipping-address">';
		if ($shipping_address) {
			echo WC()->countries->get_formatted_address($shipping_address);
		} else {
			echo __('No shipping address provided.', 'woocommerce');
		}
		echo '</div>';
		echo '<a href="#" id="shipping_" class="edit-address-button">' . __('Edit Addresses', 'woocommerce') . '</a>';
		echo '</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}
	
	function change_ship_to_different_address_text($translated_text, $text, $domain){
		if('Ship to a different address?'===$text && 'woocommerce'===$domain)
			$translated_text    =   __('My billing address is the same as my shipping address.', 'woocommerce');
		return $translated_text;
	}

	public function woocommerce_review_order_before_cart_contents(){
		$billing_first_name = WC()->session->get('custom_billing_first_name', '');
		if (empty($billing_first_name)) {
			if (isset($_POST['billing_first_name'])) {
				$billing_first_name = sanitize_text_field($_POST['billing_first_name']);
				WC()->session->set('custom_billing_first_name', $billing_first_name);
			} elseif (isset(WC()->customer)) {
				$billing_first_name = WC()->customer->get_billing_first_name();
			}
		}
		$output	=	'<h2 class="hb-plan-title"><span id="custom_client_name">';
		if (!empty($billing_first_name)) {
			$billing_first_name = strtoupper($billing_first_name);
			$_title	= sprintf("%s'S ", esc_html($billing_first_name));
			$output		.=	$_title;
		}
		$output		.=	'</span>';

		$restricted_category=	'weight-loss';
		
		$weight_loss_count = 0;
		$weight_loss_items = array();

		foreach (WC()->cart->get_cart()  as $item_key => $item) {
			$product_id = $item['product_id'];
			$product_categories = wc_get_product_terms($product_id, 'product_cat', array('fields' => 'slugs'));			
			if (in_array($restricted_category, $product_categories)) {
				$weight_loss_count++;
			}
		}

		if ($weight_loss_count >= 1) {
			$output		.=	'WEIGHT LOSS ';
		}
		$output		.=	'PLAN</h2>';
		echo $output;
	}
	public function bh_woocommerce_cart_item_name($product_name, $cart_item, $cart_item_key){	
		$product = $cart_item['data'];
		$price = wc_price($product->get_price());
		$product_name .= '<p style="font-size: 14px; color: #777;">Price: ' . $price . '</p>';

		$product_id =	$cart_item['product_id'];
		$product	=	wc_get_product($product_id);
		$_product 	=	$cart_item['data'];
		$price		=	wc_price($_product->get_price());
		//$price		=	WC()->cart->get_total();

		$bh_checkout_text_supply    =	get_post_meta( $_product->get_id(), 'bh_checkout_text_supply', true );
		$bh_checkout_text_due_today	=	get_post_meta( $_product->get_id(), 'bh_checkout_text_due_today', true );		

		$output		=	'<div class="hb-product-info">';
		$output		.=	'<h3>' . $product->get_name() . '</h3>';

		$output		.=	'<div class="hb-columns">';

		$output		.=	'<ul class="info-purchase-product">';

		if(!empty($bh_checkout_text_supply))
			$output		.=	'<li class="day-supply">' . $bh_checkout_text_supply . '</li>';

		/*
		 *	Removed Target Weight Display in Checkout (Non-Browser Data)
		 *	Action: Hide the target weight field from the checkout UI until proper integration is implemented.
		 */
		/*
		$restricted_category=	'weight-loss';
		$product_categories = wc_get_product_terms($product_id, 'product_cat', array('fields' => 'slugs'));			
		if (in_array($restricted_category, $product_categories)) {
			$output		.=	'<li class="weight">Target Weight:<span>160 lbs</span></li>';
		}
		*/

		$output		.=	'<li class="refund">Full Refund if Not Qualified</li>';		
		$output		.=	'</ul>';
		$output		.=	'<figure class="product-thumbnail">';
		$output		.=	apply_filters('woocommerce_in_cart_product_thumbnail', $product->get_image('large'), $cart_item, $cart_item_key);

		$output		.=	'</figure>';
		$output		.=	'</div>';
		$output		.=	do_shortcode('[deadlinefunnel type="inline"]');
		$output		.=	'<hr>';
		$output		.=	'<div class="hb-due">';
		$output		.=	'<div><strong>Due Today</strong>';

		if(!empty($bh_checkout_text_due_today))
			$output		.=	'<br>' . $bh_checkout_text_due_today;

		$output		.=	'</div>';
		$output		.=	'<strong>' . $price . '</strong>';
		$output		.=	'</div>';
		$output		.=	'</div>';

		return $output;
	}

	function restrict_us_states__original($states) {
		$licensed_states	=	$this->licensed_states;
		$states['US'] = $licensed_states;
		return $states;
	}

	function get_states_allowed($states){
		try {
			$licensed_states	=	$this->licensed_states;
			if ( !is_user_logged_in() ){
				$states['US'] = $licensed_states;
			}
			else {
				$user = wp_get_current_user();
				if ( !array_intersect( ['administrator', 'customer_services'], $user->roles ) ) {
					$states['US'] = $licensed_states;
				}
			}
		} catch (\Throwable $th) {
			//	
		}
		return $states;
	}
	function restrict_us_states($states) {
		$states	=	$this->get_states_allowed($states);
		return $states;
	}
	public function hb_modal_single_product(){
		ob_start();
		?>
		<script>
			jQuery(document).ready(function ($) {
				console.log('from shortcode');
				// Función para habilitar el botón del modal cuando todas las variaciones están seleccionadas
				const enableModalButton = () => {
					let selectedVariation = true;
					$('.variations_form select').each(function () {
						if ($(this).val() === '') {
							selectedVariation = false;
						}
					});

					$('#place_order').prop('disabled', !selectedVariation);
				};

				// Escuchar cambios en las variaciones
				$('.variations_form select').on('change', enableModalButton);

				// Validar correo dinámicamente
				function validateEmail(email) {
					const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
					return emailRegex.test(email);
				}

				// Validar contraseña dinámicamente
				function validatePassword(password) {
					const passwordRegex = /^(?=.*[A-Z])(?=.*\d|.*[\W_]).{8,}$/;
					return passwordRegex.test(password);
				}

				// Manejar campo de correo
				$('#modal_email').on('input', function () {
					const email = $(this).val();
					if (validateEmail(email)) {
						$('#emailError').fadeOut();
						$('#nextToPassword').prop('disabled', false);
					} else {
						$('#emailError').fadeIn();
						$('#nextToPassword').prop('disabled', true);
					}
				});

				// Transición al campo de contraseña
				$('#nextToPassword').on('click', function () {
					$('#nextToPassword').hide();
					$('#emailContainer').find('button').prop('disabled', true); // Deshabilitar el botón para evitar doble clic
					$('#passwordContainer').fadeIn().addClass('active');


				});

				// Manejar campo de contraseña
				$('#modal_password').on('input', function () {
					const password = $(this).val();
					if (validatePassword(password)) {
						$('#passwordError').fadeOut();
						$('#submitModal').prop('disabled', false);
					} else {
						$('#passwordError').fadeIn();
						$('#submitModal').prop('disabled', true);
					}
				});

				// Guardar datos y redirigir al checkout
				$('#submitModal').on('click', function () {
					const email = $('#modal_email').val();
					const password = $('#modal_password').val();

					// Guardar en localStorage
					localStorage.setItem('checkout_email', email);
					localStorage.setItem('checkout_password', password);

					// Redirigir al checkout
					$('.single_add_to_cart_button').trigger('click'); // Simula clic para continuar con el pedido
				});

				// Abrir modal
				$('#place_order').on('click', function () {
					$('#customModal').fadeIn();
				});

				// Cerrar modal
				$('.close-modal, .modal-overlay').on('click', function () {
					$('#customModal').fadeOut();
				});

				// Inicializar estado del botón del modal
				enableModalButton();
			});

		</script>
		<div id="customModal" class="modal" style="display: none;">
			<div class="modal-overlay"></div>
			<div class="modal-content">
				<button class="close-modal">×</button>
				<div class="modal-step">
					<h2>Ready to order? Unlock special pricing</h2>
					<p>You're one step closer to joining the millions of Americans using GLP-1s.</p>
					<div class="modal_fields">
						<!-- Email Input -->
						<div id="emailContainer">                
							<input type="email" id="modal_email" placeholder="Enter your email" required="" class="modal-input">
							<div id="emailError" class="error-message" style="display: none;">Invalid email address</div>
							<button id="nextToPassword" disabled="">Continue</button>
						</div>

						<!-- Password Input -->
						<div id="passwordContainer" style="display: none;">                
							<input type="password" id="modal_password" placeholder="Create a password" required="" class="modal-input">
							<div id="passwordError" class="error-message" style="display: none;">Password must be at least 8 characters long and include at least one uppercase letter, number, or symbol.</div>

							<ul class="conditions">
								<li>Password must be at least 8 characters long</li>
								<li>Include at least one uppercase letter or number or symbol</li>
							</ul>
							<button id="submitModal" disabled="">Continue</button>
						</div>
					</div>
				</div>
			</div>
		</div>
		<style>
			.modal {display: none;
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;z-index: 1000;
			}
			.modal-overlay {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background-color: rgba(0, 0, 0, 0.5);
				backdrop-filter: blur(5px);
				z-index: 1;
			}
			.modal-content {
				position: absolute;
				bottom: 0;
				left: 0;
				width: 100%;
				min-height: 50%;
				background: #fff;
				border-radius: 0;
				padding: 20px;
				z-index: 2;
				animation: slideUp 0.3s ease-out;
			}
			.close-modal {
				position: absolute;
				top: 15px;
				right: 15px;
				font-size: 24px;
				background: none !important;
				border: none;
				cursor: pointer;
				z-index: 3;
				color:#6B53A0 !important;
			}
			.smooth-show {
				animation: fadeIn 0.5s ease-in-out forwards;
			}
			#passwordContainer {
				transition: all 0.3s ease-in-out;
			}

			#passwordContainer.active {
				opacity: 1;
				pointer-events: all;
			}
			#passwordContainer.active {
				display: block;
			}
			button:disabled {
				background-color: #ddd;
				cursor: not-allowed;
			}
			.error-message {
				color: #6B53A0;
				font-size: 14px;
				margin-top: 5px;
				display: none;
				padding: 0 1rem;
				text-align: left;
			}
			.modal-step {
				padding: 2.5rem 0.5rem 1rem;
				max-width: 800px;
				margin: auto;
				font-family: 'Inter';
				font-size: 14px;
				text-align: center;
			}

			.modal-step input {    
				border: 1px solid #E5D1E6;
				border-radius: 4px;
				padding: 15px;
			}

			.modal-step button {
				background: #C8FD40;
				border-color: #C8FD40;
				color: #012169;
				width:360px;
				padding: 1rem;
				border-radius: 6px;
				margin-top: 3rem;
				max-width:100%;
			}

			.modal-step > div {
				text-align: center;
			}
			.modal_fields {
				display: flex;
				flex-direction: column;
				gap: 1.5rem;
				align-content: center;
				margin-top: 30px;
				
			}

			.modal-step h2 {
				font-family: "Barlow Condensed";
				font-size: 2rem;
				font-weight: 500;
				color: #1E005D;
			}
			.conditions {
				text-align: left;
				padding-left: 20px;
				line-height: 1.5rem;
				margin-top: 1rem;
			}
			@media(min-width:768px){
				.modal-step {
				padding: 2rem;
				font-size: 18px;
			}
			.conditions {
				line-height: 2rem;
			}
			
			}
			@media(min-width:992px){
				.modal-step {
				padding: 5rem;
			}
			}

			@keyframes slideUp {
				from {
					transform: translateY(100%);
				}
				to {
					transform: translateY(0);
				}
			}
			@keyframes fadeIn {
				from {
					opacity: 0;
					transform: translateY(20px);
				}
				to {
					opacity: 1;
					transform: translateY(0);
				}
			}
			
		</style>
		<?php
		$output_string = ob_get_contents();
		ob_end_clean();
		return $output_string;
	}

	/*
	*	set Session If email is registered
	*/
	function associate_existing_customer_checkout() {
	    if (is_user_logged_in()) return;// Si ya está logueado, no hacer nada
	    
	    $billing_email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';
	    if (empty($billing_email)) return;

	    $customer = get_user_by('email', $billing_email);
	    if ($customer) {
	        wp_clear_auth_cookie();
	        wp_set_current_user($customer->ID);
	        wp_set_auth_cookie($customer->ID);
	        
	        WC()->session->set('customer_id', $customer->ID);
	        WC()->customer->set_props(array(
	            'billing_email' => $billing_email,
	            'billing_first_name' => isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : '',
	            'billing_last_name' => isset($_POST['billing_last_name']) ? $_POST['billing_last_name'] : '',
	            'billing_phone' => isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '',
	        ));
	        WC()->customer->save();
	    }
	}
	/*
	*
	*/
	function validate_logged_in_user_restrictions() {
	    if (!is_user_logged_in()) return;

	    $user_id = get_current_user_id();
	    $user = get_userdata($user_id);
	    $user_email = $user->user_email;
	    $user_phone = get_user_meta($user_id, 'billing_phone', true);


	    $weight_loss_in_cart = false;
	    foreach (WC()->cart->get_cart() as $cart_item) {
	        $product = $cart_item['data'];
	        $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
	        if (has_term('weight-loss', 'product_cat', $product_id)) {
	            $weight_loss_in_cart = true;
	            break;
	        }
	    }

	    $subscription_in_cart = false;
	    foreach (WC()->cart->get_cart() as $cart_item) {
	        if (WC_Subscriptions_Product::is_subscription($cart_item['data'])) {
	            $subscription_in_cart = true;
	            break;
	        }
	    }
		
	    global $wpdb;

	    if ($weight_loss_in_cart) {
	    	$query	=	$wpdb->prepare(
	            "SELECT 1 FROM {$wpdb->prefix}wc_orders o
	            JOIN {$wpdb->prefix}wc_order_addresses a ON o.id = a.order_id
	            JOIN {$wpdb->prefix}wc_order_product_lookup opl ON o.id = opl.order_id
	            WHERE o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
	            AND (o.customer_id = %d OR a.email = %s OR REGEXP_REPLACE(a.phone, '[^0-9]', '') = %s)
	            AND EXISTS (
	                SELECT 1 FROM {$wpdb->prefix}term_relationships tr
	                JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	                JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
	                WHERE (tr.object_id = opl.product_id OR tr.object_id = (
	                    SELECT post_parent FROM {$wpdb->prefix}posts 
	                    WHERE ID = opl.variation_id LIMIT 1
	                ))
	                AND tt.taxonomy = 'product_cat'
	                AND t.slug = 'weight-loss'
	            )
	            LIMIT 1",
	            $user_id, $user_email, preg_replace('/[^0-9]/', '', $user_phone)
	        );
			bh_plugins_log([$_GET, $_POST, $query]);
	        $has_weight_loss_order = $wpdb->get_var($query);
	        if ($has_weight_loss_order) {
	            wc_add_notice(
	                __('You can only purchase Compounded Plans products once per account.', 'woocommerce'),
	                'error'
	            );
	            return;
	        }
	    }

	    if ($subscription_in_cart) {
	        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
		        if (!WC_Subscriptions_Product::is_subscription($cart_item['data'])) continue;
		        
		        $product = $cart_item['data'];
		        $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
		        
		        global $wpdb;

		        $query	=	$wpdb->prepare(
			            "SELECT 1 FROM {$wpdb->prefix}wc_orders o
			            JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
			            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
			            WHERE o.type = 'shop_subscription'
			            AND o.status IN ('wc-active', 'wc-pending', 'wc-on-hold')
			            AND o.customer_id = %d
			            AND oi.order_item_type = 'line_item'
			            AND (
			                (oim.meta_key = '_product_id' AND oim.meta_value = %d) OR
			                (oim.meta_key = '_variation_id' AND oim.meta_value IN (
			                    SELECT ID FROM {$wpdb->prefix}posts 
			                    WHERE post_parent = %d 
			                    AND post_type = 'product_variation'
			                ))
			            )
			            LIMIT 1",
			            $user_id,
			            $product_id,
			            $product_id
			        );
		        $subscription_exists = $wpdb->get_var($query);
		        if ($subscription_exists) {
		            wc_add_notice(
		                __('You already have an active/pending subscription. Only one subscription per product is allowed..', 'woocommerce'),
		                'error'
		            );
		            return;
		        }
		    }
	    }
	    //wc_add_notice(__('LOGGED Sep: Testing.', 'woocommerce'), 'error');
	}

	function restrict_one_product_per_email() {
		if (is_user_logged_in()) return;

		$billing_email =	'';
		if (is_user_logged_in()) {
	        $user_id 		=	get_current_user_id();
	        $billing_email 	=	get_user_meta($user_id, 'billing_email', true);
	    } else {
	        if (!isset($_POST['billing_email'])) return;
	        	$billing_email = sanitize_email($_POST['billing_email']);
	    }
	    
	    if (empty($billing_email)) return;
	    
	    $billing_email = sanitize_email($_POST['billing_email']);
	    if (empty($billing_email)) return;

	    $weight_loss_in_cart = false;
	    foreach (WC()->cart->get_cart() as $cart_item) {
	        $product = $cart_item['data'];
	        $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
	        
	        if (has_term('weight-loss', 'product_cat', $product_id)) {
	            $weight_loss_in_cart = true;
	            break;
	        }
	    }

	    if (!$weight_loss_in_cart) return;

	    global $wpdb;
	    $has_order = $wpdb->get_var($wpdb->prepare(
	        "SELECT 1 FROM {$wpdb->prefix}wc_orders o
	        JOIN {$wpdb->prefix}wc_order_addresses a ON o.id = a.order_id
	        JOIN {$wpdb->prefix}wc_order_product_lookup opl ON o.id = opl.order_id
	        WHERE a.email = %s
	        AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
	        AND (
	            EXISTS (
	                SELECT 1 FROM {$wpdb->prefix}term_relationships tr
	                JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	                JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
	                WHERE (tr.object_id = opl.product_id OR tr.object_id = (
	                    SELECT post_parent FROM {$wpdb->prefix}posts 
	                    WHERE ID = opl.variation_id LIMIT 1
	                ))
	                AND tt.taxonomy = 'product_cat'
	                AND t.slug = 'weight-loss'
	            )
	        )
	        LIMIT 1",
	        $billing_email
	    ));

	    if ($has_order) {
	        wc_add_notice(
	            __('You can only purchase one weight-loss product per email address. You already have an existing order.', 'woocommerce'), 
	            'error'
	        );
	    }
	}

	function restrict_one_product_per_phone() {
		if (is_user_logged_in()) return;

	    if (!isset($_POST['billing_phone'])) return;
	    
	    $phone = preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['billing_phone']));
	    if (empty($phone)) return;

	    $weight_loss_in_cart = false;
	    foreach (WC()->cart->get_cart() as $cart_item) {
	        $product = $cart_item['data'];
	        $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
	        
	        if (has_term('weight-loss', 'product_cat', $product_id)) {
	            $weight_loss_in_cart = true;
	            break;
	        }
	    }

	    if (!$weight_loss_in_cart) return;

	    global $wpdb;
	    $has_order = $wpdb->get_var($wpdb->prepare(
	        "SELECT 1 FROM {$wpdb->prefix}wc_orders o
	        JOIN {$wpdb->prefix}wc_order_addresses a ON o.id = a.order_id
	        JOIN {$wpdb->prefix}wc_order_product_lookup opl ON o.id = opl.order_id
	        WHERE REGEXP_REPLACE(a.phone, '[^0-9]', '') = %s
	        AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
	        AND (
	            EXISTS (
	                SELECT 1 FROM {$wpdb->prefix}term_relationships tr
	                JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	                JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
	                WHERE (tr.object_id = opl.product_id OR tr.object_id = (
	                    SELECT post_parent FROM {$wpdb->prefix}posts 
	                    WHERE ID = opl.variation_id LIMIT 1
	                ))
	                AND tt.taxonomy = 'product_cat'
	                AND t.slug = 'weight-loss'
	            )
	        )
	        LIMIT 1",
	        $phone
	    ));

	    if ($has_order) {
	        wc_add_notice(
	            __('You can only purchase one weight-loss product per phone number. You already have an existing order.', 'woocommerce'), 
	            'error'
	        );
	    }

	}
	/**
	 * Restric PO Boxes in Address
	 */
	function restrict_po_boxes_in_checkout($fields, $errors){
		$error_message	=	__('Note: PO boxes cannot be used as a shipping address. Please provide an alternate address where your order can be delivered directly to your door.', 'woocommerce');
		/*
		'billing_address_1',
		'billing_address_2',
		*/
		$address_fields	=	[
			'shipping_address_1',
			'shipping_address_2',
		];
		foreach ($address_fields as $address_field_key) {
			if(isset($fields[$address_field_key]) && !empty($fields[$address_field_key])){
				if(preg_match('/\bP\.?O\.?\s*Box\b/i', $fields[$address_field_key])){
					wc_add_notice($error_message, 'error');
					break;
				}
			}
		}
		return $fields;
	}

	function variation_settings( $loop, $variation_data, $variation ) {
		$variation_id 				=	$variation->ID;
		$bh_checkout_text_supply    =	get_post_meta( $variation_id, 'bh_checkout_text_supply', true );
		$bh_checkout_text_due_today	=	get_post_meta( $variation_id, 'bh_checkout_text_due_today', true );
		$bh_checkout_text_term_conditions	=	get_post_meta( $variation_id, 'bh_checkout_text_term_conditions', true );

		echo '<div class="form-row form-row-full woovr-variation-settings">';
		echo '<label>' . esc_html__( 'Checkout Custom Info', 'bh-features' ) . '</label>';
		echo '<div class="woovr-variation-wrap">';

		echo '<p class="form-field form-row">';
		echo '<label>' . esc_html__( 'Supply Text', 'bh-features' ) . '</label>';
		echo '<input type="text" class="bh_checkout_text_supply" name="' . esc_attr( 'bh_checkout_text_supply[' . $variation_id . ']' ) . '" value="' . esc_attr( $bh_checkout_text_supply ) . '"/>';
		echo '</p>';
		echo '<p class="form-field form-row">';
		echo '<label>' . esc_html__( 'Due Today Text', 'bh-features' ) . '</label>';
		echo '<input type="text" class="bh_checkout_text_due_today" name="' . esc_attr( 'bh_checkout_text_due_today[' . $variation_id . ']' ) . '" value="' . esc_attr( $bh_checkout_text_due_today ) . '"/>';
		echo '</p>';
		echo '<p class="form-field form-row">';
		echo '<label>' . esc_html__( 'Terms & Conditions', 'bh-features' ) . '</label>';
		echo '<input type="text" class="bh_checkout_text_term_conditions" name="' . esc_attr( 'bh_checkout_text_term_conditions[' . $variation_id . ']' ) . '" value="' . esc_attr( $bh_checkout_text_term_conditions ) . '"/>';
		echo '</p>';
		echo '</div></div>';
	}

	function save_variation_settings( $post_id ) {
		if ( isset( $_POST['bh_checkout_text_supply'][ $post_id ] ) ) {
			update_post_meta( $post_id, 'bh_checkout_text_supply', sanitize_text_field( $_POST['bh_checkout_text_supply'][ $post_id ] ) );
		} else {
			delete_post_meta( $post_id, 'bh_checkout_text_supply' );
		}

		if ( isset( $_POST['bh_checkout_text_due_today'][ $post_id ] ) ) {
			update_post_meta( $post_id, 'bh_checkout_text_due_today', sanitize_text_field( $_POST['bh_checkout_text_due_today'][ $post_id ] ) );
		} else {
			delete_post_meta( $post_id, 'bh_checkout_text_due_today' );
		}

		if ( isset( $_POST['bh_checkout_text_term_conditions'][ $post_id ] ) ) {
			update_post_meta( $post_id, 'bh_checkout_text_term_conditions', sanitize_text_field( $_POST['bh_checkout_text_term_conditions'][ $post_id ] ) );
		} else {
			delete_post_meta( $post_id, 'bh_checkout_text_term_conditions' );
		}
	}

	function force_individual_products_cart($sold_individually, $product){
		return true;
	}

	function restrict_shipping_states($fields, $errors) {
		$licensed_states	=	array_keys($this->licensed_states);
		$shipping_state	=	isset($_POST['shipping_state']) ? strtoupper(sanitize_text_field($_POST['shipping_state'])) : '';
		$billing_state	=	isset($_POST['billing_state']) ? strtoupper(sanitize_text_field($_POST['billing_state'])) : '';
		
		if (
			!in_array($shipping_state, $licensed_states) ||  
			!in_array($billing_state, $licensed_states)) {
				$errors->add('validation', __('Sorry, we only ship to certain states.', 'woocommerce'));
		}
	}

	function enqueue_google_places_and_states() {
		$api_key	=	'AIzaSyCxzEHamXCST4g_jkfQxelokodztX9tqwY';
		wp_enqueue_script(
			'google-places-api',
			'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places',
			array(),
			null,
			true
		);

		wp_enqueue_script(
			'custom-google-places',
			plugins_url('js/bh-features-public-google-places.js', __FILE__),
			array('google-places-api', 'jquery'),
			null,
			true
		);
	
		$licensed_states	=	array_keys($this->licensed_states);
		wp_localize_script('custom-google-places', 'allowedStates', $licensed_states);
	}

	function print_graphic_shortcode($atts){
		return '<div><canvas id="graph_canvas"></canvas></div>';
	}
	function enqueue_quiz_styles(){		
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'qsm') || is_singular('qsm_quiz') ) {
			wp_enqueue_style( 'quiz-styles', plugins_url('css/bh-features-quiz-public.css', __FILE__), array() );
			wp_enqueue_script( 'quiz-chart', QSM_PLUGIN_JS_URL . '/chart.min.js', array(), '3.6.0', true );
			wp_enqueue_script( 'quiz-chart-plugin-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels', array('quiz-chart'), '3.6.0', true );
			wp_enqueue_script( 'quiz-scripts', plugin_dir_url( __FILE__ ) . 'js/bh-features-public-quiz.js', array( 'quiz-chart', 'quiz-chart-plugin-datalabels', 'jquery' ), $this->version, true );

			$quiz_id = get_post_meta( $post->ID, 'quiz_id', true );
			if(file_exists(plugin_dir_path(__FILE__) . 'js/qsm/quiz-' . $quiz_id . '.js')){
				wp_enqueue_script( 'quiz-scripts-custom', plugin_dir_url( __FILE__ ) . 'js/qsm/quiz-' . $quiz_id . '.js', array( 'quiz-chart', 'quiz-chart-plugin-datalabels', 'jquery' ), $this->version, true );
			}
		}		
	}
	function hb_qsm_display_before_form($data, $options, $quiz_data ){
		$param_name='plan-selected';
		if(!isset($_REQUEST[$param_name]) || empty($_REQUEST[$param_name]))
			return ;
		$data	=	$_REQUEST[$param_name];
		$parts	=	explode('|', $data);

		$url	=	add_query_arg(
			'attribute_pa_subscription', $parts[1],
			home_url('/product/' . $parts[0])
		);
		?>
		<script>
			var hb_custom_redirect	=	"<?php echo $url ?>";
			console.log('hb_custom_redirect');
		</script>
		<?php
	}
	
	function render_pause_subscription_metabox($subscription) {
		if ($subscription->get_status() === 'active') {
			$subscription_id	=	$subscription->get_id();
			//$is_paused			=	get_post_meta($subscription_id, '_is_paused', true);
			$is_paused			=	$subscription->get_meta('_is_paused');

			$by_default_months_paused	=	2;

			include __DIR__ . '/partials/bh-features-public-display.php';

			$_date_trial	=	false;
			if(!empty($subscription->get_date('trial_end')))
				$_date_trial	=	new DateTime($subscription->get_date('trial_end'));

			$_next_payment	=	false;
			if(!empty($subscription->get_date('next_payment')))
				$_next_payment	=	new DateTime($subscription->get_date('next_payment'));

			if(!$_next_payment && !$_date_trial)
				return ;
			
			$output	=	'<script type="text/javascript">' . "\n";
			$output	.=	'var months_paused = ' . $by_default_months_paused . ";\n";
			if($_date_trial){
				$output	.=	'var originalTrialEnd = "' . esc_js( $_date_trial->format('Y-m-d') ). '";' . "\n";
			}
			if($_next_payment){
				$output	.=	'var originalNextPayment = "' . esc_js( $_next_payment->format('Y-m-d') ). '";' . "\n";
			}
			echo $output;
			echo '</script>';
			wp_enqueue_script( 'bh-features-admin-subscription-script', plugin_dir_url( __DIR__ ) . 'admin/js/bh-features-admin-subscription.js', array( 'jquery' ), $this->version, true );
		}
	}

	function save_pause_subscription_status($post_id) {
		$subscription = wcs_get_subscription($post_id);
		if (!$subscription || $subscription->get_status() !== 'active') {
			return;
		}

		$is_paused = isset($_POST['pause_subscription']);
		if ($is_paused) {
			$subscription->update_meta_data('_is_paused', 1);
			$trial_end 		=	isset($_POST['trial_end']) ? sanitize_text_field($_POST['trial_end']) : null;
			$next_payment 	=	isset($_POST['next_payment']) ? sanitize_text_field($_POST['next_payment']) : null;
			$subscription->add_order_note("Subscription is paused. New dates: Trial End: $trial_end, Next Payment: $next_payment.");
		} else {
			$_is_paused	=	$subscription->get_meta('_is_paused');
			if(!empty($_is_paused)){
				$subscription->delete_meta_data('_is_paused');
				$subscription->add_order_note("Subscription has been exited from pause mode.");
			}
		}
		$subscription->save();
	}
	function remove_status_pause_subscription_renewal_payment_completed($order_id){
		$order	=	wc_get_order($order_id);
		if(wcs_order_contains_renewal($order)){
			$subscriptions	=	wcs_get_subscriptions_for_renewal_order($order_id);
			foreach ($subscriptions as $subscription) {
				$_is_paused	=	$subscription->get_meta('_is_paused');
				if(!empty($_is_paused)){
					$subscription->delete_meta_data('_is_paused');
					$subscription->save_meta_data();
					$subscription->add_order_note("Subscription has been exited from pause mode.");
				}
			}
		}
	}
	function bh_woocommerce_checkout_fields_phone_validation($fields){
		$fields['billing']['billing_phone']['custom_attributes']	=	['minlength'=>'10', 'pattern'=>'[0-9]{10,}'];
		$fields['billing']['billing_phone']['placeholder']	=	'The phone number must have at least 10 digits.';
		return $fields;
	}
	function bh_woocommerce_checkout_process_field_phone_validation(){
		$phone	=	isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';
		$phone	=	preg_replace('/[^0-9]/', '', $phone);
		if(strlen($phone)<10 || !ctype_digit($phone)){
			wc_add_notice(__('The phone number must have at least 10 digits.', 'woocommerce'), 'error');
		}
	}
	
	function link_manual_order_and_update_subscription__old($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}
		if(wcs_order_contains_renewal($order)){
			$subscriptions	=	wcs_get_subscriptions_for_renewal_order($order_id);
			foreach ($subscriptions as $subscription) {
				if ($subscription->get_status() !== 'active') {
					continue;
				}
				$billing_period		=	$subscription->get_billing_period();
				$billing_interval	=	$subscription->get_billing_interval();	
				$current_next_payment	=	$subscription->get_date('next_payment');
				$new_next_payment 	=	date('Y-m-d H:i:s', strtotime("+{$billing_interval} {$billing_period}", strtotime($current_next_payment)));
				$subscription->update_dates([
					'next_payment' => $new_next_payment,
				]);
				$subscription->save();
			}
		}
	}

	function link_manual_order_and_update_subscription($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}
		try {
			if(wcs_order_contains_renewal($order)){
				$subscriptions	=	wcs_get_subscriptions_for_renewal_order($order_id);
				foreach ($subscriptions as $subscription) {
					if ($subscription->get_status() !== 'active') {
						continue;
					}				

					$items=$subscription->get_items();
					foreach ($items as $item) {
						$product_id		=	$item->get_product_id();
						$variation_id	=	$item->get_variation_id();
						$product	=	wc_get_product($variation_id? $variation_id:$product_id);
						if(!$product)
							continue;

						$billing_interval	=	get_post_meta($product->get_id(), '_subscription_period_interval', true);
						$billing_period		=	get_post_meta($product->get_id(), '_subscription_period', true);

						$billing_interval	=	!empty($billing_interval)? (int)$billing_interval:1;
						$billing_period		=	!empty($billing_period)? $billing_period:'month';
					}				
					$subscription_billing_period	=	$subscription->get_billing_period();
					$subscription_billing_interval	=	$subscription->get_billing_interval();
					//$current_next_payment			=	$subscription->get_date('next_payment');
					$current_next_payment   		=	wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );
					
					$days	=	70;
					if($billing_interval==1)
						$days	=	25;

					$new_next_payment = date('Y-m-d H:i:s', strtotime("+{$days} days", strtotime($current_next_payment)));

					$subscription->update_dates([
						'next_payment' => $new_next_payment,
					]);
					if($subscription_billing_period!=$billing_period){
						error_log('updating billing period');
						$subscription->set_billing_period($billing_period);
					}
					if($subscription_billing_interval!=$billing_interval){
						error_log('updating billing interval');
						$subscription->set_billing_interval($billing_interval);
					}
					$subscription->save();
				}
			}
		} catch (\Throwable $th) {
			return ;
		}
	}

	function order_renewal_payment_completed_send_to_telegram( $order_id, $old_status, $new_status, $order ){
		try {
			if (!$order || $order->get_status()!=='processing') {
				return;
			}
			
			if(wcs_order_contains_renewal($order)){
				$subscriptions	=	wcs_get_subscriptions_for_renewal_order($order_id);
				foreach ($subscriptions as $subscription) {
					if ($subscription->get_status() !== 'active') {
						continue;
					}
					$order->update_status('wc-on-hold');
				}
			}
		} catch (\Throwable $th) {
			return ;
		}
	}
	
	function bh_woocommerce_checkout_fields_kl_newsletter_checkbox($fields){
		if(isset($fields['billing']['kl_newsletter_checkbox'])){
			$fields['billing']['kl_newsletter_checkbox']['label']	=	'<span>' . $fields['billing']['kl_newsletter_checkbox']['label'] . '</span>';
		}

		return $fields;
	}

	function woocommerce_subscription_renewal_payment_failed($subscription, $renewal_order){
		global $email_sent_to_order;
		if($email_sent_to_order==$renewal_order->get_id())
			return ;

		if($renewal_order instanceof WC_Order){
			if(wcs_order_contains_renewal($renewal_order)){
				$customer_email	=	$renewal_order->get_billing_email();
				$subject		=	'Action Required: Update Your Payment Your Renewal Failed';
				$my_account_url	=	get_permalink( get_option('woocommerce_myaccount_page_id') );
				$name			=	$renewal_order->get_billing_first_name();
				$message		=	<<<EOD
				<p>Hi {$name},</p>
				<p>We weren’t able to process your renewal order payment. To ensure uninterrupted access to your subscription, please update your payment details.</p>
				<p>👉 <a href="{$my_account_url}">[Log in to your account]</a></p>

				<p><strong>How to update your payment method:</strong></p>
				<ol>
					<li>Go to <strong>Payment Methods</strong> in the left menu and add your new payment details.</li>
					<li>Navigate to <strong>My Subscription</strong>.</li>
					<li>Under <strong>Actions</strong>, select <strong>Change Payment</strong> to confirm your update.</li>
				</ol>
				<p>If you need help at any point, our support team is here for you—just reply to this email. 😊</p>
				<p>Once your payment information is updated, let us know so we can help process your order.</p>
				<p>Thank you for being part of the Brello community!</p>
				EOD;

				if(function_exists('the_custom_logo')){
					$message	=	'<div style=\'line-height: 150%; text-align: left; font-size: 14px; font-family: "Helvetica Neue",Helvetica,Roboto,Arial,sans-serif; color: #737373;\'>' .	get_custom_logo() . $message . '</div>';
				}

				$headers	=	['Content-Type: text/html; charset=UTF-8'];
				wp_mail($customer_email, $subject, $message, $headers);
				$email_sent_to_order	=	$renewal_order->get_id();
			}
			

		}

	}

	function validate_previous_order_status_before_renewal($renewal_order, $subscription) {
		if(isset($_REQUEST['wc_order_action']) && $_REQUEST['wc_order_action']=='wcs_process_renewal'){
			return $renewal_order;
		}

		if (!is_a($renewal_order, 'WC_Order') || !is_a($subscription, 'WC_Subscription')) {
			return $renewal_order;
		}
		$orders = $subscription->get_related_orders('all', 'renewal');
		if (empty($orders)) {
			return $renewal_order;
		}
		usort($orders, function($a, $b) {
			$order_a = wc_get_order($a);
			$order_b = wc_get_order($b);
			return strtotime($order_a->get_date_created()) - strtotime($order_b->get_date_created());
		});
		$last_order_id = $renewal_order->get_id();
		$previous_order = null;

		foreach ($orders as $order) {
			$order_id	=	$order->get_id();
			if ($order_id == $last_order_id) {
				break;
			}
			$previous_order = wc_get_order($order_id);
		}

		if (!$previous_order || !is_a($previous_order, 'WC_Order')) {
			return $renewal_order;
		}

		$previous_order_status = $previous_order->get_status();
		if ($previous_order_status !== 'completed') {
			$message	=	sprintf(
				'Renewal Order Cancelled: the previous order (ID: %d) has status "%s".',
				$previous_order->get_id(),
				$previous_order_status
			);
			$renewal_order->update_status('wc-cancelled', $message);
			$subscription->update_status('wc-cancelled');
		}
		return $renewal_order;
	}

	
	/**
	 * ListTable Subscriptions Meta Data
	 */
	function hb_woocommerce_order_item_name($item_name, $item, $sw ){
		if(!in_array($item_name, ['3 MONTH PLAN', 'MONTHLY']))
			return $item_name;

		try {
			if(isset($_GET['page']) && $_GET['page']=='wc-orders--shop_subscription'){
					$product = $item->get_product();
					if ($product) {
						$data		=	$product->get_data();
						$item_name	=	$data['name'];
					}
			}else{
				$product_id		=	$item->get_product_id();
				$product		=	wc_get_product($product_id);
				if($product){
					$item_name	=	$product->get_name();
				}
			}
		} catch (\Throwable $th) {
			//throw $th;
		}
		return $item_name;
	}

	/**
	 * Email Meta Data
	 */
	function hb_woocommerce_display_item_meta($html, $item, $args){
		$strings = array();
		$html    = '';
		$args['before']			=	'<ul class="wc-item-meta" style="padding-left:0"><li style="display: flex;align-items: center;gap: 5px;">';
		$args['label_before']	=	'<strong class="wc-item-meta-label">';

		foreach ( $item->get_all_formatted_meta_data() as $meta_id => $meta ) {
			$value     = $args['autop'] ? wp_kses_post( $meta->display_value ) : wp_kses_post( make_clickable( trim( $meta->display_value ) ) );
			$strings[] = $args['label_before'] . wp_kses_post( $meta->display_key ) . $args['label_after'] . $value;
		}

		if ( $strings ) {
			$html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
		}
		return $html;
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
	 * Remove the Error Messages from top of checkout pages
	 */
	function hb_init_remove_wc_hooks() {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10 );
		remove_action( 'woocommerce_before_checkout_form_cart_notices', 'woocommerce_output_all_notices', 10 );
		//add_action( 'woocommerce_checkout_tabs', 'woocommerce_output_all_notices', 10 );
	}
	/**
	 * Add Terms & Conditions to Tab Checkout
	 */

	private function get_text_term_conditions_from_product($tag=''){
		try {
			if (!WC()->cart || WC()->cart->is_empty()) return '';

			$mensajes_por_defecto = array(
				'monthly' => 'This is a subscription plan. You can cancel anytime.',
				'3-month' => 'This 3-month prescription plan is set to automatically renew every 10 weeks. You may cancel at any time.'
			);

			$mensajes_monthly = array();
			$mensajes_3month = array();

			foreach (WC()->cart->get_cart() as $item) {
				$producto = $item['data'];
				$variation_id = $item['variation_id'];

				$tipo_suscripcion = isset($item['variation']['attribute_pa_subscription']) ? 
									sanitize_title($item['variation']['attribute_pa_subscription']) : 
									'';

				$mensaje_personalizado = $variation_id ? 
										get_post_meta($variation_id, 'bh_checkout_text_term_conditions', true) : 
										'';

				if ($tipo_suscripcion === 'monthly') {
					$mensaje = !empty($mensaje_personalizado) ? $mensaje_personalizado : $mensajes_por_defecto['monthly'];
					if (!in_array($mensaje, $mensajes_monthly)) {
						$mensajes_monthly[] = $mensaje;
					}
				} elseif ($tipo_suscripcion === '3-month') {
					$mensaje = !empty($mensaje_personalizado) ? $mensaje_personalizado : $mensajes_por_defecto['3-month'];
					if (!in_array($mensaje, $mensajes_3month)) {
						$mensajes_3month[] = $mensaje;
					}
				}
			}

			$mensajes_finales = array_merge($mensajes_monthly, $mensajes_3month);

			$output	=	'';
			if (!empty($mensajes_finales)) {
				if(empty($tag))
					$output	=	implode('', $mensajes_finales);
				else {
					foreach ($mensajes_finales as $mensaje) {
						if(!empty($mensaje))
							$output .= '<' . $tag . '>' . esc_html($mensaje) . '</' .$tag . '>';
					}
				}
			}
		
		} catch (\Throwable $th) {
			//throw $th;
		}

		return $output;
	}
	function bh_arg_mc_init_options_add_step_terms_conditions($fields) {
		$terms_conditions	=	array(
									'text'  => __('Terms & Conditions', 'argMC'),
									'class' => 'bh-step-terms-conditions'
								);
		$fields['steps']['step_terms_conditions'] = $terms_conditions;
		return $fields;
	}
	function bh_arg_mc_checkout_step_add_content_terms_conditions($step) {
		if ($step == 'step_terms_conditions') {
			// $text	=	$this->get_text_term_conditions_from_product('li');
			?>
			<h3 class="title-important">Important!</h3>
	
			<div class="content-terms-conditions">
				<p><strong>Before we begin the process of finalizing your intake form please review and accept the following.</strong></p>
				<ol>
					<li>3 month plan: You will be charged $499 for compounded tirzepatide or $399 for compounded semaglutide today. Your subscription will automatically renew every 10 weeks at the same rate ($499 for compounded tirzepatide or $399 for compounded semaglutide). <strong>You may cancel at any time</strong>.<br>
						<strong>Note:</strong> If you are not approved for renewal by the healthcare provider, you will receive a refund.
					</li>
					<li>In most states, the completion of your intake form may be sufficient for approval by a healthcare provider. A telephone consultation is not required in all states. If additional information is needed, the healthcare provider will contact you.</li>
					<li>Please ensure you provide accurate information about your prior GLP-1 medication use, as healthcare providers rely on this to determine the appropriate dosage. If accurate information is not provided on the intake form, and the healthcare provider issued a prescription based on the information, changes will not be allowed and refunds will not be issued.</li>
					<li><strong>**Cancellation policy:**</strong> If the patient wishes to cancel and the provider-led health review is completed and a prescription has been written, Brello may refund the total amount paid, less a <strong>$50 professional fee</strong>.<br>
					This will only apply if the patient sends a written notice via email to <a href="mailto:info@brellohealth.com">info@brellohealth.com</a> within 24 hours from the time the provider-led health review was completed.</li>
					<li>Please ensure the shipping address is entered correctly during checkout. A refund will not be issued if the shipping address was entered incorrectly and the medications have been shipped.</li>
					<li><strong>**Replacement Policy**</strong><br>
					If your medication is not received at the address provided during checkout, we’re happy to work with one of the qualified pharmacies to send you a replacement at no extra cost.
						<ul>
							<li>Shipping Discrepancies<br>
							In instances where the patient reports non-receipt of medication, yet our records confirm delivery, a qualified pharmacy may re-ship medications.<br>
							<strong>All replacements are on a case-by-case basis and subject to verification.</strong>
							</li>
						</ul>
					</li>
				</ol>
			</div>
	
			<form class="custom-checkout-form" method="post">   
	
				<div class="woocommerce-bh-fields__field-wrapper checkboxes">
					<p class="form-row validate-required" data-priority="90">
						<span class="woocommerce-input-wrapper">
							<label for="bh_accept_terms" class="checkbox">
								<input type="checkbox" class="input-checkbox" name="bh_accept_terms" id="bh_accept_terms" value="on">
								<span>YES! I UNDERSTAND AND ACCEPT</span>
							</label>
						</span>
					</p>
				</div>
			</form>

		<?php
		}
	}
	function bh_woocommerce_checkout_process_field_accept_terms(){
		if(!isset($_POST['bh_accept_terms']) || $_POST['bh_accept_terms']!=='on'){
			wc_add_notice(__('You must be accept the Terms &amp; Conditions.', 'woocommerce'), 'error');
		}
	}	
	/**
	 * Disable custom Variation Name
	 */
	function hb_woovr_variation_get_name($show_custom_name){
		$show_custom_name	=	false;
		return $show_custom_name;
	}
	/**
	*/

	function custom_script_order_edit() {
		wp_enqueue_script( 'bh-features-admin-order-script', plugin_dir_url( __DIR__ ) . 'admin/js/bh-features-admin-order.js', array( 'jquery' ), $this->version, true);
		
		$licensed_states	=	array_keys($this->licensed_states);
		wp_localize_script('bh-features-admin-order-script', 'allowedStates', $licensed_states);
	}
	function admin_warning_if_billing_state_restricted($order) {
	    $restricted = array_keys($this->licensed_states);
	    $billing_state = $order->get_billing_state();

	    if (!in_array($billing_state, $restricted)) {
	        echo '<div class="warning-message text">
					<strong>Warning:</strong> This state (' . esc_html($billing_state) . ') is not licensed for shipping.
	              </div>';
	    }
	}
	/**
	 * Added for Allow Free Orders when use a coupon
	 */
	function disable_payment_for_free_orders($needs_payment, $cart) {
		if ($cart->get_total('edit') == 0) {
			return false;
		}
		return $needs_payment;
	}

	/**
	 * Change to on-hold the orders with total $0
	 */
	function change_to_on_hold_free_orders($order_id, $old_status, $new_status) {
		if ($new_status != 'processing') return;

		try {
			$order = wc_get_order($order_id);
			if ($order->get_total() == 0) {
				if (function_exists('wcs_order_contains_subscription') && 
					(wcs_order_contains_subscription($order_id) || 
					wcs_is_subscription($order_id))) {
					$order->update_status('on-hold');
				}
			}

		} catch (\Throwable $th) {
			//throw $th;
		}
		
	}

	/**
	 * Rename the Product name of a new Order 
	 * if the word Compounded not exist,
	 * When a new subscription renewal order is created
	 */
	function wcs_new_order_created_update_product_name($renewal_order, $subscription, $type) {
		try {
			foreach ($renewal_order->get_items() as $item_id => $item) {
				$product = $item->get_product();
				if (!$product) {
					continue;
				}
				$word		=	'Compounded';
				$original_name = $product->get_name();
				if (stripos($original_name, $word) === 0) {
					continue;
				}
				$new_name	=	$word . ' ' . $original_name;
				$item->set_name($new_name);
				$item->save();
			}

		} catch (\Throwable $th) {
			//throw $th;
		}
		return $renewal_order;
	}
	/**
	 * Rename the Product name of Subscription
	 * if the word Compounded not exist,
	 * When a renewal order is completed
	 */
	function edited_product_item_name_in_subscription_renewal_payment_completed($order_id) {
		try {
			$order = wc_get_order($order_id);
			if (!wcs_order_contains_renewal($order)) {
				return;
			}
			$subscriptions = wcs_get_subscriptions_for_renewal_order($order_id);
			foreach ($order->get_items() as $item) {
				$product = $item->get_product();
				if (!$product) {
					continue;
				}
		
				$original_name = $product->get_name();
				if (stripos($original_name, 'Compounded') === 0) {
					continue;
				}
				$new_name = 'Compounded ' . $original_name;
				foreach ($subscriptions as $subscription) {
					foreach ($subscription->get_items() as $sub_item) {
						$sub_product_id	=	$sub_item->get_variation_id() ?: $sub_item->get_product_id();
						if ($sub_product_id == $product->get_id()) {
							$sub_item->set_name($new_name);
							$sub_item->save();
						}
					}
				}
			}
		} catch (\Throwable $th) {
			//throw $th;
		}
	}

	function register_api_routes(){
        //	/wp-json/bh/stripe/webhook
        register_rest_route('bh/', '/stripe/webhook', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_stripe_webhook_events'],
            'permission_callback' => '__return_true',
        ]);
        //	/wp-json/bh/order-limits/status
        register_rest_route('bh/', '/order-limits/status', [
	        'methods'  => 'GET',
	        'callback' => [$this, 'get_restriction_status_api'],
	        'permission_callback' => '__return_true'
	    ]);
	    //	/wp-json/bh/app/track-user
	    register_rest_route('bh/', '/app/track-user', array(
	        'methods' => 'POST',
	        'callback' => [$this, 'handle_app_user_tracking'],
	        'permission_callback' => '__return_true'
	    ));
	}

	function handle_stripe_webhook_events(){
		try {
			$body = @file_get_contents('php://input');
			if(!$body)
				return ['success'=>false];

			$event = json_decode($body);
			if(!$event)
				return ['success'=>false];

			$payment_intent	=	$event->data->object;
			$events_accepted=	['payment_intent.succeeded', 'payment_intent.canceled', 'payment_intent.payment_failed'];
			if(!in_array($event->type, $events_accepted))
				return ;

			$emoji = '🔔';
			$is_status_canceled	=	false;

			switch ($event->type) {
			    case 'payment_intent.succeeded':
			        $emoji = '✅';
			        break;
			    case 'payment_intent.canceled':
			        $emoji = '⚠️';
			        /**
					 * Reason for cancellation of this PaymentIntent, 
					 * either user-provided 
					 * 		(duplicate, fraudulent, requested_by_customer, or abandoned) 
					 * or 
					 * generated by Stripe internally 
					 * 		(failed_invoice, void_invoice, automatic, or expired).
					 * 
					 * Check if is "automatic"
					 * 
					 */
					if(isset($payment_intent->cancellation_reason) && $payment_intent->cancellation_reason=='automatic')
						$is_status_canceled	=	true;

			        break;
			    case 'payment_intent.payment_failed':
			        $emoji = '❌';
			        break;
			}
			if(!$is_status_canceled)
				return ['success'=>false];

			$intent_url = "https://dashboard.stripe.com" .
		    (strpos($payment_intent->id, 'pi_test_') === 0 ? '/test' : '') .
	    	"/payments/{$payment_intent->id}";
			
			if(isset($payment_intent->metadata->order_id)){
				$order_id	=	$payment_intent->metadata->order_id;
				$url		=	admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
				$message  	=	"```";
				$message   .=	"{$emoji} Order ID: <$url|$order_id>\n";	
				$order 		=	wc_get_order($order_id);
				if($order){
					$order_status	=	$order->get_status();
					$message 	   .=	"🆕 Order Status: {$order_status}";
					if($order_status!='cancelled'){
						$order->update_status('cancelled', __('Stripe payment intent canceled (Payment Intent ID: ' . $payment_intent->id . ').', 'woocommerce'));
						$message .= " -> {$order->get_status()}";
					}
					if (function_exists('wcs_order_contains_subscription') && 
						wcs_order_contains_subscription($order)) {
						$subscriptions = wcs_get_subscriptions_for_order($order);
						foreach ($subscriptions as $subscription) {
							$subscription->update_status('cancelled');
						}
					}
					$message .= "\n";
				}

				$message .= "🔎 Stripe: <{$intent_url}|{$payment_intent->id}>";
				$message .= "```";
				//	#cancelled-orders-brello
				$channel	=	'SLACK_CHANNEL_CANCELLED_ORDERS_BRELLO';
				bh_send_slack_notification($message, $channel);
				if (function_exists('wc_get_logger')) {
					$logger = wc_get_logger();
					$logger->info("Stripe Webhook Processed: {$event->type}", [
						'order_id' => $order_id ?? 'none',
						'payment_intent' => $payment_intent->id
					]);
				}
			}
			
		} catch (Exception $e) {
			if (function_exists('wc_get_logger')) {
				$logger = wc_get_logger();
				$logger->error("Stripe Webhook Processed: {$event->type}", [
					'order_id' => $order_id ?? 'none',
					'payment_intent' => $payment_intent->id,
					'message' => $e->getMessage()
				]);
			}
			return ['success'=>false];
		}
		return ['success'=>true];
	}

	function get_restriction_status_api() {
	    return [
	        'is_restricted' => $this->common->are_orders_restricted(),
	    ];
	}

	function handle_app_user_tracking($request) {
	    $params =	$request->get_params();
	    $email 	=	sanitize_email($params['email']);
	    $action =	sanitize_text_field($params['action']); // 'install' or 'open'
	    if (empty($email) || empty($action)) {
	        return new WP_Error('missing_data', 'Email and action are required', array('status' => 400));
	    }
	    
	    $user = get_user_by('email', $email);
	    if (!$user) {
	        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
	    }
	    
	    $user_id 	=	$user->ID;
		//$today 		=	current_time('mysql');
		$today_date =	date('Y-m-d');
	    switch ($action) {
	        case 'install':
            	update_user_meta($user_id, '_app_installed_date', $today_date);
	            update_user_meta($user_id, '_app_last_opened', $today_date);
	            update_user_meta($user_id, '_uses_app', 'true');
	            //update_user_meta($user_id, '_app_installed_datetime', $today);
	            //update_user_meta($user_id, '_app_last_used', $today);
	            break;
	            
	        case 'open':
	            //update_user_meta($user_id, '_app_last_used', $today);
	            update_user_meta($user_id, '_app_last_opened', $today_date);
	            update_user_meta($user_id, '_uses_app', 'true');
	            break;
	            
	        case 'uninstall':
	            update_user_meta($user_id, '_uses_app', 'false');
	            break;
	    }
	    
	    return rest_ensure_response(array(
	        'success' => true,
	        'user_id' => $user_id,
	        'email' => $email,
	        'action' => $action,
	        'fields_updated' => array(
	            '_uses_app' => get_user_meta($user_id, '_uses_app', true),
	            '_app_installed_date' => get_user_meta($user_id, '_app_installed_date', true),
	            '_app_last_opened' => get_user_meta($user_id, '_app_last_opened', true)
	        )
	    ));
	}

	/*
	*	removing Cart added message
	*/
	function woocommerce_notice_types($types){
		$types=['error', 'notice'];
		return $types;
	}
	
	/*
	*	Print Tracking from katalys
	*/
	function disable_thankyou_redirect($should_redirect, $url){
		$should_redirect	=	false;
		return $should_redirect;
	}
	function execute_tracking_and_redirect($order_id) {
	    if (!$order_id) return;

	    $order = wc_get_order($order_id);
	    if (!$order) return;

		$telemdnow_order_creation 	=	$order->get_meta('telemdnow_order_creation', true);
	    $redirect_url 				=	$order->get_meta('telemdnow_visit_link', true);
	    $order_key 					=	$order->get_order_key();

	    if(!empty($telemdnow_order_creation) && $telemdnow_order_creation !== 'false') {
			$thank_you_url	=	wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order_key;
			$redirect_url	=	$order->get_meta('telemdnow_visit_link', true) . '&redirectUrl=' . $thank_you_url;
	    }

		global $tracking_data;
	    $tracking_data = [
	        'redirect_url' 		=> 	$redirect_url,
	        'order_id' 			=> 	$order->get_id(),
	        'sale_amount'		=>	$order->get_total(),
	        'subtotal_amount' 	=>	$order->get_subtotal(),
	        'email' 			=>	$order->get_billing_email(),
	        'discount_code' 	=>	implode(', ', $order->get_coupon_codes()),
	        'currency' 			=>	$order->get_currency()
	    ];
	}
	
	function insert_katalys_tracking_script_footer() {
		if (!is_order_received_page()) return;

		global $tracking_data;
		if(!$tracking_data)
			return ;
		?>
		<script>
			console.log('from footer');
			function checkTrackingLoaded() {
				if (typeof _revoffers_track !== 'undefined') {
					_revoffers_track.push({
						action: "convert",
						order_id: "<?php echo esc_js($tracking_data['order_id']); ?>",
						sale_amount: "<?php echo esc_js($tracking_data['sale_amount']); ?>",
						subtotal_amount: "<?php echo esc_js($tracking_data['subtotal_amount']); ?>",
						email_address: "<?php echo esc_js($tracking_data['email']); ?>",
						discount_1_code: "<?php echo esc_js($tracking_data['discount_code']); ?>"
					});
					setTimeout(function() {
						window.location.href = "<?php echo esc_url($tracking_data['redirect_url']); ?>";
					}, 100);
				} else {
					setTimeout(checkTrackingLoaded, 100);
				}
			}		    
			checkTrackingLoaded();
		</script>
		<?php
	}

	/*
	*	Print Tracking Order Number
	*/
	function display_shipment_info_shortcode( $atts ) {
		if(is_admin() && !wp_doing_ajax())
			return '';

		$a = shortcode_atts( array(
			'order_id' => '',
		), $atts );

		$html	=	'<div style="background: #fff4f4;border: 1px solid #e3a600;border-radius: 7px;margin: 5rem auto;width: 300px;padding: 1rem;text-align: center;color: #bf3939;">%s</div>';
		$order_id  =	0;
		if(isset($_GET['order_id']) && ctype_digit($_GET['order_id']))
			$order_id  =	(int) $_GET['order_id'];

		if($order_id==0){
			return sprintf($html, 'Missing Order ID');
		}

		try {
			$order 		=	wc_get_order($order_id);
			if(!$order)
				return sprintf($html, 'Order ID No Valid!');

			$telemdnow_entity_id 		=	$order->get_meta('telemdnow_entity_id', true);
			
			//$cache_key	=	'bh_order_shipping_details_' . $order_id;
			//$shippingDetails	=	get_transient($cache_key);
			$shippingDetails 		=	$order->get_meta('telegra_shipping_details', true);
			if(!$shippingDetails){
				$shippingDetails	=	false;
			}
			if(false===$shippingDetails){
				$affiliate_private_token=	get_authenticationToken();
				$telemdnow_rest_url 	=	get_option('telemdnow_rest_url');
				$api_url				=	$telemdnow_rest_url . '/orders/' . $telemdnow_entity_id . '?access_token='. $affiliate_private_token;
				$response				=	wp_remote_get($api_url);
				
				if(is_wp_error($response)){
					bh_plugins_log(['order_id'=>$order_id, 'api_url'=>$api_url, 'response'=>$response], 'bh_plugins-shipping_from_telegra');
					return sprintf($html, 'No Information Available, please try again later.');
				}else{
					$body	=	wp_remote_retrieve_body($response);
					$data	=	json_decode($body, true);
					if(isset($data['prescriptionFulfillments'])){
						$prescriptionFulfillments_item	=	@$data['prescriptionFulfillments'][0];
						if(isset($prescriptionFulfillments_item['shippingDetails']) && is_array($prescriptionFulfillments_item['shippingDetails']) && !empty($prescriptionFulfillments_item['shippingDetails']) ){
							$shippingDetails	=	$prescriptionFulfillments_item['shippingDetails'];
							if(isset($shippingDetails['trackingNumber'])){
								//set_transient($cache_key, $shippingDetails);
								$order->update_meta_data('telegra_shipping_details', $shippingDetails);
								$order->save();
							}
						}
					}
				}
			}

			if(false===$shippingDetails){
				return sprintf($html, 'No Information Available');
			}

			/*
			if(isset($shippingDetails['trackingNumber'])){
				//https://es-us.ups.com/track?loc=es_US&requester=ST&trackingNumber=1Z99V3Y21310941884/trackdetails
				$redirect_url	=	'https://es-us.ups.com/track?loc=es_US&requester=ST&trackingNumber=' . esc_html($shippingDetails['trackingNumber']) . '/trackdetails';
				wp_redirect($redirect_url);
				exit;
			}
			*/

			ob_start();
			?>
			<div id="tracking-info">
				<h3>Shipping Information</h3>
				<div class="shipping-details">
					<?php if(isset($shippingDetails['trackingNumber'])) : ?>
					<p><strong>Tracking Number: </strong><?php echo esc_html($shippingDetails['trackingNumber']) ?></p>
					<?php endif; ?>
					<?php if(isset($shippingDetails['shippingCompany'])) : ?>
					<p><strong>Shipping Company: </strong><?php echo esc_html($shippingDetails['shippingCompany']) ?></p>
					<?php endif; ?>
					<?php if(isset($shippingDetails['shipped'])) : ?>
					<p><strong>Shipped: </strong><?php echo ($shippingDetails['shipped']? 'Yes':'No') ?></p>
					<?php endif; ?>
				</div>
				<?php if(isset($shippingDetails['trackingNumber'])) : ?>
				<a class="track-button" href="https://es-us.ups.com/track?loc=es_US&requester=ST&trackingNumber=<?php echo esc_html($shippingDetails['trackingNumber']) ?>/trackdetails" title="Track My Order">Track My Order</a>
				<?php endif; ?>
			</div>
			<style>
				#tracking-info {padding:2rem 0 5rem}
				#tracking-info > .shipping-details {min-width: 20rem;padding: 1.5rem;}
				#tracking-info > .shipping-details strong{display:block;}
				#tracking-info > .track-button {max-width: 100%;background-color: #c8fd40;
				    text-transform: uppercase;font-weight: 700;border: 1px solid #c8fd40;
				    color: #012169;letter-spacing: 1px;padding: 1rem;border-radius: 7px;
				    font-size: 14px;text-align: center;text-decoration: none;
				}

				#tracking-info > .track-button:hover {background-color: #012169;color: #c8fd40;border-color: #012169;}
				@media screen {
					#tracking-info > .shipping-details {background: #fff;border: 1px solid #f1f1f1;border-radius: 7px;}
				}
				@media(min-width:361px){
					#tracking-info {display:flex;flex-direction:column;gap:2rem;align-items:center;}
					#tracking-info > .track-button{min-width:20rem}
				}
				@media(min-width:1024px){
					#tracking-info {min-height:50vh;}
				}
				@media (max-width:360px) {
					#tracking-info > .track-button{width:100%;}
				}
				@media print{
					a[href]:after{content:none !important}
					#tracking-info > .track-button{display:none}
					.elementor-location-footer {display: none;}
				}
				</style>
			<?php
			return ob_get_clean();
			
		} catch (Exception $e) {			
			return ['success'=>false];
		}
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
	
	/**
	 * Update Subscriptin Next Payment Date when a Renewal Order is completed
	 */
	function update_subscription_next_payment_date($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}
		try {
			if(wcs_order_contains_renewal($order)){
				$subscriptions	=	wcs_get_subscriptions_for_renewal_order($order_id);
				foreach ($subscriptions as $subscription) {
					if ($subscription->get_status() !== 'active') {
						continue;
					}				

					$items=$subscription->get_items();
					$has_product=false;

					foreach ($items as $item) {
						$product_id		=	$item->get_product_id();
						$variation_id	=	$item->get_variation_id();
						$product		=	wc_get_product($variation_id? $variation_id:$product_id);
						if(!$product)
							continue;

						$has_product	=	true;
						
						$product_billing_interval		=	get_post_meta($product->get_id(), '_subscription_period_interval', true);
						$product_billing_period			=	get_post_meta($product->get_id(), '_subscription_period', true);
						
						$aProduct	=	[];
						$aProduct['id']			=	$product->get_id();
						$aProduct['interval']	=	$product_billing_interval;
						$aProduct['period']		=	$product_billing_period;										

						$product_billing_interval	=	!empty($product_billing_interval)? (int)$product_billing_interval:1;
						$product_billing_period		=	!empty($product_billing_period)? $product_billing_period:'month';

						$aProduct['interval']	.=	' -> ' . $product_billing_interval;
						$aProduct['period']		.=	' -> ' . $product_billing_period;										
						$log['products'][]		=	$aProduct;

					}

					if(!$has_product)
						continue ;

					$subscription_billing_period	=	$subscription->get_billing_period();
					$subscription_billing_interval	=	$subscription->get_billing_interval();
					$subscription_next_payment		=	$subscription->get_date('next_payment');

					$current_next_payment   		=	wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );

					$log['subscription']['id']			=	$subscription->get_id();
					$log['subscription']['period']		=	$subscription_billing_period;
					$log['subscription']['interval']	=	$subscription_billing_interval;
					$log['subscription']['next_payment']=	$subscription_next_payment;
					
					$log['order']['id']					=	$order_id;
					$log['order']['period']				=	$product_billing_period;
					$log['order']['interval']			=	$product_billing_interval;
					
					$days	=	BH_DAYS_THREE_MONTH_PLAN;
					if($product_billing_interval==1)
						$days	=	BH_DAYS_MONTHLY_PLAN;

					$new_next_payment	=	date('Y-m-d H:i:s', strtotime("+{$days} days", strtotime($current_next_payment)));

					$log['subscription']['next_payment'].=	' -> ' . $new_next_payment;

					$subscription->update_dates([
						'next_payment' => $new_next_payment,
					]);
					if($subscription_billing_period!=$product_billing_period){
						$log['subscription']['period']	.=	' -> ' . $product_billing_period;
						$subscription->set_billing_period($product_billing_period);
					}
					if($subscription_billing_interval!=$product_billing_interval){
						$log['subscription']['interval']	.=	' -> ' . $product_billing_interval;
						$subscription->set_billing_interval($product_billing_interval);
					}
					$subscription->save();
					bh_plugins_log(['public:update_subscription_next_payment_date:logs', $log]);
				}
			}
		} catch (\Throwable $th) {
			$data	=	[
				'error'		=>	$th->getMessage(),
				'function'	=>	'public:update_subscription_next_payment_date',
				'args'		=>	func_get_args()
			];
			bh_plugins_error_log($data);
		}
	}

	/**
	 * Edit Next Payment Date when the subscription is created from Checkout
	 */
	function set_plan_days_to_new_subscription( $subscription, $order, $recurring_cart ){
		try {
			$subscription_billing_period	=	$subscription->get_billing_period();
			$subscription_billing_interval	=	$subscription->get_billing_interval();
			$subscription_next_payment		=	$subscription->get_date('next_payment');
			$subscription_trial_end			=	$subscription->get_date('trial_end');
			
			$log['subscription']['id']			=	$subscription->get_id();
			$log['subscription']['period']		=	$subscription_billing_period;
			$log['subscription']['interval']	=	$subscription_billing_interval;
			$log['subscription']['next_payment']=	$subscription_next_payment;
			$log['subscription']['trial_end']	=	$subscription_trial_end;

			$current_next_payment			=	wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );		

			$plan_days	=	BH_DAYS_THREE_MONTH_PLAN;
			if($subscription_billing_interval==1)
				$plan_days	=	BH_DAYS_MONTHLY_PLAN;
			
			$new_next_payment = date('Y-m-d H:i:s', strtotime("+{$plan_days} days", strtotime($current_next_payment)));

			$log['subscription']['next_payment'].=	' -> ' . $new_next_payment;
			$log['subscription']['plan_days'] = $plan_days;
			
			$dates	=	[
						'next_payment'	=>	$new_next_payment
					];
			if($subscription_trial_end){
				$dates['trial_end']	=	$new_next_payment;
			}
			$subscription->update_dates( $dates );

			$subscription->update_meta_data(
				'_set_plan_days_to_new_subscription', 
				$log);

			$subscription->save();

			// bh_plugins_log(['set_plan_days_to_new_subscription', $log]);

		} catch (\Throwable $th) {
			$data	=	[
				'error'		=>	$th->getMessage(),
				'function'	=>	'public:set_plan_days_to_new_subscription',
				'args'		=>	func_get_args()
			];
			bh_plugins_error_log($data);
		}
	}

	function wp_footer_print_tracking_thankyou() {
		if (!is_order_received_page()) return;

		global $tracking_data;
		if(!$tracking_data)
			return ;

		// <!-- Event snippet for Purchase conversion page -->
		?>		
		<script>
			function checkGtagEventLoaded() {
				if (typeof gtag !== 'undefined') {
					gtag('event', 'conversion', {
						'send_to': 'AW-16978798190/SHNSCJWTkLkaEO7Mj6A_',
						'value': <?php echo esc_js($tracking_data['sale_amount']); ?>,
						'currency': 'USD',
						'transaction_id': '<?php echo esc_js($tracking_data['order_id']); ?>'
					});
				} else {
					setTimeout(checkGtagEventLoaded, 100);
				}
			}
			checkGtagEventLoaded();
		</script>
		<?php
	}

	function insert_fbq_tracking_script_footer() {
		if (!is_order_received_page()) return;

		global $tracking_data;
		if(!$tracking_data)
			return ;
		?>
		<script>
			console.log('fbq from footer');
			function checkFbTrackingLoaded() {
				if (typeof fbq !== 'undefined') {
					fbq('track', 'Purchase', {
					    value: <?php echo esc_js($tracking_data['sale_amount']); ?>,
					    currency: 'USD'
					  });
					
				} else {
					setTimeout(checkFbTrackingLoaded, 100);
				}
			}
		</script>
		<?php
	}

	function insert_vibe_pixel_tracking(){
		if (!is_order_received_page()) return;

		global $tracking_data;
		if(!$tracking_data)
			return ;

		?>
		<script>
			console.log('tracking_code_conversion vibe O17U5n');
			console.log('vbpx','event','Purchase', 'price_usd', <?php echo esc_js($tracking_data['sale_amount']); ?>, 'USD');
		</script>
		<script>
				!function(v,i,b,e,c,o){if(!v[c]){var s=v[c]=function(){s.process?s.process.apply(s,arguments):s.queue.push(arguments)};s.queue=[],s.b=1*new Date;var t=i.createElement(b);t.async=!0,t.src=e;var n=i.getElementsByTagName(b)[0];n.parentNode.insertBefore(t,n)}}(window,document,"script","https://s.vibe.co/vbpx.js","vbpx");
				vbpx('init','O17U5n');
				vbpx('event','purchase', {'price_usd': '<?php echo esc_js($tracking_data['sale_amount']); ?>'});
		</script> 
		<?php 
	}


	/*
	*	Rules
	*	1: Only 1 weight-loss product in the entire cart
	*	2: For SUBSCRIPTIONS (all)
	*	3: For NORMAL products (avoid exact duplicates)
	*/
	function strict_cart_restrictions($passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array()) {
		$product = wc_get_product($product_id);
		$is_weight_loss = has_term('weight-loss', 'product_cat', $product_id);
		// Rule 1
		if ($is_weight_loss) {
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				if (has_term('weight-loss', 'product_cat', $cart_item['product_id'])) {
					WC()->cart->remove_cart_item($cart_item_key);
					break;
				}
			}
		}
		// Rule 2
		if ($product->is_type('subscription') || $product->is_type('variable-subscription')) {
			$quantity = 1;
			
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				$cart_product = wc_get_product($cart_item['product_id']);
				if ($cart_item['product_id'] == $product_id) {
					WC()->cart->remove_cart_item($cart_item_key);
					break;
				}
			}
		}
		// Rule 3
		else {
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				if ($cart_item['product_id'] == $product_id && $cart_item['variation_id'] == $variation_id) {
					WC()->cart->set_quantity($cart_item_key, $quantity);
					wp_safe_redirect(wc_get_checkout_url());
					exit;
				}
			}
		}

		return $passed;
	}


	/*
	*	[_bh_disclaimer_plan_selected]
	*
	*/
	public function arg_mc_init_options($options){
		$options['footer_text']	=	do_shortcode($options['footer_text']);
		return $options;
	}
	public function disclaimer_plan_selected_shortcode(){
		if (!WC()->cart || WC()->cart->is_empty()) return '';

	    foreach (WC()->cart->get_cart() as $item) {
	        $attribute_slug = $item['variation']['attribute_pa_subscription'] ?? '';
        
	        if ($attribute_slug === 'monthly' && empty($mensaje_monthly)) {
	            $mensaje_monthly = '<li>If you selected the monthly plan: After your first month, you will be charged the regular price per month until canceled. You may cancel at any time.</li>';
	        } elseif ($attribute_slug === '3-month' && empty($mensaje_3month)) {
	            $mensaje_3month = '<li>If you selected the 3-month plan: You will be charged the same rate every 10 weeks until canceled. You may cancel at any time.</li>';
	        }
	    }
		$mensajes_finales = array();
	    if (!empty($mensaje_monthly)) $mensajes_finales[] = $mensaje_monthly;
	    if (!empty($mensaje_3month)) $mensajes_finales[] = $mensaje_3month;

		$output	=	'';
		if(!empty($mensajes_finales))
			$output	=	implode('', $mensajes_finales);

		return $output;
	}

	/*
	*	Updates the status of an upsell order to "processing" 
	*	when its associated parent order is marked as "completed." 
	*	Ignores subscription renewals
	*/
	function process_upsell_when_main_order_completed($order_id) {
	    if (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order_id)) {
	        return;
	    }

	    $order = wc_get_order($order_id);
        if (!$order) {
            return ;
        }

	    $upsell_order_id =	$order->get_meta('_cuw_offer_order_id', true);
	    if (empty($upsell_order_id) || $upsell_order_id == $order_id) {
	        return;
	    }

	    try {
	        $upsell_order = wc_get_order($upsell_order_id);
	        if (!$upsell_order || $upsell_order->get_status() === 'trash') {
	            return ;
	        }

	        if ($upsell_order->get_meta('_processed_by_parent', false)) {
	            wc_get_logger()->debug("Upsell Order {$upsell_order_id} already processed", ['source' => 'upsell-status']);
	            return;
	        }

	        if (!in_array($upsell_order->get_status(), ['on-hold', 'pending'])) {
	            $upsell_order->add_meta_data('_rejected_reason', 'Ineligible status: ' . $upsell_order->get_status());
	            $upsell_order->save();
	            return;
	        }

	        if ($upsell_order->get_meta('_processing_lock', false)) {
	            wc_get_logger()->warning("Upsell order {$upsell_order_id} is being processed by another process", ['source' => 'upsell-status']);
	            return;
	        }

	        $upsell_order->update_meta_data('_processing_lock', time());
	        $upsell_order->save();

	        if (!$upsell_order->update_status('processing', "Processed by parent order #{$order_id}")) {
	            return ;
	        }

	        $upsell_order->update_meta_data('_processed_by_parent', $order_id);
	        $upsell_order->delete_meta_data('_processing_lock');
	        $upsell_order->save();

	        wc_get_logger()->info("Upsell Order {$upsell_order_id} processed successfully", ['source' => 'upsell-status']);

	    } catch (Exception $e) {
	        if (isset($upsell_order)) {
	            $upsell_order->delete_meta_data('_processing_lock');
	            $upsell_order->save();
	        }

	        wc_get_logger()->error("Error processing upsell: " . $e->getMessage(), [
	            'source' => 'upsell-status',
	            'order_id' => $order_id,
	            'upsell_id' => $upsell_order_id ?? 'N/A'
	        ]);

	        //wp_schedule_single_event(time() + 300, 'reintentar_procesamiento_upsell', [$order_id, $upsell_order_id]);
	    }
	}

	/**
	 * Filters the formatted line subtotal in WooCommerce orders to append custom renewal text 
	 * for subscription products based on their billing interval.
	 */
	function woocommerce_order_formatted_line_subtotal($subtotal, $item, $order){
		if (!class_exists('WC_Subscriptions_Product'))
			return $subtotal;

		$product = $item->get_product(); // Obtiene el objeto WC_Product
		if (!WC_Subscriptions_Product::is_subscription($product))
			return $subtotal;

		global $printed_new_line;
		if (isset($printed_new_line) && $printed_new_line)
			return $subtotal;

		$subscription_interval = get_post_meta( $product->get_id(), '_subscription_period_interval', true );
		$custom_text = '';
		if ( $subscription_interval == 1 ) {
			$custom_text = 'Renews every 25 days';
		} elseif ( $subscription_interval == 3 ) {
			$custom_text = 'Renews every 10 weeks';
		}
		if(!empty($custom_text))
			$custom_text = '<br/><small style="display:flex;line-height:1rem">'. $custom_text .'</small>';
		return $subtotal . $custom_text;
	}

	/**
	 * Close session after purchase
	 */
	function custom_checkout_session_validation($order_id, $posted_data, $order) {
	    $order			=	wc_get_order($order_id);
	    $current_user 	=	wp_get_current_user();	    
	    if ($order && $current_user->ID !== 0 ) {
	        WC()->session->set('session__cleanup', true);
	        WC()->session->set('session__cleanup_order', $order_id);
	    }
	}

	function custom_post_purchase_logout_handler() {
	    if (!is_wc_endpoint_url('order-received')) return;	    
	    if (!WC()->session->get('session__cleanup')) return;
	    global $wp;
	    $order_id = absint($wp->query_vars['order-received']);

	    add_action('shutdown', function() use ($order_id) {
	        if (!is_admin() && !wp_doing_ajax()) {
	            wp_destroy_all_sessions();
	            wp_clear_auth_cookie();
	            
	            WC()->session->__unset('session__cleanup');
	            WC()->session->__unset('session__cleanup_order');

	            if (function_exists('WC') && WC()->session) {
	                WC()->session->destroy_session();
	            }
	            
	            if (!headers_sent()) {
	                setcookie('wordpress_logged_in_' . COOKIEHASH, '', 1, COOKIEPATH, COOKIE_DOMAIN);
	                setcookie('wp_woocommerce_session_', '', 1, COOKIEPATH, COOKIE_DOMAIN);
	            }
	        }
	    }, PHP_INT_MAX);
	}

	/**
	 * Edit Upsell template for add prodcut content
	 * 
	 */	
	function cuw_offer_processed_data($offer, $product, $original_data){
		global $upsell_product;
		$upsell_product	=	$product;
		return $offer;
	}
	/*
	*	[bh_upsell_content]
	*/
	function shortcode_bh_upsell_content(){
		try {
				if (defined('ELEMENTOR_VERSION') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
		        return '[Product content hidden in editor]';
		    }

			global $upsell_product;
			$content	=	'';

			if (!is_object($upsell_product) || !function_exists('wc_get_product') || 'product' !== $upsell_product->post_type) {
		        return '';
		    }

			$id		=	$upsell_product->get_id();
			$product=	get_post( $id );

			$content	=	apply_filters('the_content', $product->post_content);
			
		} catch (Exception $e) {
			$content	=	'';
		}
		return $content;
	    
	}
	
	/*
	*	[bh_order_limit_is_activated]
	*/
	function shortcode_order_limit_is_activated() {
		return $this->common->are_orders_restricted();
	}

	/**
	 * Shortcode to display product variations as a form with radio buttons
	 * [bh_product_variations]
	 */	
	function brello_product_variations_form_shortcode($atts) {
		$atts = shortcode_atts(array(
			'product_id' 	=> '',
			'button_text' 	=> 'GET STARTED',
			'redirect_base' => 'https://start.brellohealth.com/goal'
		), $atts, 'brello_product_variations');
		
		if (empty($atts['product_id'])) {
			return '<p>Error: Debes especificar el ID del producto con el atributo product_id.</p>';
		}
		
		$product = wc_get_product($atts['product_id']);
		if (!$product || !$product->is_type('variable')) {
			return '<p>Error: El ID especificado no corresponde a un producto variable.</p>';
		}
		
		$variations = $product->get_available_variations();
		$attribute = 'pa_subscription';
		$attribute_options = $product->get_attribute($attribute);
		
		if (empty($variations)) {
			return '<p>No hay variaciones disponibles para este producto.</p>';
		}
		ob_start();
		?>
		<form method="GET" action="<?php echo esc_url($atts['redirect_base']); ?>" class="form-prices bh semaglutide">
			<div class="plan-list">
				<?php 
				$first = true;
				foreach ($variations as $variation) :
					$variation_obj = wc_get_product($variation['variation_id']);
					if (!$variation_obj || !$variation_obj->is_in_stock()) continue;
					
					$subscription_value = $variation_obj->get_attribute($attribute);
					if (empty($subscription_value)) continue;

					$subscription_interval = get_post_meta( $variation_obj->get_id(), '_subscription_period_interval', true );
					$frequency_text = '';
					if ($subscription_interval == 1) {
						$frequency_text = 'Every 25 days';
					} elseif ($subscription_interval == 3) {
						$frequency_text = 'Every 10 weeks';
					}

					$plan_label = ucfirst($subscription_value) . ' Plan';
					
					$regular_price = $variation_obj->get_regular_price();
					$sale_price = $variation_obj->get_sale_price();
					$price = $sale_price ? $sale_price : $regular_price;
					
					$redirect_url = add_query_arg(array(
						'attribute_pa_subscription' => $subscription_value
					), $product->get_permalink());
				?>
				<label class="plan">
					<div class="duration">
						<input type="radio" id="plan-<?php echo esc_attr($subscription_value); ?>" 
							name="redirect" 
							value="<?php echo esc_url($redirect_url); ?>" 
							<?php echo $first ? 'checked' : ''; ?>>
						<div class="d-flex flex-column ms-2">
							<span class="duration-plan-label"><?php echo esc_html($plan_label); ?></span>
							<span class="duration-plan-label-2">
								Billed 
								<div class="after-price-text">&nbsp;$<?php echo esc_html($price); ?>&nbsp;</div>
								Today
							</span>
							<span class="duration-plan-label-2">
								Then 
								<div class="after-price-text">&nbsp;$<?php echo esc_html($price); ?>&nbsp;</div>
								<?php echo esc_html($frequency_text); ?>
							</span>
							<span class="duration-plan-label-2">Cancel anytime</span>
						</div>
					</div>
					<div class="duration-price">
						<div class="total-bill-text">$<?php echo esc_html($regular_price); ?></div>
						<div class="discount-price">$<?php echo esc_html($price); ?></div>
					</div>
				</label>
				<?php 
				$first = false;
				endforeach; 
				?>
			</div>
			<input type="hidden" name="product" value="<?php echo esc_attr($product->get_slug()); ?>">
			<input type="submit" value="<?php echo esc_attr($atts['button_text']); ?>"/>
		</form>		
		<?php
		return ob_get_clean();
	}

	/**
	 * Modify Text in order Total
	 * */
	function modify_subscription_renewal_text($total_rows, $subscription, $tax_display) {
	    if (isset($total_rows['order_total'])) {
	        $items = $subscription->get_items();
	        foreach ($items as $item) {
	            $product = $item->get_product();
	            
	            $subscription_interval = get_post_meta( $product->get_id(), '_subscription_period_interval', true );
				$custom_text = '';
				if ( $subscription_interval == 1 ) {
					$custom_text = 'Renews every 25 days';
				} elseif ( $subscription_interval == 3 ) {
					$custom_text = 'Renews every 10 weeks';
				}
				if ( ! empty( $custom_text ) ) {
					//$formatted_total = wc_price( $this->get_total(), array( 'currency' => $this->get_currency() ) );
					$total		=	 wc_price($subscription->get_total());
					$custom_text=	'<br/><small style="display:flex;line-height:1rem">'. $custom_text .'</small>';
					$total_rows['order_total']['value'] = $total . $custom_text;
		        }
	        }
	    }
	    
	    return $total_rows;
	}

	/**
	 * Friendbuy tracking
	 * */
	function insert_friendbuy_tracking_customer(){
	    if (is_user_logged_in()) {
	        $user = wp_get_current_user();
	        ?>
	        <script>
	            friendbuyAPI.push([
	              "track",
	              "customer",
	              {
	                email: "<?php echo esc_js($user->user_email); ?>",
	                id: "<?php echo esc_js($user->ID); ?>",
	              },
	            ]);
	        </script>
	        <?php
	    }
	}
	

	function insert_friendbuy_tracking(){
		if (!is_order_received_page()) return;

		global $tracking_data;
		if(!$tracking_data)
			return ;
		?>
		<script>
		    friendbuyAPI.push([
		      "track",
		      "purchase",
		      {
		        id: "<?php echo esc_js($tracking_data['order_id']); ?>",
		        amount: <?php echo esc_js($tracking_data['sale_amount']); ?>,
		        currency: "USD", 
		        couponCode: "<?php echo esc_js( $tracking_data['discount_code'] ); ?>"
		      },
		    ]);
		    console.log("track","purchase",{id: "<?php echo esc_js($tracking_data['order_id']); ?>", amount: <?php echo esc_js($tracking_data['sale_amount']); ?>, currency: "USD", couponCode: "<?php echo esc_js( $tracking_data['discount_code'] ); ?>"});
		</script>
		<?php 
	}

	
	// Redirect non-logged-in users
	function check_page_access() {
	    // Only apply to single pages
	    if (!is_singular('page')) {
	        return;
	    }
	    
	    global $post;
	    $logged_in_only = get_post_meta($post->ID, '_logged_in_only', true);
	    
	    // If page requires logged-in user and user is NOT logged in
	    if ($logged_in_only == '1' && !is_user_logged_in()) {
	        wp_redirect(home_url());
	        exit;
	    }
	}

	/**
	 * US Phone Number Standardization
	 * */
	 /**
     * Cargar scripts y estilos necesarios
     */
    public function enqueue_scripts_phone_validations() {
        if (is_checkout() || is_account_page() || is_wc_endpoint_url('edit-address')) {
            wp_enqueue_script('bh-us-phone-validation', plugin_dir_url(__FILE__) . 'js/phone-validation.js', array('jquery'), '1.0.0', true);
            
            wp_localize_script('bh-us-phone-validation', 'bh_us_phone_params', array(
                'required' => __('Phone number is required', 'bh-features'),
                'invalid_phone' => __('Please enter a valid US phone number. Format: (555) 123-4567', 'bh-features'),
                'placeholder' => __('(555) 123-4567', 'bh-features')
            ));
            
            // CSS para el formato
            wp_add_inline_style('woocommerce-general', '
                .bh-phone-error {
                    color: #e2401c;
                    font-size: 12px;
                    margin-top: 5px;
                    display: block;
                }
                input.bh-phone-formatted {
                    font-family: monospace;
                    letter-spacing: 1px;
                }
            ');
        }
    }
    
    /**
     * Modificar el campo de teléfono en el checkout
     */
    public function modify_billing_phone_field($fields) {
        if (isset($fields['billing_phone'])) {
            $fields['billing_phone']['class'] = array('form-row-wide');
            $fields['billing_phone']['placeholder'] = __('(555) 123-4567', 'bh-features');
            $fields['billing_phone']['custom_attributes']['data-phone-us'] = 'true';
        }
        
        return $fields;
    }
    
    /**
     * Validar el número de teléfono durante el checkout
     */
    public function validate_phone_number() {
        if (isset($_POST['billing_phone'])) {
            $phone = sanitize_text_field($_POST['billing_phone']);
            
            if (!$this->is_valid_us_phone($phone)) {
                wc_add_notice(__('Please enter a valid US phone number in the format (555) 123-4567.', 'bh-features'), 'error');
            }
        }
    }
    
    /**
     * Validación adicional en checkout
     */
    public function validate_checkout_phone($data, $errors) {
        if (isset($data['billing_phone']) && !empty($data['billing_phone'])) {
            if (!$this->is_valid_us_phone($data['billing_phone'])) {
                $errors->add('validation', __('Please enter a valid US phone number in the format (555) 123-4567.', 'bh-features'));
            }
        }
    }
    
    /**
     * Formatear el número antes de guardarlo - GUARDAR EN FORMATO E.164
     */
    public function format_phone_before_save($phone) {
        return $this->format_to_e164($phone);
    }
    
    /**
     * Validar número de teléfono USA
     */
    private function is_valid_us_phone($phone) {
        // Patrón para (555) 123-4567 o 555-123-4567 o 5551234567
        $pattern = '/^\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/';
        
        // Validación básica del patrón
        if (!preg_match($pattern, trim($phone))) {
            return false;
        }
        
        // Extraer solo dígitos y verificar que sean 10
        $digits_only = preg_replace('/\D/', '', $phone);
        if (strlen($digits_only) !== 10) {
            return false;
        }
        
        // Validar que el código de área sea válido (no 555)
        $area_code = substr($digits_only, 0, 3);
        if ($area_code === '555') {
            return false; // 555 es para ejemplos
        }
        
        return true;
    }
    
    /**
     * Convertir a formato E.164 (+15551234567)
     */
    // private function format_to_e164($phone) {
    //     // Extraer solo dígitos
    //     $digits_only = preg_replace('/\D/', '', $phone);
        
    //     // Si tiene 10 dígitos, agregar código de país USA
    //     if (strlen($digits_only) === 10) {
    //         return '+1' . $digits_only;
    //     }
        
    //     // Si ya tiene el +1, dejarlo como está
    //     if (substr($digits_only, 0, 1) === '1' && strlen($digits_only) === 11) {
    //         return '+' . $digits_only;
    //     }
        
    //     // Devolver limpio para mantener consistencia
    //     return '+1' . $digits_only;
    // }
    
    /**
     * Formatear para visualización (555) 123-4567
     */
    // private function format_to_display($phone) {
    //     $digits_only = preg_replace('/\D/', '', $phone);
        
    //     if (strlen($digits_only) === 10 || (strlen($digits_only) === 11 && substr($digits_only, 0, 1) === '1')) {
    //         // Remover el 1 inicial si existe
    //         if (strlen($digits_only) === 11) {
    //             $digits_only = substr($digits_only, 1);
    //         }
            
    //         return '(' . substr($digits_only, 0, 3) . ') ' . substr($digits_only, 3, 3) . '-' . substr($digits_only, 6, 4);
    //     }
        
    //     return $phone; // Devolver original si no se puede formatear
    // }

    
    /**
     * Cargar scripts y estilos necesarios
     */
    public function enqueue_scripts_mobile_phone_validation() {
        if (is_checkout() || is_account_page() || is_wc_endpoint_url('edit-address')) {
            wp_enqueue_script('bh-us-mobile-phone-validation', plugin_dir_url(__FILE__) . 'js/mobile-phone-validation.js', array('jquery'), '1.0.0', true);
            
            wp_localize_script('bh-us-mobile-phone-validation', 'bh_us_mobile_phone_params', array(
                'required' => __('Mobile phone number is required', 'bh-features'),
                'invalid_phone' => __('Please enter a valid US mobile number. Format: +1 408-600-4784', 'bh-features'),
                'placeholder' => __('+1 408-600-4784', 'bh-features')
            ));
            
            // CSS para el formato
            wp_add_inline_style('woocommerce-general', '
                .bh-mobile-phone-error {
                    color: #e2401c;
                    font-size: 12px;
                    margin-top: 5px;
                    display: block;
                }
                input.bh-mobile-phone-formatted {
                    font-family: monospace;
                    letter-spacing: 1px;
                    direction: ltr;
                    text-align: left;
                }
                .bh-phone-prefix {
                    color: #666;
                    font-size: 14px;
                    margin-right: 5px;
                }
            ');
        }
    }
    
    /**
     * Agregar campo móvil al checkout
     */
    public function add_mobile_phone_to_checkout($fields) {
        $fields['billing']['billing_mobile_phone'] = array(
            'label' => __('Mobile Phone', 'bh-features'),
            'placeholder' => __('+1 408-600-4784', 'bh-features'),
            'required' => true,
            'class' => array('form-row-wide'),
            'clear' => true,
            'type' => 'tel',
            'priority' => 25, // Después del teléfono regular
            'custom_attributes' => array(
                'data-mobile-phone-us' => 'true'
            )
        );
        
        return $fields;
    }
    
    /**
     * Modificar el campo de móvil existente
     */
    public function modify_billing_mobile_phone_field($fields) {
        if (isset($fields['billing_mobile_phone'])) {
            $fields['billing_mobile_phone']['class'] = array('form-row-wide');
            $fields['billing_mobile_phone']['placeholder'] = __('+1 408-600-4784', 'bh-features');
            $fields['billing_mobile_phone']['custom_attributes']['data-mobile-phone-us'] = 'true';
        }
        
        return $fields;
    }
    
    /**
     * Validar el número de móvil durante el checkout
     */
    public function validate_mobile_phone_number() {
        if (isset($_POST['billing_mobile_phone'])) {
            $mobile_phone = sanitize_text_field($_POST['billing_mobile_phone']);
            
            if (!$this->is_valid_us_mobile_phone($mobile_phone)) {
                wc_add_notice(__('Please enter a valid US mobile number in the format +1 408-600-4784.', 'bh-features'), 'error');
            }
        }
    }
    
    /**
     * Validación adicional en checkout
     */
    public function validate_checkout_mobile_phone($data, $errors) {
        if (isset($data['billing_mobile_phone']) && !empty($data['billing_mobile_phone'])) {
            if (!$this->is_valid_us_mobile_phone($data['billing_mobile_phone'])) {
                $errors->add('validation', __('Please enter a valid US mobile number in the format +1 408-600-4784.', 'bh-features'));
            }
        }
    }
    
    /**
     * Guardar campo móvil en la orden
     */
    public function save_mobile_phone_field($order_id) {
        if (isset($_POST['billing_mobile_phone']) && !empty($_POST['billing_mobile_phone'])) {
            $mobile_phone = sanitize_text_field($_POST['billing_mobile_phone']);
            $formatted_phone = $this->format_to_e164($mobile_phone);
            
            update_post_meta($order_id, '_billing_mobile_phone', $formatted_phone);
            update_post_meta($order_id, '_billing_mobile_phone_display', $mobile_phone);
        }
    }
    
    /**
     * Guardar campo móvil en el cliente
     */
    public function save_mobile_phone_customer($user_id, $address_type) {
        if ($address_type === 'billing' && isset($_POST['billing_mobile_phone'])) {
            $mobile_phone = sanitize_text_field($_POST['billing_mobile_phone']);
            $formatted_phone = $this->format_to_e164($mobile_phone);
            
            update_user_meta($user_id, 'billing_mobile_phone', $formatted_phone);
        }
    }
    
    /**
     * Mostrar campo móvil en admin
     */
    public function display_mobile_phone_in_admin($order) {
        $mobile_phone = get_post_meta($order->get_id(), '_billing_mobile_phone', true);
        if ($mobile_phone) {
            echo '<p><strong>' . __('Mobile Phone:') . '</strong><br>' . esc_html($this->format_to_display($mobile_phone)) . '</p>';
        }
    }
    
    /**
     * Validar número de móvil USA con formato +1 408-600-4784
     */
    private function is_valid_us_mobile_phone($phone) {
        // Patrón para +1 408-600-4784
        $pattern = '/^\+\d{1}\s\d{3}-\d{3}-\d{4}$/';
        
        // Validación básica del patrón
        if (!preg_match($pattern, trim($phone))) {
            return false;
        }
        
        // Verificar que empiece con +1
        if (substr($phone, 0, 2) !== '+1') {
            return false;
        }
        
        // Extraer solo dígitos y verificar que sean 11 (1 + 10)
        $digits_only = preg_replace('/\D/', '', $phone);
        if (strlen($digits_only) !== 11) {
            return false;
        }
        
        // Validar que el código de área sea válido (no 555)
        $area_code = substr($digits_only, 1, 3);
        if ($area_code === '555') {
            return false; // 555 es para ejemplos
        }
        
        return true;
    }
    
    /**
     * Convertir a formato E.164 (+14086004784)
     */
    private function format_to_e164($phone) {
        // Extraer solo dígitos
        $digits_only = preg_replace('/\D/', '', $phone);
        
        // Si tiene 10 dígitos, agregar código de país USA
        if (strlen($digits_only) === 10) {
            return '+1' . $digits_only;
        }
        
        // Si ya tiene 11 dígitos (incluyendo el 1), agregar +
        if (strlen($digits_only) === 11) {
            return '+' . $digits_only;
        }
        
        return $phone; // Devolver original si no cumple
    }
    
    /**
     * Formatear para visualización +1 408-600-4784
     */
    private function format_to_display($phone) {
        $digits_only = preg_replace('/\D/', '', $phone);
        
        if (strlen($digits_only) === 10 || (strlen($digits_only) === 11 && substr($digits_only, 0, 1) === '1')) {
            // Remover el 1 inicial si existe y tiene 11 dígitos
            if (strlen($digits_only) === 11) {
                $digits_only = substr($digits_only, 1);
            }
            
            return '+1 ' . substr($digits_only, 0, 3) . '-' . substr($digits_only, 3, 3) . '-' . substr($digits_only, 6, 4);
        }
        
        return $phone; // Devolver original si no se puede formatear
    }
    
    /**
     * Validar formato E.164
     */
    private function is_valid_e164($phone) {
        return preg_match('/^\+\d{11}$/', $phone);
    }




}