<?php

namespace Objectiv\Plugins\Checkout;

use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery;
use Objectiv\Plugins\Checkout\Features\ABTesting;
use Objectiv\Plugins\Checkout\Features\LocalPickup;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;
use Exception;

class DatabaseUpdatesManager extends SingletonAbstract {
	protected $db_version;

	/**
	 * Whether the database updates should run
	 *
	 * Does not run if db version is unset or is equal to or greater than the plugin version
	 *
	 * @return bool
	 */
	public function needs_updates(): bool {
		return $this->db_version && version_compare( $this->db_version, CFW_VERSION, '<' );
	}

	public function get_updates(): array {
		return array(
			'3.0.0'   => array( $this, 'update_300' ),
			'3.3.0'   => array( $this, 'update_330' ),
			'3.6.1'   => array( $this, 'update_361' ),
			'3.14.0'  => array( $this, 'update_3140' ),
			'5.3.0'   => array( $this, 'update_530' ),
			'5.3.1'   => array( $this, 'update_531' ),
			'5.3.2'   => array( $this, 'update_532' ),
			'6.0.0'   => array( $this, 'update_600' ),
			'6.0.1'   => array( $this, 'update_601' ),
			'6.0.6'   => array( $this, 'update_606' ),
			'6.1.0'   => array( $this, 'update_610' ),
			'6.1.4'   => array( $this, 'update_614' ),
			'7.0.0'   => array( $this, 'update_700' ),
			'7.0.17'  => array( $this, 'update_7017' ),
			'7.1.5'   => array( $this, 'update_715' ),
			'7.1.8'   => array( $this, 'update_718' ),
			'7.3.0'   => array( $this, 'update_730' ),
			'7.3.1'   => array( $this, 'update_731' ),
			'7.6.0'   => array( $this, 'update_760' ),
			'7.8.0'   => array( $this, 'update_780' ),
			'7.9.0'   => array( $this, 'update_790' ),
			'7.10.2'  => array( $this, 'update_7102' ),
			'8.0.0'   => array( $this, 'update_800' ),
			'8.2.11'  => array( $this, 'update_8211' ),
			'8.2.19'  => array( $this, 'update_8219' ),
			'8.2.20'  => array( $this, 'update_8220' ),
			// Start 9.0.0 betas
			'8.2.96'  => array( $this, 'update_8296' ),
			'8.2.97'  => array( $this, 'update_8297' ),
			'8.2.98'  => array( $this, 'update_8298' ),
			'8.2.100' => array( $this, 'update_82100' ),
			'8.2.104' => array( $this, 'update_82104' ),
			'8.2.107' => array( $this, 'update_82107' ),
			// End 9.0.0 betas
			'9.0.0'   => array( $this, 'update_900' ),
			'9.0.2'   => array( $this, 'update_902' ),
			'9.0.8'   => array( $this, 'update_908' ),
			'9.0.9'   => array( $this, 'update_909' ),
			'9.0.18'  => array( $this, 'update_9018' ),
			'9.0.22'  => array( $this, 'update_9022' ),
			'9.0.25'  => array( $this, 'update_9025' ),
			'9.0.27'  => array( $this, 'update_9027' ),
			'9.0.29'  => array( $this, 'update_9029' ),
			'9.0.35'  => array( $this, 'update_9035' ),
			'9.1.2'   => array( $this, 'update_912' ),
			'9.1.700' => array( $this, 'update_91700' ), // 10.0.0-alpha
			'10.0.0'  => array( $this, 'update_1000' ), // 10.0.0
			'10.1.7'  => array( $this, 'update_1017' ),
			'10.1.8'  => array( $this, 'update_1018' ),
			'10.2.0'  => array( $this, 'update_1020' ),
			'10.2.6'  => array( $this, 'update_1026' ),
			'10.2.9'  => array( $this, 'update_1029' ),
			'10.3.2'  => array( $this, 'update_1032' ),
			'10.3.6'  => array( $this, 'update_1036' ),
			'11.0.0'  => array( $this, 'update_1100' ),
			'11.0.1'  => array( $this, 'update_1101' ),
			'11.0.3'  => array( $this, 'update_1103' ),
			// TODO: For future updates, bifurcate pro and lite versions?
		);
	}

	/**
	 * Init
	 *
	 * @throws Exception The exception.
	 */
	public function init() {
		$this->db_version = get_option( 'cfw_db_version', false );

		// Maybe update from lite version
		$lite_settings = get_option( '_cfwlite__settings', false );
		$upgraded      = get_option( '_cfw_updated_lite_to_pro', false );

		// Look, we don't like nested ifs, but they are required here
		if ( ! empty( $lite_settings ) && ! $upgraded ) {
			foreach ( $lite_settings as $key => $value ) {
				SettingsManager::instance()->add_setting( $key, $value ); // don't overwrite pro settings
			}

			// Prevent duplicate runs in the future
			add_option( '_cfw_updated_lite_to_pro', true, '', false );

			// Make sure this is a real lite upgrade
			// This is required for people who previously upgraded from lite
			// to pro before 10.1 when we added the _cfw_updated_lite_to_pro option
			if ( $this->db_version && version_compare( $this->db_version, '2.0.0', '<' ) ) {
				// Update the db version here
				$this->update_db_version();

				// And don't proceed with the rest of updates
				return;
			}

			// Scenario: User downgrades to free when their last version was < 10.1.0
			if ( $this->db_version && version_compare( $this->db_version, '10.1.0', '<' ) && ! defined( 'CFW_PREMIUM_PLAN_IDS' ) ) {
				// Update the db version here
				$this->update_db_version();

				// And don't proceed with the rest of updates
				return;
			}
		}

		// Don't run upgrades for first time activators
		if ( ! $this->db_version ) {
			$this->update_db_version();

			return;
		}

		if ( ! $this->needs_updates() ) {
			return;
		}

		/**
		 * Fires before plugin data upgrades.
		 *
		 * @since 9.0.0
		 * @param int $db_version The current database version.
		 */
		do_action( 'cfw_before_plugin_data_upgrades', $this->db_version );

		$previous_updates = get_option( 'cfw_previous_updates', array() );

		// Run updates
		foreach ( $this->get_updates() as $version => $callback ) {
			// If the update version is greater than the db version and the update hasn't previously run, do your thang
			if ( version_compare( $version, $this->db_version, '>' ) && empty( $previous_updates[ $version ] ) ) {
				try {
					call_user_func( $callback );
				} catch ( Exception $e ) {
					wc_get_logger()->error( 'CheckoutWC: Failed to run DB update. Update version: ' . $version . ' Error: ' . $e->getMessage(), array( 'source' => 'checkout-wc' ) );
				}

				$previous_updates[ $version ] = true;
			}
		}

		update_option( 'cfw_previous_updates', $previous_updates );

		// Always enforce defaults so that we don't have to do discrete updates for setting defaults
		Install::add_settings();

		$this->update_db_version();

		/**
		 * Fires after plugin data upgrades.
		 *
		 * @param string $this ->db_version The current db version.
		 *
		 * @since 5.0.0
		 */
		do_action( 'cfw_after_plugin_data_upgrades', $this->db_version );
	}

