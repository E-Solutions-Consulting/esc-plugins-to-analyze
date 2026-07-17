<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use SkyVerge\WooCommerce\Google_Analytics_Pro\Plugin;

class GoogleAnalyticsProV1 extends CompatibilityAbstract {
	public function is_available(): bool {
		if ( ! function_exists( 'wc_google_analytics_pro' ) ) {
			return false;
		}

		if ( ! class_exists( '\\SkyVerge\\WooCommerce\\Google_Analytics_Pro\\Plugin' ) ) {
			return false;
		}

		if ( ! Plugin::VERSION ) {
			return false;
		}

		// Return true if version is less than 2.0.0
		return version_compare( Plugin::VERSION, '2.0.0', '<' );
	}

	public function run() {
		$wc_google_analytics_pro             = wc_google_analytics_pro();
		$wc_google_analytics_pro_integration = $wc_google_analytics_pro->get_integration();

		// selected payment method
		if ( $wc_google_analytics_pro_integration->has_event( 'selected_payment_method' ) ) {
			add_action( 'cfw_checkout_after_payment_methods', array( $wc_google_analytics_pro_integration, 'selected_payment_method' ) );
		}
	}
}
