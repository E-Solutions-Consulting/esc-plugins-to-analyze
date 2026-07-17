<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class CoderockzWooDelivery extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'run_coderockz_woo_delivery' );
	}

	public function run() {
		$other_settings = get_option( 'coderockz_woo_delivery_other_settings' );
		$position       = isset( $other_settings['field_position'] ) && '' !== $other_settings['field_position'] ? $other_settings['field_position'] : 'after_billing';
		$action         = 'woocommerce_after_checkout_billing_form';

		if ( 'before_billing' === $position ) {
			$action = 'woocommerce_checkout_billing';
		} elseif ( 'before_shipping' === $position ) {
			$action = 'woocommerce_checkout_shipping';
		} elseif ( 'after_shipping' === $position ) {
			$action = 'woocommerce_after_checkout_shipping_form';
		} elseif ( 'before_notes' === $position ) {
			$action = 'woocommerce_before_order_notes';
		} elseif ( 'after_notes' === $position ) {
			$action = 'woocommerce_after_order_notes';
		} elseif ( 'before_payment' === $position ) {
			$action = 'woocommerce_review_order_before_payment';
		} elseif ( 'before_your_order' === $position ) {
			$action = 'woocommerce_checkout_before_order_review_heading';
		}

		$instance = cfw_get_hook_instance_object( $action, 'coderockz_woo_delivery_add_custom_field' );

		if ( ! $instance ) {
			return;
		}

		add_action( 'cfw_checkout_after_shipping_methods', array( $instance, 'coderockz_woo_delivery_add_custom_field' ) );

		add_filter( 'woocommerce_form_field_args', array( $this, 'maybe_prevent_select2' ), 10, 1 );
		add_filter( 'cfw_select_field_options', array( $this, 'remove_empty_options' ), 10, 3 );
	}

	/**
	 * Plugin expects select2 and thus provides a null option which is not desirable
	 * It isn't desirable because Select 2 doesn't allow selecting the null option, but
	 * without select2 it is selectable which throws errors
	 *
	 * @param array  $options The options to filter.
	 * @param array  $args The arguments for the field.
	 * @param string $key The key of the field.
	 *
	 * @return array $options The filtered options.
	 */
	public function remove_empty_options( $options, $args, $key ) {
		// If key doesn't start with coderockz_, return
		if ( 0 !== strpos( $key, 'coderockz_' ) ) {
			return $options;
		}

		reset( $options );

		if ( key( $options ) !== '' ) {
			return $options;
		}

		// Remove first element of array and return the array
		array_shift( $options );

		return $options;
	}

	public function maybe_prevent_select2( $args ): array {
		if ( in_array( $args['id'], array( 'coderockz_woo_delivery_pickup_location_field', 'coderockz_woo_delivery_pickup_time_field', 'coderockz_woo_delivery_tips_field', 'coderockz_woo_delivery_time_field', 'coderockz_woo_delivery_delivery_selection_box' ), true ) ) {
			$args['input_class'][] = 'cfw-no-select2';
		}

		return $args;
	}
}
