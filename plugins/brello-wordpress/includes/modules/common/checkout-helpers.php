<?php
/**
 * Shared checkout helpers.
 *
 * Our checkout validators are written for the classic checkout: they hang on
 * woocommerce_checkout_process / woocommerce_after_checkout_validation, read
 * $_POST and report through wc_add_notice() or a WP_Error. The Store API
 * checkout (used by Stripe express checkout: Apple Pay, Google Pay, Link)
 * fires none of those hooks. These helpers run the same callbacks for any
 * checkout path from a plain field array, so every path applies one set of
 * rules and the validators are not copied.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bh_checkout_shared_validators' ) ) {
	/**
	 * Validators every checkout path must run, grouped by the classic hook they are registered on.
	 *
	 * Only callbacks that are really registered on that hook run, in the hook's priority order,
	 * so remove_action() on the classic hook also disables them on the other paths.
	 *
	 * Left out on purpose:
	 * - Bh_Checkout_Validations::validate_accept_terms: wallet sheets have no checkboxes; paying there accepts the terms.
	 * - Bh_Checkout_Validations::associate_existing_customer_checkout: logs a guest in by e-mail (open security issue).
	 * - Bh_Checkout_Validations::test_checkout_validations: test-mode notice for the classic form.
	 *
	 * @return array<string, array<int, array{0: string, 1: string}>> Hook name => list of [ class, method ].
	 */
	function bh_checkout_shared_validators(): array {
		$validators = array(
			'woocommerce_checkout_process'          => array(
				array( 'Bh_Checkout_Validations', 'restrict_one_product_per_email_or_phone' ),
				array( 'Bh_Checkout_Validations', 'validate_logged_in_user_restrictions' ),
				array( 'Bh_Checkout_Validations', 'restrict_po_boxes_in_checkout' ),
				array( 'Bh_Features_Public', 'bh_woocommerce_checkout_process_field_phone_validation' ),
				array( 'AH_Checkout_Phone_Standardization', 'validate_phone_format' ),
				array( 'AH_Orders_Telegra_Renewal_Blocker', 'block_checkout_for_invalid_renewals' ),
			),
			'woocommerce_after_checkout_validation' => array(
				array( 'Bh_Features_Public', 'restrict_shipping_states' ),
				array( 'Bh_Product_Shipping_States_Checkout', 'validate' ),
			),
		);

		/**
		 * Filters the validators shared by every checkout path.
		 *
		 * @param array $validators Hook name => list of [ class, method ].
		 */
		return (array) apply_filters( 'bh_checkout_shared_validators', $validators );
	}
}

if ( ! function_exists( 'bh_checkout_registered_validators' ) ) {
	/**
	 * Callbacks registered on $hook that are in the shared list, in priority order.
	 *
	 * @param string $hook Classic checkout hook name.
	 * @return array<int, array{id: string, callback: callable}>
	 */
	function bh_checkout_registered_validators( string $hook ): array {
		global $wp_filter;

		$allowed = bh_checkout_shared_validators()[ $hook ] ?? array();
		if ( empty( $allowed ) || empty( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof WP_Hook ) {
			return array();
		}

		$callbacks = $wp_filter[ $hook ]->callbacks;
		ksort( $callbacks, SORT_NUMERIC );

		$found = array();
		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $registered ) {
				$function = $registered['function'] ?? null;
				if ( ! is_array( $function ) || ! is_object( $function[0] ?? null ) || ! is_string( $function[1] ?? null ) ) {
					continue;
				}
				foreach ( $allowed as $pair ) {
					if ( $function[0] instanceof $pair[0] && $function[1] === $pair[1] ) {
						$found[] = array(
							'id'       => $pair[0] . '::' . $pair[1],
							'callback' => $function,
						);
						break;
					}
				}
			}
		}

		return $found;
	}
}

if ( ! function_exists( 'bh_checkout_normalize_us_phone' ) ) {
	/**
	 * Digits of a US phone number without the country code, as the classic form posts it.
	 *
	 * Wallets send E.164 (+17273263213); the classic validators expect 10 digits.
	 * Anything that is not a US number with a leading 1 keeps all its digits.
	 *
	 * @param string $phone Raw phone.
	 * @return string
	 */
	function bh_checkout_normalize_us_phone( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );

		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			return substr( $digits, 1 );
		}

		return $digits;
	}
}

