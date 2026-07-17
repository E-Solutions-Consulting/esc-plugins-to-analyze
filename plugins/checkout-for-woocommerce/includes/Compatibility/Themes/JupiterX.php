<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Themes;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class JupiterX extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'jupiterx_define_constants' );
	}

	public function remove_styles( array $styles ): array {
		$styles['jupiterx-core-raven-frontend'] = 'jupiterx-core-raven-frontend';

		return $styles;
	}

	public function run_immediately() {
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'remove_actions' ), 0 );
	}

	public function remove_actions() {
		remove_action( 'woocommerce_review_order_after_submit', 'jupiterx_wc_continue_shopping_button' );
	}
}
