<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @return bool
 */
function cfw_is_thank_you_view_order_page_active() : bool {
	return cfw_is_thank_you_page_active() && PlanManager::can_access_feature( 'override_view_order_template' );
}

/**
 * @throws Exception if the order is invalid
 */
function cfw_get_all_order_bumps() : array {
	return BumpFactory::get_all();
}

/**
 * Automatically applies filter with prefixing for setting name
 *
 * @param string $setting the setting name
 * @param mixed $value the value to set
 * @return void
 */
function cfw_add_setting_value_override( $setting, $value ) {
	add_filter(
		'cfw_get_setting_' . $setting,
		function() use ( $value ) {
			return $value;
		}
	);
}

function cfw_debug_log( string $message ) {
	if ( 'yes' !== SettingsManager::instance()->get_setting( 'enable_debug_log' ) ) {
		return;
	}

	wc_get_logger()->debug( $message, [ 'source' => 'checkout-wc' ] );
}

/**
 * Get CheckoutWC setting value
 *
 * If the setting is 'yes' or 'no', it will return a boolean value.
 *
 * @param string $setting the setting name
 * @param array $keys the keys to add uniqueness to the setting name
 * @param mixed $default_value the default value
 * @return bool|mixed
 *
 * @since 9.0.15
 * @since 9.0.16 If the setting is an empty string and the default is an array, return the default.
 */
function cfw_get_setting( $setting, $keys = [], $default_value = false ) {
	$value = SettingsManager::instance()->get_setting( $setting, (array) $keys );

	// Prevent type juggling for values that are expected to be arrays
	if ( is_string( $value ) && is_array( $default_value ) ) {
		return $default_value;
	}

	// If the setting doesn't exist, return the default
	if ( $value !== $default_value && false === $value ) {
		return $default_value;
	}

	// Truthy / falsy string values
	if ( 'yes' === $value ) {
		return true;
	}

	if ( 'no' === $value ) {
		return false;
	}

	// Ok just return the value then
	return $value;
}
