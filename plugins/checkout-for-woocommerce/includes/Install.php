<?php

namespace Objectiv\Plugins\Checkout;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use WP_Roles;

class Install {
	public function __construct() {}

	public function init() {
		self::add_capabilities();
		self::add_settings();
	}

	public static function add_capabilities() {
		global $wp_roles;

		if ( ! class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$capabilities = array(
			'cfw_manage_order_bumps',
			'cfw_view_acr_reports',
			'cfw_manage_pages',
			'cfw_manage_trust_badges',
			'cfw_manage_side_cart',
			'cfw_manage_local_pickup',
			'cfw_manage_ab_tests',
			'cfw_manage_integrations',
			'cfw_manage_general',
			'cfw_manage_express_checkout',
			'cfw_manage_appearance',
			'cfw_manage_advanced',
			'cfw_export_settings',
			'cfw_import_settings',
			'cfw_manage_acr',
			'cfw_manage_options', // required to modify settings
		);

		foreach ( $capabilities as $capability ) {
			$wp_roles->add_cap( 'administrator', $capability );
		}

		SettingsManager::instance()->add_setting( 'capabilities_loaded', true );
	}

	public static function add_settings() {
		$version = get_option( 'cfw_db_version', false );

		// For fresh installations only
		if ( ! $version ) {
			SettingsManager::instance()->add_setting( 'installed', gmdate( 'Y-m-d H:i:s' ) );
		}

		SettingsManager::instance()->add_setting( 'enable', 'no' );
		SettingsManager::instance()->add_setting( 'allow_tracking', '' );
		SettingsManager::instance()->add_setting( 'allow_uninstall', 'no' );
		SettingsManager::instance()->add_setting( 'login_style', 'enhanced' );
		SettingsManager::instance()->add_setting( 'registration_style', 'enhanced' );
		SettingsManager::instance()->add_setting( 'cart_item_link', 'disabled' );
		SettingsManager::instance()->add_setting( 'cart_item_link_target_new_window', 'no' );
		SettingsManager::instance()->add_setting( 'cart_item_data_display', 'short' );
		SettingsManager::instance()->add_setting( 'cart_summary_mobile_label', '' );
		SettingsManager::instance()->add_setting( 'skip_shipping_step', 'no' );
		SettingsManager::instance()->add_setting( 'disable_auto_open_login_modal', 'no' );
		SettingsManager::instance()->add_setting( 'discreet_address_1_fields_order', 'default' );
		SettingsManager::instance()->add_setting( 'enable_order_notes', 'no' );
		SettingsManager::instance()->add_setting( 'enable_debug_log', 'no' );
		SettingsManager::instance()->add_setting( 'enable_highlighted_countries', 'no' );
		SettingsManager::instance()->add_setting( 'active_template', 'groove' );
		SettingsManager::instance()->add_setting( 'template_loader', 'redirect' );
		SettingsManager::instance()->add_setting( 'show_logos_mobile', 'no' );
		SettingsManager::instance()->add_setting( 'show_mobile_coupon_field', 'no' );
		SettingsManager::instance()->add_setting( 'enable_mobile_cart_summary', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_mobile_totals', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_order_pay', 'no' );
		SettingsManager::instance()->add_setting( 'enable_thank_you_page', 'no' );
		SettingsManager::instance()->add_setting( 'thank_you_order_statuses', array() );
		SettingsManager::instance()->add_setting( 'enable_map_embed', 'no' );
		SettingsManager::instance()->add_setting( 'override_view_order_template', 'no' );
		SettingsManager::instance()->add_setting( 'google_places_api_key', '' );
		SettingsManager::instance()->add_setting( 'user_matching', 'enabled' );
		SettingsManager::instance()->add_setting( 'hide_optional_address_fields_behind_link', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_pickup_ship_option', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_pickup_method_step', 'no' );
		SettingsManager::instance()->add_setting( 'enable_pickup_shipping_method_other_regex', 'no' );
		SettingsManager::instance()->add_setting( 'enable_coupon_code_link', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_order_bumps', 'yes' );
		SettingsManager::instance()->add_setting( 'max_bumps', '10' );
		SettingsManager::instance()->add_setting( 'max_after_checkout_bumps', '1' );
		SettingsManager::instance()->add_setting( 'enable_ab_testing', 'no' );
		SettingsManager::instance()->add_setting( 'shake_floating_cart_button', 'no' );
		SettingsManager::instance()->add_setting( 'enable_side_cart_suggested_products', 'no' );
		SettingsManager::instance()->add_setting( 'enable_side_cart_suggested_products_random_fallback', 'no' );
		SettingsManager::instance()->add_setting( 'side_cart_suggested_products_link_to_product', 'no' );
		SettingsManager::instance()->add_setting( 'fetchify_access_token', '' );

		// Turnstile settings
		SettingsManager::instance()->add_setting( 'turnstile_enabled', 'no' );
		SettingsManager::instance()->add_setting( 'turnstile_site_key', '' );
		SettingsManager::instance()->add_setting( 'turnstile_secret_key', '' );
		SettingsManager::instance()->add_setting( 'turnstile_checkout_enabled', 'yes' );
		SettingsManager::instance()->add_setting( 'turnstile_order_pay_enabled', 'no' );
		SettingsManager::instance()->add_setting( 'turnstile_login_enabled', 'no' );
		SettingsManager::instance()->add_setting( 'turnstile_register_enabled', 'no' );
		SettingsManager::instance()->add_setting( 'turnstile_position', 'before_place_order' );
		SettingsManager::instance()->add_setting( 'turnstile_theme', 'light' );
		SettingsManager::instance()->add_setting( 'turnstile_size', 'normal' );
		SettingsManager::instance()->add_setting( 'turnstile_guest_only', 'no' );
		SettingsManager::instance()->add_setting( 'pickup_option_label', '' );
		SettingsManager::instance()->add_setting( 'pickup_ship_option_label', '' );
		SettingsManager::instance()->add_setting( 'pickup_shipping_method_other_label', '' );
		SettingsManager::instance()->add_setting( 'side_cart_custom_icon_attachment_id', 0 );
		SettingsManager::instance()->add_setting( 'force_different_billing_address', 'no' );
		SettingsManager::instance()->add_setting( 'skip_cart_step', 'no' );
		SettingsManager::instance()->add_setting( 'smartystreets_auth_id', '' );
		SettingsManager::instance()->add_setting( 'smartystreets_auth_token', '' );
		SettingsManager::instance()->add_setting( 'hide_admin_bar_button', 'no' );
		SettingsManager::instance()->add_setting( 'highlighted_countries', array() );
		SettingsManager::instance()->add_setting( 'international_phone_field_standard', 'raw' );
		SettingsManager::instance()->add_setting( 'enable_beta_version_updates', 'no' );
		SettingsManager::instance()->add_setting( 'show_item_remove_button', 'no' );
		SettingsManager::instance()->add_setting( 'enable_promo_codes_on_side_cart', 'no' );
		SettingsManager::instance()->add_setting( 'enable_side_cart_totals', 'no' );
		SettingsManager::instance()->add_setting( 'footer_text_editor_mode', 'WYSIWYG' );
		SettingsManager::instance()->add_setting( 'disable_domain_autocomplete', 'no' );
		SettingsManager::instance()->add_setting( 'auto_select_free_shipping_method', 'no' );
		SettingsManager::instance()->add_setting( 'show_cart_item_discount', 'yes' );
		SettingsManager::instance()->add_setting( 'acr_simulate_only', 'no' );
		SettingsManager::instance()->add_setting( 'pickup_methods', array() );
		SettingsManager::instance()->add_setting( 'hide_billing_address_for_free_orders', 'no' );
		SettingsManager::instance()->add_setting( 'header_scripts', '' );
		SettingsManager::instance()->add_setting( 'footer_scripts', '' );
		SettingsManager::instance()->add_setting( 'php_snippets', '' );
		SettingsManager::instance()->add_setting( 'header_scripts_checkout', '' );
		SettingsManager::instance()->add_setting( 'footer_scripts_checkout', '' );
		SettingsManager::instance()->add_setting( 'header_scripts_thank_you', '' );
		SettingsManager::instance()->add_setting( 'footer_scripts_thank_you', '' );
		SettingsManager::instance()->add_setting( 'header_scripts_order_pay', '' );
		SettingsManager::instance()->add_setting( 'footer_scripts_order_pay', '' );
		SettingsManager::instance()->add_setting( 'store_policies', array() );
		SettingsManager::instance()->add_setting( 'trust_badges', array() );
		SettingsManager::instance()->add_setting( 'trust_badges_title', '' );
		SettingsManager::instance()->add_setting( 'disable_express_checkout', 'no' );
		SettingsManager::instance()->add_setting( 'allow_checkout_cart_item_variation_changes', 'no' );
		SettingsManager::instance()->add_setting( 'allow_side_cart_item_variation_changes', 'no' );
		SettingsManager::instance()->add_setting( 'enable_astra_support', 'no' );
		SettingsManager::instance()->add_setting( 'enable_discreet_address_1_fields', 'no' );
		SettingsManager::instance()->add_setting( 'enable_free_shipping_progress_bar_at_checkout', 'no' );
		SettingsManager::instance()->add_setting( 'enable_side_cart_continue_shopping_button', 'no' );
		SettingsManager::instance()->add_setting( 'hide_floating_cart_button_empty_cart', 'no' );
		SettingsManager::instance()->add_setting( 'hide_pickup_methods', 'no' );
		SettingsManager::instance()->add_setting( 'show_cart_item_discounts', 'no' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_progress_bg_color', '#f5f5f5' );
		SettingsManager::instance()->add_setting( 'trust_badge_position', 'below_cart_summary' );
		SettingsManager::instance()->add_setting( 'enable_wc_review_badges', 'no' );
		SettingsManager::instance()->add_setting( 'wc_review_source', 'cart_first' );
		SettingsManager::instance()->add_setting( 'wc_review_min_rating', '4' );
		SettingsManager::instance()->add_setting( 'wc_review_limit', '3' );
		SettingsManager::instance()->add_setting( 'use_fullname_field', 'no' );
		SettingsManager::instance()->add_setting( 'enable_smartystreets_integration', 'no' );
		SettingsManager::instance()->add_setting( 'enable_acr', 'no' );
		SettingsManager::instance()->add_setting( 'acr_abandoned_time', 15 );
		SettingsManager::instance()->add_setting( 'acr_excluded_roles', array() );
		SettingsManager::instance()->add_setting(
			'acr_recovered_order_statuses',
			array(
				'wc-processing',
				'wc-completed',
			)
		);
		SettingsManager::instance()->add_setting( 'acr_from_name', get_bloginfo( 'name' ) );
		SettingsManager::instance()->add_setting( 'acr_from_address', get_option( 'admin_email' ) );
		SettingsManager::instance()->add_setting( 'acr_reply_to_address', get_option( 'admin_email' ) );
		SettingsManager::instance()->add_setting( 'enable_fetchify_address_autocomplete', 'no' );
		SettingsManager::instance()->add_setting( 'enable_order_review_step', 'no' );
		SettingsManager::instance()->add_setting( 'enable_address_autocomplete', 'no' );
		SettingsManager::instance()->add_setting( 'enable_international_phone_field', 'no' );
		SettingsManager::instance()->add_setting( 'enable_pickup', 'no' );
		SettingsManager::instance()->add_setting( 'enable_trust_badges', 'no' );
		SettingsManager::instance()->add_setting( 'enable_cart_editing', 'yes' );
		SettingsManager::instance()->add_setting( 'cart_edit_empty_cart_redirect', '' );
		SettingsManager::instance()->add_setting( 'enable_side_cart', 'no' );
		SettingsManager::instance()->add_setting( 'enable_ajax_add_to_cart', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_free_shipping_progress_bar', 'no' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_threshold', '' );
		SettingsManager::instance()->add_setting( 'side_cart_amount_remaining_message', '' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_message', '' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_progress_indicator_color', cfw_get_active_template()->get_default_setting( 'button_color' ) );
		SettingsManager::instance()->add_setting( 'enable_floating_cart_button', 'yes' );
		SettingsManager::instance()->add_setting( 'floating_cart_button_bottom_position', '20' );
		SettingsManager::instance()->add_setting( 'floating_cart_button_right_position', '20' );
		SettingsManager::instance()->add_setting( 'enable_order_bumps_on_side_cart', 'no' );
		SettingsManager::instance()->add_setting( 'side_cart_icon_color', '#222222' );
		SettingsManager::instance()->add_setting( 'side_cart_icon_width', '34' );
		SettingsManager::instance()->add_setting( 'side_cart_icon', 'cart-outline.svg' );
		SettingsManager::instance()->add_setting( 'show_side_cart_item_discount', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_side_cart_payment_buttons', 'yes' );
		SettingsManager::instance()->add_setting( 'side_cart_suggested_products_heading', '' );
		SettingsManager::instance()->add_setting( 'enable_one_page_checkout', 'no' );
		SettingsManager::instance()->add_setting( 'enable_side_cart_coupon_code_link', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_sticky_cart_summary', 'no' );

		SettingsManager::instance()->add_setting(
			'enabled_billing_address_fields',
			array(
				'billing_first_name',
				'billing_last_name',
				'billing_address_1',
				'billing_address_2',
				'billing_company',
				'billing_country',
				'billing_postcode',
				'billing_state',
				'billing_city',
				'billing_phone',
			)
		);

		$custom_logo_id = get_theme_mod( 'custom_logo' );

		// Init templates and template settings
		foreach ( cfw_get_available_templates() as $template ) {
			if ( $custom_logo_id ) {
				SettingsManager::instance()->add_setting( 'logo_attachment_id', $custom_logo_id, array( $template->get_slug() ) );
			}

			SettingsManager::instance()->add_setting( 'label_style', 'floating', array( $template->get_slug() ) );
			SettingsManager::instance()->add_setting( 'footer_text', '', array( $template->get_slug() ) );
			SettingsManager::instance()->add_setting( 'custom_css', '', array( $template->get_slug() ) );

			$template->init();
		}

		// Compatibility modules
		SettingsManager::instance()->add_setting( 'allow_cashier_for_woocommerce_address_modification', 'no' );
		SettingsManager::instance()->add_setting( 'allow_thcfe_address_modification', 'no' );
		SettingsManager::instance()->add_setting( 'enable_beaver_themer_support', 'no' );
		SettingsManager::instance()->add_setting( 'enable_elementor_pro_support', 'no' );
		SettingsManager::instance()->add_setting( 'allow_checkout_field_editor_address_modification', 'no' );
		SettingsManager::instance()->add_setting( 'enable_wp_rocket_delay_js_compatibility_mode', 'no' );
	}
}
