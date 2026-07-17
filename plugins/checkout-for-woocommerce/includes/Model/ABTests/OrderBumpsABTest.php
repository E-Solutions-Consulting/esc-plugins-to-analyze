<?php

namespace Objectiv\Plugins\Checkout\Model\ABTests;

use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Model\Bumps\NullBump;
use Objectiv\Plugins\Checkout\Interfaces\ABTestUIProvidable;
use Objectiv\Plugins\Checkout\Interfaces\ABTestUIProviderInterface;
use Objectiv\Plugins\Checkout\Features\ABTesting\UIProviders\OrderBumpABTestUIProvider;

/**
 * Order Bumps Test Type
 *
 * @link checkoutwc.com
 * @since 11.0.0
 * @package Objectiv\Plugins\Checkout\Features\ABTesting\Types
 */
class OrderBumpsABTest extends ABTestAbstract implements ABTestUIProvidable {

	/**
	 * UI Provider instance
	 *
	 * @var ABTestUIProviderInterface|null
	 */
	private ?ABTestUIProviderInterface $ui_provider = null;

	public function __construct() {
		parent::__construct();

		$this->label       = __( 'Order Bumps', 'checkout-wc' );
		$this->description = __( 'Test different variations of order bumps to see which performs better.', 'checkout-wc' );
		$this->supports    = array( 'order_bump_variants' );
	}

	/**
	 * Apply the A/B test
	 *
	 * @return void
	 */
	public function apply(): void {
		// Early returns for inactive or out-of-date tests
		if ( ! $this->is_test_active() ) {
			return;
		}

		// Get test configuration
		$order_bump_id = (int) get_post_meta( $this->id, 'cfw_ab_test_order_bump', true );
		$variants      = get_post_meta( $this->id, 'cfw_ab_test_order_bump_variants', true );

		if ( ! $order_bump_id || ! is_array( $variants ) || empty( $variants ) ) {
			return;
		}

		$traffic_split_type        = get_post_meta( $this->id, 'cfw_ab_test_traffic_split_type', true ) ?: 'equal';
		$traffic_split_percentages = $this->normalize_percentages( get_post_meta( $this->id, 'cfw_ab_test_traffic_split_percentages', true ) );
		$config_hash               = $this->generate_test_config_hash( $order_bump_id, $variants, $traffic_split_type, $traffic_split_percentages );

		// Get or assign variant for this user
		$selected_variant = $this->get_or_assign_variant( $order_bump_id, $variants, $traffic_split_type, $config_hash );

		// Hook into BumpFactory::get_all() to replace parent with variant
		$this->apply_bump_replacement( $order_bump_id, $selected_variant );
	}

