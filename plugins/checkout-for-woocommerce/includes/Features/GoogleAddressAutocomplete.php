<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Admin\Pages\AdminPagesRegistry;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 */
class GoogleAddressAutocomplete extends FeaturesAbstract {

	protected function run_if_cfw_is_enabled() {
		add_action( 'cfw_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'cfw_event_object', array( $this, 'add_localized_settings' ) );
		add_filter( 'cfw_enable_zip_autocomplete', '__return_false' );
	}

	public function enqueue_scripts() {
		if ( ! cfw_is_checkout() ) {
			return;
		}

		/**
		 * Whether to enable Google Maps compatibility mode
		 *
		 * @since 4.3.7
		 * @param bool $compatibility_mode Whether to enable Google Maps compatibility mode
		 */
		if ( apply_filters( 'cfw_google_maps_compatibility_mode', false ) ) {
			return;
		}

		$locale        = get_locale();
		$parsed_locale = strstr( $locale, '_', true );
		$language      = $parsed_locale ? $parsed_locale : $locale;

		/**
		 * Filter Google Maps language code
		 *
		 * @since 4.3.7
		 * @param string $lanugage_code Google Maps language code
		 */
		$language = apply_filters( 'cfw_google_maps_language_code', $language );

		$google_api_key = $this->settings_getter->get_setting( 'google_places_api_key' );

		wp_enqueue_script(
			'cfw-google-places',
			"https://maps.googleapis.com/maps/api/js?key={$google_api_key}&libraries=places&language={$language}&callback=cfw_google_maps_loaded&loading=async",
			array( 'woocommerce' ),
			CFW_VERSION,
			array(
				'in_footer' => true,
			)
		);
	}

	/**
	 * Add localized settings
	 *
	 * @param array $event_data The event data.
	 * @return array
	 */
	public function add_localized_settings( array $event_data ): array {
		$event_data['settings']['enable_address_autocomplete'] = true;

		/**
		 * Filter list of shipping country restrictions for Google Maps address autocomplete
		 *
		 * @since 3.0.0
		 *
		 * @param array $address_autocomplete_shipping_countries List of country restrictions for Google Maps address autocomplete
		 */
		$event_data['settings']['address_autocomplete_shipping_countries'] = apply_filters( 'cfw_address_autocomplete_shipping_countries', array() );

		/**
		 * Filter Google address autocomplete type
		 *
		 * @since 7.3.0
		 *
		 * @param string $autocomplete_type Google address autocomplete type
		 */
		$event_data['settings']['google_address_autocomplete_type'] = apply_filters( 'cfw_google_address_autocomplete_type', 'geocode|establishment' );

		return $event_data;
	}

	public function init() {
		parent::init();

		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_js_object' ), 1002 );
	}

	public function add_admin_js_object() {
		wp_localize_script(
			'cfw-admin-settings',
			'cfw_google_address_autocomplete',
			array(
				'google_api_key_settings_page_url' => AdminPagesRegistry::get( 'integrations' )->get_url(),
			)
		);
	}
}
