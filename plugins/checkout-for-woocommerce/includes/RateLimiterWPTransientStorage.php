<?php

namespace Objectiv\Plugins\Checkout;

use CheckoutWC\Symfony\Component\RateLimiter\LimiterStateInterface;
use CheckoutWC\Symfony\Component\RateLimiter\Storage\StorageInterface;

class RateLimiterWPTransientStorage implements StorageInterface {
	private $prefix;
	private $expiration;

	public function __construct( $prefix = 'cfw_rate_limiter_', $expiration = 3600 ) {
		$this->prefix     = $prefix;
		$this->expiration = $expiration; // Default 1 hour
	}

	public function save( LimiterStateInterface $limiterState ): void {
		set_transient(
			$this->prefix . $limiterState->getId(),
			$limiterState,
			$this->expiration
		);
	}

	public function fetch( string $limiterStateId ): ?LimiterStateInterface {
		$value = get_transient( $this->prefix . $limiterStateId );

		if ( $value instanceof LimiterStateInterface ) {
			return $value;
		}

		return null;
	}

	public function delete( string $limiterStateId ): void {
		delete_transient( $this->prefix . $limiterStateId );
	}
}