	private function update_db_version() {
		$this->db_version = get_option( 'cfw_db_version', '0.0.0' );

		// Only update db version if the current version is greater than the db version
		if ( version_compare( CFW_VERSION, $this->db_version, '>' ) ) {
			update_option( 'cfw_db_version', CFW_VERSION );
		}
	}

	public function get_ob_product_data( $product_data ) {
		// Don't port the data twice
		if ( is_array( $product_data ) && isset( $product_data['key'] ) && isset( $product_data['label'] ) ) {
			return $product_data;
		}

		$product = wc_get_product( $product_data );

		if ( ! $product ) {
			return false;
		}

		return array(
			'key'   => $product_data,
			'label' => $product->get_name(),
		);
	}

	public function get_ob_category_data( $category_data ) {
		// Don't port the data twice
		if ( is_array( $category_data ) && isset( $category_data['key'] ) && isset( $category_data['label'] ) ) {
			return $category_data;
		}

		$term = get_term_by( 'slug', $category_data, 'product_cat' );

		if ( ! $term ) {
			return false;
		}

		return array(
			'key'   => $term->term_id,
			'label' => $term->name,
		);
	}

	public function update_300() {
		cfw_get_active_template()->init();

		if ( SettingsManager::instance()->get_setting( 'allow_tracking' ) === 1 ) {
			SettingsManager::instance()->update_setting( 'allow_tracking', md5( trailingslashit( home_url() ) ) );
		}
	}

	public function update_330() {
		SettingsManager::instance()->add_setting( 'override_view_order_template', 'yes' );
	}

	public function update_361() {
		SettingsManager::instance()->update_setting( 'accent_color', '#dee6fe', array( 'glass' ) );
	}

	public function update_3140() {
		SettingsManager::instance()->add_setting( 'enable_order_review_step', 'no' );
	}

	public function update_530() {
		foreach ( cfw_get_available_templates() as $template ) {
			$breadcrumb_completed_text_color   = '#7f7f7f';
			$breadcrumb_current_text_color     = '#333333';
			$breadcrumb_next_text_color        = '#7f7f7f';
			$breadcrumb_completed_accent_color = '#333333';
			$breadcrumb_current_accent_color   = '#333333';
			$breadcrumb_next_accent_color      = '#333333';

			if ( $template->get_slug() === 'glass' ) {
				$breadcrumb_current_text_color   = SettingsManager::instance()->get_setting( 'button_color', array( 'glass' ) );
				$breadcrumb_current_accent_color = SettingsManager::instance()->get_setting( 'button_color', array( 'glass' ) );
				$breadcrumb_next_text_color      = '#dfdcdb';
				$breadcrumb_next_accent_color    = '#dfdcdb';

			} elseif ( $template->get_slug() === 'futurist' ) {
				$futurist_header_bg_color          = SettingsManager::instance()->get_setting( 'header_background_color', array( $template->get_slug() ) );
				$color                             = '#ffffff' === $futurist_header_bg_color ? '#333333' : '#222222';
				$breadcrumb_completed_text_color   = $color;
				$breadcrumb_current_text_color     = $color;
				$breadcrumb_next_text_color        = $color;
				$breadcrumb_completed_accent_color = $color;
				$breadcrumb_current_accent_color   = $color;
				$breadcrumb_next_accent_color      = $color;
			}

			SettingsManager::instance()->update_setting( 'breadcrumb_completed_text_color', $breadcrumb_completed_text_color, array( $template->get_slug() ) );
			SettingsManager::instance()->update_setting( 'breadcrumb_current_text_color', $breadcrumb_current_text_color, array( $template->get_slug() ) );
			SettingsManager::instance()->update_setting( 'breadcrumb_next_text_color', $breadcrumb_next_text_color, array( $template->get_slug() ) );
			SettingsManager::instance()->update_setting( 'breadcrumb_completed_accent_color', $breadcrumb_completed_accent_color, array( $template->get_slug() ) );
			SettingsManager::instance()->update_setting( 'breadcrumb_current_accent_color', $breadcrumb_current_accent_color, array( $template->get_slug() ) );
			SettingsManager::instance()->update_setting( 'breadcrumb_next_accent_color', $breadcrumb_next_accent_color, array( $template->get_slug() ) );
		}

		global $wpdb;

		// Convert order bump data
		$items = $wpdb->get_results( "SELECT order_item_id, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_cfw_order_bump_id';" );

		foreach ( $items as $item ) {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item->order_item_id ) );

