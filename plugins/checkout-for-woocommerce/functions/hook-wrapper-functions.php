<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

function cfw_apply_filters( $hook_name, $value, ...$args ) {
	/**
	 * Filter the value of a hook
	 *
	 * @since 8.0.0
	 */
	return apply_filters( $hook_name, $value, ...$args );
}

function cfw_do_action( $hook_name, ...$arg ) {
	/**
	 * Action hook
	 *
	 * @since 8.0.0
	 */
	do_action( $hook_name, ...$arg );
}
