<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use ElementorPro\Modules\Woocommerce\Module;
use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use ElementorPro\Modules\ThemeBuilder\Module as Theme_Builder_Module;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class ElementorPro extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	public function pre_init() {
		add_filter( 'cfw_admin_integrations_checkbox_fields', array( $this, 'admin_integration_settings' ) );

		add_action( 'cfw_permissioned_init', array( $this, 'prevent_thank_you_page_override' ) );
	}

	public function prevent_thank_you_page_override() {
		if ( ! PlanManager::can_access_feature( 'enable_thank_you_page', 'plus' ) ) {
			return;
		}

		if ( ! class_exists( '\ElementorPro\Modules\Woocommerce\Module' ) ) {
			return;
		}

		$instance = Module::instance();

		if ( ! $instance ) {
			return;
		}

		remove_filter( 'woocommerce_get_endpoint_url', array( $instance, 'get_order_received_endpoint_url' ), 10 );
	}

	public function run() {
		$this->maybe_load_elementor_header_footer();
	}

	public function run_on_thankyou() {
		$this->maybe_load_elementor_header_footer();
	}

	public function maybe_load_elementor_header_footer() {
		if ( SettingsManager::instance()->get_setting( 'enable_elementor_pro_support' ) === 'yes' ) {

			/**
			 * Theme_Builder_Module instance
			 *
			 * @var Theme_Builder_Module $theme_builder_module
			 */
			$theme_builder_module = Theme_Builder_Module::instance();

			$header_documents_by_conditions = $theme_builder_module->get_conditions_manager()->get_documents_for_location( 'header' );
			$footer_documents_by_conditions = $theme_builder_module->get_conditions_manager()->get_documents_for_location( 'footer' );

			if ( ! empty( $header_documents_by_conditions ) ) {
				add_action(
					'cfw_custom_header',
					function () {
						elementor_theme_do_location( 'header' );
					}
				);
			}

			if ( ! empty( $footer_documents_by_conditions ) ) {
				add_action(
					'cfw_custom_footer',
					function () {
						elementor_theme_do_location( 'footer' );
					}
				);
			}
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
			'name'          => 'enable_elementor_pro_support',
			'label'         => __( 'Enable Elementor Pro support.', 'checkout-wc' ),
			'description'   => __( 'Allow Elementor Pro to replace header and footer.', 'checkout-wc' ),
			'initial_value' => SettingsManager::instance()->get_setting( 'enable_elementor_pro_support' ) === 'yes',
		);

		return $integrations;
	}
}
