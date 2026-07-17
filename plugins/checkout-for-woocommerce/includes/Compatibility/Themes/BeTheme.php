<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class BeTheme extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'mfn_return_cart_link' );
	}

	public function pre_init() {
		add_action(
			'cfw_before_process_checkout',
			function () {
				add_filter( 'wc_get_template', array( $this, 'force_correct_notice_templates' ), 10, 5 );
			}
		);
	}

	public function run() {
		add_filter( 'wc_get_template', array( $this, 'force_correct_notice_templates' ), 10, 5 );
		remove_action( 'woocommerce_review_order_after_submit', 'mfn_return_cart_link' );
	}

	public function force_correct_notice_templates( $template, $template_name, $args, $template_path, $default_path ): string {
		// If notice template, return template from WooCommerce core
		if ( strpos( $template_name, 'notices/' ) !== false ) {
			return WC()->plugin_path() . '/templates/' . $template_name;
		}

		return $template;
	}
}
