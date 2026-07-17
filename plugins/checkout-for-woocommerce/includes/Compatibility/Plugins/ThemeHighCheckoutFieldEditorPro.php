<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class ThemeHighCheckoutFieldEditorPro extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'THWCFE_VERSION' );
	}

	public function pre_init() {
		add_filter( 'cfw_admin_integrations_checkbox_fields', array( $this, 'admin_integration_settings' ) );
		add_filter(
			'thwcfe_hidden_fields_display_position',
			array(
				$this,
				'thwcfe_hidden_fields_display_position',
			),
			1000
		);

		// Use both of these filters - the first one isn't sufficient by itself and seems to be a legacy holdover
		// But it's the only way to modify the display position select in settings
		add_filter( 'thwcfe_custom_section_positions', array( $this, 'custom_section_display_positions' ) );

		// This one is required to actually render the custom section
		add_filter( 'thwcfe_custom_section_display_positions', array( $this, 'custom_section_display_positions' ) );
	}

	public function run() {
		add_filter( 'thwcfe_public_script_deps', array( $this, 'cleanup_select_woo' ), 1000 );

		// Stop modifying address fields
		$hp_cf    = apply_filters( 'thwcfd_woocommerce_checkout_fields_hook_priority', 1000 ); // phpcs:ignore
		$instance = cfw_get_hook_instance_object( 'woocommerce_billing_fields', 'woo_billing_fields', $hp_cf );

		if ( $instance && SettingsManager::instance()->get_setting( 'allow_thcfe_address_modification' ) !== 'yes' ) {
			remove_filter( 'woocommerce_billing_fields', array( $instance, 'woo_billing_fields' ), $hp_cf );
			remove_filter( 'woocommerce_shipping_fields', array( $instance, 'woo_shipping_fields' ), $hp_cf );
		}
	}

	public function cleanup_select_woo( $deps ) {
		$key = array_search( 'selectWoo', $deps, true );

		if ( $key ) {
			unset( $deps[ $key ] );
		}

		return $deps;
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
			'name'          => 'allow_thcfe_address_modification',
			'label'         => __( 'Enable ThemeHigh Checkout Field Editor address field overrides', 'checkout-wc' ),
			'description'   => __( 'Allow ThemeHigh Checkout Field Editor to modify billing and shipping address fields. (Not Recommended)', 'checkout-wc' ),
			'initial_value' => SettingsManager::instance()->get_setting( 'allow_thcfe_address_modification' ) === 'yes',
		);

		return $integrations;
	}

	public function thwcfe_hidden_fields_display_position(): string {
		if ( $this->is_available() ) {
			return 'cfw_checkout_customer_info_tab';
		}
	}

	public function custom_section_display_positions( $positions ) {
		unset( $positions['before_customer_details'] );
		unset( $positions['after_customer_details'] );

		$positions['cfw_checkout_after_customer_info_address'] = 'CheckoutWC: After customer information step address';

		return $positions;
	}
}
