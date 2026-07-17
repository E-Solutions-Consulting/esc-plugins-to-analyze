<?php

namespace Objectiv\Plugins\Checkout;

use CheckoutWC\Symfony\Component\RateLimiter\LimiterStateInterface;
use CheckoutWC\Symfony\Component\RateLimiter\Storage\StorageInterface;

class RateLimiterWooCommerceSessionStorage implements StorageInterface {
	private $session;

	public function __construct() {
		$this->session = WC()->session;
	}

	public function save( $limiterState ): void {
		$this->session->set( $limiterState->getId(), $limiterState );
	}

	public function fetch( $limiterStateId ): ?LimiterStateInterface {
		$value = $this->session->get( $limiterStateId, null );

		if ( $value instanceof LimiterStateInterface ) {
			return $value;
		}

		return null;
	}

	public function delete( $limiterStateId ): void {
		$this->session->set( $limiterStateId, null );
	}
}
