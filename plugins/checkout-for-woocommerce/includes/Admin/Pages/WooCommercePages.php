<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class WooCommercePages extends PageAbstract {
	use TabbedAdminPageTrait;

	public function __construct() {

		parent::__construct( __( 'Pages', 'checkout-wc' ), 'cfw_manage_pages', 'checkout' );
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'checkout' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Checkout', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'checkout' ), $this->get_url() ), 'checkout' );
		$this->get_tabbed_navigation()->add_tab( __( 'Thank You', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'thankyou' ), $this->get_url() ), 'thankyou' );
		$this->get_tabbed_navigation()->add_tab( __( 'Global Options', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'globaloptions' ), $this->get_url() ), 'globaloptions' );
	}

	public function output() {
		$this->get_tabbed_navigation()->display_tabs();

		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = array( $this, $current_tab_function );

		if ( is_callable( $callable ) ) {
			call_user_func( $callable );
		}
	}

	public function checkout_tab() {
		?>
		<div id="cfw-admin-pages-checkout"></div>
		<?php
	}

	public function thankyou_tab() {
		?>
		<div id="cfw-admin-pages-thank-you"></div>
		<?php
	}

	public function globaloptions_tab() {
		?>
		<div id="cfw-admin-pages-global-options"></div>
		<?php
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$store_policies = cfw_get_setting( 'store_policies', null, array() );

		// Seed store policies with internal ID that won't change
		foreach ( $store_policies as $index => $policy ) {
			// Handle edge case where policies end up set to array where 0 element = false
			// Ticket https://secure.helpscout.net/conversation/2311670760/16562?folderId=2454654
			if ( ! $policy ) {
				continue;
			}

			// This is a failsafe - we have seen some stores where policies weren't updated to array format
			if ( is_object( $policy ) ) {
				$policy = (array) $policy;
			}

			$policy['id']             = 'policy-' . $index;
			$store_policies[ $index ] = $policy;
		}

		$countries = WC()->countries->countries;
		asort( $countries );

		$this->set_script_data(
			array(
				'settings'             => array(
					// Checkout settings
					'skip_cart_step'                       => SettingsManager::instance()->get_setting( 'skip_cart_step' ) === 'yes',
					'skip_shipping_step'                   => SettingsManager::instance()->get_setting( 'skip_shipping_step' ) === 'yes',
					'enable_order_review_step'             => SettingsManager::instance()->get_setting( 'enable_order_review_step' ) === 'yes',
					'enable_one_page_checkout'             => SettingsManager::instance()->get_setting( 'enable_one_page_checkout' ) === 'yes',
					'registration_style'                   => SettingsManager::instance()->get_setting( 'registration_style' ),
					'user_matching'                        => SettingsManager::instance()->get_setting( 'user_matching' ),
					'disable_auto_open_login_modal'        => SettingsManager::instance()->get_setting( 'disable_auto_open_login_modal' ) === 'yes',
					'enable_order_notes'                   => SettingsManager::instance()->get_setting( 'enable_order_notes' ) === 'yes',
					'enable_coupon_code_link'              => SettingsManager::instance()->get_setting( 'enable_coupon_code_link' ) === 'yes',
					'enable_discreet_address_1_fields'     => SettingsManager::instance()->get_setting( 'enable_discreet_address_1_fields' ) === 'yes',
					'discreet_address_1_fields_order'      => SettingsManager::instance()->get_setting( 'discreet_address_1_fields_order' ),
					'enable_highlighted_countries'         => SettingsManager::instance()->get_setting( 'enable_highlighted_countries' ) === 'yes',
					'highlighted_countries'                => SettingsManager::instance()->get_setting( 'highlighted_countries' ),
					'enable_international_phone_field'     => SettingsManager::instance()->get_setting( 'enable_international_phone_field' ) === 'yes',
					'international_phone_field_standard'   => SettingsManager::instance()->get_setting( 'international_phone_field_standard' ),
					'force_different_billing_address'      => SettingsManager::instance()->get_setting( 'force_different_billing_address' ) === 'yes',
					'enabled_billing_address_fields'       => SettingsManager::instance()->get_setting( 'enabled_billing_address_fields' ),
					'hide_billing_address_for_free_orders' => SettingsManager::instance()->get_setting( 'hide_billing_address_for_free_orders' ) === 'yes',
					'enable_address_autocomplete'          => SettingsManager::instance()->get_setting( 'enable_address_autocomplete' ) === 'yes',
					'enable_fetchify_address_autocomplete' => SettingsManager::instance()->get_setting( 'enable_fetchify_address_autocomplete' ) === 'yes',
					'fetchify_access_token'                => SettingsManager::instance()->get_setting( 'fetchify_access_token' ),
					'enable_mobile_cart_summary'           => SettingsManager::instance()->get_setting( 'enable_mobile_cart_summary' ) === 'yes',
					'enable_mobile_totals'                 => SettingsManager::instance()->get_setting( 'enable_mobile_totals' ) === 'yes',
					'show_mobile_coupon_field'             => SettingsManager::instance()->get_setting( 'show_mobile_coupon_field' ) === 'yes',
					'show_logos_mobile'                    => SettingsManager::instance()->get_setting( 'show_logos_mobile' ) === 'yes',
					'cart_summary_mobile_label'            => SettingsManager::instance()->get_setting( 'cart_summary_mobile_label' ),
					'enable_order_pay'                     => SettingsManager::instance()->get_setting( 'enable_order_pay' ) === 'yes',
					'enable_smartystreets_integration'     => SettingsManager::instance()->get_setting( 'enable_smartystreets_integration' ) === 'yes',
					'smartystreets_auth_id'                => SettingsManager::instance()->get_setting( 'smartystreets_auth_id' ),
					'smartystreets_auth_token'             => SettingsManager::instance()->get_setting( 'smartystreets_auth_token' ),
					'disable_domain_autocomplete'          => SettingsManager::instance()->get_setting( 'disable_domain_autocomplete' ) === 'yes',
					'auto_select_free_shipping_method'     => SettingsManager::instance()->get_setting( 'auto_select_free_shipping_method' ) === 'yes',
					'enable_cart_editing'                  => SettingsManager::instance()->get_setting( 'enable_cart_editing' ) === 'yes',
					'allow_checkout_cart_item_variation_changes' => SettingsManager::instance()->get_setting( 'allow_checkout_cart_item_variation_changes' ) === 'yes',
					'show_item_remove_button'              => SettingsManager::instance()->get_setting( 'show_item_remove_button' ) === 'yes',
					'cart_edit_empty_cart_redirect'        => SettingsManager::instance()->get_setting( 'cart_edit_empty_cart_redirect' ),
					'hide_optional_address_fields_behind_link' => SettingsManager::instance()->get_setting( 'hide_optional_address_fields_behind_link' ) === 'yes',
					'enable_sticky_cart_summary'           => SettingsManager::instance()->get_setting( 'enable_sticky_cart_summary' ) === 'yes',
					// Thank You settings
					'enable_thank_you_page'                => SettingsManager::instance()->get_setting( 'enable_thank_you_page' ) === 'yes',
					'thank_you_order_statuses'             => cfw_get_setting( 'thank_you_order_statuses', null, array() ),
					'enable_map_embed'                     => SettingsManager::instance()->get_setting( 'enable_map_embed' ) === 'yes',
					'override_view_order_template'         => SettingsManager::instance()->get_setting( 'override_view_order_template' ) === 'yes',
					// Global Options settings
					'show_cart_item_discount'              => SettingsManager::instance()->get_setting( 'show_cart_item_discount' ) === 'yes',
					'cart_item_link'                       => SettingsManager::instance()->get_setting( 'cart_item_link' ),
					'cart_item_link_target_new_window'     => SettingsManager::instance()->get_setting( 'cart_item_link_target_new_window' ) === 'yes',
					'cart_item_data_display'               => SettingsManager::instance()->get_setting( 'cart_item_data_display' ),
					'store_policies'                       => array_values( $store_policies ),
					'wp_option/woocommerce_checkout_phone_field' => get_option( 'woocommerce_checkout_phone_field', 'required' ),
				),
				'woocommerce_settings' => array(
					'countries'                => $countries,
					'thank_you_order_statuses' => wc_get_order_statuses(),
				),
				'conditional_settings' => array(
					'order_notes_enable' => ! has_filter( 'woocommerce_enable_order_notes_field' ) || ( SettingsManager::instance()->get_setting( 'enable_order_notes' ) === 'yes' && 1 === cfw_count_filters( 'woocommerce_enable_order_notes_field' ) ),
				),
				'plan'                 => $this->get_plan_data(),
			)
		);
	}
}
