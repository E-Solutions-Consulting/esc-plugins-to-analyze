<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 */
class FetchifyAddressAutocomplete extends FeaturesAbstract {
	protected function run_if_cfw_is_enabled() {
		add_action( 'cfw_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'cfw_event_object', array( $this, 'add_localized_settings' ) );
		add_filter( 'cfw_enable_zip_autocomplete', '__return_false' );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'add_search_field' ), 100000 + 1 );
	}

	public function enqueue_scripts() {
		if ( ! cfw_is_checkout() ) {
			return;
		}

		wp_enqueue_script( 'cfw-fetchify', 'https://cc-cdn.com/generic/scripts/v1/cc_c2a.min.js', array( 'woocommerce' ), CFW_VERSION, true );
	}

	/**
	 * Add localized settings
	 *
	 * @param array $event_data The event data.
	 *
	 * @return array
	 */
	public function add_localized_settings( array $event_data ): array {
		$event_data['settings']['enable_fetchify_address_autocomplete'] = $this->enabled;

		/**
		 * Filter list of shipping country restrictions for Google Maps address autocomplete
		 *
		 * @param array $address_autocomplete_shipping_countries List of country restrictions for Google Maps address autocomplete
		 *
		 * @since 3.0.0
		 */
		$event_data['settings']['fetchify_address_autocomplete_countries'] = apply_filters( 'cfw_fetchify_address_autocomplete_countries', false );

		$event_data['settings']['fetchify_access_token'] = $this->settings_getter->get_setting( 'fetchify_access_token' );

		/**
		 * Filter whether to enable geolocation
		 *
		 * @param bool $enable_geolocation
		 *
		 * @since 5.3.2
		 */
		$event_data['settings']['fetchify_enable_geolocation'] = apply_filters( 'cfw_fetchify_address_autocomplete_enable_geolocation', true );

		/**
		 * Filter Fetchify address autocomplete default country
		 *
		 * @param string $default_country
		 *
		 * @since 5.3.2
		 */
		$event_data['settings']['fetchify_default_country'] = apply_filters( 'cfw_fetchify_address_autocomplete_default_country', 'gbr' );

		return $event_data;
	}

	public function add_search_field( $fields ) {
		$fields['fetchify_search'] = array(
			'label'       => __( 'Address Search', 'checkout-wc' ),
			'required'    => false,
			'input_class' => array(),
			'priority'    => 28,
			'columns'     => 12,
			'before_html' => sprintf( '<h4>%s</h4>', __( 'Search for your address', 'checkout-wc' ) ),
			'after_html'  => sprintf( '<a class="cfw-fetchify-enter-address-manually cfw-small" href="#">%s</a>', __( 'Or enter address manually.', 'checkout-wc' ) ) . '<hr class="cfw-fetchify-search-hr" />',
		);

		return $fields;
	}
}