			$order = \wc_get_order( (int) $order_id );
			if ( $order ) {
				if ( (int) $order->get_meta( 'cfw_has_bump' ) === 1 ) {
					continue;
				}
				$order->add_meta_data( 'cfw_has_bump', true );
				$order->add_meta_data( 'cfw_bump_' . $item->meta_value, true );
				$order->save();
			}
		}
	}

	public function update_531() {
		foreach ( cfw_get_available_templates() as $template ) {
			$template->init();
		}

		SettingsManager::instance()->update_setting( 'summary_background_color', '#f8f8f8', array( 'futurist' ) );
	}

	public function update_532() {
		$futurist_header_bg_color = SettingsManager::instance()->get_setting( 'header_background_color', array( 'futurist' ) );
		$color                    = '#ffffff' === $futurist_header_bg_color ? '#333333' : $futurist_header_bg_color;

		SettingsManager::instance()->update_setting( 'breadcrumb_completed_text_color', $color, array( 'futurist' ) );
		SettingsManager::instance()->update_setting( 'breadcrumb_current_text_color', $color, array( 'futurist' ) );
		SettingsManager::instance()->update_setting( 'breadcrumb_next_text_color', $color, array( 'futurist' ) );
		SettingsManager::instance()->update_setting( 'breadcrumb_completed_accent_color', $color, array( 'futurist' ) );
		SettingsManager::instance()->update_setting( 'breadcrumb_current_accent_color', $color, array( 'futurist' ) );
		SettingsManager::instance()->update_setting( 'breadcrumb_next_accent_color', $color, array( 'futurist' ) );
	}

	public function update_600() {
		SettingsManager::instance()->add_setting( 'enable_international_phone_field', 'no' );
		SettingsManager::instance()->add_setting( 'enable_side_cart', 'no' );
		SettingsManager::instance()->add_setting( 'enable_free_shipping_progress_bar', 'no' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_threshold', '' );
		SettingsManager::instance()->add_setting( 'side_cart_amount_remaining_message', '' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_message', '' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_threshold', '' );
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_progress_indicator_color', cfw_get_active_template()->get_default_setting( 'button_color' ) );
		SettingsManager::instance()->add_setting( 'enable_floating_cart_button', 'yes' );
		SettingsManager::instance()->add_setting( 'floating_cart_button_bottom_position', '20' );
		SettingsManager::instance()->add_setting( 'floating_cart_button_right_position', '20' );
		SettingsManager::instance()->add_setting( 'enable_ajax_add_to_cart', 'no' );
	}

	public function update_601() {
		SettingsManager::instance()->add_setting( 'side_cart_free_shipping_progress_bg_color', '#f5f5f5' );
	}

	public function update_606() {
		SettingsManager::instance()->add_setting( 'enable_order_bumps_on_side_cart', 'no' );
	}

	public function update_610() {
		SettingsManager::instance()->add_setting( 'side_cart_icon_color', '#222222' );
		SettingsManager::instance()->add_setting( 'side_cart_icon_width', '34' );
		SettingsManager::instance()->add_setting( 'side_cart_icon', 'cart-outline.svg' );
	}

	public function update_614() {
		if ( ! class_exists( '\Objectiv\Plugins\Checkout\Factories\BumpFactory' ) ) {
			return;
		}

		SettingsManager::instance()->add_setting( 'hide_floating_cart_button_empty_cart', 'no' );
		SettingsManager::instance()->add_setting( 'enable_astra_support', 'no' );

		$bumps = BumpFactory::get_all();

		foreach ( $bumps as $bump ) {
			add_post_meta( $bump->get_id(), 'captured_revenue', $bump->get_estimated_revenue(), true );
		}
	}

	public function update_700() {
		SettingsManager::instance()->add_setting( 'hide_optional_address_fields_behind_link', 'yes' );
		SettingsManager::instance()->add_setting( 'enable_discreet_address_1_fields', 'no' );
		SettingsManager::instance()->add_setting( 'use_fullname_field', 'no' );

		SettingsManager::instance()->add_setting( 'header_scripts_checkout', '' );
		SettingsManager::instance()->add_setting( 'header_scripts_thank_you', '' );
		SettingsManager::instance()->add_setting( 'header_scripts_order_pay', '' );

		SettingsManager::instance()->add_setting( 'footer_scripts_checkout', '' );
		SettingsManager::instance()->add_setting( 'footer_scripts_thank_you', '' );
		SettingsManager::instance()->add_setting( 'footer_scripts_order_pay', '' );
	}

	public function update_7017() {
		SettingsManager::instance()->add_setting( 'trust_badge_position', 'below_cart_summary' );
	}

	public function update_715() {
		SettingsManager::instance()->add_setting( 'enable_side_cart_continue_shopping_button', 'no' );
	}

	public function update_718() {
		foreach ( cfw_get_available_templates() as $template ) {
			SettingsManager::instance()->update_setting( 'summary_link_color', '#0073aa', array( $template->get_slug() ) );
		}

		SettingsManager::instance()->add_setting( 'show_cart_item_discounts', 'no' );
		SettingsManager::instance()->add_setting( 'show_side_cart_item_discount', 'no' );
	}

	public function update_730() {
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
	}

	public function update_731() {
		if ( ! class_exists( '\Objectiv\Plugins\Checkout\Features\LocalPickup' ) ) {
			return;
		}

		$pickup_times     = LocalPickup::get_pickup_times();
		$pickup_locations = get_posts(
			array(
				'post_type' => LocalPickup::get_post_type(),
			)
		);

		if ( $pickup_locations ) {
			foreach ( $pickup_locations as $pickup_location ) {
				$cfw_pl_estimated_time = get_post_meta( $pickup_location->ID, 'cfw_pl_estimated_time', true );

				$key = array_search( $cfw_pl_estimated_time, $pickup_times, true );

				if ( $key ) {
					update_post_meta( $pickup_location->ID, 'cfw_pl_estimated_time', $key );
				}
			}
		}

		$skip_shipping_step = SettingsManager::instance()->get_setting( 'enable_pickup_skip_shipping_step' );

		if ( 'no' === $skip_shipping_step ) {
			SettingsManager::instance()->add_setting( 'enable_pickup_method_step', 'yes' );
		} else {
			SettingsManager::instance()->add_setting( 'enable_pickup_method_step', 'no' );
		}
	}

	public function update_760() {
		SettingsManager::instance()->add_setting( 'hide_pickup_methods', 'no' );
		SettingsManager::instance()->add_setting( 'enable_pickup_ship_option', 'yes' );
	}

	public function update_780() {
		UpdatesManager::instance()->get_license_data();
	}

	public function update_790() {
		// This previously forced a stat checkin, but we don't need it anymore
	}

	public function update_7102() {
		$side_cart_icon = SettingsManager::instance()->get_setting( 'side_cart_icon' );

		if ( file_exists( $side_cart_icon ) ) {
			SettingsManager::instance()->update_setting( 'side_cart_icon', basename( $side_cart_icon ) );
		}
	}

	public function update_800() {
		$data_migrated = get_option( 'cfw_v80_data_migrated', false );

		if ( $data_migrated ) {
			return;
		}

		// Migrate settings
		$settings_obj = SettingsManager::instance()->get_settings_obj( true );

		if ( ! empty( $settings_obj ) ) {
			foreach ( $settings_obj as $key => $value ) {
				// Have to use update_setting because of edge case where someone upgrades by activating the plugin
				SettingsManager::instance()->update_setting( $key, $value );
			}
		}

		add_option( 'cfw_v80_data_migrated', true );

		// Migrate Inter to Inter-cfw
		$body_font    = SettingsManager::instance()->get_setting( 'body_font' );
		$heading_font = SettingsManager::instance()->get_setting( 'heading_font' );

		if ( 'Inter' === $body_font ) {
			SettingsManager::instance()->update_setting( 'body_font', 'inter-cfw' );
		}

		if ( 'Inter' === $heading_font ) {
			SettingsManager::instance()->update_setting( 'heading_font', 'inter-cfw' );
		}

		// Make font a template setting by migrating existing font setting to the templates
		$body_font    = SettingsManager::instance()->get_setting( 'body_font' );
		$heading_font = SettingsManager::instance()->get_setting( 'heading_font' );

		// Update the current active template
		SettingsManager::instance()->add_setting( 'body_font', $body_font, array( cfw_get_active_template()->get_slug() ) );
		SettingsManager::instance()->add_setting( 'heading_font', $heading_font, array( cfw_get_active_template()->get_slug() ) );

		// Cleanup old global settings
		SettingsManager::instance()->delete_setting( 'body_font' );
		SettingsManager::instance()->delete_setting( 'heading_font' );

		// Standard settings
		SettingsManager::instance()->add_setting( 'allow_checkout_cart_item_variation_changes', 'no' );
		SettingsManager::instance()->add_setting( 'allow_side_cart_item_variation_changes', 'no' );
		SettingsManager::instance()->add_setting( 'enable_order_bumps', 'yes' );
		SettingsManager::instance()->add_setting( 'max_bumps', '-1' );
		SettingsManager::instance()->add_setting( 'shake_floating_cart_button', 'no' );
		SettingsManager::instance()->add_setting( 'enable_beta_version_updates', 'no' );
		SettingsManager::instance()->add_setting( 'disable_express_checkout', 'no' );

		/**
		 * Action: cfw_acr_activate
		 *
		 * Fires when the plugin is activated.
		 *
		 * @since 8.0.0
		 */
		do_action( 'cfw_acr_activate' );

		// Between 8.0.0 and 8.0.2 data fix
		if ( version_compare( $this->db_version, '8.0.0', '>=' ) && version_compare( '8.0.3', $this->db_version, '>' ) ) {
			// Groove was accidentally set to the current body / heading font instead of its default font
			// This repairs the font setting for Groove unless it's already the active template in which
			// case we stand down as we don't know if the customer prefers the System Font Stack option
			if ( 'groove' !== cfw_get_active_template()->get_slug() ) {
				SettingsManager::instance()->update_setting( 'body_font', 'inter-cfw', array( 'groove' ) );
				SettingsManager::instance()->update_setting( 'heading_font', 'inter-cfw', array( 'groove' ) );
			}
		}
	}

	public function update_8211() {
		SettingsManager::instance()->add_setting( 'max_after_checkout_bumps', '1' );
	}

	public function update_8219() {
		SettingsManager::instance()->add_setting( 'enable_side_cart_totals', 'no' );
	}

	public function update_8220() {
		update_option( '_cfw__settings', get_option( '_cfw__settings' ), false );
	}

	public function update_8296() {
		$trust_badges = array_filter( cfw_get_setting( 'trust_badges', null, array() ) );

		// Backup the trust badges
		SettingsManager::instance()->add_setting( 'trust_badges_v8', $trust_badges );
		SettingsManager::instance()->delete_setting( 'trust_badges', $trust_badges );

		foreach ( $trust_badges as $index => $badge ) {
			$badge['id']         = 'tb-' . $index;
			$badge['template']   = 'guarantee';
			$badge['subtitle']   = '';
			$badge['mode']       = 'HTML';
			$badge['image']      = new \stdClass();
			$badge['image']->id  = $badge['badge_attachment_id'] ?? null;
			$badge['image']->url = ! empty( $badge['badge_attachment_id'] ) ? wp_get_attachment_url( $badge['badge_attachment_id'] ) : null;
			$badge['image']->alt = ! empty( $badge['badge_attachment_id'] ) ? get_post_meta( $badge['badge_attachment_id'], '_wp_attachment_image_alt', true ) : null;

			$trust_badges[ $index ] = $badge;
		}

		SettingsManager::instance()->update_setting( 'trust_badges', $trust_badges );

		// Refresh license data
		UpdatesManager::instance()->get_license_data();

		// Make all design settings per template
		foreach ( cfw_get_available_templates() as $template ) {
			SettingsManager::instance()->add_setting( 'logo_attachment_id', SettingsManager::instance()->get_setting( 'logo_attachment_id' ), array( $template->get_slug() ) );
			SettingsManager::instance()->add_setting( 'label_style', SettingsManager::instance()->get_setting( 'label_style' ), array( $template->get_slug() ) );
			SettingsManager::instance()->add_setting( 'footer_text', SettingsManager::instance()->get_setting( 'footer_text' ), array( $template->get_slug() ) );
			SettingsManager::instance()->add_setting( 'custom_css', SettingsManager::instance()->get_setting( 'custom_css' ), array( $template->get_slug() ) );
		}

		SettingsManager::instance()->add_setting( 'footer_text_editor_mode', 'WYSIWYG' );
		SettingsManager::instance()->add_setting( 'disable_domain_autocomplete', 'no' );
		SettingsManager::instance()->add_setting( 'enable_mobile_totals', 'yes' );
	}

	/**
	 * @throws Exception If the bump cannot be updated.
	 */
	public function update_8297() {
		if ( ! class_exists( '\Objectiv\Plugins\Checkout\Factories\BumpFactory' ) ) {
			return;
		}

		$bumps = BumpFactory::get_all();

		foreach ( $bumps as $bump ) {
			$cfw_ob_db_version = get_post_meta( $bump->get_id(), 'cfw_ob_data_version', true );

			if ( CFW_VERSION === $cfw_ob_db_version ) {
				continue;
			}

			$cfw_ob_products           = get_post_meta( $bump->get_id(), 'cfw_ob_products', true );
			$cfw_ob_categories         = get_post_meta( $bump->get_id(), 'cfw_ob_categories', true );
			$cfw_ob_exclude_products   = get_post_meta( $bump->get_id(), 'cfw_ob_exclude_products', true );
			$cfw_ob_products_to_remove = get_post_meta( $bump->get_id(), 'cfw_ob_products_to_remove', true );
			$cfw_ob_exclude_categories = get_post_meta( $bump->get_id(), 'cfw_ob_exclude_categories', true );
			$cfw_ob_offer_product      = get_post_meta( $bump->get_id(), 'cfw_ob_offer_product', true );

			// Convert array of IDs to array of objects with key => id and label => name
			$cfw_ob_products = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_products ) ? $cfw_ob_products : array()
			);

			$cfw_ob_exclude_products = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_exclude_products ) ? $cfw_ob_exclude_products : array()
			);

			$cfw_ob_products_to_remove = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_products_to_remove ) ? $cfw_ob_products_to_remove : array()
			);

			$cfw_ob_categories = array_map(
				array( $this, 'get_ob_category_data' ),
				is_array( $cfw_ob_categories ) ? $cfw_ob_categories : array()
			);

			$cfw_ob_exclude_categories = array_map(
				array( $this, 'get_ob_category_data' ),
				is_array( $cfw_ob_exclude_categories ) ? $cfw_ob_exclude_categories : array()
			);

			$cfw_ob_offer_product = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_offer_product ) ? $cfw_ob_offer_product : array( $cfw_ob_offer_product )
			);

			add_post_meta( $bump->get_id(), 'cfw_ob_products_v9', array_filter( $cfw_ob_products ), true );
			add_post_meta( $bump->get_id(), 'cfw_ob_categories_v9', array_filter( $cfw_ob_categories ), true );
			add_post_meta( $bump->get_id(), 'cfw_ob_products_to_remove_v9', array_filter( $cfw_ob_products_to_remove ), true );
			add_post_meta( $bump->get_id(), 'cfw_ob_exclude_products_v9', array_filter( $cfw_ob_exclude_products ), true );
			add_post_meta( $bump->get_id(), 'cfw_ob_exclude_categories_v9', array_filter( $cfw_ob_exclude_categories ), true );
			add_post_meta( $bump->get_id(), 'cfw_ob_offer_product_v9', array_filter( $cfw_ob_offer_product ), true );
			add_post_meta( $bump->get_id(), 'cfw_ob_data_version', CFW_VERSION, true );

			// Mark data as ported to v9 keys - if this happens here, it won't happen in update_909
			add_option( 'cfw_ob_data_ported_v9_keys', true );
		}
	}

	public function update_8298() {
		Install::add_capabilities();
	}

	public function update_82100() {
		SettingsManager::instance()->add_setting( 'acr_simulate_only', 'no' );

		$pickup_methods = SettingsManager::instance()->get_setting( 'pickup_methods' );

		if ( false === $pickup_methods ) {
			SettingsManager::instance()->add_setting( 'pickup_methods', array() );
		}
	}

	public function update_82104() {
		SettingsManager::instance()->add_setting( 'hide_billing_address_for_free_orders', 'no' );
	}

	public function update_82107() {
		SettingsManager::instance()->add_setting( 'enable_free_shipping_progress_bar_at_checkout', 'no' );
	}

	public function update_900() {
		// Only do this redirect until we get to 9.1
		if ( version_compare( '9.1.0', CFW_VERSION, '>' ) ) {
			set_transient( '_cfw_90_upgrade_welcome_redirect', true, 30 );
		}
	}

	public function update_902() {
		$new_trust_badges = SettingsManager::instance()->get_setting( 'new_trust_badges' );

		/**
		 * Fix Trust Badges Saved Data
		 *
		 * Reverse course on new setting name
		 * - store the old trust badges in a separate setting
		 * - convert the new trust badges to arrays
		 * - store in original setting name
		 */
		if ( ! empty( $new_trust_badges ) && count( $new_trust_badges ) > 0 ) {
			// Backup the old trust badges
			$old_trust_badges = cfw_get_setting( 'trust_badges', null, array() );

			// Only do this if we actually have old trust badges
			if ( count( $old_trust_badges ) > 0 ) {
				SettingsManager::instance()->add_setting( 'trust_badges_v8', $old_trust_badges );
			}

			// Convert array of stdClasses to array of arrays
			$new_trust_badges = array_map(
				function ( $badge ) {
					if ( is_object( $badge ) ) {
						$badge = (array) $badge;
					}

					return $badge;
				},
				$new_trust_badges
			);

			SettingsManager::instance()->update_setting( 'trust_badges', $new_trust_badges );

			// Finally, clean up the new setting to save db space
			SettingsManager::instance()->delete_setting( 'new_trust_badges' );
		}

		/**
		 * Fix Store Policy Saved Data
		 *
		 * Convert nested array objects to arrays
		 */
		$store_policies = cfw_get_setting( 'store_policies', null, array() );

		if ( empty( $store_policies ) || ! is_array( $store_policies ) ) {
			return;
		}

		// Convert array of stdClasses to array of arrays
		$store_policies = array_map(
			function ( $policy ) {
				if ( is_object( $policy ) ) {
					$policy = (array) $policy;
				}

				return $policy;
			},
			$store_policies
		);

		SettingsManager::instance()->update_setting( 'store_policies', $store_policies );
	}

	public function update_908() {
		$settings_manager = SettingsManager::instance();

		$settings_manager->add_setting( 'header_scripts', '' );
		$settings_manager->add_setting( 'footer_scripts', '' );
		$settings_manager->add_setting( 'php_snippets', '' );
		$settings_manager->add_setting( 'header_scripts_checkout', '' );
		$settings_manager->add_setting( 'footer_scripts_checkout', '' );
		$settings_manager->add_setting( 'header_scripts_thank_you', '' );
		$settings_manager->add_setting( 'footer_scripts_thank_you', '' );
		$settings_manager->add_setting( 'header_scripts_order_pay', '' );
		$settings_manager->add_setting( 'footer_scripts_order_pay', '' );
	}

	/**
	 * This cleans up a terrible situation
	 * - User upgrades to v9 and gets new data format for fields
	 * - User downgrades to v8 and has to re-enter all their data
	 * - User subsequently re-upgrades to v9
	 *
	 * @return void
	 * @throws Exception If the bump cannot be updated.
	 */
	public function update_909() {
		if ( ! class_exists( '\Objectiv\Plugins\Checkout\Factories\BumpFactory' ) ) {
			return;
		}

		$data_ported = get_option( 'cfw_ob_data_ported_v9_keys', false );

		if ( $data_ported ) {
			return;
		}

		$bumps = BumpFactory::get_all();

		foreach ( $bumps as $bump ) {
			$cfw_ob_products           = get_post_meta( $bump->get_id(), 'cfw_ob_products', true );
			$cfw_ob_categories         = get_post_meta( $bump->get_id(), 'cfw_ob_categories', true );
			$cfw_ob_exclude_products   = get_post_meta( $bump->get_id(), 'cfw_ob_exclude_products', true );
			$cfw_ob_products_to_remove = get_post_meta( $bump->get_id(), 'cfw_ob_products_to_remove', true );
			$cfw_ob_exclude_categories = get_post_meta( $bump->get_id(), 'cfw_ob_exclude_categories', true );
			$cfw_ob_offer_product      = get_post_meta( $bump->get_id(), 'cfw_ob_offer_product', true );

			// Convert array of IDs to array of objects with key => id and label => name
			$cfw_ob_products = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_products ) ? $cfw_ob_products : array()
			);

			$cfw_ob_exclude_products = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_exclude_products ) ? $cfw_ob_exclude_products : array()
			);

			$cfw_ob_products_to_remove = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_products_to_remove ) ? $cfw_ob_products_to_remove : array()
			);

			$cfw_ob_categories = array_map(
				array( $this, 'get_ob_category_data' ),
				is_array( $cfw_ob_categories ) ? $cfw_ob_categories : array()
			);

			$cfw_ob_exclude_categories = array_map(
				array( $this, 'get_ob_category_data' ),
				is_array( $cfw_ob_exclude_categories ) ? $cfw_ob_exclude_categories : array()
			);

			$cfw_ob_offer_product = array_map(
				array( $this, 'get_ob_product_data' ),
				is_array( $cfw_ob_offer_product ) ? $cfw_ob_offer_product : array( $cfw_ob_offer_product )
			);

			add_post_meta( $bump->get_id(), 'cfw_ob_products_v9', $cfw_ob_products, true );
			add_post_meta( $bump->get_id(), 'cfw_ob_categories_v9', $cfw_ob_categories, true );
			add_post_meta( $bump->get_id(), 'cfw_ob_products_to_remove_v9', $cfw_ob_products_to_remove, true );
			add_post_meta( $bump->get_id(), 'cfw_ob_exclude_products_v9', $cfw_ob_exclude_products, true );
			add_post_meta( $bump->get_id(), 'cfw_ob_exclude_categories_v9', $cfw_ob_exclude_categories, true );
			add_post_meta( $bump->get_id(), 'cfw_ob_offer_product_v9', $cfw_ob_offer_product, true );
		}

		// Mark data as ported to v9 keys
		add_option( 'cfw_ob_data_ported_v9_keys', true );
	}

	public function update_9018() {
		if ( ! class_exists( '\Objectiv\Plugins\Checkout\Factories\BumpFactory' ) ) {
			return;
		}

		$bumps     = BumpFactory::get_all();
		$meta_keys = array(
			'cfw_ob_products_v9',
			'cfw_ob_categories_v9',
			'cfw_ob_products_to_remove_v9',
			'cfw_ob_exclude_products_v9',
			'cfw_ob_exclude_categories_v9',
			'cfw_ob_offer_product_v9',
		);

		foreach ( $bumps as $bump ) {
			foreach ( $meta_keys as $meta_key ) {
				$meta_value = get_post_meta( $bump->get_id(), $meta_key );

				if ( empty( $meta_value ) ) {
					continue;
				}

				if ( count( $meta_value ) === 1 ) {
					continue;
				}

				// Get the last value
				$meta_value = end( $meta_value );

				delete_post_meta( $bump->get_id(), $meta_key );
				add_post_meta( $bump->get_id(), $meta_key, $meta_value, true );
			}
		}
	}

	public function update_9022() {
		if ( 'no' === SettingsManager::instance()->get_setting( 'cart_summary_mobile_label' ) ) {
			SettingsManager::instance()->update_setting( 'cart_summary_mobile_label', '' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'discreet_address_1_fields_order' ) ) {
			SettingsManager::instance()->update_setting( 'discreet_address_1_fields_order', 'default' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'fetchify_access_token' ) ) {
			SettingsManager::instance()->update_setting( 'fetchify_access_token', '' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'pickup_option_label' ) ) {
			SettingsManager::instance()->update_setting( 'pickup_option_label', '' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'pickup_ship_option_label' ) ) {
			SettingsManager::instance()->update_setting( 'pickup_ship_option_label', '' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'pickup_shipping_method_other_label' ) ) {
			SettingsManager::instance()->update_setting( 'pickup_shipping_method_other_label', '' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'side_cart_custom_icon_attachment_id' ) ) {
			SettingsManager::instance()->update_setting( 'side_cart_custom_icon_attachment_id', 0 );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'smartystreets_auth_id' ) ) {
			SettingsManager::instance()->update_setting( 'smartystreets_auth_id', '' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'smartystreets_auth_token' ) ) {
			SettingsManager::instance()->update_setting( 'smartystreets_auth_token', '' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'highlighted_countries' ) ) {
			SettingsManager::instance()->update_setting( 'highlighted_countries', array() );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'international_phone_field_standard' ) ) {
			SettingsManager::instance()->update_setting( 'international_phone_field_standard', 'raw' );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'store_policies' ) ) {
			SettingsManager::instance()->update_setting( 'store_policies', array() );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'trust_badges' ) ) {
			SettingsManager::instance()->update_setting( 'trust_badges', array() );
		}

		if ( 'no' === SettingsManager::instance()->get_setting( 'trust_badges_title' ) ) {
			SettingsManager::instance()->update_setting( 'trust_badges_title', '' );
		}
	}

	public function update_9025() {
		SettingsManager::instance()->add_setting( 'enable_side_cart_coupon_code_link', 'yes' );
	}

	public function update_9027() {
		$value = SettingsManager::instance()->get_setting( 'thank_you_order_statuses' );

		if ( is_string( $value ) || false === $value ) {
			SettingsManager::instance()->update_setting( 'thank_you_order_statuses', array() );
		}
	}

	public function update_9029() {
		SettingsManager::instance()->add_setting( 'enable_wp_rocket_delay_js_compatibility_mode', 'yes' );
	}

	public function update_9035() {
		// Filter the array and cast it to an array for the weird situations where trust_badges is an empty string instead of an array
		$trust_badge_items = array_filter( cfw_get_setting( 'trust_badges', null, array() ) );

		foreach ( $trust_badge_items as $index => $badge ) {
			// Handle edge case where badges end up set to array where 0 element = false
			// Ticket https://secure.helpscout.net/conversation/2311670760/16562?folderId=2454654
			if ( ! $badge ) {
				unset( $trust_badge_items[ $index ] );
			}

			// Or if the badge is an array where the first element is false, also scrub it
			if ( isset( $badge[0] ) && false === $badge[0] ) {
				unset( $trust_badge_items[ $index ] );
			}
		}

		SettingsManager::instance()->update_setting( 'trust_badges', $trust_badge_items );
	}

	public function update_912() {
		SettingsManager::instance()->add_setting( 'cart_item_link_target_new_window', 'yes' );
	}

	/**
	 * @return void
	 */
	public function update_91700() {
		if ( ! class_exists( '\Objectiv\Plugins\Checkout\Factories\BumpFactory' ) ) {
			return;
		}

		// Get all order bumps
		$bumps = BumpFactory::get_all();

		foreach ( $bumps as $bump ) {
			$bump_id = $bump->get_id();

			// Initialize variables
			$rules = get_post_meta( $bump_id, 'cfw_ob_rules', true );

			if ( ! is_array( $rules ) ) {
				$rules = array();
			}

			$has_changes       = false;
			$display_for       = get_post_meta( $bump_id, 'cfw_ob_display_for', true );
			$is_upsell         = get_post_meta( $bump_id, 'cfw_ob_upsell', true ) === 'yes';
			$specific_products = get_post_meta( $bump_id, 'cfw_ob_products_v9', true );

			// 1. If bump is not an upsell but is configured for specific products, migrate those products to rules
			if ( 'specific_products' === $display_for && ! $is_upsell && count( $specific_products ) > 0 ) {
				$rules[] = array(
					'fieldKey'  => 'cartContents',
					'subFields' => array(
						'cartContents' => 'containsProducts',
						'field_1'      => get_post_meta( $bump_id, 'cfw_ob_any_product', true ) === 'yes' ? 'atLeastOne' : 'all',
						'field_2'      => $specific_products,
					),
				);

				$has_changes = true;
			}

			// 2. If bump is a specific category bump, migrate category to rules
			$specific_categories = get_post_meta( $bump_id, 'cfw_ob_categories_v9', true );

			if ( 'specific_categories' === $display_for && count( $specific_categories ) > 0 ) {
				$rules[] = array(
					'fieldKey'  => 'cartContents',
					'subFields' => array(
						'cartContents' => 'containsCategories',
						'field_1'      => 'atLeastOne',
						'field_2'      => $specific_categories,
					),
				);

				$has_changes = true;
			}

			// 3. If bump has product exclusions, migrate to rules
			$product_exclusions = get_post_meta( $bump_id, 'cfw_ob_exclude_products_v9', true );

			if ( count( $product_exclusions ) > 0 ) {
				$rules[] = array(
					'fieldKey'  => 'cartContents',
					'subFields' => array(
						'cartContents' => 'containsProducts',
						'field_1'      => 'none',
						'field_2'      => $product_exclusions,
					),
				);

				$has_changes = true;
			}

			// 4. If bump has category exclusions, migrate to rules
			$category_exclusions = get_post_meta( $bump_id, 'cfw_ob_exclude_categories_v9', true );

			if ( count( $category_exclusions ) > 0 ) {
				$rules[] = array(
					'fieldKey'  => 'cartContents',
					'subFields' => array(
						'cartContents' => 'containsCategories',
						'field_1'      => 'none',
						'field_2'      => $category_exclusions,
					),
				);

				$has_changes = true;
			}

			// 5. If bump has minimum subtotal, migrate to rules
			$minimum_subtotal = get_post_meta( $bump_id, 'cfw_ob_minimum_subtotal', true );

			if ( ! empty( $minimum_subtotal ) ) {
				$rules[] = array(
					'fieldKey'  => 'cartTotalValue',
					'subFields' => array(
						'field_0' => 'greaterThanEqual',
						'field_1' => $minimum_subtotal,
					),
				);

				$has_changes = true;
			}

			// 6. If bump is an upsell, migrate from specific products to upsell product setting
			if ( $is_upsell && 'specific_products' === $display_for && count( $specific_products ) === 1 ) {
				add_post_meta( $bump_id, 'cfw_ob_upsell_product', $specific_products );
			}

			// 7. If quantity matching is enabled for a specific product, migrate to cfw_ob_quantity_match_product setting
			$match_quantity = get_post_meta( $bump_id, 'cfw_ob_match_offer_product_quantity', true ) === 'yes';

			if ( ! $is_upsell && 'specific_products' === $display_for && count( $specific_products ) === 1 && $match_quantity ) {
				add_post_meta( $bump_id, 'cfw_ob_quantity_match_product', $specific_products );
			}

			// 8. If auto match variations enabled, migrate to cfw_ob_auto_match_product setting
			$auto_match_variations = get_post_meta( $bump_id, 'cfw_ob_enable_auto_match', true ) === 'yes';

			if ( ! $is_upsell && 'specific_products' === $display_for && count( $specific_products ) === 1 && $auto_match_variations ) {
				add_post_meta( $bump_id, 'cfw_ob_variation_match_product', $specific_products );
			}

			// Update the bump's rules if changes were made
			if ( $has_changes ) {
				add_post_meta( $bump_id, 'cfw_ob_rules', $rules );
			}
		}
	}

	public function update_1000() {
		// Only invoke this if the version is < 11.0.0
		if ( version_compare( CFW_VERSION, '11.0.0', '<' ) ) {
			set_transient( '_cfw_100_upgrade_welcome_redirect', true, 30 );
		}
	}

	public function update_1017() {
		/**
		 * Action hook fired after the 10.1.7 database update routine runs.
		 * Used to trigger actions like initial telemetry sync.
		 *
		 * @since 10.1.7
		 */
		do_action( 'cfw_updated_to_1017' ); // Fire the action
	}

	public function update_1018() {
		/**
		 * Action hook fired after the 10.1.8 database update routine runs.
		 * Used to trigger actions like initial telemetry sync.
		 *
		 * @since 10.1.8
		 */
		do_action( 'cfw_updated_to_1018' );
	}

	public function update_1020() {
		// Add default values for new Turnstile settings
		Install::add_settings();

		/**
		 * Action hook fired after the 10.2.0 database update routine runs.
		 * Used to trigger actions when Turnstile feature is added.
		 *
		 * @since 10.2.0
		 */
		do_action( 'cfw_updated_to_1020' );
	}

	public function update_1026() {
		if ( ! class_exists( '\\Objectiv\\Plugins\\Checkout\\Features\\AbandonedCartRecovery' ) ) {
			return;
		}

		AbandonedCartRecovery::create_or_update_table();

		global $wpdb;

		$table_name = AbandonedCartRecovery::get_table_name();

		// Use MySQL to generate hashes in bulk for better performance on large stores
		$wpdb->query(
			"UPDATE {$table_name} SET cart_hash = SHA2(CONCAT(email, '|', cart), 256) WHERE status != 'abandoned' AND (cart_hash = '' OR cart_hash IS NULL)" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	public function update_1029() {
		// Add WooCommerce review trust badge settings
		SettingsManager::instance()->add_setting( 'enable_wc_review_badges', 'no' );
		SettingsManager::instance()->add_setting( 'wc_review_source', 'cart_first' );
		SettingsManager::instance()->add_setting( 'wc_review_min_rating', '4' );
		SettingsManager::instance()->add_setting( 'wc_review_limit', '3' );
	}

	public function update_1032() {
		/**
		 * Action hook fired after the 10.3.2 database update routine runs.
		 *
		 * @since 10.3.2
		 */
		do_action( 'cfw_updated_to_1032' );
	}

	public function update_1036() {
		/**
		 * Action hook fired after the 10.3.2 database update routine runs.
		 *
		 * @since 10.3.6
		 */
		do_action( 'cfw_updated_to_1036' );
	}

	public function update_1100() {
		// Add A/B testing setting
		SettingsManager::instance()->add_setting( 'enable_ab_testing', 'no' );

		// Add A/B testing capabilities
		Install::add_capabilities();
	}

	public function update_1101() {
		SettingsManager::instance()->add_setting( 'side_cart_suggested_products_link_to_product', 'no' );

		// Regenerate Order Bump thumbnails for new cfw_order_bump_thumb size
		if ( ! class_exists( '\\Objectiv\\Plugins\\Checkout\\Factories\\BumpFactory' ) ) {
			return;
		}

		$bumps                 = BumpFactory::get_all(); // 'any' status by default
		$processed_attachments = array();

		foreach ( $bumps as $bump ) {
			$offer_product = $bump->get_offer_product();

			if ( ! $offer_product ) {
				continue;
			}

			$attachment_id = $offer_product->get_image_id();

			if ( ! $attachment_id || in_array( $attachment_id, $processed_attachments, true ) ) {
				continue;
			}

			$this->regenerate_order_bump_thumbnail( $attachment_id );
			$processed_attachments[] = $attachment_id;
		}
	}

	public function update_1103() {
		if ( ! class_exists( '\\Objectiv\\Plugins\\Checkout\\Features\\ABTesting' ) ) {
			return;
		}

		ABTesting::map_capabilities();
	}

	private function regenerate_order_bump_thumbnail( int $attachment_id ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! $metadata ) {
			return;
		}

		// Skip if size already exists
		if ( isset( $metadata['sizes']['cfw_order_bump_thumb'] ) ) {
			return;
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		// Get the registered size dimensions
		$size_data = wp_get_registered_image_subsizes();

		if ( ! isset( $size_data['cfw_order_bump_thumb'] ) ) {
			return;
		}

		$width  = $size_data['cfw_order_bump_thumb']['width'];
		$height = $size_data['cfw_order_bump_thumb']['height'];
		$crop   = $size_data['cfw_order_bump_thumb']['crop'];

		// Use WordPress's built-in function to create the intermediate size
		$resized = image_make_intermediate_size( $file_path, $width, $height, $crop );

		if ( ! $resized ) {
			wc_get_logger()->warning(
				sprintf( 'CheckoutWC: Failed to regenerate Order Bump thumbnail for attachment %d', $attachment_id ),
				array( 'source' => 'checkout-wc' )
			);
			return;
		}

		// Update metadata with new size
		$metadata['sizes']['cfw_order_bump_thumb'] = $resized;
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}
}
