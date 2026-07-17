<?php

namespace Objectiv\Plugins\Checkout\Interfaces;

use WP_Post;

/**
 * Interface for A/B Test Types
 *
 * @link checkoutwc.com
 * @since 11.0.0
 * @package Objectiv\Plugins\Checkout\Features\ABTesting
 */
interface ABTestInterface {
	/**
	 * @return int The test id.
	 */
	public function get_id(): int;

	public function load( ?WP_Post $post = null ): void;

	/**
	 * Get the test type identifier
	 *
	 * @return string
	 */
	public function get_type(): string;

	/**
	 * Get the test type label
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Get the test type description
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * @return string The test status;
	 */
	public function get_status(): string;

	/**
	 * Get the fields configuration for this test type
	 * This should return an array of field configurations that will be shown
	 * when this test type is selected
	 *
	 * @return array Array of field configurations
	 */
	public function get_fields_config(): array;

	/**
	 * Apply the A/B test
	 *
	 * @return void
	 */
	public function apply(): void;

	/**
	 * @param string $feature Whether the test supports feature.
	 *
	 * @return bool
	 */
	public function supports( string $feature ): bool;

	public function get_date_from(): string;
	public function get_date_to(): string;
}
