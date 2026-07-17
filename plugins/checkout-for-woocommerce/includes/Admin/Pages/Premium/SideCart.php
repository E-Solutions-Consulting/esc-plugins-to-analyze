<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages\Premium;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * Side Cart Admin Page
 *
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class SideCart extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Side Cart', 'checkout-wc' ), 'cfw_manage_side_cart', 'side-cart' );
	}

	public function output() {
		?>
		<div id="cfw-admin-pages-side-cart"></div>
		<?php
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$icon_options = [];

		foreach ( glob( CFW_PATH . '/assets/images/cart-icons/*.svg' ) as $icon_filename ) {
			$icon_options[ basename( $icon_filename ) ] = file_get_contents( $icon_filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		$this->set_script_data(
			[
				'settings' => [
					'enable_side_cart'                     => SettingsManager::instance()->get_setting( 'enable_side_cart' ) === 'yes',
					'side_cart_icon'                       => SettingsManager::instance()->get_setting( 'side_cart_icon' ),
					'side_cart_custom_icon_attachment_id'  => SettingsManager::instance()->get_setting( 'side_cart_custom_icon_attachment_id' ),
					'side_cart_icon_color'                 => SettingsManager::instance()->get_setting( 'side_cart_icon_color' ),
					'side_cart_icon_width'                 => SettingsManager::instance()->get_setting( 'side_cart_icon_width' ),
					'enable_floating_cart_button'          => SettingsManager::instance()->get_setting( 'enable_floating_cart_button' ) === 'yes',
					'floating_cart_button_right_position'  => SettingsManager::instance()->get_setting( 'floating_cart_button_right_position' ),
					'floating_cart_button_bottom_position' => SettingsManager::instance()->get_setting( 'floating_cart_button_bottom_position' ),
					'hide_floating_cart_button_empty_cart' => SettingsManager::instance()->get_setting( 'hide_floating_cart_button_empty_cart' ) === 'yes',
					'shake_floating_cart_button'           => SettingsManager::instance()->get_setting( 'shake_floating_cart_button' ) === 'yes',
					'enable_ajax_add_to_cart'              => SettingsManager::instance()->get_setting( 'enable_ajax_add_to_cart' ) === 'yes',
					'enable_side_cart_payment_buttons'     => SettingsManager::instance()->get_setting( 'enable_side_cart_payment_buttons' ) === 'yes',
					'enable_order_bumps_on_side_cart'      => SettingsManager::instance()->get_setting( 'enable_order_bumps_on_side_cart' ) === 'yes',
					'enable_side_cart_suggested_products'  => SettingsManager::instance()->get_setting( 'enable_side_cart_suggested_products' ) === 'yes',
					'side_cart_suggested_products_heading' => SettingsManager::instance()->get_setting( 'side_cart_suggested_products_heading' ),
					'enable_side_cart_suggested_products_random_fallback' => SettingsManager::instance()->get_setting( 'enable_side_cart_suggested_products_random_fallback' ) === 'yes',
					'side_cart_suggested_products_link_to_product' => SettingsManager::instance()->get_setting( 'side_cart_suggested_products_link_to_product' ) === 'yes',
					'allow_side_cart_item_variation_changes' => SettingsManager::instance()->get_setting( 'allow_side_cart_item_variation_changes' ) === 'yes',
					'show_side_cart_item_discount'         => SettingsManager::instance()->get_setting( 'show_side_cart_item_discount' ) === 'yes',
					'enable_promo_codes_on_side_cart'      => SettingsManager::instance()->get_setting( 'enable_promo_codes_on_side_cart' ) === 'yes',
					'enable_side_cart_totals'              => SettingsManager::instance()->get_setting( 'enable_side_cart_totals' ) === 'yes',
					'enable_side_cart_continue_shopping_button' => SettingsManager::instance()->get_setting( 'enable_side_cart_continue_shopping_button' ) === 'yes',
					'enable_free_shipping_progress_bar'    => SettingsManager::instance()->get_setting( 'enable_free_shipping_progress_bar' ) === 'yes',
					'side_cart_free_shipping_threshold'    => SettingsManager::instance()->get_setting( 'side_cart_free_shipping_threshold' ),
					'side_cart_amount_remaining_message'   => SettingsManager::instance()->get_setting( 'side_cart_amount_remaining_message' ),
					'side_cart_free_shipping_message'      => SettingsManager::instance()->get_setting( 'side_cart_free_shipping_message' ),
					'side_cart_free_shipping_progress_indicator_color' => SettingsManager::instance()->get_setting( 'side_cart_free_shipping_progress_indicator_color' ),
					'side_cart_free_shipping_progress_bg_color' => SettingsManager::instance()->get_setting( 'side_cart_free_shipping_progress_bg_color' ),
					'enable_free_shipping_progress_bar_at_checkout' => SettingsManager::instance()->get_setting( 'enable_free_shipping_progress_bar_at_checkout' ) === 'yes',
				],
				'params'   => [
					'icon_options'            => $icon_options,
					'default_free_shipping_progress_bar_color' => cfw_get_active_template()->get_default_setting( 'button_color' ),
					'custom_icon_preview_url' => wp_get_attachment_url( SettingsManager::instance()->get_setting( 'side_cart_custom_icon_attachment_id' ) ),
				],
				'plan'     => $this->get_plan_data(),
			]
		);
	}
}
