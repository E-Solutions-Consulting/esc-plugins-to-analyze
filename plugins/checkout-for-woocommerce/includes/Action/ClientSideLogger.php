<?php

namespace Objectiv\Plugins\Checkout\Action;

use CheckoutWC\Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Objectiv\Plugins\Checkout\RateLimiterWooCommerceSessionStorage;

class ClientSideLogger extends CFWAction {
	public function __construct() {
		parent::__construct( 'cfw_log_error' );
	}

	public function action() {
		// Ensure WooCommerce session is available
		if ( ! WC()->session ) {
			$this->out(
				array(
					'success' => false,
					'error'   => 'WooCommerce session is not available.',
				)
			);
			return;
		}

		// Use WooCommerce session storage for rate limiting
		$storage = new RateLimiterWooCommerceSessionStorage();
		$limiter = new FixedWindowLimiter( 'cfw_log_error', 10, new \DateInterval( 'PT1M' ), $storage );

		// Check the rate limit
		$rateLimit = $limiter->consume();

		if ( ! $rateLimit->isAccepted() ) {
			$this->out(
				array(
					'success' => false,
					'error'   => 'Too many requests. Please try again later.',
				)
			);
			return;
		}

		// Process the log data
		$log_data = $_POST['log_data'] ?? array(); // phpcs:ignore

		if ( empty( $log_data ) ) {
			$this->out(
				array(
					'success' => false,
				)
			);

			return;
		}

		wc_get_logger()->error( 'CheckoutWC Client Side Error: ' . print_r( $log_data, true ), array( 'source' => 'checkout-wc' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		$this->out(
			array(
				'success' => true,
			)
		);
	}
}
