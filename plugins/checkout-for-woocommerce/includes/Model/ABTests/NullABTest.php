<?php

namespace Objectiv\Plugins\Checkout\Model\ABTests;
use Objectiv\Plugins\Checkout\Interfaces\ABTestInterface;
use WP_Post;

/**
 * Order Bumps Test Type
 *
 * @link checkoutwc.com
 * @since 11.0.0
 * @package Objectiv\Plugins\Checkout\Features\ABTesting\Types
 */
class NullABTest implements ABTestInterface {
	public function get_type(): string {
		return '';
	}

	public function get_label(): string {
		return '';
	}

	public function get_description(): string {
		return '';
	}

	public function get_fields_config(): array {
		return array();
	}

	public function apply(): void {
		return;
	}

	public function get_id(): int {
		return 0;
	}

	public function get_status(): string {
		return 'paused';
	}

	public function supports( string $feature ): bool {
		return false;
	}

	public function get_date_from(): string {
		return '';
	}

	public function get_date_to(): string {
		return '';
	}

	public function load( ?WP_Post $post = null ): void {
		return;
	}
}
