<?php

namespace Objectiv\Plugins\Checkout\Managers;

/**
 * Determine whether the user has the right plan for a feature
 *
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Managers
 */
class PlanManager {
	const PLAN_HIERARCHY = array(
		'basic'  => 1,
		'plus'   => 2,
		'pro'    => 3,
		'agency' => 4,
	);

	protected static $plan_lookup = array();

	/**
	 * Does the user have the required plan?
	 *
	 * @param string $required_plan The required plan. Defaults to 'basic'.
	 *
	 * @return bool
	 */
	public static function has_premium_plan_or_higher( string $required_plan = 'basic' ): bool {
		$minimum_required_plan_level = self::PLAN_HIERARCHY[ $required_plan ];
		$user_plan_level             = self::get_user_plan_level();

		if ( $user_plan_level < $minimum_required_plan_level ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns an English formatted list of plans
	 *
	 * Examples:
	 * - X or Y
	 * - X, Y, or Z
	 *
	 * @param array $array_of_strings Array of strings to join.
	 * @return string
	 */
	public static function get_formatted_english_list( array $array_of_strings ): string {
		if ( count( $array_of_strings ) <= 2 ) {
			return join( ' or ', $array_of_strings );
		}

		return implode( ', ', array_slice( $array_of_strings, 0, -1 ) ) . ', or ' . end( $array_of_strings );
	}

	/**
	 * Get English list of required plans
	 *
	 * @param string $minimum_plan The minimum plan required.
	 *
	 * @return string
	 */
	public static function get_english_list_of_required_plans_html( string $minimum_plan = 'basic' ): string {
		// Get the hierarchy and starting level
		$minimum_level   = self::PLAN_HIERARCHY[ $minimum_plan ] ?? 0;
		$user_plan_level = self::get_user_plan_level();

		if ( $user_plan_level > $minimum_level ) {
			return '';
		}

		if ( ! $minimum_level ) {
			return '';
		}

		// Filter plans to include only the required and higher levels
		$filtered_plans = array_filter(
			self::PLAN_HIERARCHY,
			function ( $level ) use ( $minimum_level, $user_plan_level ) {
				return $level >= $minimum_level && $user_plan_level <= $level;
			}
		);

		// Get the plan names, ucfirst each, and format as <strong>
		$plans = array_map(
			function ( $plan ) {
				return '<strong>' . ucfirst( $plan ) . '</strong>';
			},
			array_keys( $filtered_plans )
		);

		// Return formatted English list
		return self::get_formatted_english_list( $plans );
	}

	public static function get_user_plan_level(): int {
		if ( ! UpdatesManager::instance()->is_license_good() && ! UpdatesManager::instance()->is_license_valid() ) {
			return 0;
		}

		$plan_lookup = self::get_plan_lookup();
		$price_id    = UpdatesManager::instance()->get_license_price_id();

		return isset( $plan_lookup[ $price_id ] ) ? self::PLAN_HIERARCHY[ $plan_lookup[ $price_id ] ] : 0;
	}

	/**
	 * Can access feature?
	 *
	 * @param string $setting_key The setting key to check.
	 * @param string $required_plan The minimum plan required.
	 *
	 * @return bool
	 */
	public static function can_access_feature( string $setting_key, string $required_plan = 'basic' ): bool {
		if ( ! self::has_premium_plan_or_higher( $required_plan ) ) {
			return false;
		}

		$value = SettingsManager::instance()->get_setting( $setting_key );

		return 'yes' === $value || 'enabled' === $value;
	}

	public static function get_plan_lookup(): array {
		if ( ! empty( self::$plan_lookup ) ) {
			return self::$plan_lookup;
		}

		$plan_ids = defined( 'CFW_PREMIUM_PLAN_IDS' ) ? CFW_PREMIUM_PLAN_IDS : array();

		foreach ( $plan_ids as $plan => $ids ) {
			foreach ( $ids as $id ) {
				self::$plan_lookup[ $id ] = $plan;
			}
		}

		return self::$plan_lookup;
	}
}
