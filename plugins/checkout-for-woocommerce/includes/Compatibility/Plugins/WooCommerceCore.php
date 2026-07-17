<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class WooCommerceCore extends CompatibilityAbstract {
	public function is_available(): bool {
		return true; // always on, baby
	}

	public function pre_init() {
		// Using this instead of is_ajax() in case is_ajax() is not available
		// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( cfw_apply_filters( 'wp_doing_ajax', defined( 'DOING_AJAX' ) && DOING_AJAX ) && ! isset( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		add_action( 'woocommerce_before_checkout_process', [ $this, 'sync_billing_fields_on_process_checkout' ] );
		add_filter( 'wc_add_to_cart_message_html', [ $this, 'maybe_suppress_add_to_cart_notice' ] );
	}

	public function run() {
		//phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
		add_action(
			'cfw_checkout_before_billing_address',
			function () {
				do_action( 'woocommerce_before_checkout_billing_form', WC()->checkout() );
			}
		);

		add_action(
			'cfw_checkout_after_billing_address',
			function () {
				do_action( 'woocommerce_after_checkout_billing_form', WC()->checkout() );
			}
		);

		add_action(
			'cfw_checkout_before_shipping_address',
			function () {
				do_action( 'woocommerce_before_checkout_shipping_form', WC()->checkout() );
			}
		);

		add_action(
			'cfw_checkout_after_shipping_address',
			function () {
				do_action( 'woocommerce_after_checkout_shipping_form', WC()->checkout() );
			}
		);

		add_action(
			'cfw_checkout_customer_info_tab',
			function () {
				/**
				 * This action is generally used to output HTML that breaks our layout
				 *
				 * It's also used to output express payment sections so we really can't show it
				 */
				ob_start();

				do_action( 'woocommerce_checkout_before_customer_details' );

				ob_clean();
			},
			5
		);

		add_action(
			'cfw_checkout_customer_info_tab',
			function () {
				/**
				 * This action is generally used to output HTML that breaks our layout
				 * so we run it but hide the output. This allows plugins that
				 * initialize their stuff on this hook to still function
				 */
				ob_start();

				do_action( 'woocommerce_checkout_after_customer_details' );

				echo '<div class="cfw-force-hidden">' . cfw_clean_html( ob_get_clean() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			55
		);
		//phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment

		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10 );

		if ( PlanManager::can_access_feature( 'enable_order_pay' ) ) {
			remove_action( 'before_woocommerce_pay', 'woocommerce_output_all_notices', 10 );
		}

		// Highlighted Countries
		if ( SettingsManager::instance()->get_setting( 'enable_highlighted_countries' ) === 'yes' ) {
			add_filter( 'woocommerce_countries_shipping_countries', [ $this, 'highlight_countries' ] );
			add_filter( 'woocommerce_countries_allowed_countries', [ $this, 'highlight_countries' ] );
		}

		add_filter( 'woocommerce_countries_shipping_countries', [ $this, 'fool_woo_into_handling_one_country_the_way_we_prefer' ] );
		add_filter( 'woocommerce_countries_allowed_countries', [ $this, 'fool_woo_into_handling_one_country_the_way_we_prefer' ] );
		add_filter( 'woocommerce_form_field', [ $this, 'removed_shim_country_so_woo_handles_one_country_the_way_we_prefer' ], 200000, 3 );
	}

	public function run_on_thankyou() {
		if ( PlanManager::can_access_feature( 'enable_thank_you_page', 'plus' ) ) {
			remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );

			add_action(
				'cfw_thank_you_main_container_start',
				function ( $order ) {
					if ( $order ) {
						//phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
						do_action( 'woocommerce_before_thankyou', $order->get_id() );
						//phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
					}
				}
			);
		}

		// Remove default view order stuff
		if ( PlanManager::can_access_feature( 'override_view_order_template' ) ) {
			remove_action( 'woocommerce_view_order', 'woocommerce_order_details_table', 10 );
		}
	}

	public function sync_billing_fields_on_process_checkout() {
		// Is the CFW flag present and is it set to use the shipping address as the billing address?
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bill_to_different_address'] ) && 'same_as_shipping' === $_POST['bill_to_different_address'] ) {
			foreach ( $_POST as $key => $value ) {
				// If a plugin has added a shipping email field, don't sync it
				// Specifically fixes issues with WooCommerce Pakettikauppa
				if ( 'shipping_email' === $key ) {
					continue;
				}

				// If this is a shipping field, create a duplicate billing field
				if ( substr( $key, 0, 9 ) === 'shipping_' ) {
					$billing_field_key = substr_replace( $key, 'billing_', 0, 9 );

					$_POST[ $billing_field_key ] = $value;
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function maybe_suppress_add_to_cart_notice( $message ) {
		// Guard against re-entrant calls: Example: WC Subscriptions' woocommerce_add_to_cart_redirect
		// callback can invoke wc_add_to_cart_message(), which re-fires this filter, causing infinite recursion.
		static $is_running = false;

		if ( $is_running ) {
			return $message;
		}

		/**
		 * Filters whether to suppress add to cart notices at checkout
		 *
		 * @since 2.0.0
		 *
		 * @param bool $supress_notices True suppress, false allow
		 */
		if ( ! apply_filters( 'cfw_suppress_add_to_cart_notices', true ) ) {
			return $message;
		}

		if ( empty( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $message;
		}

		$wc_ajax = sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Only suppress here for checkout redirected add to carts.
		// The try/finally ensures $is_running is reset even if a callback throws and the exception
		// is caught higher up, which would otherwise leave the guard stuck at true for the request.
		$checkout_url = wc_get_checkout_url();
		$is_running   = true;
		try {
			$redirect_url = cfw_apply_filters( 'woocommerce_add_to_cart_redirect', wc_get_cart_url(), null );
		} finally {
			$is_running = false;
		}

		// If not redirecting to checkout, bail
		if ( $redirect_url !== $checkout_url ) {
			return $message;
		}

		if ( in_array( $wc_ajax, [ 'add_to_cart', 'cfw_add_to_cart' ], true ) ) {
			return '';
		}

		return $message;
	}

	public function highlight_countries( array $countries ): array {
		/**
		 * The list of highlighted countries
		 *
		 * @since 6.0.0
		 * @param array $highlighted_countries The highlighted countries
		 */
		$highlighted_countries = array_flip( (array) apply_filters( 'cfw_highlighted_countries', SettingsManager::instance()->get_setting( 'highlighted_countries' ) ) );

		if ( empty( $highlighted_countries ) ) {
			return $countries;
		}

		foreach ( $highlighted_countries as $key => $value ) {
			if ( ! isset( $countries[ $key ] ) ) {
				unset( $highlighted_countries[ $key ] );
			}
		}

		return array_merge( $highlighted_countries, [ '--' => '---' ], $countries );
	}

	public function fool_woo_into_handling_one_country_the_way_we_prefer( array $countries ): array {
		if ( count( $countries ) === 1 ) {
			$countries['shim'] = 'shim';
		}

		return $countries;
	}

	public function removed_shim_country_so_woo_handles_one_country_the_way_we_prefer( $field, $key, $args ) {
		if ( 'country' === $args['type'] ) {
			// Remove the shim option
			$field = preg_replace( '/<option value="shim".*shim<\/option>/', '', $field, -1, $count );

			// Now select the remaining country
			if ( $count ) {
				$field = str_replace( 'value=', 'selected="selected" value=', $field );
			}
		}

		return $field;
	}
}
