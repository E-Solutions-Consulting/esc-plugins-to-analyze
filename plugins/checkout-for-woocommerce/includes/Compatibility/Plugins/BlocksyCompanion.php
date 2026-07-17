<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class BlocksyCompanion extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'BLOCKSY_PATH' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter( 'blocksy:woocommerce:checkout:has-custom-markup', '__return_false' );
	}
}
