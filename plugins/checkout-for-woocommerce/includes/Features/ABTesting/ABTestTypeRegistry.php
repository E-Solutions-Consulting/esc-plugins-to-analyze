<?php

namespace Objectiv\Plugins\Checkout\Features\ABTesting;

use Objectiv\Plugins\Checkout\Model\ABTests\OrderBumpsABTest;

/**
 * Registry for A/B Test Types
 *
 * Manages registration and retrieval of A/B test types, including their
 * metadata such as labels and descriptions.
 *
 * @link checkoutwc.com
 * @since 8.0.0
 * @package Objectiv\Plugins\Checkout\Features\ABTesting
 */
class ABTestTypeRegistry {
	/**
	 * Registered test types
	 *
	 * @var array
	 */
	private static array $types = array();

	/**
	 * Register a test type
	 *
	 * @param string $identifier The test type identifier (e.g., 'order_bumps').
	 * @param string $class_name The fully qualified class name.
	 * @param string $label The human-readable label for the test type.
	 * @param string $description Optional description of the test type.
	 * @return void
	 */
	public static function register( string $identifier, string $class_name, string $label, string $description = '' ): void {
		self::$types[ $identifier ] = array(
			'class'       => $class_name,
			'label'       => $label,
			'description' => $description,
		);
	}

	/**
	 * Get all registered test type identifiers
	 *
	 * This replaces ABTesting::get_all_test_types()
	 *
	 * @return array Array of test type identifiers
	 */
	public static function get_all_types(): array {
		return array_keys( self::$types );
	}

	/**
	 * Get the human-readable label for a test type
	 *
	 * @param string $type The test type identifier.
	 * @return string The label, or a formatted version of the identifier if not found
	 */
	public static function get_type_label( string $type ): string {
		if ( isset( self::$types[ $type ]['label'] ) ) {
			return self::$types[ $type ]['label'];
		}

		// Fallback: Convert identifier to title case
		return ucwords( str_replace( '_', ' ', $type ) );
	}

	/**
	 * Get the description for a test type
	 *
	 * @param string $type The test type identifier.
	 * @return string The description, or empty string if not found
	 */
	public static function get_type_description( string $type ): string {
		return self::$types[ $type ]['description'] ?? '';
	}

	/**
	 * Get all test types with their labels
	 *
	 * Useful for populating dropdowns
	 *
	 * @return array Associative array of identifier => label
	 */
	public static function get_types_with_labels(): array {
		$labeled = array();
		foreach ( self::$types as $identifier => $data ) {
			$labeled[ $identifier ] = $data['label'];
		}
		return $labeled;
	}

	/**
	 * Check if a test type is registered
	 *
	 * @param string $type The test type identifier.
	 * @return bool True if registered
	 */
	public static function is_registered( string $type ): bool {
		return isset( self::$types[ $type ] );
	}

	/**
	 * Get the class name for a test type
	 *
	 * @param string $type The test type identifier.
	 * @return string|null The class name or null if not found
	 */
	public static function get_test_class( string $type ): ?string {
		return self::$types[ $type ]['class'] ?? null;
	}

	/**
	 * Initialize the registry with default test types
	 *
	 * @return void
	 */
	public static function init(): void {
		// Register core test types
		self::register(
			'order_bumps',
			OrderBumpsABTest::class,
			__( 'Order Bumps', 'checkout-wc' ),
			__( 'Test different variations of order bumps to see which performs better.', 'checkout-wc' )
		);

		/**
		 * Allow third parties to register additional test types
		 *
		 * @param ABTestTypeRegistry $registry The registry instance
		 * @since 8.0.0
		 */
		do_action( 'cfw_register_ab_test_types', self::class );
	}

	/**
	 * Get all registered test types with full data
	 *
	 * @return array Array of test type data (identifier => ['class' => ..., 'label' => ..., 'description' => ...])
	 */
	public static function get_all_test_types(): array {
		return self::$types;
	}

	/**
	 * Reset the registry (mainly for testing)
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$types = array();
	}
}
