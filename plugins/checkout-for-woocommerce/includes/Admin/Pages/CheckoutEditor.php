<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Model\Template;
use function WordpressEnqueueChunksPlugin\get as cfwChunkedScriptsConfigGet;

class CheckoutEditor extends PageAbstract {

	public function __construct() {
		parent::__construct( __( 'Checkout Editor', 'checkout-wc' ), 'cfw_manage_pages', 'checkout-editor' );
	}

	public function init() {
		parent::init();

		add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );
		add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar' ] );
		add_filter( 'admin_title', [ $this, 'filter_admin_title' ], 10, 2 );
	}

	public function add_body_class( $classes ) {
		if ( ! $this->is_current_page() ) {
			return $classes;
		}

		return $classes . ' cfw-checkout-editor-page';
	}

	public function hide_admin_bar( $show ) {
		if ( $this->is_current_page() ) {
			return false;
		}

		return $show;
	}

	public function filter_admin_title( $admin_title, $title ) {
		if ( ! $this->is_current_page() ) {
			return $admin_title;
		}

		$site_name = get_bloginfo( 'name', 'display' );

		return sprintf(
			/* translators: 1: Admin page title, 2: Network or site name. */
			__( '%1$s ‹ %2$s — WordPress' ),
			$this->title,
			$site_name
		);
	}

	public function setup_menu() {
		add_submenu_page( '', $this->title, $this->title, $this->capability, $this->slug, [ $this, 'output_with_wrap' ], $this->priority );
	}

	public function add_admin_bar_menu_node( \WP_Admin_Bar $admin_bar ) {
		// No admin bar node for the editor.
	}

	public function output_with_wrap() {
		cfw_do_action( 'cfw_admin_output_page', $this->get_slug() );
		?>
		<div id="cfw-checkout-editor"></div>
		<?php
	}

	public function output() {
		// Handled by output_with_wrap.
	}

	public function enqueue_scripts() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		wp_enqueue_media();

		cfw_register_scripts( [ 'admin-checkout-editor' ] );

		wp_localize_script(
			'cfw-admin-checkout-editor',
			'cfwAdminPagesData',
			$this->get_script_data()
		);

		wp_enqueue_script( 'cfw-admin-checkout-editor' );

		$front    = CFW_PATH_ASSETS;
		$manifest = cfwChunkedScriptsConfigGet( 'manifest' );

		if ( isset( $manifest['chunks']['admin-checkout-editor-styles']['file'] ) ) {
			wp_enqueue_style(
				'objectiv-cfw-admin-checkout-editor-styles',
				"{$front}/{$manifest['chunks']['admin-checkout-editor-styles']['file']}",
				[],
				$manifest['chunks']['admin-checkout-editor-styles']['hash']
			);
		}
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$settings_manager = SettingsManager::instance();
		$saved_slug       = cfw_get_active_template()->get_slug();

		// Optional: edit a specific template in the editor without changing the saved option (template only updates on Save).
		$requested_slug = isset( $_GET['cfw_editor_template'] ) ? sanitize_text_field( wp_unslash( $_GET['cfw_editor_template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all_slugs      = array_keys( Template::get_all_available() );
		$template_slug = ( $requested_slug && in_array( $requested_slug, $all_slugs, true ) ) ? $requested_slug : $saved_slug;

		// Editor-only settings: only the keys used by the editor sections (Template, Logo, Typography, Colors, Steps, Fields, Addresses, Cart Summary, Footer).
		$editor_settings = [];

		// When the editor is previewing a template other than the saved active one, always show that
		// template's defined defaults (consistent with the "Changing templates will update colors..." dialog).
		// This also fixes the Lite→Pro upgrade bug where Lite wrote standard fallback defaults for Pro
		// templates that didn't have their defaults.php, and add_option() won't overwrite those values.
		$template           = new Template( $template_slug );
		$template_defaults  = $template->get_default_settings();
		$is_active_template = ( $template_slug === $saved_slug );

		// Logo (template-scoped).
		$editor_settings[ $settings_manager->add_suffix( 'logo_attachment_id', [ $template_slug ] ) ] = $settings_manager->get_setting( 'logo_attachment_id', [ $template_slug ] );

		// Typography (template-scoped).
		if ( $is_active_template ) {
			$body_font_saved    = $settings_manager->get_setting( 'body_font', [ $template_slug ] );
			$heading_font_saved = $settings_manager->get_setting( 'heading_font', [ $template_slug ] );
			$editor_settings[ $settings_manager->add_suffix( 'body_font', [ $template_slug ] ) ]    = false !== $body_font_saved ? $body_font_saved : ( $template_defaults['body_font'] ?? '' );
			$editor_settings[ $settings_manager->add_suffix( 'heading_font', [ $template_slug ] ) ] = false !== $heading_font_saved ? $heading_font_saved : ( $template_defaults['heading_font'] ?? '' );
		} else {
			$editor_settings[ $settings_manager->add_suffix( 'body_font', [ $template_slug ] ) ]    = $template_defaults['body_font'] ?? '';
			$editor_settings[ $settings_manager->add_suffix( 'heading_font', [ $template_slug ] ) ] = $template_defaults['heading_font'] ?? '';
		}

		// Colors: body, buttons, breadcrumbs, cart_summary, header, footer (template-scoped).
		$color_section_ids  = [ 'body', 'buttons', 'breadcrumbs', 'cart_summary', 'header', 'footer' ];
		$raw_color_settings = Appearance::get_theme_color_settings( $template_slug );
		foreach ( $color_section_ids as $section_id ) {
			if ( isset( $raw_color_settings[ $section_id ]['settings'] ) ) {
				foreach ( array_keys( $raw_color_settings[ $section_id ]['settings'] ) as $key ) {
					if ( $is_active_template ) {
						$saved  = $settings_manager->get_setting( $key, [ $template_slug ] );
						$value  = false !== $saved ? $saved : ( $template_defaults[ $key ] ?? '' );
					} else {
						$value = $template_defaults[ $key ] ?? '';
					}
					$editor_settings[ $settings_manager->add_suffix( $key, [ $template_slug ] ) ] = $value;
				}
			}
		}

		// Steps.
		$editor_settings['skip_shipping_step']       = $settings_manager->get_setting( 'skip_shipping_step' ) === 'yes';
		$editor_settings['enable_order_review_step'] = $settings_manager->get_setting( 'enable_order_review_step' ) === 'yes';
		$editor_settings['enable_one_page_checkout'] = $settings_manager->get_setting( 'enable_one_page_checkout' ) === 'yes';

		// Fields (template-scoped: label_style; rest global).
		$editor_settings[ $settings_manager->add_suffix( 'label_style', [ $template_slug ] ) ] = $settings_manager->get_setting( 'label_style', [ $template_slug ] );
		$editor_settings['wp_option/woocommerce_checkout_phone_field'] = get_option( 'woocommerce_checkout_phone_field', 'required' );
		$editor_settings['enable_order_notes']                         = $settings_manager->get_setting( 'enable_order_notes' ) === 'yes';
		$editor_settings['enable_coupon_code_link']                    = $settings_manager->get_setting( 'enable_coupon_code_link' ) === 'yes';
		$editor_settings['hide_optional_address_fields_behind_link']   = $settings_manager->get_setting( 'hide_optional_address_fields_behind_link' ) === 'yes';
		$editor_settings['enable_discreet_address_1_fields']           = $settings_manager->get_setting( 'enable_discreet_address_1_fields' ) === 'yes';
		$editor_settings['discreet_address_1_fields_order']            = $settings_manager->get_setting( 'discreet_address_1_fields_order' );
		$editor_settings['use_fullname_field']                         = $settings_manager->get_setting( 'use_fullname_field' ) === 'yes';
		$editor_settings['enable_highlighted_countries']               = $settings_manager->get_setting( 'enable_highlighted_countries' ) === 'yes';
		$editor_settings['highlighted_countries']                      = $settings_manager->get_setting( 'highlighted_countries' );

		// Addresses.
		$editor_settings['force_different_billing_address'] = $settings_manager->get_setting( 'force_different_billing_address' ) === 'yes';
		$editor_settings['enabled_billing_address_fields']   = $settings_manager->get_setting( 'enabled_billing_address_fields' );

		// Cart Summary.
		$editor_settings['enable_cart_editing']                         = $settings_manager->get_setting( 'enable_cart_editing' ) === 'yes';
		$editor_settings['allow_checkout_cart_item_variation_changes']  = $settings_manager->get_setting( 'allow_checkout_cart_item_variation_changes' ) === 'yes';
		$editor_settings['show_item_remove_button']                     = $settings_manager->get_setting( 'show_item_remove_button' ) === 'yes';
		$editor_settings['cart_edit_empty_cart_redirect']               = $settings_manager->get_setting( 'cart_edit_empty_cart_redirect' );
		$editor_settings['enable_sticky_cart_summary']                  = $settings_manager->get_setting( 'enable_sticky_cart_summary' ) === 'yes';
		$editor_settings['show_cart_item_discount']                     = $settings_manager->get_setting( 'show_cart_item_discount' ) === 'yes';
		$editor_settings['cart_item_link']                              = $settings_manager->get_setting( 'cart_item_link' );
		$editor_settings['cart_item_data_display']                      = $settings_manager->get_setting( 'cart_item_data_display' );

		// Badges.
		$editor_settings['enable_trust_badges']     = $settings_manager->get_setting( 'enable_trust_badges' ) === 'yes';
		$editor_settings['trust_badge_position']    = $settings_manager->get_setting( 'trust_badge_position' );
		$editor_settings['trust_badges_title']      = $settings_manager->get_setting( 'trust_badges_title' );
		$editor_settings['enable_wc_review_badges'] = $settings_manager->get_setting( 'enable_wc_review_badges' ) === 'yes';
		$editor_settings['wc_review_source']        = $settings_manager->get_setting( 'wc_review_source' );
		$editor_settings['wc_review_min_rating']    = $settings_manager->get_setting( 'wc_review_min_rating' );
		$editor_settings['wc_review_limit']         = (int) $settings_manager->get_setting( 'wc_review_limit' );

		// Footer (template-scoped + mode).
		$editor_settings[ $settings_manager->add_suffix( 'footer_text', [ $template_slug ] ) ] = $settings_manager->get_setting( 'footer_text', [ $template_slug ] );
		$editor_settings['footer_text_editor_mode'] = $settings_manager->get_setting( 'footer_text_editor_mode' );

		// Go Live (activation toggle, same setting as CheckoutWC > Start Here).
		$editor_settings['enable'] = $settings_manager->get_setting( 'enable' ) === 'yes';

		// Express Checkout (same as CheckoutWC > Express Checkout page).
		$editor_settings['disable_express_checkout'] = $settings_manager->get_setting( 'disable_express_checkout' ) === 'yes';

		// Color defaults for reset/preview (only for body, buttons, breadcrumbs, cart_summary, header, footer).
		$all_color_defaults = Appearance::get_theme_color_settings_defaults( $template_slug );
		$color_settings_defaults = [];
		foreach ( $color_section_ids as $section_id ) {
			if ( isset( $raw_color_settings[ $section_id ]['settings'] ) ) {
				foreach ( array_keys( $raw_color_settings[ $section_id ]['settings'] ) as $key ) {
					if ( isset( $all_color_defaults[ $key ] ) ) {
						$color_settings_defaults[ $key ] = $all_color_defaults[ $key ];
					}
				}
			}
		}

		// When previewing a non-active template, pre-populate the preview transient with the
		// template's correct defaults so the preview iframe shows the right colors on first load
		// (before FormObserver fires). Without this the iframe falls back to stale DB values.
		if ( ! $is_active_template ) {
			$preview_transient = [];
			foreach ( $color_section_ids as $section_id ) {
				if ( isset( $raw_color_settings[ $section_id ]['settings'] ) ) {
					foreach ( array_keys( $raw_color_settings[ $section_id ]['settings'] ) as $key ) {
						$preview_transient[ $settings_manager->add_suffix( $key, [ $template_slug ] ) ] = $template_defaults[ $key ] ?? '';
					}
				}
			}
			$preview_transient[ $settings_manager->add_suffix( 'body_font', [ $template_slug ] ) ]    = $template_defaults['body_font'] ?? '';
			$preview_transient[ $settings_manager->add_suffix( 'heading_font', [ $template_slug ] ) ] = $template_defaults['heading_font'] ?? '';
			set_transient( '_cfw_editor_preview_' . get_current_user_id(), $preview_transient, 30 * MINUTE_IN_SECONDS );
		}

		$appearance_instance = new Appearance();

		// Determine where the Close button should send the user.
		$default_close_url = add_query_arg(
			[
				'page'    => 'cfw-settings-checkout',
				'subpage' => 'checkout',
			],
			admin_url( 'admin.php' )
		);

		$editor_base_url = add_query_arg(
			[
				'page' => $this->get_slug(),
			],
			admin_url( 'admin.php' )
		);

		// Preserve the original admin page the user came from across editor reloads.
		$return_param_key = 'cfw_editor_return';
		$encoded_return   = isset( $_GET[ $return_param_key ] ) ? wp_unslash( $_GET[ $return_param_key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$original_return  = $encoded_return ? esc_url_raw( rawurldecode( $encoded_return ) ) : '';

		if ( $original_return && false !== strpos( $original_return, admin_url() ) && false === strpos( $original_return, $editor_base_url ) ) {
			$close_url = $original_return;
		} else {
			$referer = wp_get_referer();

			if ( $referer && false !== strpos( $referer, admin_url() ) && false === strpos( $referer, $editor_base_url ) ) {
				// First load from another admin page: use that as the return URL.
				$close_url = $referer;
			} else {
				// Direct access or referer is the editor itself: fall back to Checkout settings.
				$close_url = $default_close_url;
			}
		}

		// Build preview URL: find a purchasable product for add-to-cart preview.
		$preview_url = wc_get_checkout_url();
		$product     = null;

		$simple_query = new \WC_Product_Query(
			[
				'limit'        => 10,
				'status'       => 'publish',
				'stock_status' => 'instock',
				'type'         => [ 'simple' ],
			]
		);
		$candidates = $simple_query->get_products();
		foreach ( $candidates as $p ) {
			if ( $p->is_purchasable() ) {
				$product = $p;
				break;
			}
		}

		if ( ! $product ) {
			$variation_query = new \WC_Product_Query(
				[
					'limit'        => 10,
					'status'       => 'publish',
					'stock_status' => 'instock',
					'type'         => [ 'variation' ],
				]
			);
			$candidates = $variation_query->get_products();
			foreach ( $candidates as $p ) {
				if ( $p->is_purchasable() ) {
					$product = $p;
					break;
				}
			}
		}

		$has_products = $product !== null;

		if ( $has_products ) {
			$preview_url = add_query_arg( [ 'add-to-cart' => $product->get_id() ], $preview_url );
		}

		$preview_nonce = wp_create_nonce( 'cfw-editor-preview' );
		$preview_url   = add_query_arg(
			[
				'cfw-editor-preview'  => '1',
				'_cfw_preview_nonce'  => $preview_nonce,
				'cfw-preview'         => $template_slug,
			],
			$preview_url
		);

		$countries = WC()->countries->countries;
		asort( $countries );

		// Templates data for the editor (for switching templates from the Design tab).
		$templates        = Template::get_all_available();
		$editor_templates = [];
		$has_premium_plan = PlanManager::has_premium_plan_or_higher();

		foreach ( $templates as $template ) {
			$slug = $template->get_slug();

			$editor_templates[] = [
				'slug'   => $slug,
				'name'   => $template->get_name(),
				'active' => $slug === $template_slug,
				'locked' => ! $has_premium_plan && $slug !== $template_slug,
			];
		}

		// Ensure template switches keep sending the user back to the same place.
		$editor_url = add_query_arg(
			[
				'page'             => $this->get_slug(),
				$return_param_key  => rawurlencode( $close_url ),
			],
			admin_url( 'admin.php' )
		);

		$this->set_script_data(
			[
				'editor_settings' => [
					'settings' => $editor_settings,
					'params'   => [
						'font_options'              => $appearance_instance->get_font_settings(),
						'template_path'             => $template_slug,
						'color_settings'            => Appearance::get_theme_color_settings( $template_slug ),
						'color_settings_defaults'   => $color_settings_defaults,
						'logo_preview_url'          => wp_get_attachment_url( $settings_manager->get_setting( 'logo_attachment_id', [ $template_slug ] ) ),
						'countries'                => $countries,
						'conditional_settings'      => [
							'order_notes_enable' => ! has_filter( 'woocommerce_enable_order_notes_field' ) || ( $settings_manager->get_setting( 'enable_order_notes' ) === 'yes' && 1 === cfw_count_filters( 'woocommerce_enable_order_notes_field' ) ),
						],
						'express_checkout_gateways' => apply_filters( 'cfw_detected_gateways', [] ),
						'requires_license'          => defined( 'CFW_PREMIUM_PLAN_IDS' ),
					],
				],
				'preview_url'          => $preview_url,
				'has_products'         => $has_products,
				'close_url'            => $close_url,
				'editor_url'           => $editor_url,
				'saved_active_template' => $saved_slug,
				'admin_url'            => admin_url( 'admin.php' ),
				'editor_logo_url' => CFW_PATH_URL_BASE . 'assets/images/cfw.svg',
				'plan'            => $this->get_plan_data(),
				'templates'       => $editor_templates,
			]
		);
	}
}
