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
		//add_action('init', [ $this, 'hb_init_remove_wc_hooks'], 999999);

        //add_filter('gettext', [ $this, 'change_ship_to_different_address_text'], 20, 3);
        //add_action( 'woocommerce_checkout_before_order_review', [ $this, 'add_billing_shipping_summary' ], 20 );
        //add_action( 'woocommerce_order_review', [$this, 'woocommerce_review_order_before_cart_contents'], 1 );
        //add_filter('woocommerce_cart_item_name', [ $this, 'bh_woocommerce_cart_item_name'], 10, 3);

        /**
		 * Add Terms & Conditions to Tab Checkout
		 */
		//add_filter('arg-mc-init-options', [ $this, 'bh_arg_mc_init_options_add_step_terms_conditions']);
		//add_action('arg-mc-checkout-step', [ $this, 'bh_arg_mc_checkout_step_add_content_terms_conditions']);
		/*
		*	Print Custom Text depend of Subscription Variation
		*/
		//add_filter( 'arg-mc-init-options', [$this, 'arg_mc_init_options'] );
		//add_shortcode('_bh_disclaimer_plan_selected', [ $this, 'disclaimer_plan_selected_shortcode']);

		add_filter('woocommerce_states', [$this, 'restrict_us_states']);
		add_filter( 'woocommerce_states', [ $this, 'augment_state_labels_for_checkout' ], 999 );


		//add_action('wp_enqueue_scripts', [ $this, 'enqueue_google_places_and_states']);

		//add_filter('woocommerce_billing_fields', [$this, 'reorder_billing_fields'], 9999999);
		/**
		 * Hide company field in WooCommerce checkout
		 * */
		//add_filter( 'woocommerce_checkout_fields', [$this, 'hide_company_field_checkout'] );

		add_filter('woocommerce_checkout_fields', [ $this, 'bh_woocommerce_checkout_fields_phone_validation']);
		add_filter('woocommerce_checkout_fields', [ $this, 'bh_woocommerce_checkout_fields_kl_newsletter_checkbox'], 99999);

		add_filter( 'woocommerce_add_error', [ $this, 'sanitize_state_validation_error' ], 10, 1 );

		// Handle marketing subscription checkbox
		add_action('woocommerce_checkout_update_order_meta', [ $this, 'save_marketing_subscription_checkbox' ]);

		//add_action('wp_footer', [$this, 'inject_checkout_coupon_sync_script'], 999);

		/**
		 * Remove WC Terms & Conditions checkbox from Checkout Page
		 */
		add_filter( 'woocommerce_checkout_show_terms', [$this, 'remove_wc_checkout_terms'] );
		add_action( 'cfw_checkout_before_payment_method_terms_checkbox', [$this, 'render_terms_checkbox'] );

		add_filter( 'woocommerce_cart_item_name', [ $this, 'bh_checkout_item_name_with_price' ], 20, 3 );

		add_action('wp_footer', [$this, 'inject_coupon_toggle_disclaimer_script'], 1000 );
		add_action('wp_head', [$this, 'add_custom_style_on_order_received']);

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

	public function bh_woocommerce_cart_item_name__original($product_name, $cart_item, $cart_item_key){	
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

	public function bh_woocommerce_cart_item_name( $product_name, $cart_item, $cart_item_key ) {

		$product      = $cart_item['data'];
		$product_id   = $cart_item['product_id'];
		$quantity     = $cart_item['quantity'];

		$regular_price = wc_price( $product->get_price() );

		$cart_total_raw = WC()->cart->get_total( 'edit' );
		$cart_total     = wc_price( $cart_total_raw );

		$applied_coupons = WC()->cart->get_applied_coupons();

		$bh_checkout_text_supply    = get_post_meta( $product_id, 'bh_checkout_text_supply', true );
		$bh_checkout_text_due_today = get_post_meta( $product_id, 'bh_checkout_text_due_today', true );

		$output  = '<div class="hb-product-info">';
		$output .= '<h3>' . esc_html( $product->get_name() ) . '</h3>';
		$output .= '<div class="hb-columns">';
		$output .= '<ul class="info-purchase-product">';

		if ( ! empty( $bh_checkout_text_supply ) ) {
			$output .= '<li class="day-supply">' . esc_html( $bh_checkout_text_supply ) . '</li>';
		}

		$output .= '<li class="refund">Full Refund if Not Qualified</li>';
		$output .= '</ul>';

		$output .= '<figure class="product-thumbnail">';
		$output .= apply_filters(
			'woocommerce_in_cart_product_thumbnail',
			$product->get_image('large'),
			$cart_item,
			$cart_item_key
		);
		$output .= '</figure>';
		$output .= '</div>';

		//$output .= do_shortcode('[deadlinefunnel type="inline"]');

		$discount_total = WC()->cart->get_discount_total();
		$fees           = WC()->cart->get_fees();

		if ( ! empty( $applied_coupons ) || ( $discount_total > 0 ) || ( ! empty( $fees ) ) ) {
			$output .= '<hr>';
			$output .= '<div class="hb-due">';
			$output .= '<div>Price</div>';
			$output .= '<span>' . $regular_price . '</span>';
			$output .= '</div><!--hb-due-->';
		}

		if ( $discount_total > 0 ) {
			
			$output .= '<div class="hb-due discount">';
			$output .= '<div>Discount:</div>';
			$output .= '<span>-' . wc_price( $discount_total ) . '</span>';
			$output .= '</div><!--hb-due-->';
		}

		if ( ! empty( $applied_coupons ) ) {
			$output .= '<div class="hb-due">';
			$output .= '<div>Coupon: ';
			foreach ( $applied_coupons as $coupon_code ) {

				$output .= '<small>' . esc_html( strtoupper( $coupon_code ) ) . '</small>';
			}
			$output .= '</div>';
			$output .= '</div><!--hb-due-->';
		}

		if ( ! empty( $fees ) ) {
			foreach ( $fees as $fee ) {

				if ( $fee->total < 0 ) {
					$output .= '<div class="hb-due discount">';
					$output .= '<div>' . esc_html( $fee->name ) . '</div>';
					$output .= '<span>' . wc_price( $fee->total ) . '</span>';
					$output .= '</div><!--hb-due-->';
				}
			}
		}

		$output .= '<hr>';
		$output .= '<div class="hb-due">';
		$output .= '<div><strong>Due Today</strong>';

		if ( ! empty( $bh_checkout_text_due_today ) ) {
			$output .= '<br>' . esc_html( $bh_checkout_text_due_today );
		}

		$output .= '</div>';

		$output .= '<strong>' . $cart_total . '</strong>';
		$output .= '</div><!--hb-due-->';
		
		$output .= '</div>';

		$output	.=	'<style>';
		$output	.=	'.hb-product-info .hb-due span {text-align: right;color: #453796;}';
		$output	.=	'</style>';

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
				<p><u>Before you proceed and move to completing your <strong>clinical intake form</strong>, please review and accept the following terms:</u></p>
				<ol>
					<li>
						<strong>3-Month Plan Pricing and Renewal Terms</strong>. You will be charged the applicable rate for the plan selected at checkout as set forth below. Your subscription will automatically renew at the same rate on the renewal cycle corresponding to your plan. You may cancel at any time.
						<br>
						<br>
						<strong>Medication-Only Plans (3-Month):</strong>
						<ul>
							<li>Compounded Tirzepatide – $499 (renews every 10 weeks)</li>
							<li>Compounded Semaglutide – $399 (renews every 10 weeks)</li>
							<li>Compounded NAD+ – $239 (renews every 10 weeks)</li>
							<li>Compounded Sermorelin – $349 (renews every 11 weeks)</li>
						</ul>
						<br>
						<strong>Longevity Lifestyle Plans (3-Month):</strong>
						<ul>
							<li>Empowered+ Longevity Lifestyle Plan - Tirzepatide + NAD+ – $698 (renews every 10 weeks)</li>
							<li>Empowered+ Longevity Lifestyle Plan - Semaglutide + NAD+ – $598 (renews every 10 weeks)</li>
							<li>Thrive Forward Longevity Lifestyle Plan - Tirzepatide + Sermorelin + NAD+ – $997 (renews every 11 weeks)</li>
							<li>Thrive Forward Longevity Lifestyle Plan - Semaglutide + Sermorelin + NAD+ – $897 (renews every 11 weeks)</li>
						</ul>
						<br>
						<strong>The Metabolic Compass Plan:</strong>
						<ul>
							<li>The Metabolic Compass Plan - Semaglutide & Lumen Metabolism Tracker Device - $499 today (renews every 10 weeks at $399) </li>
							<li>The Metabolic Compass Plan - Tirzepatide & Lumen Metabolism Tracker Device - $599 today (renews every 10 weeks at $499)</li>
						</ul>
						<br>
						After 90 days, the Lumen app renews at $19.90/month, or save with an annual plan at $9.90/month ($119/year, billed annually). Cancel anytime and keep your device for lifetime breath measurements.**
						<br>
						<br>
						<strong>Note:</strong> If you are not approved for renewal by the healthcare provider, you will receive a refund as it relates to your Brello subscription. Please refer to the Lumen website at https://www.lumen.me/subscription-policy for all information regarding cancellation of your subscription for the GLP-1 Support Program with Lumen as well as https://www.lumen.me/refund-and-return-policy regarding refunds and returns of your Lumen device and/or subscription for the GLP-1 Support Program with Lumen.
						<br>
						<br>
						<i>All plans containing compounded sermorelin renew every 11 weeks. All other plans renew every 10 weeks. Renewal rates are the same as the initial charge for the applicable plan.</i>						

					</li>
					<li>In order to start receiving medications under one of our plans, a healthcare provider will review your intake form and determine the appropriate consultation type with you to determine if the plan is appropriate for you.</li>

					<li>Please ensure you provide accurate information about your prior medication use, as healthcare providers rely on this to determine the appropriate dosage. If accurate information is not provided on the intake form, and the healthcare provider issued a prescription based on the information, changes will not be allowed and refunds will not be issued.</li>

					<li><strong>**Cancellation policy:**</strong> If the patient wishes to cancel and the provider-led health review is completed and a prescription has been written, may refund the total amount paid, <strong>less a $50 professional fee</strong>.
						<br>
						<br>
						This will only apply if the patient sends a written notice via email to <a href="mailto:info@brellohealth.com">info@brellohealth.com</a> within <strong>24 hours</strong> from the time the provider-led health review was completed. Requests submitted after this timeframe may not be eligible for a refund.
						<br>
						<br>
						Please refer to the Lumen website at <a href="https://www.lumen.me/subscription-policy" target="_blank">https://www.lumen.me/subscription-policy</a> for all information regarding cancellation of your subscription for the GLP-1 Support Program with Lumen.
					</li>

					<li>
						<strong>**Post-Dispatch:**</strong> Due to the nature of compounded medications and in accordance with pharmacy regulations, dispensed medications are non-refundable. If you have concerns about your medication or suspect a dispensing error, please contact the dispensing pharmacy within 48 hours of receiving your medication.
						<br>
						<br>
						<strong>Note:</strong> The dispensing pharmacy contact information can be found on the prescription label.
					</li>

					<li>Please ensure the shipping address is entered correctly during checkout. A refund will not be issued if the shipping address was entered incorrectly and the medications have been shipped.</li>

					<li>
						<strong>**Shipments:**</strong> For subscriptions that have more than one medication the intention is to ship all products together in a single shipment where possible, there may be, however, circumstances, including circumstances beyond our control, that require the products to be shipped separately. Such circumstances could include, but are not limited to, supply chain disruptions, inventory availability, or logistical considerations. In the event that the products are shipped separately, we will do our best to notify you in advance and provide any relevant details regarding the separate shipments.
					</li>

					<li>
						<strong>**Replacement Policy**</strong> Once a delivery confirmation is recorded by the carrier, the fulfillment of the order is considered complete. Please note that we are unable to reship medications for orders that have been marked as delivered by the carrier.
						<br>
						<br>
						If a patient reports non-receipt of a medication despite the delivery confirmation, the patient will be required to pay for a new order but can file a claim directly with the courier for any lost or stolen packages.
					</li>

					<li>Brello expressly disclaims any ownership, responsibility, or liability regarding the Lumen device or the Lumen app. Brello does not manufacture, endorse, or assume any regulatory or operational responsibilities for the Lumen device or the Lumen app, and makes no representations or warranties concerning the safety, efficacy, or compliance of such products. Please refer to the Lumen websites at <a href="https://www.lumen.me/subscription-policy" target="_blank">https://www.lumen.me/subscription-policy</a> for all information regarding your subscription for the GLP-1 Support Program as well as <a href="https://www.lumen.me/refund-and-return-policy" target="_blank">https://www.lumen.me/refund-and-return-policy</a> regarding refunds and returns of your Lumen device and/or subscription for the GLP-1 Support Program.</li>

					<li>Any information provided by Brello is for informational purposes only and should not be construed as medical advice; it is not a substitute for professional medical consultation, diagnosis, or treatment. All patients must consult with a healthcare provider prior to the prescription or dispensing of any medication, which will be done only pursuant to a valid prescription. Compounded drug products are not FDA-approved, and the FDA does not evaluate their safety, effectiveness, or quality. Patients are encouraged to discuss the risks, benefits, and appropriateness of any medications, including compounded products, with their healthcare provider before use.</li>
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

		// Add marketing subscription checkbox
		$fields['billing']['bh_marketing_subscription'] = array(
			'type'        => 'checkbox',
			'label'       => '<span>Subscribe to email and SMS marketing</span>',
			'description' => 'Get exclusive offers, product updates, and health tips via email and text messages.',
			'required'    => false,
			'class'       => array('form-row-wide'),
			'priority'    => 125,
			'default'     => 1
		);

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

	/**
	 * Adds a JS handler on checkout to synchronize coupon AJAX responses
	 * with checkout refresh and UI notices.
	 */
	function inject_checkout_coupon_sync_script() {
	    if ( ! is_checkout() || is_order_received_page() ) return;
	    ?>
	    <script>
			jQuery(function($){

				$('form.checkout_coupon').on('submit', function(){
					$('.coupon-error-notice').remove();
					$('.coupon-success-notice').remove();
					$('.woocommerce-error').remove();
					$('#coupon_code')
						.removeClass('has-error')
						.removeClass('woocommerce-invalid');
				});
				$(document).ajaxComplete(function(event, xhr, settings){
					if ( !settings.url || settings.url.indexOf('_coupon') === -1 ) {
						return;
					}
					if ( xhr.status !== 200 || xhr.responseText ) {
						return;
					}
					const isApply  = settings.url.indexOf('apply_coupon') !== -1;
					const isRemove = settings.url.indexOf('remove_coupon') !== -1;
					setTimeout(function(){
						$(document.body).trigger('update_checkout');
					}, 150);
					$('.coupon-error-notice, .coupon-success-notice').remove();
					let message = '';
					let coupon  = '';
					if ( isApply ) {
						coupon = $('#coupon_code').val();
						$('#coupon_code')
							.val('')
							.removeClass('has-error woocommerce-invalid')
							.blur();
						message = 'Coupon "' + coupon + '" applied successfully!';
					}
					if ( isRemove ) {
						coupon = $('.hb-applied-coupon removing').data('coupon') || '';
						message = 'Coupon removed successfully.';
					}
					const successHtml =
						'<span class="coupon-success-notice" role="alert">' +
						message +
						'</span>';
					$('.checkout_coupon .form-row-wide').append(successHtml);
					setTimeout(function(){
						$('.coupon-success-notice').fadeOut(300, function(){
							$(this).remove();
						});
					}, 4000);
				});

				$(document).on('click', '.woocommerce-remove-coupon', function(){
					$('.hb-applied-coupon').removeClass('removing');
					$(this).closest('.hb-applied-coupon')
						.addClass('removing')
						.data('coupon', $(this).data('coupon'));
				});
			});
	    </script>
	    <?php
	}

	/**
	 * Save marketing subscription checkbox to order meta and user meta
	 */
	function save_marketing_subscription_checkbox( $order_id ) {
		$logger = wc_get_logger();
		$context = array( 'source' => 'bh_marketing' );
		
		$marketing_checked = isset( $_POST['bh_marketing_subscription'] ) && $_POST['bh_marketing_subscription'] === '1';
		
		update_post_meta( $order_id, '_bh_marketing_subscription_checkout', $marketing_checked ? 'yes' : 'no' );
		AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_checkout', $marketing_checked ? 'yes' : 'no' );

		if ( $marketing_checked ) {
			$order = wc_get_order( $order_id );
			$user_id = $order ? $order->get_user_id() : 0;
			
			$logger->info( "CHECKOUT MARKETING: User opted in for marketing - Order: {$order_id}, User: {$user_id}, Email: " . ($order ? $order->get_billing_email() : 'unknown') . ", Phone: " . ($order ? $order->get_billing_phone() : 'unknown'), $context );
			
			// Save to user meta if user exists
			if ( $user_id ) {
				update_user_meta( $user_id, 'bh_marketing_subscription', 'yes' );
				update_user_meta( $user_id, 'bh_marketing_subscription_updated', current_time( 'mysql' ) );
				
				$logger->info( "CHECKOUT MARKETING: Updated user meta - Order: {$order_id}, User: {$user_id}, Preference: yes", $context );
			}
			
			// Mark order for marketing subscription processing
			update_post_meta( $order_id, '_bh_marketing_subscription', 'yes' );
			AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_checkout_consent', 'yes' );
			
		} else {			
			update_post_meta( $order_id, '_bh_marketing_subscription', 'no' );
			AH_Order_Meta::set( $order_id, '_bh_marketing_subscription_checkout_consent', 'no' );
		}
	}

	function remove_wc_checkout_terms($show){
		return false;
	}

	public function render_terms_checkbox() {
	    $terms_page_id = 671478;//wc_get_page_id( 'checkout-terms-conditions' );
	    $terms_url     = $terms_page_id ? get_permalink( $terms_page_id ) : '#';
	    echo '<div class="cfw-custom-terms" style="margin-bottom:15px;">
	        <label>
	            <input type="checkbox" name="bh_accept_terms" id="bh_accept_terms" value="on" checked />
	            ' . sprintf(
	                __( 'I have read and agree to the website <a href="%s" target="_blank">Terms & Conditions</a>.', 'hw-features' ),
	                esc_url( $terms_url )
	            ) . '
	        </label>
	    </div>';
	}

	/**
	 * Append variation interval and price breakdown to cart item name in checkout order review.
	 * Format: Parent Name - N Month(s) for $Price ($Price/Month)
	 *
	 * @param string $product_name
	 * @param array  $cart_item
	 * @param string $cart_item_key
	 * @return string
	 */
	public function bh_checkout_item_name_with_price( $product_name, $cart_item, $cart_item_key ) {

	    if ( ! is_checkout() ) {
	        return $product_name;
	    }

	    $product = $cart_item['data'] ?? null;
	    if ( ! $product instanceof WC_Product || ! $product->is_type( 'variation' ) ) {
	        return $product_name;
	    }

	    $parent = wc_get_product( $product->get_parent_id() );
	    if ( ! $parent ) {
	        return $product_name;
	    }

	    $interval = (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true );
	    if ( $interval < 1 ) {
	        return $product_name;
	    }

	    $price       = (float) ( $product->get_sale_price() ?: $product->get_regular_price() );
	    $per_month   = round( $price / $interval );
	    $month_label = $interval === 1 ? '1 Month' : "{$interval} Months";

	    return esc_html( sprintf(
	        '%s - %s for $%s ($%s/Month)',
	        $parent->get_name(),
	        $month_label,
	        number_format( $price, 0 ),
	        number_format( $per_month, 0 )
	    ) );
	}

	/**
	 * Inject JavaScript to add disclaimer when CFW coupon toggle is opened
	 */
	function inject_coupon_toggle_disclaimer_script(){
		if ( ! is_checkout() || is_order_received_page() ) return;
		?>
		<script>
		jQuery(function($){
			var disclaimerText = 'If a coupon code is not successfully applied during checkout—it cannot be applied retroactively or used for future orders. Please ensure the discount is reflected in your order\'s Total before completing your payment.';
			
			console.log('CFW Coupon Disclaimer Script Loaded');
			
			function addDisclaimerToCFWCoupon() {
				// Target the specific CFW coupon structure
				var couponWrapper = $('#cfw-cart-summary-coupons .wrapper');
				
				if (couponWrapper.length && !couponWrapper.find('.coupon-code-disclaimer').length) {
					var disclaimer = '<div class="coupon-code-disclaimer">' + disclaimerText + '</div>';
					
					// Add disclaimer at the end of the wrapper, after the promo row
					couponWrapper.append(disclaimer);
					console.log('CFW Disclaimer added to coupon wrapper');
				}
			}
			
			// Monitor clicks on the specific CFW show coupons link
			$(document).on('click', '.cfw-show-coupons-module', function(e) {
				console.log('CFW coupon toggle clicked');
				// Add small delay for animation to complete
				setTimeout(addDisclaimerToCFWCoupon, 300);
			});
			
			// Monitor for height changes on the slide-toggle div
			if (typeof MutationObserver !== 'undefined') {
				var observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
							var target = $(mutation.target);
							
							// Check if this is the slide-toggle div with height change
							if (target.parent().hasClass('slide-toggle') && target.css('height') !== '0px') {
								console.log('CFW coupon area expanded - height:', target.css('height'));
								setTimeout(addDisclaimerToCFWCoupon, 100);
							}
						}
					});
				});
				
				// Observe the specific CFW coupon area
				var cfwCouponArea = document.getElementById('cfw-cart-summary-coupons');
				if (cfwCouponArea) {
					observer.observe(cfwCouponArea, {
						childList: true,
						subtree: true,
						attributes: true,
						attributeFilter: ['style']
					});
					console.log('CFW Coupon MutationObserver initialized');
				}
			}
			
			// Fallback: Check every 2 seconds if coupon area is open
			setInterval(function() {
				var slideToggleDiv = $('#cfw-cart-summary-coupons .slide-toggle > div');
				if (slideToggleDiv.length && slideToggleDiv.css('height') !== '0px') {
					addDisclaimerToCFWCoupon();
				}
			}, 2000);
		});
		</script>
		<style>
			.coupon-code-disclaimer {
				font-size: 12px;
				padding: 1rem 1rem 1rem 3rem;
				font-style: italic;
				position: relative;
				line-height: 1.125rem;
				border-left: 5px solid #1b254f;
				background: #ede9ff;
				color: #666;
			}

			.coupon-code-disclaimer:before {
				content: "⚠️";
				position: absolute;
				font-style: normal;
				left: 1rem;
				font-size: 1rem;
			}
			</style>
		<?php
	}

	function add_custom_style_on_order_received($endpoint) {

		if (!is_wc_endpoint_url('order-received')) {
			return;
		}

		?>
		<style>
			body.woocommerce-order-received .page-header .entry-title{display:none;}
			body.woocommerce-order-received .woocommerce-order{display:none}
			body.woocommerce-order-received .page-content{min-height:50vh;}
			body.woocommerce-order-received .page-content{position:relative;display:flex;gap: 1rem;flex-direction:column;align-items: center;justify-content: center;text-align:center;}
			body.woocommerce-order-received.logged-in .page-content:before {
				content:"Preparing your order. Please don't close this window...";
			}
			body.woocommerce-order-received.logged-in .page-content:after {
				content:"";
				width: 40px;
				height: 40px;
				border: 4px solid #ddd;
				border-top-color: #333;
				border-radius: 50%;	
				animation: telemdnow-spin 1s linear infinite;
			}
			@keyframes telemdnow-spin {
				to {
					transform: rotate(360deg);
				}
			}

		</style>
		<?php

	}

}

/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new BH_Checkout_UI();
});