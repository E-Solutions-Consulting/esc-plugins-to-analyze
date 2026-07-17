<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WooCommerceGermanized extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'WC_germanized' );
	}

	public function pre_init() {
		/**
		 * Don't monkey around with gateways
		 */
		add_filter( 'woocommerce_gzd_compatibilities', array( $this, 'override_ppec_compat' ), 1000, 1 );
	}

	public function run() {
		/**
		 * Don't let WooCommerce Germanized Eff Up the Submit Button
		 */
		$wc_gzd_checkout = \WC_GZD_Checkout::instance();
		remove_filter( 'woocommerce_update_order_review_fragments', array( $wc_gzd_checkout, 'refresh_order_submit' ), 150 );
		remove_action( 'woocommerce_review_order_before_submit', 'woocommerce_gzd_template_set_order_button_remove_filter', PHP_INT_MAX );
		remove_action( 'woocommerce_review_order_after_submit', 'woocommerce_gzd_template_set_order_button_show_filter', PHP_INT_MAX );
		remove_action( 'woocommerce_gzd_review_order_before_submit', 'woocommerce_gzd_template_set_order_button_show_filter', PHP_INT_MAX );

		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_gzd_template_order_submit', wc_gzd_get_hook_priority( 'checkout_order_submit' ) );
		remove_action( 'woocommerce_checkout_after_order_review', 'woocommerce_gzd_template_order_submit_fallback', 50 );
		remove_action( 'woocommerce_review_order_before_cart_contents', 'woocommerce_gzd_template_checkout_table_content_replacement' );

		remove_action( 'woocommerce_review_order_after_payment', 'woocommerce_gzd_template_render_checkout_checkboxes', 10 );
		remove_action( 'woocommerce_review_order_after_payment', 'woocommerce_gzd_template_checkout_set_terms_manually', wc_gzd_get_hook_priority( 'checkout_set_terms' ) );

		// Don't let Germanized add thumbnails
		add_action(
			'woocommerce_review_order_before_cart_contents',
			function () {
				remove_filter( 'woocommerce_cart_item_name', 'woocommerce_gzd_template_inject_checkout_table_thumbnails', 11 );
				remove_filter( 'woocommerce_cart_item_class', 'woocommerce_gzd_template_inject_checkout_table_thumbnails_class', 10 );
			}
		);
	}

	public function run_immediately() {
		/**
		 * Filter the rendering hook for WooCommerce Germanized compatibility
		 *
		 * @param string $hook
		 * @return string
		 * @since 10.1.0
		 */
		$hook = apply_filters( 'cfw_compatibility_woocommerce_germanized_render_hook', 'cfw_checkout_before_payment_method_tab_nav' );

		/**
		 * Filter the priority of the render hook for WooCommerce Germanized compatibility
		 *
		 * @param int $priority
		 * @return int
		 * @since 10.1.0
		 */
		$priority = apply_filters( 'cfw_compatibility_woocommerce_germanized_render_priority', 10 );

		add_action( $hook, 'woocommerce_gzd_template_render_checkout_checkboxes', $priority );
		add_action( $hook, 'woocommerce_gzd_template_checkout_set_terms_manually', $priority );
	}

	public function override_ppec_compat( $plugins ) {
		if ( isset( $plugins['woocommerce-gateway-paypal-express-checkout'] ) ) {
			unset( $plugins['woocommerce-gateway-paypal-express-checkout'] );
		}

		if ( isset( $plugins['woocommerce-paypal-payments'] ) ) {
			unset( $plugins['woocommerce-paypal-payments'] );
		}

		return $plugins;
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'WooCommerceGermanized',
			'params' => array(),
		);

		return $compatibility;
	}
}
