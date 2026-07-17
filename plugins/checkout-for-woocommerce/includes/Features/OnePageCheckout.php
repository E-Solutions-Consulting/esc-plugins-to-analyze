<?php

namespace Objectiv\Plugins\Checkout\Features;

class OnePageCheckout extends FeaturesAbstract {
	protected function run_if_cfw_is_enabled() {
		add_action( 'template_redirect', array( $this, 'one_page_checkout_layout' ), 0 );
		add_filter( 'cfw_event_object', array( $this, 'add_localized_settings' ) );
		add_filter( 'cfw_checkout_main_container_classes', array( $this, 'add_class_to_main_container' ) );
	}

	public function one_page_checkout_layout() {
		// Remove breadcrumbs
		remove_action( 'cfw_checkout_before_order_review', 'cfw_breadcrumb_navigation', 10 );
		remove_action( 'cfw_checkout_main_container_start', 'futurist_breadcrumb_navigation', 10 );

		// Remove customer info tab nav
		remove_action( 'cfw_checkout_customer_info_tab', 'cfw_customer_info_tab_nav', 60 );

		// Remove shipping address review
		remove_action( 'cfw_checkout_shipping_method_tab', 'cfw_shipping_method_address_review_pane' );

		// Remove shipping tab nav
		remove_action( 'cfw_checkout_shipping_method_tab', 'cfw_shipping_method_tab_nav', 30 );

		// Remove payment tab address review
		remove_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_method_address_review_pane', 1 );

		// Remove payment tab navigation
		remove_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_tab_nav', 50 );
		add_action( 'cfw_checkout_payment_method_tab', 'cfw_payment_tab_nav_one_page_checkout', 50 );

		/**
		 * WooCommerce Germanized Handling
		 *
		 * Restores original hook
		 */
		remove_all_filters( 'cfw_compatibility_woocommerce_germanized_render_hook' );
		remove_all_filters( 'cfw_compatibility_woocommerce_germanized_render_priority' );
	}

	/**
	 * @param array $event_data The event data.
	 * @return array
	 */
	public function add_localized_settings( array $event_data ): array {
		$event_data['settings']['enable_one_page_checkout'] = true;

		return $event_data;
	}

	/**
	 * @param string $classes The classes.
	 * @return string
	 */
	public function add_class_to_main_container( string $classes ): string {
		$classes .= ' cfw-one-page-checkout';

		return $classes;
	}
}