if ( ! function_exists( 'bh_checkout_fields_from_customer' ) ) {
	/**
	 * Classic checkout field array (billing_* / shipping_*) built from a customer.
	 *
	 * An empty shipping field takes the billing value, as the classic checkout does
	 * when "Ship to a different address" is not checked.
	 *
	 * @param WC_Customer $customer Customer already filled from the checkout request.
	 * @return array<string, string>
	 */
	function bh_checkout_fields_from_customer( WC_Customer $customer ): array {
		$keys   = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' );
		$fields = array( 'billing_email' => (string) $customer->get_billing_email() );

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			foreach ( $keys as $key ) {
				$getter = "get_{$type}_{$key}";
				$value  = is_callable( array( $customer, $getter ) ) ? (string) $customer->$getter() : '';
				if ( '' === $value && 'shipping' === $type ) {
					$value = $fields[ "billing_{$key}" ];
				}
				$fields[ "{$type}_{$key}" ] = $value;
			}
		}

		foreach ( array( 'billing_phone', 'shipping_phone' ) as $key ) {
			$fields[ $key ] = bh_checkout_normalize_us_phone( $fields[ $key ] );
		}
		foreach ( array( 'billing_state', 'shipping_state', 'billing_country', 'shipping_country' ) as $key ) {
			$fields[ $key ] = strtoupper( $fields[ $key ] );
		}

		return $fields;
	}
}

if ( ! function_exists( 'bh_checkout_collect_validation_errors' ) ) {
	/**
	 * Runs the shared validators against $fields and returns their errors.
	 *
	 * $fields is exposed as $_POST while the validators run (slashed, like a real request);
	 * $_POST and the session notices are restored afterwards, so nothing leaks to the page.
	 * A validator that throws is logged and refuses the order with WooCommerce's generic
	 * message, as the classic checkout does: a check that could not run never lets it through.
	 *
	 * @param array<string, string> $fields Classic checkout field array.
	 * @return array<int, array{source: string, message: string}>
	 */
	function bh_checkout_collect_validation_errors( array $fields ): array {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}
		if ( ! WC()->session ) {
			wc_get_logger()->warning( 'Shared checkout validators skipped: no WooCommerce session.', array( 'source' => 'bh-checkout-helpers' ) );
			return array();
		}

		$errors       = array();
		$saved_post   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$saved_notice = wc_get_notices();

		$run = function ( array $validator, array $args ) use ( &$errors ) {
			wc_clear_notices();
			$wp_error = $args ? end( $args ) : null;

			try {
				call_user_func_array( $validator['callback'], $args );
			} catch ( \Throwable $e ) {
				wc_get_logger()->error(
					'Shared checkout validator threw: ' . $validator['id'] . ' ' . $e->getMessage(),
					array( 'source' => 'bh-checkout-helpers' )
				);
				$errors[] = array(
					'source'  => $validator['id'],
					'message' => __( 'We were unable to process your order, please try again.', 'woocommerce' ),
				);
			}

			$messages = array();
			foreach ( wc_get_notices( 'error' ) as $notice ) {
				$messages[] = is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice;
			}
			if ( $wp_error instanceof WP_Error ) {
				$messages = array_merge( $messages, $wp_error->get_error_messages() );
			}

			foreach ( $messages as $message ) {
				$message = trim( html_entity_decode( wp_strip_all_tags( $message ), ENT_QUOTES, 'UTF-8' ) );
				if ( '' !== $message ) {
					$errors[] = array(
						'source'  => $validator['id'],
						'message' => $message,
					);
				}
			}
		};

		$_POST = wp_slash( $fields );

		try {
			foreach ( bh_checkout_registered_validators( 'woocommerce_checkout_process' ) as $validator ) {
				$run( $validator, array() );
			}
			foreach ( bh_checkout_registered_validators( 'woocommerce_after_checkout_validation' ) as $validator ) {
				$run( $validator, array( $fields, new WP_Error() ) );
			}
		} finally {
			$_POST = $saved_post;
			wc_set_notices( $saved_notice );
		}

		return $errors;
	}
}
