<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class ThemeHighCheckoutFieldEditor extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'THWCFD_VERSION' );
	}

	public function pre_init() {
		add_filter( 'cfw_admin_integrations_checkbox_fields', array( $this, 'admin_integration_settings' ) );
	}

	public function run() {
		// Stop modifying address fields
		// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
		$hp_billing_fields  = apply_filters( 'thwcfd_billing_fields_priority', 1000 );
		$hp_shipping_fields = apply_filters( 'thwcfd_shipping_fields_priority', 1000 );
		// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment

		$instance = cfw_get_hook_instance_object( 'woocommerce_billing_fields', 'billing_fields', $hp_billing_fields );

		if ( $instance && SettingsManager::instance()->get_setting( 'allow_thcfe_address_modification' ) !== 'yes' ) {
			remove_filter( 'woocommerce_billing_fields', array( $instance, 'billing_fields' ), $hp_billing_fields );
			remove_filter( 'woocommerce_shipping_fields', array( $instance, 'shipping_fields' ), $hp_shipping_fields );
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
			'name'          => 'allow_thcfe_address_modification',
			'label'         => __( 'Enable ThemeHigh Checkout Field Editor address field overrides.', 'checkout-wc' ),
			'description'   => __( 'Allow ThemeHigh Checkout Field Editor to modify billing and shipping address fields. (Not Recommended)', 'checkout-wc' ),
			'initial_value' => SettingsManager::instance()->get_setting( 'allow_thcfe_address_modification' ) === 'yes',
		);

		return $integrations;
	}
}
