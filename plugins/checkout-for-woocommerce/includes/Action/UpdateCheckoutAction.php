<?php

namespace Objectiv\Plugins\Checkout\Action;

use Exception;
use Objectiv\Plugins\Checkout\Managers\AssetManager;

class UpdateCheckoutAction extends CFWAction {
	public function __construct() {
		parent::__construct( 'update_order_review' );
	}

	/**
	 * @since 1.0.0
	 */
	public function load() {
		if ( ! isset( $_POST['cfw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		remove_all_actions( "wc_ajax_{$this->get_id()}" );
		add_action( "wc_ajax_{$this->get_id()}", array( $this, 'execute' ), 1 );

		/**
		 * These legacy handlers are here because Woo adds them and 3rd party plugins
		 * sometimes expect them. This is particularly important for WooCommerce Memberships
		 * which uses these handlers to detect valid WC ajax requests when the home page is
		 * restricted
		 */
		remove_all_actions( "wp_ajax_woocommerce_{$this->get_id()}" );
		add_action( "wp_ajax_woocommerce_{$this->get_id()}", array( $this, 'execute' ), 1 );

		remove_all_actions( "wp_ajax_nopriv_woocommerce_{$this->get_id()}" );
		add_action( "wp_ajax_nopriv_woocommerce_{$this->get_id()}", array( $this, 'execute' ), 1 );
	}

	/**
	 * @throws Exception If the nonce is invalid.
	 */
	public function action() {
		/**
		 * Filters whether to validate nonce for update order review
		 *
		 * @param bool $validate_nonce Whether to validate nonce for update order review
		 * @since 10.0.2
		 * @return bool
		 */
		if ( apply_filters( 'cfw_validate_update_order_review_nonce', true ) ) {
			check_ajax_referer( 'update-order-review', 'security' );
		}

		\WC_Checkout::instance();
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		if ( WC()->cart->is_empty() && ! is_customize_preview() && cfw_apply_filters( 'woocommerce_checkout_update_order_review_expired', true ) ) {
			/**
			 * Filters which element to update with session expired notice
			 *
			 * @param string $element Element to update with session expired notice
			 *
			 * @since 5.2.0
			 */
			$target_selector = apply_filters( 'cfw_session_expired_target_element', 'form.woocommerce-checkout' );

			$this->out(
				array(
					'redirect'  => false,
					'fragments' => cfw_apply_filters(
						'woocommerce_update_order_review_fragments',
						array(
							$target_selector => '<div class="woocommerce-error">' . __( 'Sorry, your session has expired.', 'woocommerce' ) . ' <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" class="wc-backward">' . __( 'Return to shop', 'woocommerce' ) . '</a></div>',
						)
					),
				)
			);
		}

		/** This action is documented in woocommerce/includes/class-wc-ajax.php */
		cfw_do_action( 'woocommerce_checkout_update_order_review', isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/**
		 * Fires when updating CheckoutWC order review
		 *
		 * @param string $post_data The POST data
		 *
		 * @since 2.0.0
		 */
		do_action( 'cfw_checkout_update_order_review', isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		$posted_shipping_methods = isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : array();

		if ( is_array( $posted_shipping_methods ) ) {
			foreach ( $posted_shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = $value;
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		if ( ! empty( $_POST['payment_method'] ) ) {
			WC()->session->set( 'chosen_payment_method', wc_clean( wp_unslash( $_POST['payment_method'] ) ) );
		}

		WC()->customer->set_props(
			array(
				'billing_country'   => isset( $_POST['country'] ) ? wc_clean( wp_unslash( $_POST['country'] ) ) : null,
				'billing_state'     => isset( $_POST['state'] ) ? wc_clean( wp_unslash( $_POST['state'] ) ) : null,
				'billing_postcode'  => isset( $_POST['postcode'] ) ? trim( wc_clean( wp_unslash( $_POST['postcode'] ) ) ) : null,
				'billing_city'      => isset( $_POST['city'] ) ? wc_clean( wp_unslash( $_POST['city'] ) ) : null,
				'billing_address_1' => isset( $_POST['address'] ) ? wc_clean( wp_unslash( $_POST['address'] ) ) : null,
				'billing_address_2' => isset( $_POST['address_2'] ) ? wc_clean( wp_unslash( $_POST['address_2'] ) ) : null,
			)
		);

		if ( wc_ship_to_billing_address_only() || ! WC()->cart->needs_shipping() ) {
			WC()->customer->set_props(
				array(
					'shipping_country'   => isset( $_POST['country'] ) ? wc_clean( wp_unslash( $_POST['country'] ) ) : null,
					'shipping_state'     => isset( $_POST['state'] ) ? wc_clean( wp_unslash( $_POST['state'] ) ) : null,
					'shipping_postcode'  => isset( $_POST['postcode'] ) ? trim( wc_clean( wp_unslash( $_POST['postcode'] ) ) ) : null,
					'shipping_city'      => isset( $_POST['city'] ) ? wc_clean( wp_unslash( $_POST['city'] ) ) : null,
					'shipping_address_1' => isset( $_POST['address'] ) ? wc_clean( wp_unslash( $_POST['address'] ) ) : null,
					'shipping_address_2' => isset( $_POST['address_2'] ) ? wc_clean( wp_unslash( $_POST['address_2'] ) ) : null,
				)
			);
		} else {
			WC()->customer->set_props(
				array(
					'shipping_country'   => isset( $_POST['s_country'] ) ? wc_clean( wp_unslash( $_POST['s_country'] ) ) : null,
					'shipping_state'     => isset( $_POST['s_state'] ) ? wc_clean( wp_unslash( $_POST['s_state'] ) ) : null,
					'shipping_postcode'  => isset( $_POST['s_postcode'] ) ? trim( wc_clean( wp_unslash( $_POST['s_postcode'] ) ) ) : null,
					'shipping_city'      => isset( $_POST['s_city'] ) ? wc_clean( wp_unslash( $_POST['s_city'] ) ) : null,
					'shipping_address_1' => isset( $_POST['s_address'] ) ? wc_clean( wp_unslash( $_POST['s_address'] ) ) : null,
					'shipping_address_2' => isset( $_POST['s_address_2'] ) ? wc_clean( wp_unslash( $_POST['s_address_2'] ) ) : null,
				)
			);
		}

		$calculated_shipping = isset( $_POST['has_full_address'] ) && wc_string_to_bool( wc_clean( wp_unslash( $_POST['has_full_address'] ) ) );
		WC()->customer->set_calculated_shipping( $calculated_shipping );

		WC()->customer->save();

		/**
		 * Handle situation where cart transitions from shipped to non-shipped or vice versa
		 *
		 * In this edge case we have to refresh the page
		 */
		$needs_shipping_before = WC()->cart->needs_shipping_address();

		/**
		 * Same for free to paid orders
		 */
		$needs_payment_before = WC()->cart->needs_payment();

		// Is free shipping an available method before we update the cart?
		$was_free_shipping_available_pre_cart_update = cfw_is_free_shipping_available();

		/**
		 * Fires after customer address data has been updated. This is where we do cart updates
		 *
		 * @since 7.0.0
		 */
		do_action( 'cfw_update_checkout_after_customer_save', isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$reload_checkout = isset( WC()->session->reload_checkout );

		if ( ! $reload_checkout && WC()->cart->needs_shipping_address() !== $needs_shipping_before ) {
			$reload_checkout = true;
		}

		if ( ! $reload_checkout && WC()->cart->needs_payment() !== $needs_payment_before ) {
			$reload_checkout = true;
		}

		/**
		 * Filters whether to reload checkout
		 *
		 * @param bool $reload_checkout Whether to reload checkout
		 * @since 10.3.9
		 */
		$reload_checkout = apply_filters( 'cfw_reload_checkout', $reload_checkout );

		/**
		 * Filters whether to redirect the checkout page during refresh
		 *
		 * @param bool|string Boolean false means don't redirect, string means redirect to URL
		 *
		 * @since 2.0.0
		 */
		$redirect = apply_filters( 'cfw_update_checkout_redirect', false );

		parse_str( wp_unslash( $_POST['post_data'] ), $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$applied_coupon = '';

		if ( ! empty( $post_data['coupon_code'] ) ) {
			// Fix issue with email restricted coupons and WooCommerce 8.8.x+. Ticket: https://secure.helpscout.net/conversation/2586133248/19534?folderId=2454654
			$billing_email = isset( $post_data['billing_email'] ) ? wc_clean( wp_unslash( $post_data['billing_email'] ) ) : null;

			if ( is_string( $billing_email ) && is_email( $billing_email ) ) {
				WC()->customer->set_billing_email( $billing_email );
			}

			if ( WC()->cart->add_discount( wc_format_coupon_code( wp_unslash( $post_data['coupon_code'] ) ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$applied_coupon = $post_data['coupon_code'];
			}
		}

		// Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		/**
		 * Fires after shipping and totals calculated during update_checkout refresh
		 *
		 * @since 9.0.0
		 */
		do_action( 'cfw_after_update_checkout_calculated', isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : '', $was_free_shipping_available_pre_cart_update ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		unset( WC()->session->refresh_totals, WC()->session->reload_checkout );

		/**
		 * Filters payment methods during update_checkout refresh
		 *
		 * @param string The payment methods container and content
		 *
		 * @since 4.0.2
		 */
		$updated_payment_methods = apply_filters( 'cfw_update_payment_methods', cfw_get_payment_methods() );

		/** This action is documented in woocommerce/includes/class-wc-checkout.php */
		cfw_do_action( 'woocommerce_check_cart_items' );

		$update_checkout_output = array(
			'fragments'                 => cfw_apply_filters(
				'woocommerce_update_order_review_fragments', /** This filter is documented in woocommerce/includes/class-wc-ajax.php */
				array(
					'#cfw-checkout-before-order-review' => $this->get_action_output( 'woocommerce_checkout_before_order_review', 'cfw-checkout-before-order-review' ),
					'#cfw-checkout-after-order-review'  => $this->get_action_output( 'woocommerce_checkout_after_order_review', 'cfw-checkout-after-order-review' ),
					'#cfw-place-order'                  => cfw_get_place_order(),
					'#cfw-billing-methods'              => $updated_payment_methods,
					'#woocommerce_review_order_before_cart_contents' => $this->get_action_output( 'woocommerce_review_order_before_cart_contents' ),
				)
			),
			'reload'                    => $reload_checkout,
			'redirect'                  => $redirect,
			'show_shipping_tab'         => cfw_show_shipping_tab(),
			'applied_coupon'            => $applied_coupon,
			'has_valid_shipping_method' => cfw_all_packages_have_available_shipping_methods( WC()->shipping()->get_packages() ),
			'total'                     => WC()->cart->get_total( 'edit' ),
			'data'                      => AssetManager::get_data(),
			'cart_hash'                 => WC()->cart->get_cart_hash(),
		);

		if ( ! $reload_checkout ) {
			// Do this last so that anything that runs above can bubble up a notice
			$update_checkout_output['notices']  = cfw_get_woocommerce_notices( false ); // don't clear the notices so that
			$update_checkout_output['messages'] = wc_print_notices( true ); // <-- we can use them here
		}

		$this->out( $update_checkout_output );
	}

	protected function get_action_output( $action, $container = '' ) {
		ob_start();

		echo '<div id="' . esc_attr( $container ) . '">';
		cfw_do_action( $action );
		echo '</div>';

		return ob_get_clean();
	}
}