	/**
	 * Check if test is active and within date range
	 *
	 * @return bool
	 */
	private function is_test_active(): bool {
		$status = get_post_meta( $this->id, 'cfw_ab_test_status', true ) ?: 'active';
		if ( 'active' !== $status ) {
			return false;
		}

		$date_from = get_post_meta( $this->id, 'cfw_ab_test_date_from', true );
		$date_to   = get_post_meta( $this->id, 'cfw_ab_test_date_to', true );
		$now       = strtotime( current_time( 'mysql' ) );

		if ( ! empty( $date_from ) && $now < strtotime( $date_from ) ) {
			return false;
		}

		if ( ! empty( $date_to ) && $now > strtotime( $date_to ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize percentages array to ensure consistent integer values
	 *
	 * @param mixed $percentages Raw percentages from post meta.
	 * @return array Normalized percentages array.
	 */
	private function normalize_percentages( $percentages ): array {
		if ( ! is_array( $percentages ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $percentages as $id => $percentage ) {
			$normalized[ (int) $id ] = (int) $percentage;
		}

		return $normalized;
	}

	/**
	 * Get or assign variant for this user from session or select new one
	 *
	 * @param int    $order_bump_id The parent order bump ID.
	 * @param array  $variants Array of variant IDs.
	 * @param string $traffic_split_type 'equal' or 'custom'.
	 * @param string $config_hash Current configuration hash.
	 * @return int The selected variant ID.
	 */
	private function get_or_assign_variant( int $order_bump_id, array $variants, string $traffic_split_type, string $config_hash ): int {
		$wc_session = $this->get_wc_session();
		if ( ! $wc_session ) {
			return $this->select_variant( $order_bump_id, $variants, $traffic_split_type, $this->id );
		}

		$session_key        = 'cfw_ab_test_' . $this->id . '_variant';
		$config_session_key = 'cfw_ab_test_' . $this->id . '_config_hash';
		$all_options        = array_merge( array( $order_bump_id ), $variants );
		$stored_hash        = $wc_session->get( $config_session_key );
		$config_changed     = ( $stored_hash !== $config_hash );

		// If config changed, clear old values
		if ( $config_changed ) {
			$wc_session->set( $session_key, null );
			$wc_session->set( $config_session_key, null );
		} else {
			// Try to get stored variant
			$stored_variant = $wc_session->get( $session_key );
			if ( $stored_variant && in_array( (int) $stored_variant, $all_options, true ) ) {
				// Verify stored variant is still published (in case it became a draft)
				if ( $this->is_bump_published( (int) $stored_variant ) ) {
					return (int) $stored_variant;
				}
			}
		}

		// Select and store new variant
		$selected_variant = $this->select_variant( $order_bump_id, $variants, $traffic_split_type );
		$wc_session->set( $session_key, $selected_variant );
		$wc_session->set( $config_session_key, $config_hash );

		return $selected_variant;
	}

	/**
	 * Get WooCommerce session if available
	 *
	 * @return \WC_Session|null
	 */
	private function get_wc_session() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		// @phpstan-ignore-next-line
		return WC()->session ?? null;
	}

	/**
	 * Apply bump replacement filter
	 *
	 * @param int $order_bump_id The parent order bump ID.
	 * @param int $selected_variant The selected variant ID.
	 * @return void
	 */
	private function apply_bump_replacement( int $order_bump_id, int $selected_variant ): void {
		// If selected variant is the parent, no replacement needed
		if ( $selected_variant === $order_bump_id ) {
			return;
		}

		add_filter(
			'cfw_get_order_bumps',
			function ( $bumps ) use ( $order_bump_id, $selected_variant ) {
				foreach ( $bumps as $key => $bump ) {
					if ( $bump->get_id() !== $order_bump_id ) {
						continue;
					}

					$variant_bump = BumpFactory::get( $selected_variant );

					if ( $variant_bump instanceof NullBump ) {
						unset( $bumps[ $key ] );
					} else {
						$bumps[ $key ] = $variant_bump;
					}

					break;
				}

				return $bumps;
			},
			10,
			1
		);
	}

	/**
	 * Select a variant based on traffic split settings
	 *
	 * @param int    $order_bump_id The parent order bump ID.
	 * @param array  $variants Array of variant IDs.
	 * @param string $traffic_split_type 'equal' or 'custom'.
	 * @return int The selected variant ID (or parent if no variant selected)
	 */
	private function select_variant( int $order_bump_id, array $variants, string $traffic_split_type ): int {
		// Filter out draft variants (parent should be published if test is active)
		$published_variants = $this->filter_published_bumps( $variants );
		$all_options        = array_merge( array( $order_bump_id ), $published_variants );

		// If no published variants available, return parent
		if ( empty( $published_variants ) ) {
			return $order_bump_id;
		}

		if ( 'equal' === $traffic_split_type ) {
			return (int) $all_options[ array_rand( $all_options ) ];
		}

		// Custom split: use percentages
		$percentages = $this->normalize_percentages( get_post_meta( $this->id, 'cfw_ab_test_traffic_split_percentages', true ) );
		if ( empty( $percentages ) ) {
			// Fallback to equal if no percentages set
			return (int) $all_options[ array_rand( $all_options ) ];
		}

		// Build weighted options (only include options with percentage > 0)
		$weighted_options = array();
		foreach ( $all_options as $option_id ) {
			$option_id = (int) $option_id;
			if ( isset( $percentages[ $option_id ] ) && $percentages[ $option_id ] > 0 ) {
				$weighted_options[ $option_id ] = $percentages[ $option_id ];
			}
		}

		// Validate and select
		if ( empty( $weighted_options ) || array_sum( $weighted_options ) !== 100 ) {
			// Fallback if percentages don't work out
			return ! empty( $all_options ) ? (int) $all_options[0] : $order_bump_id;
		}

		// Weighted random selection
		$random     = wp_rand( 1, 100 );
		$cumulative = 0;
		foreach ( $weighted_options as $option_id => $weight ) {
			$cumulative += $weight;
			if ( $random <= $cumulative ) {
				return (int) $option_id;
			}
		}

		return $order_bump_id;
	}

	/**
	 * Filter out draft bumps from an array of bump IDs
	 *
	 * @param array $bump_ids Array of bump IDs to filter.
	 * @return array Array of published bump IDs.
	 */
	private function filter_published_bumps( array $bump_ids ): array {
		$published = array();
		foreach ( $bump_ids as $bump_id ) {
			if ( $this->is_bump_published( (int) $bump_id ) ) {
				$published[] = (int) $bump_id;
			}
		}
		return $published;
	}

	/**
	 * Check if a bump is published
	 *
	 * @param int $bump_id The bump ID to check.
	 * @return bool True if published, false otherwise.
	 */
	private function is_bump_published( int $bump_id ): bool {
		$status = get_post_status( $bump_id );
		return 'publish' === $status;
	}

	/**
	 * Generate a hash of the test configuration to detect changes
	 *
	 * @param int    $order_bump_id The parent order bump ID
	 * @param array  $variants Array of variant IDs
	 * @param string $traffic_split_type 'equal' or 'custom'
	 * @param array  $traffic_split_percentages Array of percentages for custom split
	 * @return string The configuration hash
	 */
	private function generate_test_config_hash( int $order_bump_id, array $variants, string $traffic_split_type, array $traffic_split_percentages ): string {
		$sorted_variants = $variants;
		sort( $sorted_variants );

		$sorted_percentages = $traffic_split_percentages;
		ksort( $sorted_percentages );

		$config_string = sprintf(
			'%d|%s|%s|%s',
			$order_bump_id,
			implode( ',', $sorted_variants ),
			$traffic_split_type,
			wp_json_encode( $sorted_percentages )
		);

		return md5( $config_string );
	}

	/**
	 * Check if this test type provides UI components
	 *
	 * @return bool
	 */
	public function has_ui_provider(): bool {
		return true;
	}

	/**
	 * Get the UI provider instance
	 *
	 * @return ABTestUIProviderInterface|null
	 */
	public function get_ui_provider(): ?ABTestUIProviderInterface {
		if ( null === $this->ui_provider ) {
			$this->ui_provider = new OrderBumpABTestUIProvider( $this );
		}
		return $this->ui_provider;
	}
}
