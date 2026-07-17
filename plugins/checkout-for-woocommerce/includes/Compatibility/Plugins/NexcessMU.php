<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class NexcessMU extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'NEXCESS_MAPPS_SITE' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		/**
		 * Prevent disabling fragments when Nexcess MU is active
		 *
		 * @param bool $prevent_disable_fragments Prevent disabling fragments
		 * @return bool
		 * @since 10.1.0
		 */
		if ( apply_filters( 'cfw_compatibility_nexcessmu_prevent_disable_fragments', false ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'prevent_disable_fragments' ), 1 );
	}

	public function prevent_disable_fragments() {
		$instance = cfw_get_hook_instance_object( 'wp_enqueue_scripts', 'dequeueCartFragments', 20 );

		if ( ! $instance ) {
			cfw_debug_log( 'Instance not found ' );
			return;
		}

		remove_action( 'wp_enqueue_scripts', array( $instance, 'dequeueCartFragments' ), 20 );
	}
}
