<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class BeaverThemer extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\FLThemeBuilderLayoutRenderer' );
	}

	public function pre_init() {
		add_filter( 'cfw_admin_integrations_checkbox_fields', array( $this, 'admin_integration_settings' ) );
	}

	public function run() {
		if ( SettingsManager::instance()->get_setting( 'enable_beaver_themer_support' ) === 'yes' ) {
			add_action( 'cfw_custom_header', 'FLThemeBuilderLayoutRenderer::render_header' );
			add_action( 'cfw_custom_footer', 'FLThemeBuilderLayoutRenderer::render_footer' );
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
			'name'          => 'enable_beaver_themer_support',
			'label'         => __( 'Enable Beaver Themer Support', 'checkout-wc' ),
			'description'   => __( 'Allow Beaver Themer to replace header and footer.', 'checkout-wc' ),
			'initial_value' => SettingsManager::instance()->get_setting( 'enable_beaver_themer_support' ) === 'yes',
		);

		return $integrations;
	}
}
