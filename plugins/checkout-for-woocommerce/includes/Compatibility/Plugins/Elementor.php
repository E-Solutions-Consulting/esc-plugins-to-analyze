<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Elementor\Plugin as ElementorPlugin;

class Elementor extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'ELEMENTOR_VERSION' );
	}

	public function run() {
		// Because Elementor refuses to fix this bug: https://github.com/elementor/elementor/issues/18722
		add_action( 'cfw_before_get_store_policy_content', array( $this, 'prevent_store_policy_bug' ) );
		add_action( 'cfw_after_get_store_policy_content', array( $this, 'add_filters_back' ) );
	}

	public function prevent_store_policy_bug() {
		ElementorPlugin::instance()->frontend->remove_content_filter();
	}

	public function add_filters_back() {
		ElementorPlugin::instance()->frontend->add_content_filter();
	}
}
