<?php

namespace Objectiv\Plugins\Checkout\Managers;

use _WP_Dependency;
use Exception;
use Objectiv\Plugins\Checkout\Features\SideCart;
use function WordpressEnqueueChunksPlugin\get as cfwChunkedScriptsConfigGet;

/**
 * Manage stylesheet and script assets
 *
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Managers
 */
class AssetManager {
	protected $front;
	protected $version;
	protected $manifest;

	public function __construct() {
		$this->front = CFW_PATH_ASSETS;

		// Version extension
		$this->version = CFW_VERSION;

		$this->manifest = cfwChunkedScriptsConfigGet( 'manifest' );
	}

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'set_global_assets' ) );
		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'set_cfw_page_assets',
			),
			100
		); // Woo uses 6, divi uses 15, yith uses 25 - so this is after those
	}

	public static function enqueue_style( $handle, $replacement_handle = '' ) {
		$front    = CFW_PATH_ASSETS;
		$manifest = cfwChunkedScriptsConfigGet( 'manifest' );
		$src      = "{$front}/{$manifest['chunks'][$handle]['file']}";

		if ( empty( $replacement_handle ) ) {
			$replacement_handle = $handle;
		}

		// If handle doesn't start with cfw, add the prefix
		if ( strpos( $replacement_handle, 'cfw' ) !== 0 ) {
			$replacement_handle = "cfw-{$replacement_handle}";
		}

		// Load RTL CSS for side cart styles when in RTL mode
		if ( is_rtl() && $handle === 'side-cart-styles' ) {
			$src = str_replace( '.css', '-rtl.css', $src );
		}

		wp_enqueue_style( $replacement_handle, $src, array(), $manifest['chunks'][ $handle ]['hash'] ?? CFW_VERSION );
	}

	/**
	 * @throws Exception If the asset cannot be enqueued.
	 */
	public function set_global_assets() {
		// TODO: Move this into a feature class maybe
		if ( SettingsManager::instance()->get_setting( 'allow_checkout_cart_item_variation_changes' ) === 'yes' ) {
			wp_enqueue_script( 'wc-add-to-cart-variation' );
		}

		if ( is_cfw_page() ) {
			return;
		}

		if ( PlanManager::can_access_feature( 'enable_side_cart', 'plus' ) ) {
			cfw_register_scripts( array( 'side-cart' ) );
			self::enqueue_style( 'cfw-grid' );
			self::enqueue_style( 'side-cart-styles' );

			wp_enqueue_script( 'cfw-side-cart' );

			wp_localize_script(
				'cfw-side-cart',
				'cfw',
				$this->get_side_cart_event_object()
			);

			$this->set_api_info_object( 'cfw-side-cart' );
		}
	}

	public function set_api_info_object( string $handle ) {
		wp_localize_script(
			$handle,
			'wpApiSettings',
			array(
				'root'  => esc_url_raw( rest_url() ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * @throws Exception If the asset cannot be enqueued.
	 */
	public function set_cfw_page_assets() {
		if ( ! is_cfw_page() ) {
			return;
		}

		/**
		 * Dequeue Native Scripts
		 */
		// Many plugins enqueue their scripts with 'woocommerce' and 'wc-checkout' as a dependent scripts
		// So, instead of modifying these scripts we dequeue WC's native scripts and then
		// queue our own scripts using the same handles. Magic!

		// Don't load our scripts when the form has been replaced.
		// This works because WP won't let you replace registered scripts
		/** This filter is documented in templates/default/content.php */
		if ( apply_filters( 'cfw_replace_form', false ) === false ) { // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
			wp_dequeue_script( 'woocommerce' );
			wp_deregister_script( 'woocommerce' );
			wp_dequeue_script( 'wc-checkout' );
			wp_deregister_script( 'wc-checkout' );
			wp_dequeue_style( 'woocommerce-general' );
			wp_dequeue_style( 'woocommerce-layout' );
			wp_dequeue_style( 'woocommerce-blocktheme' ); // required for FSE themes
			wp_deregister_script( 'selectWoo' );
			wp_deregister_script( 'select2' );
		}

		/**
		 * Dequeue Emojis
		 */
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		self::enqueue_style( 'cfw-grid' );

		if ( cfw_is_checkout() ) {
			cfw_register_scripts( array( 'checkout' ) );

			wp_enqueue_script( 'woocommerce' );

			if ( isset( $this->manifest['chunks']['checkout-styles']['file'] ) ) {
				self::enqueue_style( 'checkout-styles', 'cfw_front' );
			}

			wp_dequeue_style( 'woocommerce-smallscreen' );
		} elseif ( is_checkout_pay_page() ) {
			cfw_register_scripts( array( 'order-pay' ) );

			wp_enqueue_script( 'woocommerce' );

			if ( isset( $this->manifest['chunks']['order-pay-styles']['file'] ) ) {
				self::enqueue_style( 'order-pay-styles', 'cfw_front' );
			}
		} elseif ( is_order_received_page() ) {
			cfw_register_scripts( array( 'thank-you' ) );

			wp_enqueue_script( 'woocommerce' );

			if ( isset( $this->manifest['chunks']['thank-you-styles']['file'] ) ) {
				self::enqueue_style( 'thank-you-styles', 'cfw_front' );
			}

			self::enqueue_style( 'fontawesome' );
		}

		/**
		 * Fires after script setup
		 *
		 * @since 5.0.0
		 */
		do_action( 'cfw_enqueue_scripts' );

		/**
		 * Fires to trigger Templates to load their assets
		 *
		 * @since 3.0.0
		 */
		do_action( 'cfw_load_template_assets' );

		$cfw_event_object = $this->get_checkout_event_object();

		if ( is_order_received_page() ) {
			$order = cfw_get_order_received_order();

			if ( $order ) {
				/**
				 * Filter thank you page map address
				 *
				 * @param array $address The address for the map
				 * @param \WC_Order $order The order
				 *
				 * @since 5.3.9
				 */
				$address = apply_filters( 'cfw_thank_you_page_map_address', $order->get_address( 'shipping' ), $order );

				// Remove name and company before generating the Google Maps URL.
				unset( $address['first_name'], $address['last_name'], $address['company'] );

				// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
				$address = cfw_apply_filters( 'woocommerce_shipping_address_map_url_parts', $address, $order );
				// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment

				$address = array_filter( $address );
				$address = implode( ', ', $address );

				$cfw_event_object['settings']['thank_you_shipping_address'] = $address;
			}
		}

		wp_localize_script(
			'woocommerce',
			'cfw',
			$cfw_event_object
		);

		$this->set_api_info_object( 'woocommerce' );

		// Some plugins (WooCommerce Square for example?) want to use wc_cart_fragments_params on the checkout page
		wp_localize_script(
			'woocommerce',
			'wc_cart_fragments_params',
			array(
				'ajax_url'    => WC()->ajax_url(),
				'wc_ajax_url' => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			)
		);

		// Used by our copy of address-i18n.js which is called AddressInternationalizationService
		wp_localize_script(
			'woocommerce',
			'wc_address_i18n_params',
			array(
				'locale'             => wp_json_encode( WC()->countries->get_country_locale() ),
				'locale_fields'      => wp_json_encode( WC()->countries->get_country_locale_field_selectors() ),
				'i18n_required_text' => esc_attr__( 'required', 'woocommerce' ),
				'i18n_optional_text' => esc_html__( 'optional', 'woocommerce' ),
			)
		);

		if ( cfw_is_checkout() || cfw_is_checkout_pay_page() ) {
			global $wp_scripts;
			$wp_scripts->registered['wc-country-select']->deps = array( 'jquery' );

			$this->enqueue_selectWoo();

			wp_enqueue_script( 'wc-country-select' );
			wp_dequeue_script( 'wc-address-i18n' );
			$this->nuke_script( 'wc-address-i18n' );
		}
	}

	public function enqueue_selectWoo() {
		cfw_register_scripts( array( 'selectwoo' ) );
		wp_enqueue_script( 'selectWoo' );

		$this->nuke_script( 'select2' );
		$this->nuke_script( 'selectWoo_js' ); // Coderockz what are you doing?

		// Make sure that we don't load the same script twice
		// TODO: Whu would this be necessary after we nuke it?
		if ( wp_script_is( 'select2', 'enqueued' ) ) {
			wp_dequeue_script( 'selectWoo' );
		}

		if ( isset( $this->manifest['chunks']['selectwoo-styles']['file'] ) ) {
			self::enqueue_style( 'selectwoo-styles', 'select2' );
		}
	}

	public function nuke_script( $handle_to_be_nuked ): bool {
		$wp_scripts = wp_scripts();
		$result     = false;

		/**
		 * Go through scripts and unregister if they are dependencies of the script we want to nuke
		 *
		 * @var string $handle
		 * @var _WP_Dependency $script
		 */
		foreach ( $wp_scripts->registered as $handle => $script ) {
			$key = array_search( $handle_to_be_nuked, $script->deps, true );

			if ( false !== $key ) {
				unset( $wp_scripts->registered[ $handle ]->deps[ $key ] );
				$result = true;
			}
		}

		$key = array_search( $handle_to_be_nuked, $wp_scripts->queue, true );

		if ( false !== $key ) {
			unset( $wp_scripts->queue[ $key ] );
			$result = true;
		}

		return $result;
	}

	public function get_locale_prefix() {
		$raw_locale = determine_locale();

		$locale = defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : strstr( $raw_locale, '_', true );

		/**
		 * Filter locale prefix
		 *
		 * @param string $locale Locale prefix
		 *
		 * @since 9.1.5
		 */
		return apply_filters( 'cfw_locale_prefix', $locale );
	}

	public function get_parsley_locale() {
		$raw_locale = determine_locale();

		// Handle special raw locale cases
		switch ( $raw_locale ) {
			case 'pt_BR':
				$locale = 'pt-br';
				break;
			case 'pt_PT':
			case 'pt_AO':
				$locale = 'pt-pt';
				break;
			default:
				$locale = defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : strstr( $raw_locale, '_', true );
		}

		// Handle special locale cases
		switch ( $locale ) {
			case 'nb':
			case 'nn':
				$locale = 'no';
		}

		// Fallback to the raw locale
		if ( ! $locale ) {
			$locale = $raw_locale;
		}

		/**
		 * Filter Parsley validation service locale
		 *
		 * @param string $locale Parsley validation service locale
		 *
		 * @since 3.0.0
		 */
		return apply_filters( 'cfw_parsley_locale', $locale );
	}

	/**
	 * Returns data common to all event objects.
	 */
	protected function get_default_event_data(): array {
		// Moved duplicated country logic here.
		$shipping_countries = WC()->countries->get_shipping_countries();
		unset( $shipping_countries['shim'] );

		$allowed_countries = WC()->countries->get_allowed_countries();
		unset( $allowed_countries['shim'] );

		$max_bumps = SettingsManager::instance()->get_setting( 'max_bumps' );

		return array(
			'settings' => array(
				'user_logged_in'                   => is_user_logged_in(),
				'shipping_countries'               => $shipping_countries,
				'allowed_countries'                => $allowed_countries,
				/**
				 * Filter whether to disable cart quantity prompt
				 *
				 * @param bool $disable_cart_quantity_prompt Disable cart quantity prompt
				 *
				 * @since 8.2.19
				 */
				'disable_cart_quantity_prompt'     => apply_filters( 'cfw_disable_cart_quantity_prompt', false ),
				/**
				 * Filters whether to link cart items to products
				 *
				 * @param bool $link_cart_items Link cart items to products
				 *
				 * @since 1.0.0
				 */
				'link_items'                       => apply_filters( 'cfw_link_cart_items', SettingsManager::instance()->get_setting( 'cart_item_link' ) === 'enabled' ),
				'cart_item_link_target_new_window' => SettingsManager::instance()->get_setting( 'cart_item_link_target_new_window' ) === 'yes',
				'show_item_remove_button'          => PlanManager::can_access_feature( 'show_item_remove_button' ),
				/**
				 * Filters whether to show cart item discount on cart item
				 *
				 * @param bool $show_cart_item_discount Show cart item discount on cart item
				 *
				 * @since 2.0.0
				 */
				'show_item_discount'               => apply_filters( 'cfw_show_cart_item_discount', SettingsManager::instance()->get_setting( 'show_side_cart_item_discount' ) === 'yes' ),
				'max_bumps'                        => $max_bumps < 0 ? 999 : $max_bumps,
				'coupons_enabled'                  => wc_coupons_enabled(),
				'show_free_shipping_progress_bar_without_calculated_packages' => apply_filters( 'cfw_show_free_shipping_progress_bar_without_calculated_packages', false ),
			),
			'messages' => array(
				/**
				 * Filters promo code button label
				 *
				 * @param string $promo_code_button_label Promo code button label
				 *
				 * @since 3.0.0
				 */
				'promo_code_button_label'     => apply_filters( 'cfw_promo_code_apply_button_label', esc_attr__( 'Apply', 'checkout-wc' ) ),

				/**
				 * Filters promo code toggle link text
				 *
				 * @param string $promo_code_toggle_link_text Filters promo code toggle link text
				 *
				 * @since 3.0.0
				 */
				'promo_code_toggle_link_text' => apply_filters( 'cfw_promo_code_toggle_link_text', __( 'Have a promo code? Click here.', 'checkout-wc' ) ),

				/**
				 * Filters promo code label
				 *
				 * @param string $promo_code_label Promo code label
				 *
				 * @since 3.0.0
				 */
				'promo_code_label'            => apply_filters( 'cfw_promo_code_label', __( 'Promo Code', 'checkout-wc' ) ),

				/**
				 * Filters promo code placeholder
				 *
				 * @param string $promo_code_placeholder Promo code placeholder
				 *
				 * @since 3.0.0
				 */
				'promo_code_placeholder'      => apply_filters( 'cfw_promo_code_placeholder', __( 'Enter Promo Code', 'checkout-wc' ) ),
			),
		);
	}

	/**
	 * @throws Exception If the event object cannot be created.
	 */
	protected function get_checkout_event_object(): array {
		$max_after_checkout_bumps = SettingsManager::instance()->get_setting( 'max_after_checkout_bumps' );

		// Store policies data massage
		$store_policies = PlanManager::has_premium_plan_or_higher() ? cfw_get_setting( 'store_policies', null, array() ) : array();
		$store_policies = is_array( $store_policies ) ? $store_policies : array(); // abundance of caution
		$store_policies = array_filter(
			$store_policies,
			function ( $item ) {
				if ( is_object( $item ) ) {
					$item = (array) $item; // prevent fatal error
				}

				return isset( $item['page'] );
			}
		);
		$store_policies = array_values( $store_policies );

		/** This filter is documented in includes/AddressFieldsAugmenter.php */
		$enable_separate_address_1_fields = apply_filters( 'cfw_enable_separate_address_1_fields', 'yes' === SettingsManager::instance()->get_setting( 'enable_discreet_address_1_fields' ) );  // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
		$enable_separate_address_1_fields = apply_filters_deprecated( 'cfw_enable_discrete_address_1_fields', array( $enable_separate_address_1_fields ), '10.0.0', 'cfw_enable_separate_address_1_fields' );

		/**
		 * Filter cfw_event_object array
		 *
		 * Localized data available via DataService
		 *
		 * @param array $cfw_event_object The data
		 *
		 * @since 1.0.0
		 */
		$cfw_event_object = apply_filters(
			'cfw_event_object',
			array(
				'data'            => array_merge_recursive(
					self::get_data(),
					array(
						'login_form'         => cfw_get_login_form_html(),
						'lost_password_form' => cfw_get_lost_password_form_html(),
					)
				),
				/**
				 * Filter TypeScript compatibility classes and params
				 *
				 * @param array $compatibility TypeScript compatibility classes and params
				 *
				 * @since 3.0.0
				 */
				'compatibility'   => apply_filters( 'cfw_typescript_compatibility_classes_and_params', array() ),
				'settings'        => array(
					'base_country'                      => WC()->countries->get_base_country(),
					'locale_prefix'                     => $this->get_locale_prefix(),
					'parsley_locale'                    => $this->get_parsley_locale(),
					'login_allowed_at_checkout'         => cfw_is_login_at_checkout_allowed(),
					/**
					 * Filter whether to validate required registration
					 *
					 * @param bool $validate_required_registration Validate required registration
					 *
					 * @since 3.0.0
					 */
					'validate_required_registration'    => apply_filters( 'cfw_validate_required_registration', true ),
					'default_address_fields'            => array_keys( WC()->countries->get_default_address_fields() ),
					/**
					 * Filter whether to enable zip autocomplete
					 *
					 * @param bool $enable_zip_autocomplete Enable zip autocomplete
					 *
					 * @since 2.0.0
					 */
					'enable_zip_autocomplete'           => apply_filters( 'cfw_enable_zip_autocomplete', true ) && defined( 'CFW_PREMIUM_PLAN_IDS' ),
					/**
					 * Filter whether to disable email domain validation
					 *
					 * @param bool $disable_email_domain_validation Disable email domain validation
					 *
					 * @since 8.2.26
					 */
					'disable_email_domain_validation'   => (bool) apply_filters( 'cfw_disable_email_domain_validation', false ),
					/**
					 * Filter whether to enable field peristence with Garlic.js
					 *
					 * @param bool $cfw_enable_field_persistence Enable field persistence
					 *
					 * @since 7.1.10
					 */
					'enable_field_persistence'          => (bool) apply_filters( 'cfw_enable_field_persistence', true ),
					/**
					 * Filter whether to check create account by default
					 *
					 * @param bool $check_create_account_by_default Check create account by default
					 *
					 * @since 3.0.0
					 */
					'check_create_account_by_default'   => (bool) apply_filters( 'cfw_check_create_account_by_default', true ),
					/**
					 * Filter whether to check whether an existing account matches provided email address
					 *
					 * @param bool $enable_account_exists_check Enable account exists check when billing email field changed
					 *
					 * @since 5.3.7
					 */
					'enable_account_exists_check'       => apply_filters( 'cfw_enable_account_exists_check', ! is_user_logged_in() ),
					'needs_shipping_address'            => WC()->cart && WC()->cart->needs_shipping_address(),
					'show_shipping_tab'                 => cfw_show_shipping_tab(),
					'enable_map_embed'                  => PlanManager::can_access_feature( 'enable_map_embed' ),
					'disable_auto_open_login_modal'     => SettingsManager::instance()->get_setting( 'disable_auto_open_login_modal' ) === 'yes',
					'disable_domain_autocomplete'       => SettingsManager::instance()->get_setting( 'disable_domain_autocomplete' ) === 'yes',
					'enable_coupon_code_link'           => SettingsManager::instance()->get_setting( 'enable_coupon_code_link' ) === 'yes',
					/**
					 * Filter whether to load tabs
					 *
					 * @param bool $load_tabs Load tabs
					 *
					 * @since 3.0.0
					 */
					'load_tabs'                         => apply_filters( 'cfw_load_tabs', cfw_is_checkout() ),
					'is_checkout_pay_page'              => is_checkout_pay_page(),
					'is_order_received_page'            => is_order_received_page(),
					/**
					 * Filter list of billing country restrictions for Google Maps address autocomplete
					 *
					 * @param array $address_autocomplete_billing_countries List of country restrictions for Google Maps address autocomplete
					 *
					 * @since 3.0.0
					 */
					'address_autocomplete_billing_countries' => apply_filters( 'cfw_address_autocomplete_billing_countries', array() ),
					'is_registration_required'          => WC()->checkout()->is_registration_required(),
					/**
					 * Filter whether to automatically generate password for new accounts
					 *
					 * @param bool $registration_generate_password Automatically generate password for new accounts
					 *
					 * @since 3.0.0
					 */
					'registration_generate_password'    => SettingsManager::instance()->get_setting( 'registration_style' ) !== 'woocommerce',
					'thank_you_shipping_address'        => false,
					'enable_separate_address_1_fields'  => $enable_separate_address_1_fields,
					/**
					 * Filters whether to enable fullname field
					 *
					 * @param boolean $enable_fullname_field Whether to enable fullname field
					 *
					 * @since 7.0.17
					 */
					'use_fullname_field'                => apply_filters( 'cfw_enable_fullname_field', 'yes' === SettingsManager::instance()->get_setting( 'use_fullname_field' ) ),
					'trust_badges_display'              => SettingsManager::instance()->get_setting( 'trust_badge_position' ),
					'enable_one_page_checkout'          => SettingsManager::instance()->get_setting( 'enable_one_page_checkout' ) === 'yes',
					/**
					 * Filter intl-tel-input preferred countries
					 *
					 * @param array $phone_field_preferred_countries List of preferred countries
					 *
					 * @since 8.2.22
					 */
					'phone_field_highlighted_countries' => (array) apply_filters( 'cfw_phone_field_highlighted_countries', SettingsManager::instance()->get_setting( 'enable_highlighted_countries' ) === 'yes' ? SettingsManager::instance()->get_setting( 'highlighted_countries' ) : array() ),
					'store_policies'                    => $store_policies,
					'ship_to_billing_address_only'      => wc_ship_to_billing_address_only(),
					'max_after_checkout_bumps'          => $max_after_checkout_bumps < 0 ? 999 : $max_after_checkout_bumps,
					'enable_acr'                        => PlanManager::can_access_feature( 'enable_acr' ),
					/**
					 * Bypass cookie for automatically showing login modal
					 *
					 * @param bool $bypass_login_modal_shown_cookie Bypass cookie for automatically showing login modal (default: false, do not bypass)
					 *
					 * @since 9.0.16
					 */
					'bypass_login_modal_shown_cookie'   => apply_filters( 'cfw_bypass_login_modal_shown_cookie', false ),
					'is_login_at_checkout_allowed'      => cfw_is_login_at_checkout_allowed(),
					'google_maps_api_key'               => SettingsManager::instance()->get_setting( 'google_places_api_key' ),
					/**
					 * Filter list of field persistence service excludes
					 *
					 * @param array $field_persistence_excludes List of field persistence service excludes
					 *
					 * @since 3.0.0
					 */
					'field_persistence_excludes'        => apply_filters(
						'cfw_field_data_persistence_excludes',
						array(
							'input[type="button"]',
							'input[type="file"]',
							'input[type="hidden"]',
							'input[type="submit"]',
							'input[type="reset"]',
							'#cfw-promo-code',
							'.cfw-create-account-checkbox',
							'input[name="payment_method"]',
							'input[name="paypal_pro-card-number"]',
							'input[name="paypal_pro-card-cvc"]',
							'input[name="wc-authorize-net-aim-account-number"]',
							'input[name="wc-authorize-net-aim-csc"]',
							'input[name="paypal_pro_payflow-card-number"]',
							'input[name="paypal_pro_payflow-card-cvc"]',
							'input[name="paytrace-card-number"]',
							'input[name="paytrace-card-cvc"]',
							'input[id="stripe-card-number"]',
							'input[id="stripe-card-cvc"]',
							'input[name="creditCard"]',
							'input[name="cvv"]',
							'input.wc-credit-card-form-card-number',
							'input[name="wc-authorize-net-cim-credit-card-account-number"]',
							'input[name="wc-authorize-net-cim-credit-card-csc"]',
							'input.wc-credit-card-form-card-cvc',
							'input.js-sv-wc-payment-gateway-credit-card-form-account-number',
							'input.js-sv-wc-payment-gateway-credit-card-form-csc',
							'.wc-braintree-payment-type', // payment plugins braintree
							'input.shipping_method',
							'#order_comments',
							'input[name^="tocheckoutcw"]',
							'#_sumo_pp_enable_order_payment_plan',
							'.gift-certificate-show-form input',
							'.cfw_order_bump_check',
							'#shipping_fetchify_search',
							'#billing_fetchify_search',
							'#terms',
							'#ship-to-different-address-checkbox',
							'[data-persist="false"]', // catch-all, used in cfw_form_field() for non-empty values
						)
					),
				),
				'messages'        => array(
					/**
					 * Filter the invalid phone number error message
					 *
					 * @param string $invalid_phone_number_message Invalid phone number error message
					 *
					 * @since 5.3.5
					 */
					'invalid_phone_message'             => apply_filters( 'cfw_invalid_phone_validation_error_message', __( 'Please enter a valid phone number.', 'checkout-wc' ) ),

					/**
					 * Filter the invalid fullname error message
					 *
					 * @param string $invalid_fullname_message Invalid fullname error message
					 *
					 * @since 6.2.4
					 */
					'invalid_full_name_message'         => apply_filters( 'cfw_invalid_full_name_validation_error_message', __( 'Please enter your first and last name.', 'checkout-wc' ) ),
					'invalid_name_email_message'        => apply_filters( 'cfw_invalid_name_email_validation_error_message', __( 'Please enter a name, not an email address.', 'checkout-wc' ) ),
					'shipping_address_label'            => __( 'Shipping address', 'checkout-wc' ),
					'quantity_prompt_message'           => __( 'Please enter a new quantity:', 'checkout-wc' ),
					'cvv_tooltip_message'               => __( '3-digit security code usually found on the back of your card. American Express cards have a 4-digit code located on the front.', 'checkout-wc' ),
					'delete_confirm_message'            => __( 'Are you sure you want to remove this item from your cart?', 'checkout-wc' ),
					'account_already_registered_notice' => cfw_apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. <a href="#" class="showlogin">Please log in.</a>', 'woocommerce' ), '' ),
					/* translators: %s: Field name */
					'generic_field_validation_error_message' => __( '%s is a required field.', 'woocommerce' ),
					'update_checkout_error'             => __( 'There was a problem checking out. Please try again. If the problem persists, please get in touch with us so we can assist.', 'woocommerce' ),
					'invalid_postcode'                  => __( 'Please enter a valid postcode / ZIP.', 'checkout-wc' ),
					'pickup_label'                      => __( 'Pickup', 'checkout-wc' ),
					'pickup_btn_label'                  => __( 'Continue to pickup', 'checkout-wc' ),
					'update_cart_item_variation_button' => __( 'Update', 'woocommerce' ),
					'ok_button_label'                   => __( 'Add to cart', 'woocommerce' ),
					'cancel_button_label'               => __( 'Cancel', 'woocommerce' ),
					/**
					 * Filter the fetchify search placeholder
					 *
					 * @param string $fetchify_default_placeholder Fetchify search placeholder
					 *
					 * @since 8.2.3
					 */
					'fetchify_default_placeholder'      => apply_filters( 'cfw_fetchify_search_placeholder', __( 'Start with post/zip code or street', 'checkout-wc' ) ),
					/**
					 * Filter the shipping methods heading
					 *
					 * @param string $shipping_methods_heading Shipping methods heading
					 *
					 * @since 9.0.0
					 */
					'shipping_methods_heading'          => apply_filters( 'cfw_shipping_method_heading', esc_html__( 'Shipping method', 'checkout-wc' ) ),
					'edit_cart_variation_label'         => __( 'Edit', 'woocommerce' ),
				),
				'checkout_params' => array(
					'ajax_url'                  => WC()->ajax_url(),
					'wc_ajax_url'               => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
					'update_order_review_nonce' => wp_create_nonce( 'update-order-review' ),
					'apply_coupon_nonce'        => wp_create_nonce( 'apply-coupon' ),
					'remove_coupon_nonce'       => wp_create_nonce( 'remove-coupon' ),
					'option_guest_checkout'     => get_option( 'woocommerce_enable_guest_checkout' ),
					'checkout_url'              => \WC_AJAX::get_endpoint( 'checkout' ),
					'is_checkout'               => is_checkout() && empty( $wp->query_vars['order-pay'] ) && ! isset( $wp->query_vars['order-received'] ) ? 1 : 0,
					'debug_mode'                => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'cfw_debug_mode'            => isset( $_GET['cfw-debug'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'i18n_checkout_error'       => esc_attr__( 'Error processing checkout. Please try again.', 'woocommerce' ),
					'dist_path'                 => CFW_PATH_ASSETS,
					'is_rtl'                    => is_rtl(),
					'cart_hash_key'             => cfw_apply_filters( 'woocommerce_cart_hash_key', 'wc_cart_hash_' . md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() ) ),
				),
				'runtime_params'  => array(
					'runtime_email_matched_user' => false, // default to false
				),
			)
		);

		// Do this because of the order of replacement
		// Otherwise the default data overwrites the real data
		$side_cart_data = PlanManager::can_access_feature( 'enable_side_cart', 'plus' ) ? $this->get_side_cart_event_object() : array();
		unset( $side_cart_data['data'] );

		return array_replace_recursive( self::get_default_event_data(), $cfw_event_object, $side_cart_data );
	}

	/**
	 * @throws Exception The exception.
	 */
	protected function get_side_cart_event_object(): array {
		$suggested_products_heading = SettingsManager::instance()->get_setting( 'side_cart_suggested_products_heading' );

		if ( empty( $suggested_products_heading ) ) {
			$suggested_products_heading = __( 'You may also like&hellip;', 'woocommerce' );
		}

		return array_merge_recursive(
			self::get_default_event_data(),
			/**
			 * Filter cfw_event_object array
			 *
			 * Localized data available via DataService
			 *
			 * @param array $cfw_event_object The data
			 *
			 * @since 1.0.0
			 */
			apply_filters(
				'cfw_side_cart_event_object',
				array(
					'data'            => self::get_default_data(),
					'settings'        => array(
						/**
						 * Filter whether to auto open the side cart on add to cart
						 *
						 * @param bool $disable_side_cart_auto_open Disable side cart auto open
						 *
						 * @since 7.1.5
						 */
						'disable_side_cart_auto_open'      => (bool) apply_filters( 'cfw_disable_side_cart_auto_open', SettingsManager::instance()->get_setting( 'shake_floating_cart_button' ) === 'yes' ),
						'enable_floating_cart_button'      => SettingsManager::instance()->get_setting( 'enable_floating_cart_button' ) === 'yes',
						'enable_side_cart_suggested_products' => SettingsManager::instance()->get_setting( 'enable_side_cart_suggested_products' ) === 'yes',

						/**
						 * Filter whether to automatically generate password for new accounts
						 *
						 * @param string $additional_side_cart_trigger_selectors CSS selector for additional side cart open buttons / links
						 *
						 * @since 5.4.0
						 */
						'additional_side_cart_trigger_selectors' => apply_filters( 'cfw_additional_side_cart_trigger_selectors', false ),
						'cart_icon_contents'               => SideCart::get_cart_icon_file_contents(),
						'coupons_enabled_side_cart'        => wc_coupons_enabled() && SettingsManager::instance()->get_setting( 'enable_promo_codes_on_side_cart' ) === 'yes',
						/**
						 * Filters whether to enable continue shopping button in side cart
						 *
						 * @param bool $enable_continue_shopping_btn Whether to enable continue shopping button in side cart
						 *
						 * @since 7.7.0
						 */
						'enable_continue_shopping_btn'     => apply_filters( 'cfw_side_cart_enable_continue_shopping_button', SettingsManager::instance()->get_setting( 'enable_side_cart_continue_shopping_button' ) === 'yes' ),
						'enable_side_cart_payment_buttons' => SettingsManager::instance()->get_setting( 'enable_side_cart_payment_buttons' ) === 'yes',

						/**
						 * Filters whether to show shipping and tax totals in side cart
						 *
						 * @param bool $show_total Whether to show shipping and tax totals in side cart
						 *
						 * @since 7.7.0
						 */
						'side_cart_show_total'             => apply_filters( 'cfw_side_cart_show_total', SettingsManager::instance()->get_setting( 'enable_side_cart_totals' ) === 'yes' ),
						'wc_get_pay_buttons'               => cfw_get_function_output( 'wc_get_pay_buttons' ),
						'enable_free_shipping_progress_bar' => SettingsManager::instance()->get_setting( 'enable_free_shipping_progress_bar' ) === 'yes',
						'suggested_products_heading'       => $suggested_products_heading,
						'side_cart_suggested_products_link_to_product' => SettingsManager::instance()->get_setting( 'side_cart_suggested_products_link_to_product' ) === 'yes',
						'enable_ajax_add_to_cart'          => SettingsManager::instance()->get_setting( 'enable_ajax_add_to_cart' ) === 'yes',
						'checkout_page_url'                => wc_get_checkout_url(),
						'enable_free_shipping_progress_bar_at_checkout' => SettingsManager::instance()->get_setting( 'enable_free_shipping_progress_bar_at_checkout' ) === 'yes',
						'enable_promo_codes_on_side_cart'  => SettingsManager::instance()->get_setting( 'enable_promo_codes_on_side_cart' ) === 'yes',
						'hide_floating_cart_button_empty_cart' => SettingsManager::instance()->get_setting( 'hide_floating_cart_button_empty_cart' ) === 'yes',
						'enable_accessibility_improvements' => function_exists( 'cfw_enable_accessibility_improvements' ) ? \cfw_enable_accessibility_improvements() : (bool) apply_filters( 'cfw_enable_accessibility_improvements', false ),
						'enable_side_cart_coupon_code_link' => SettingsManager::instance()->get_setting( 'enable_side_cart_coupon_code_link' ) === 'yes',
						'enable_order_bumps'               => SettingsManager::instance()->get_setting( 'enable_order_bumps' ) === 'yes',
						'enable_order_bumps_on_side_cart'  => SettingsManager::instance()->get_setting( 'enable_order_bumps_on_side_cart' ) === 'yes',
					),
					'messages'        => array(
						'quantity_prompt_message'   => __( 'Please enter a new quantity:', 'checkout-wc' ),
						'delete_confirm_message'    => __( 'Are you sure you want to remove this item from your cart?', 'checkout-wc' ),
						'view_cart'                 => __( 'View cart', 'woocommerce' ),
						'update_cart_item_variation_button' => __( 'Update', 'woocommerce' ),
						'ok_button_label'           => __( 'Add to cart', 'woocommerce' ),
						'cancel_button_label'       => __( 'Cancel', 'woocommerce' ),
						'remove_item_label'         => __( 'Remove %s from cart', 'checkout-wc' ),
						'proceed_to_checkout_label' => __( 'Proceed to checkout', 'woocommerce' ),
						'continue_shopping_label'   => __( 'Continue shopping', 'woocommerce' ),
						'edit_cart_variation_label' => __( 'Edit', 'woocommerce' ),
					),
					'checkout_params' => array(
						'ajax_url'            => WC()->ajax_url(),
						'wc_ajax_url'         => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
						'remove_coupon_nonce' => wp_create_nonce( 'remove-coupon' ),
						'checkout_url'        => \WC_AJAX::get_endpoint( 'checkout' ),
						'is_checkout'         => is_checkout() && empty( $wp->query_vars['order-pay'] ) && ! isset( $wp->query_vars['order-received'] ) ? 1 : 0,
						'debug_mode'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
						'cfw_debug_mode'      => isset( $_GET['cfw-debug'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'dist_path'           => CFW_PATH_ASSETS,
						'is_rtl'              => is_rtl(),

					),
					'runtime_params'  => array(),
				)
			)
		);
	}

	/**
	 * @throws Exception If the data cannot be gathered.
	 */
	public static function get_data(): array {
		/**
		 * Fires before gathering data
		 *
		 * @since 9.0.38
		 */
		do_action( 'cfw_before_get_data' );

		// required to work with Advanced Coupons
		// Ticket: https://secure.helpscout.net/conversation/3002667039/22184?viewId=8492330
		WC()->cart->calculate_totals();
		cfw_do_action( 'woocommerce_check_cart_items' );

		$data = array(
			'cart'         => array(
				'isEmpty'       => WC()->cart && WC()->cart->is_empty(),
				'needsPayment'  => WC()->cart && WC()->cart->needs_payment(),
				'items'         => cfw_get_cart_items_data(),
				'actions'       => cfw_get_cart_actions_data(),
				'staticActions' => cfw_get_cart_static_actions_data(),
				/**
				 * Filters whether to clear notices when gathering data
				 *
				 * @param bool $clear_notices Clear notices
				 * @since 9.0.36
				 */
				'notices'       => cfw_get_function_output( 'cfw_wc_print_notices', apply_filters( 'cfw_get_data_clear_notices', ! is_checkout() ) ),
				'shipping'      => cfw_get_cart_shipping_data(),
			),
			'bumps'        => array(), // placeholder to prevent errors
			'trust_badges' => PlanManager::can_access_feature( 'enable_trust_badges' ) ? array_values( cfw_get_trust_badges() ) : array(),
			'review'       => cfw_get_review_data(),
		);

		/**
		 * Always grab totals last
		 *
		 * This is necessary because the totals can change when shipping data
		 * is gathered if the chosen method is no longer valid
		 */
		$data['cart']['totals'] = cfw_get_cart_totals_data();

		/**
		 * Filter extra data available to JavaScript
		 *
		 * Merge with default data. Even though we load this by default for non-checkout pages, we have to do this
		 * so that the data gets the default action values during AJAX updates
		 *
		 * @param array $data The checkout data
		 *
		 * @since 8.0.0
		 */
		return array_replace_recursive( self::get_default_data(), apply_filters( 'cfw_checkout_data', $data ) );
	}

	public static function get_default_data(): array {
		return array(
			'cart'         => array(
				'isEmpty'       => WC()->cart && WC()->cart->is_empty(),
				'needsPayment'  => false,
				'items'         => array(),
				'actions'       => array(),
				'staticActions' => array(
					'woocommerce_cart_is_empty'          => ( ! is_checkout() && WC()->cart && WC()->cart->is_empty() ) ? cfw_get_action_output( 'woocommerce_cart_is_empty' ) : '',

					/**
					 * Fires when CheckoutWC side cart is empty
					 *
					 * @since 7.0.0
					 */
					'checkoutwc_empty_side_cart_content' => WC()->cart && WC()->cart->is_empty() ? cfw_get_action_output( 'checkoutwc_empty_side_cart_content' ) : '',
				),
				'notices'       => array(),
				'shipping'      => array(),
				'totals'        => array(
					'actions'  => array(),
					'subtotal' => array(
						'label' => '',
						'value' => '',
					),
					'total'    => array(
						'label' => '',
						'value' => '',
					),
					'coupons'  => array(),
					'fees'     => array(),
					'taxes'    => array(),
					'quantity' => 0,
				),
			),
			'bumps'        => array(),
			'trust_badges' => array(),
			'side_cart'    => array(
				'free_shipping_progress_bar' => array(
					'has_free_shipping'        => false,
					'amount_remaining'         => 0,
					'fill_percentage'          => 0,
					'free_shipping_message'    => '',
					'amount_remaining_message' => '',
				),
				'suggested_products'         => array(),
			),
		);
	}
}
