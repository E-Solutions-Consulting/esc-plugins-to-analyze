<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class YITHPointsAndRewards extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'YITH_YWPAR_VERSION' );
	}

	public function pre_init() {
		add_action( 'cfw_template_redirect_priority', array( $this, 'maybe_change_template_redirect_priority' ) );
	}

	public function maybe_change_template_redirect_priority( $priority ) {
		if ( $this->is_available() ) {
			$priority = 31;
		}

		return $priority;
	}
}
