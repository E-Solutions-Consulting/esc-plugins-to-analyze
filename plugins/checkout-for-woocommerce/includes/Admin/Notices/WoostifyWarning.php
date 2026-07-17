<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\PlanManager;

class WoostifyWarning extends NoticeAbstract {
	protected function should_add(): bool {
		// If woostify isn't activated, bail
		if ( ! function_exists( 'woostify_options' ) ) {
			return false;
		}

		$woostify_options = woostify_options( false );

		// AJAX add to cart is enabled and the side cart is enabled.
		return ( $woostify_options['shop_single_ajax_add_to_cart'] ?? false ) && PlanManager::can_access_feature( 'enable_side_cart', 'plus' );
	}
}
