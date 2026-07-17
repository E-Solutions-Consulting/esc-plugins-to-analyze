<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Action\SmartyStreetsAddressValidationAction;
use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class SmartyStreets extends FeaturesAbstract {
	protected function run_if_cfw_is_enabled() {
		add_filter( 'cfw_event_object', array( $this, 'add_localized_settings' ) );
		add_action( 'cfw_checkout_after_main_container', array( $this, 'add_container_element' ) );
	}

	/**
	 * Add localized settings
	 *
	 * @param array $event_data The event data.
	 * @return array
	 */
	public function add_localized_settings( array $event_data ): array {
		/**
		 * Whether to enable Smarty integration
		 *
		 * @since 5.2.1
		 * @param bool $enable Whether to enable Smarty integration
		 */
		$event_data['settings']['enable_smartystreets_integration'] = apply_filters( 'cfw_enable_smartystreets_integration', true );

		return $event_data;
	}

	public function load_ajax_action() {
		( new SmartyStreetsAddressValidationAction( $this->settings_getter->get_setting( 'smartystreets_auth_id' ), $this->settings_getter->get_setting( 'smartystreets_auth_token' ) ) )->load();
	}

	public function add_container_element() {
		echo '<div id="cfw_smartystreets_confirm_modal_container"></div>';
	}
}
