<?php
/**
 * Checkout UI Customizations Module
 *
 * @description
 * This module contains all UI customizations for the checkout,
 * including custom fields, custom step content, integrations with
 * multistep checkout plugins, and HTML output modifications.
 *
 * Important: Keep only UI-related logic here.
 * Validation logic should remain inside the checkout-validation module.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Checkout_UI {

    public function __construct() {

        /**
         * ---------------------------------------------------------
         * REGISTER HOOKS
         * ---------------------------------------------------------
         *
         * You may paste your hooks here. Example:
         *
         * add_filter('arg_msc_steps', [ $this, 'your_custom_function' ]);
         * add_action('arg_msc_step_content_custom', [ $this, 'render_custom_step' ]);
         * add_filter('woocommerce_checkout_fields', [ $this, 'modify_checkout_fields' ]);
         *
         * Don't rename your original functions,
         * but add comments recommending a better name.
         */
		
		/**
		 * Remove the Error Messages from top of checkout pages
		 */
		add_action('init', [ $this, 'hb_init_remove_wc_hooks'], 999999);

        add_filter('gettext', [ $this, 'change_ship_to_different_address_text'], 20, 3);
        add_action( 'woocommerce_checkout_before_order_review', [ $this, 'add_billing_shipping_summary' ], 20 );
        add_action( 'woocommerce_order_review', [$this, 'woocommerce_review_order_before_cart_contents'], 1 );
        add_filter('woocommerce_cart_item_name', [ $this, 'bh_woocommerce_cart_item_name'], 10, 3);

        /**
		 * Add Terms & Conditions to Tab Checkout
		 */
		add_filter('arg-mc-init-options', [ $this, 'bh_arg_mc_init_options_add_step_terms_conditions']);
		add_action('arg-mc-checkout-step', [ $this, 'bh_arg_mc_checkout_step_add_content_terms_conditions']);
		/*
		*	Print Custom Text depend of Subscription Variation
		*/
		add_filter( 'arg-mc-init-options', [$this, 'arg_mc_init_options'] );
		add_shortcode('_bh_disclaimer_plan_selected', [ $this, 'disclaimer_plan_selected_shortcode']);

		add_filter('woocommerce_states', [$this, 'restrict_us_states']);
		add_filter( 'woocommerce_states', [ $this, 'augment_state_labels_for_checkout' ], 999 );


		add_action('wp_enqueue_scripts', [ $this, 'enqueue_google_places_and_states']);

		add_filter('woocommerce_billing_fields', [$this, 'reorder_billing_fields'], 9999999);
		/**
		 * Hide company field in WooCommerce checkout
		 * */
		add_filter( 'woocommerce_checkout_fields', [$this, 'hide_company_field_checkout'] );

		add_filter('woocommerce_checkout_fields', [ $this, 'bh_woocommerce_checkout_fields_phone_validation']);
		add_filter('woocommerce_checkout_fields', [ $this, 'bh_woocommerce_checkout_fields_kl_newsletter_checkbox'], 99999);

		add_filter( 'woocommerce_add_error', [ $this, 'sanitize_state_validation_error' ], 10, 1 );

	}

    /**
     * ---------------------------------------------------------
     * HOOK CALLBACKS
     * ---------------------------------------------------------
     * Paste all your current functions here.
     *
     * ⚠️ DO NOT rename your existing functions.
     * Add comments inside the function suggesting better names.
     * This keeps backwards compatibility with your current plugin/version.
     */
	
	/**
	 * Remove the Error Messages from top of checkout pages
	 */
	function hb_init_remove_wc_hooks() {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10 );
		remove_action( 'woocommerce_before_checkout_form_cart_notices', 'woocommerce_output_all_notices', 10 );
		//add_action( 'woocommerce_checkout_tabs', 'woocommerce_output_all_notices', 10 );
	}
    
	function change_ship_to_different_address_text($translated_text, $text, $domain){
		if('Ship to a different address?'===$text && 'woocommerce'===$domain)
			$translated_text    =   __('My billing address is the same as my shipping address.', 'woocommerce');
		return $translated_text;
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
					<li><strong>**Post-Dispatch:**</strong> Due to the nature of compounded medications and in accordance with pharmacy regulations, dispensed medications are non-refundable. If you have concerns about your medication or suspect a dispensing error, please contact the dispensing pharmacy within 48 hours of receiving your medication.
						<br><strong>Note:</strong> The dispensing pharmacy contact information can be found on the prescription label.
					</li>
					<li>Please ensure the shipping address is entered correctly during checkout. A refund will not be issued if the shipping address was entered incorrectly and the medications have been shipped.</li>
					<li><strong>**Replacement Policy**</strong><br>
						Once a delivery confirmation is recorded by the carrier, the fulfillment of the order is considered complete. Please note that we are unable to reship medications for orders that have been marked as delivered by the carrier.
						<br>
						If a patient reports non-receipt of a medication despite the delivery confirmation, the patient will be required to pay for a new order but can file a claim directly with the courier for any lost or stolen packages

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

	function restrict_us_states($states) {
		if(!class_exists('AH_States'))
			return $states;

		$states = AH_States::get_states_for_current_user($states);
		return $states;
	}
	/**
     * Modify US state labels ONLY in checkout, adding their description.
     * Format: "State Name - Description"
     */
    public static function augment_state_labels_for_checkout( $states ) {
        if ( ! self::is_checkout_context() ) {
            return $states;
        }
		// URL fallback
		// $uri = $_SERVER['REQUEST_URI'] ?? '';
		// if ( strpos( $uri, 'checkout' ) === false ) {
		// 	return $states;
		// }
		
		if(!class_exists('AH_States') || !class_exists('AH_Licensed_States_Manager'))
			return $states;
		
		$_states = AH_States::get_states_for_current_user($states);
		$states = $_states['US'];
        foreach ( $states as $code => $label ) {
            $code = strtoupper( trim( $code ) );
			$description = AH_Licensed_States_Manager::get_state_description( $code );
			$description = wp_strip_all_tags( $description );
			if ( empty( $description ) ) {
				continue;
			}
			$states['US'][ $code ] = sprintf(
				'%s - %s',
				$label,
				$description
			);
        }

        return $states;
    }

	function enqueue_google_places_and_states() {
		if (!is_checkout())
			return;

		if(!class_exists('AH_States'))
			return;
		
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
			plugin_dir_url( __FILE__ ) . 'assets/js/bh-google-places.js',
			array('google-places-api', 'jquery'),
			null,
			true
		);
		// $licensed_states	=	AH_States::get_codes();//array_keys($this->licensed_states);
		$states				=	AH_States::get_all();
		$_states = AH_States::get_states_for_current_user($states);
		$licensed_states	=	array_keys($_states['US']);
		wp_localize_script('custom-google-places', 'allowedStates', $licensed_states);
	}

	function bh_woocommerce_checkout_fields_phone_validation($fields){
		$fields['billing']['billing_phone']['custom_attributes']	=	['minlength'=>'10', 'pattern'=>'[0-9]{10,}'];
		$fields['billing']['billing_phone']['placeholder']	=	'The phone number must have at least 10 digits.';
		return $fields;
	}

	function bh_woocommerce_checkout_fields_kl_newsletter_checkbox($fields){
		if(isset($fields['billing']['kl_newsletter_checkbox'])){
			$fields['billing']['kl_newsletter_checkbox']['label']	=	'<span>' . $fields['billing']['kl_newsletter_checkbox']['label'] . '</span>';
		}

		return $fields;
	}

	private static function is_checkout_context() {

		// Classic checkout
		if ( is_checkout() ) {
			return true;
		}

		// Checkout blocks (page content)
		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
			return true;
		}

		// Fallback: URL contains /checkout (some themes/plugins wrap differently)
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $uri, 'checkout' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove state descriptions from WooCommerce validation error messages.
	 *
	 * Example:
	 * "Texas - Available (No Shipping Delays)" -> "Texas"
	 */
	public function sanitize_state_validation_error( $error ) {
		// error_log('SANITIZE STATE ERROR: ' . $error);
		$error = strip_tags($error);
		// error_log('SANITIZE STATE ERROR: ' . $error);

		// Solo nos interesa el error de estado inválido
		if ( stripos( $error, 'State is not valid' ) === false ) {
			return $error;
		}

		// Recorremos todos los estados conocidos y limpiamos el label
		foreach ( AH_States::get_all() as $code => $state_name ) {

			// Regex: "State Name - anything"
			$pattern = sprintf(
				'/%s\s*-\s*[^,]+/i',
				preg_quote( $state_name, '/' )
			);

			$error = preg_replace( $pattern, $state_name, $error );
		}

		return $error;
	}

	function reorder_billing_fields($fields) {
		//_print($fields);
	    if (isset($fields['billing_email'])) {
	        $fields['billing_email']['priority'] = 1;
	    }
	    // Adjust other fields' priorities if needed
	    if (isset($fields['billing_first_name'])) {
	        $fields['billing_first_name']['priority'] = 10;
	    }
	    if (isset($fields['billing_last_name'])) {
	        $fields['billing_last_name']['priority'] = 20;
	    }

		return $fields;
	}

	/**
	 * Hide company field in WooCommerce checkout
	 * */
	function hide_company_field_checkout( $fields ) {
	    unset( $fields['billing']['billing_company']);
	    unset( $fields['shipping']['shipping_company']);
	    
	    return $fields;
	}
	
}

//new BH_Checkout_UI();
// add_action('woocommerce_init', function() {
//     new BH_Checkout_UI();
// });
// add_action('init', function () {
//     if (class_exists('WooCommerce')) {
//         new BH_Checkout_UI();
//     }
// }, 30);
// add_action('init', function() {
//     if (class_exists('WooCommerce') && did_action('woocommerce_loaded')) {
//         new BH_Checkout_UI();
//     }
// }, 20);

/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new BH_Checkout_UI();
});