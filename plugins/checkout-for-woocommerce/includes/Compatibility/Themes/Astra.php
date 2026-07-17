<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class Astra extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'ASTRA_THEME_VERSION' );
	}

	public function pre_init() {
		add_filter( 'cfw_admin_integrations_checkbox_fields', array( $this, 'admin_integration_settings' ) );
	}

	public function run() {
		$astra_support_enabled = SettingsManager::instance()->get_setting( 'enable_astra_support' ) === 'yes';

		if ( ! $astra_support_enabled ) {
			$this->remove_astra_scripts();
		}

		if ( $astra_support_enabled ) {
			add_action( 'cfw_custom_header', array( $this, 'astra_header' ) );
			add_action( 'cfw_custom_footer', array( $this, 'astra_footer' ) );

			// Allow Astra's styles and scripts to load (prevent them from being blocked).
			remove_filter( 'cfw_blocked_style_handles', 'cfw_remove_theme_styles', 10 );
			remove_filter( 'cfw_blocked_script_handles', 'cfw_remove_theme_scripts', 10 );

			// Allow Astra Addon to load its assets.
			add_filter( 'astra_addon_enqueue_assets', '__return_true', 100 );
		}

		// Only dequeue Astra styles when using Distraction Free Portal without Astra Support.
		$is_distraction_free = SettingsManager::instance()->get_setting( 'template_loader' ) === 'redirect';

		if ( $is_distraction_free && ! $astra_support_enabled ) {
			add_action(
				'wp_enqueue_scripts',
				function () {
					wp_dequeue_style( 'astra-theme-css' );
					wp_dequeue_style( 'astra-addon-css' );
				},
				99
			);
		}
	}

	public function run_on_thankyou() {
		$this->run();
	}

	public function astra_header() {
		astra_header_before();

		astra_header();

		astra_header_after();
	}

	public function astra_footer() {
		astra_footer_before();

		astra_footer();

		astra_footer_after();
	}

	public function remove_astra_scripts() {
		if ( cfw_is_checkout() ) {
			remove_all_actions( 'astra_get_js_files' );
		}
	}

	/**
	 * Add the admin settings
	 *
	 * @param array $integrations The integrations.
	 *
	 * @return array
	 */
	public function admin_integration_settings( array $integrations ): array {
		if ( ! $this->is_available() ) {
			return $integrations;
		}

		$integrations[] = array(
			'name'          => 'enable_astra_support',
			'label'         => __( 'Enable Astra Support (Beta)', 'checkout-wc' ),
			'description'   => __( 'If enabled with Advanced > Template Loader > Distraction Free Portal, Astra\'s header and footer are displayed and Astra styles and scripts load on checkout. If disabled with Distraction Free Portal, Astra styles and scripts are excluded for a minimal layout. With WordPress Theme loader, the full theme loads regardless of this setting.', 'checkout-wc' ),
			'initial_value' => SettingsManager::instance()->get_setting( 'enable_astra_support' ) === 'yes',
		);

		return $integrations;
	}
}
