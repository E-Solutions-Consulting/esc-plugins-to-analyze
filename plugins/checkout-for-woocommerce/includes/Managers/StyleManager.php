<?php

namespace Objectiv\Plugins\Checkout\Managers;

use Objectiv\Plugins\Checkout\GoogleFontsURLGenerator;

/**
 * Handle CSS custom properties and custom styles
 *
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Managers
 */
class StyleManager {
	public static $excluded_fonts = array( 'System Font Stack', 'inter-cfw' );

	/**
	 * Enqueues Google Fonts using the GoogleFontsURLGenerator class.
	 *
	 * This function identifies the body and heading fonts from settings,
	 * creates a filterable configuration array, and then uses the
	 * GoogleFontsURLGenerator to build a single, optimized URL for
	 * enqueuing.
	 *
	 * @uses GoogleFontsURLGenerator To build the font URL.
	 */
	public static function queue_custom_font_includes() {
		$template         = cfw_get_active_template();
		$settings_manager = SettingsManager::instance();
		$body_font        = $settings_manager->get_setting( 'body_font', array( $template->get_slug() ) );
		$heading_font     = $settings_manager->get_setting( 'heading_font', array( $template->get_slug() ) );

		$font_configs = array();

		$unique_fonts = array_unique( array_filter( array( $body_font, $heading_font ) ) );

		foreach ( $unique_fonts as $font ) {
			if ( in_array( $font, self::$excluded_fonts, true ) ) {
				continue;
			}

			// Use the font family name as the key. This provides a predictable
			// key for the filter and prevents duplicate configurations.
			$font_configs[ $font ] = array(
				'family'  => $font,
				'weights' => array( '400', '700' ),
				'italic'  => true,
			);
		}

		/**
		 * Filter the Google Font configurations before generating the URL.
		 *
		 * This is the primary filter for customizing font loading. You can add,
		 * remove, or modify font configurations.
		 *
		 * @param array $font_configs An associative array of font configurations,
		 * keyed by the font family name (e.g., 'Open Sans').
		 *
		 * Example:
		 * $font_configs[ $font_name ] = array(
		 *    'family'  => $font_name,
		 *    'weights' => array( '400', '700' ),
		 *    'italic'  => true,
		 * );
		 *
		 * @since 10.1.16
		 */
		$font_configs = apply_filters( 'cfw_google_font_configurations', $font_configs );

		// If there are no fonts to load after filtering, exit.
		if ( empty( $font_configs ) ) {
			return;
		}

		$font_generator = new GoogleFontsURLGenerator();

		// The generator processes the configuration. The array keys are discarded
		// by array_values() as the generator organizes fonts by the 'family' property internally.
		$font_generator->addFonts( array_values( $font_configs ) );

		/**
		 * Filter the font-display property for the Google Fonts URL.
		 *
		 * @since 10.1.16
		 *
		 * @param string $display The CSS font-display property.
		 * Accepts 'auto', 'block', 'swap', 'fallback', 'optional'.
		 */
		$display = apply_filters( 'cfw_google_font_display', 'swap' );
		$font_generator->setDisplay( $display );

		$font_url = $font_generator->getUrl();

		if ( empty( $font_url ) ) {
			return;
		}

		// Version must be null or WP will clobber duplicate GET keys for multiple fonts (i.e., family=foo&family=bar)
		wp_enqueue_style( 'cfw-google-fonts', $font_url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	public static function get_css_custom_property_overrides(): string {
		$settings_manager                  = SettingsManager::instance();
		$active_template                   = cfw_get_active_template();
		$active_theme                      = $active_template->get_slug();
		$body_background_color             = $settings_manager->get_setting( 'body_background_color', array( $active_theme ) );
		$body_text_color                   = $settings_manager->get_setting( 'body_text_color', array( $active_theme ) );
		$body_font                         = $settings_manager->get_setting( 'body_font', array( $active_template->get_slug() ) );
		$heading_font                      = $settings_manager->get_setting( 'heading_font', array( $active_template->get_slug() ) );
		$header_background_color           = $settings_manager->get_setting( 'header_background_color', array( $active_theme ) );
		$footer_background_color           = $settings_manager->get_setting( 'footer_background_color', array( $active_theme ) );
		$summary_bg_color                  = $settings_manager->get_setting( 'summary_background_color', array( $active_theme ) );
		$summary_mobile_bg_color           = $settings_manager->get_setting( 'summary_mobile_background_color', array( $active_theme ) );
		$summary_text_color                = $settings_manager->get_setting( 'summary_text_color', array( $active_theme ) );
		$summary_link_color                = $settings_manager->get_setting( 'summary_link_color', array( $active_theme ) );
		$header_text_color                 = $settings_manager->get_setting( 'header_text_color', array( $active_theme ) );
		$footer_text_color                 = $settings_manager->get_setting( 'footer_color', array( $active_theme ) );
		$body_link_color                   = $settings_manager->get_setting( 'link_color', array( $active_theme ) );
		$primary_button_bg_color           = $settings_manager->get_setting( 'button_color', array( $active_theme ) );
		$primary_button_text_color         = $settings_manager->get_setting( 'button_text_color', array( $active_theme ) );
		$primary_button_hover_bg_color     = $settings_manager->get_setting( 'button_hover_color', array( $active_theme ) );
		$primary_button_hover_text_color   = $settings_manager->get_setting( 'button_text_hover_color', array( $active_theme ) );
		$secondary_button_bg_color         = $settings_manager->get_setting( 'secondary_button_color', array( $active_theme ) );
		$secondary_button_text_color       = $settings_manager->get_setting( 'secondary_button_text_color', array( $active_theme ) );
		$secondary_button_hover_bg_color   = $settings_manager->get_setting( 'secondary_button_hover_color', array( $active_theme ) );
		$secondary_button_hover_text_color = $settings_manager->get_setting( 'secondary_button_text_hover_color', array( $active_theme ) );
		$cart_item_background_color        = $settings_manager->get_setting( 'cart_item_quantity_color', array( $active_theme ) );
		$cart_item_text_color              = $settings_manager->get_setting( 'cart_item_quantity_text_color', array( $active_theme ) );
		$breadcrumb_completed_text_color   = $settings_manager->get_setting( 'breadcrumb_completed_text_color', array( $active_theme ) );
		$breadcrumb_current_text_color     = $settings_manager->get_setting( 'breadcrumb_current_text_color', array( $active_theme ) );
		$breadcrumb_next_text_color        = $settings_manager->get_setting( 'breadcrumb_next_text_color', array( $active_theme ) );
		$breadcrumb_completed_accent_color = $settings_manager->get_setting( 'breadcrumb_completed_accent_color', array( $active_theme ) );
		$breadcrumb_current_accent_color   = $settings_manager->get_setting( 'breadcrumb_current_accent_color', array( $active_theme ) );
		$breadcrumb_next_accent_color      = $settings_manager->get_setting( 'breadcrumb_next_accent_color', array( $active_theme ) );
		$logo_url                          = cfw_get_logo_url();

		if ( in_array( $body_font, self::$excluded_fonts, true ) ) {
			switch ( $body_font ) {
				case 'inter-cfw':
					$body_font = 'var(--cfw-inter-font-family)';
					break;
				case 'System Font Stack':
					$body_font = false;
					break;
			}
		}

		if ( in_array( $heading_font, self::$excluded_fonts, true ) ) {
			switch ( $heading_font ) {
				case 'inter-cfw':
					$heading_font = 'var(--cfw-inter-font-family)';
					break;
				case 'System Font Stack':
					$heading_font = false;
					break;
			}
		}

		/**
		 * Filter the CSS custom property overrides
		 *
		 * @since 5.0.0
		 * @param array $overrides The CSS custom properties
		 */
		$custom_properties = apply_filters(
			'cfw_custom_css_properties',
			array(
				'--cfw-body-background-color'              => $body_background_color,
				'--cfw-body-text-color'                    => $body_text_color,
				'--cfw-body-font-family'                   => $body_font,
				'--cfw-heading-font-family'                => $heading_font,
				'--cfw-header-background-color'            => $active_template->supports( 'header-background' ) ? $header_background_color : $body_background_color,
				'--cfw-header-bottom-margin'               => strtolower( $header_background_color ) !== strtolower( $body_background_color ) ? '2em' : false,
				'--cfw-footer-background-color'            => $active_template->supports( 'footer-background' ) ? $footer_background_color : $body_background_color,
				'--cfw-footer-top-margin'                  => '#ffffff' !== strtolower( $footer_background_color ) ? '2em' : false,
				'--cfw-cart-summary-background-color'      => $active_template->supports( 'summary-background' ) ? $summary_bg_color : false,
				'--cfw-cart-summary-mobile-background-color' => $summary_mobile_bg_color,
				'--cfw-cart-summary-text-color'            => $active_template->supports( 'summary-background' ) ? $summary_text_color : false,
				'--cfw-cart-summary-link-color'            => $summary_link_color,
				'--cfw-header-text-color'                  => $header_text_color,
				'--cfw-footer-text-color'                  => $footer_text_color,
				'--cfw-body-link-color'                    => $body_link_color,
				'--cfw-buttons-primary-background-color'   => $primary_button_bg_color,
				'--cfw-buttons-primary-text-color'         => $primary_button_text_color,
				'--cfw-buttons-primary-hover-background-color' => $primary_button_hover_bg_color,
				'--cfw-buttons-primary-hover-text-color'   => $primary_button_hover_text_color,
				'--cfw-buttons-secondary-background-color' => $secondary_button_bg_color,
				'--cfw-buttons-secondary-text-color'       => $secondary_button_text_color,
				'--cfw-buttons-secondary-hover-background-color' => $secondary_button_hover_bg_color,
				'--cfw-buttons-secondary-hover-text-color' => $secondary_button_hover_text_color,
				'--cfw-cart-summary-item-quantity-background-color' => $cart_item_background_color,
				'--cfw-cart-summary-item-quantity-text-color' => $cart_item_text_color,
				'--cfw-breadcrumb-completed-text-color'    => $breadcrumb_completed_text_color,
				'--cfw-breadcrumb-current-text-color'      => $breadcrumb_current_text_color,
				'--cfw-breadcrumb-next-text-color'         => $breadcrumb_next_text_color,
				'--cfw-breadcrumb-completed-accent-color'  => $breadcrumb_completed_accent_color,
				'--cfw-breadcrumb-current-accent-color'    => $breadcrumb_current_accent_color,
				'--cfw-breadcrumb-next-accent-color'       => $breadcrumb_next_accent_color,
				'--cfw-logo-url'                           => "url({$logo_url})",
			)
		);

		$output = ':root, body { ' . PHP_EOL;

		foreach ( $custom_properties as $custom_property => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$output .= "	{$custom_property}: {$value};" . PHP_EOL;
		}

		$output .= ' }' . PHP_EOL;

		return $output;
	}

	public static function get_custom_css(): string {
		$settings_manager  = SettingsManager::instance();
		$active_template   = cfw_get_active_template();
		$custom_css        = $settings_manager->get_setting( 'custom_css', array( $active_template->get_slug() ) );
		$show_mobile_logos = $settings_manager->get_setting( 'show_logos_mobile' );

		$output = 'html { background: var(--cfw-body-background-color) !important; }' . PHP_EOL;

		if ( 'yes' === $show_mobile_logos ) {
			$output .= '@media(max-width: 900px) { form #cfw-billing-methods .payment_method_icons { display: flex !important; } }';
		}

		if ( ! empty( $custom_css ) ) {
			$output .= $custom_css;
		}

		return $output;
	}

	public static function add_styles( $handle = 'cfw_front' ) {
		wp_add_inline_style( $handle, self::get_css_custom_property_overrides() . self::get_custom_css() );
	}
}
