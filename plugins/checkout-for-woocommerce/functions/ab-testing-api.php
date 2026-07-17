<?php

/**
 * These functions were introduced prior to the A/B Testing feature being added to the plugin.
 * They are kept here for backwards compatibility.
 */
function cfw_register_ab_test( $name, callable $callback ) {
	add_action( 'cfw_ab_test_' . $name, $callback );
}

function cfw_activate_ab_test( $name ) {
	if ( ! cfw_is_ab_test_registered( $name ) ) {
		/* translators: Error message logged when trying to activate an unregistered AB test */
		wc_get_logger()->error( __( 'CheckoutWC: You cannot activate an AB test that is not registered.', 'checkout-wc' ), [ 'source' => 'checkout-wc' ] );

		return;
	}

	$user_tests = cfw_get_ab_tests();

	if ( isset( $user_tests[ $name ] ) ) {
		return;
	}

	$user_tests[ $name ] = $name;

	// Set to WC session
	setcookie( 'cfw_ab_tests', wp_json_encode( $user_tests ), time() + ( DAY_IN_SECONDS * 30 ), '/' );
}

function cfw_get_ab_tests() : array {
	return isset( $_COOKIE['cfw_ab_tests'] ) ? json_decode( $_COOKIE['cfw_ab_tests'], true ) : []; // phpcs:ignore
}

function cfw_is_ab_test_registered( $name ) : bool {
	return has_action( 'cfw_ab_test_' . $name );
}

function cfw_apply_ab_test( $name ) {
	if ( did_action( 'cfw_ab_test_' . $name ) ) {
		return;
	}

	cfw_do_action( 'cfw_ab_test_' . $name );
}

function cfw_maybe_activate_test_from_url() {
	/**
	 * Filters the URL parameter for loading AB tests by URL
	 *
	 * @since 8.2.8
	 *
	 * @param string $url_parameter
	 */
	$url_parameter = apply_filters( 'cfw_ab_test_url_parameter', 'cfw_ab_test' );

	if ( ! isset( $_GET[ $url_parameter ] ) ) { // phpcs:ignore
		return;
	}

	$test = sanitize_text_field( $_GET[ $url_parameter ] ); // phpcs:ignore

	cfw_activate_ab_test( $test );
}

function cfw_maybe_apply_active_ab_test() {
	$tests = cfw_get_ab_tests();

	if ( empty( $tests ) ) {
		return;
	}

	foreach ( $tests as $test ) {
		if ( ! cfw_is_ab_test_registered( $test ) ) {
			continue;
		}

		cfw_apply_ab_test( $test );
	}
}
