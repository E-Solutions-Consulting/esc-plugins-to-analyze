<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class YITHCompositeProducts extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'YITH_WCP_VERSION' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter( 'cfw_cart_item_row_class', array( $this, 'maybe_add_class_to_composite_items' ), 10, 2 );
	}

	public function maybe_add_class_to_composite_items( $classes, $cart_item ): string {
		if ( ! isset( $cart_item['yith_wcp_child_component_data'] ) ) {
			return '';
		}

		return $classes . ' yith-composite-product-component';
	}
}
