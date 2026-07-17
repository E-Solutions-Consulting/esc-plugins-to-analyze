<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 */
class HideOptionalAddressFields extends FeaturesAbstract {
	protected function run_if_cfw_is_enabled() {
		add_filter( 'cfw_get_billing_checkout_fields', array( $this, 'hide_optional_billing_address_fields' ), 10, 2 );
		add_filter( 'cfw_get_shipping_checkout_fields', array( $this, 'hide_optional_shipping_address_fields' ), 10, 2 );
	}

	public function hide_optional_billing_address_fields( $fields ): array {
		return $this->hide_optional_address_fields( $fields, 'billing' );
	}

	public function hide_optional_shipping_address_fields( $fields ): array {
		return $this->hide_optional_address_fields( $fields, 'shipping' );
	}

	public function hide_optional_address_fields( array $fields, string $fieldset ): array {
		if ( ! is_cfw_page() ) {
			return $fields;
		}

		$address_2_field_key = "{$fieldset}_address_2";
		$company_field_key   = "{$fieldset}_company";

		/**
		 * Filters whether to hide the optional address line 2 field behind a link.
		 *
		 * @since 7.2.1
		 * @param bool $hide Whether to hide the optional address line 2 field behind a link.
		 * @param string $fieldset The fieldset.
		 */
		if ( isset( $fields[ $address_2_field_key ] ) && ! $fields[ $address_2_field_key ]['required'] && apply_filters( 'cfw_hide_optional_fields_behind_links', true, 'address_2' ) ) {
			$fields[ $address_2_field_key ]['class'][] = 'cfw-hidden';
			/**
			 * Filters the link text for adding the optional address line 2 field.
			 *
			 * @since 9.0.17
			 * @param string $address_2_link_text The link text.
			 */
			$address_2_link_text = apply_filters( 'cfw_optional_address_2_link_text', sprintf( '%s (%s)', __( 'Add Address Line 2', 'checkout-wc' ), __( 'optional', 'woocommerce' ) ) );

			// This link needs form-row because WooCommerce Checkout Field Editor is forcefully sorting the address fields
			$fields[ $address_2_field_key ]['before_html'] = sprintf( '<a href="javascript:" class="cfw-small cfw-add-field form-row"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>%s</a>', $address_2_link_text );
		}

		/**
		 * Filters whether to hide the optional company field behind a link.
		 *
		 * @since 7.2.1
		 * @param bool $hide Whether to hide the optional company field behind a link.
		 * @param string $fieldset The fieldset.
		 */
		if ( isset( $fields[ $company_field_key ] ) && ! $fields[ $company_field_key ]['required'] && apply_filters( 'cfw_hide_optional_fields_behind_links', true, 'company' ) ) {
			$fields[ $company_field_key ]['class'][] = 'cfw-hidden';

			/**
			 * Filters the link text for adding the optional address line 2 field.
			 *
			 * @since 9.0.17
			 * @param string $company_link_text The link text.
			 */
			$company_link_text = apply_filters( 'cfw_optional_company_link_text', sprintf( '%s (%s)', __( 'Add Company', 'checkout-wc' ), __( 'optional', 'woocommerce' ) ) );

			// This link needs form-row because WooCommerce Checkout Field Editor is forcefully sorting the address fields
			$fields[ $company_field_key ]['before_html'] = sprintf( '<a href="javascript:" class="cfw-small cfw-add-field form-row"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>%s</a>', $company_link_text );
		}

		return $fields;
	}
}
