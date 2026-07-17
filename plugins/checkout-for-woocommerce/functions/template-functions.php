<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * Takes a callable and excutes it, then returns the content inside
 * a row / max width column
 *
 * @param callable $callback the callback to call to get the content
 */
function cfw_auto_wrap( callable $callback ) {
	if ( is_callable( $callback ) ) {
		ob_start();

		call_user_func( $callback );

		$func_output = ob_get_clean();

		if ( ! empty( $func_output ) ) {
			$output = '<div class="row">';
			$output .= '<div class="col-12">';

			$output .= $func_output;

			$output .= '</div>';
			$output .= '</div>';

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $output;
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

/**
 * Thank you page section wrap open
 *
 * @param string $container_class the class name
 */
function cfw_thank_you_section_start( string $container_class ) {
	$container_class = esc_attr( $container_class );
	echo "<section class=\"{$container_class}\">"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function cfw_thank_you_section_auto_wrap( callable $callback, $container_class, $parameters = [] ) {
	if ( is_callable( $callback ) ) {
		ob_start();

		call_user_func( $callback, ...$parameters );

		$func_output = ob_get_clean();

		if ( ! empty( $func_output ) ) {
			$output = "<section class=\"{$container_class}\">";
			$output .= '<div class="inner">';

			$output .= $func_output;

			$output .= '</div>';
			$output .= '</section>';

			echo $output; // phpcs:ignore
		}
	}
}

function cfw_cart_summary_mobile_header( $total = false ) {
	?>
	<div id="cfw-mobile-cart-header">
		<div class="cfw-display-table cfw-w100">
			<a id="cfw-expand-cart" class="cfw-display-table-row">
				<span class="cfw-cart-icon cfw-display-table-cell">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-shopping-cart"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
				</span>

				<span class="cfw-cart-summary-label-show cfw-small cfw-display-table-cell">
					<span>
						<?php
						$cart_summary_mobile_label = SettingsManager::instance()->get_setting( 'cart_summary_mobile_label' );
						if ( ! empty( $cart_summary_mobile_label ) ) {
							echo wp_kses_post( $cart_summary_mobile_label );
						} else {
							/**
							 * Filters show order summary link label
							 *
							 * @param string $show_order_summary_label The show order summary link label
							 *
							 * @since 2.0.0
							 */
							echo wp_kses_post( apply_filters( 'cfw_show_order_summary_link_text', esc_html__( 'Show order summary', 'checkout-wc' ) ) );
						}

						?>
					</span>

					<svg width="11" height="6" xmlns="http://www.w3.org/2000/svg" class="cfw-arrow" fill="#000"><path d="M.504 1.813l4.358 3.845.496.438.496-.438 4.642-4.096L9.504.438 4.862 4.534h.992L1.496.69.504 1.812z"></path></svg>
				</span>

				<span class="cfw-cart-summary-label-hide cfw-small cfw-display-table-cell">
					<span>
						<?php
						/**
						 * Filters hide order summary link label
						 *
						 * @param string $hide_order_summary_label The hide order summary label
						 *
						 * @since 3.0.0
						 */
						echo wp_kses_post( apply_filters( 'cfw_show_order_summary_hide_link_text', esc_html__( 'Hide order summary', 'checkout-wc' ) ) );
						?>
					</span>

					<svg width="11" height="6" xmlns="http://www.w3.org/2000/svg" class="cfw-arrow" fill="#000"><path d="M.504 1.813l4.358 3.845.496.438.496-.438 4.642-4.096L9.504.438 4.862 4.534h.992L1.496.69.504 1.812z"></path></svg>
				</span>

				<span id="cfw-mobile-total" class="total amount cfw-display-table-cell">
					<?php echo empty( $total ) ? WC()->cart->get_total() : $total; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</a>
		</div>
	</div>
	<?php
}

/**
 * Helper function to output a close div tag
 */
function cfw_close_cart_summary_div() {
	/**
	 * Fires after cart summary before closing </div> tag
	 *
	 * @since 3.0.0
	 */
	do_action( 'cfw_after_cart_summary' );
	?>
	</div>
	<?php
}

/**
 * The opening div tag for the cart summary content
 */
function cfw_cart_summary_content_open_wrap() {
	?>
	<div id="cfw-cart-summary-content" class="<?php echo 'yes' === SettingsManager::instance()->get_setting( 'enable_sticky_cart_summary' ) ? 'cfw-sticky' : ''; ?>">
	<?php
}

/**
 * Output a visually hidden h1 for the checkout page so screen readers can identify the page.
 */
function cfw_checkout_page_heading() {
	if ( ! cfw_is_checkout() ) {
		return;
	}

	?>
	<h1 class="visually-hidden">
		<?php
		echo esc_html( apply_filters( 'cfw_checkout_page_heading', __( 'Checkout', 'checkout-wc' ) ) );
		?>
	</h1>
	<?php
}

/**
 * Handles WooCommerce before order review hooks
 *
 * This hook is in a different place on our checkout so
 * we have to wrap it with an ID and apply styles similar to native
 */
function cfw_cart_summary_before_order_review() {
	?>
	<div id="cfw-checkout-before-order-review">
		<?php cfw_do_action( 'woocommerce_checkout_before_order_review' ); ?>
	</div>
	<?php
}

/**
 * Handles WooCommerce after order review hooks
 *
 * This hook is in a different place on our checkout so
 * we have to wrap it with an ID and apply styles similar to native
 */
function cfw_cart_summary_after_order_review() {
	?>
	<div id="cfw-checkout-after-order-review">
		<?php cfw_do_action( 'woocommerce_checkout_after_order_review' ); ?>
	</div>
	<?php
}

/**
 * Print WooCommerce notices with placeholder div for JS behaviors
 *
 * @param bool $clear_notices whether to clear notices after they are printed
 */
function cfw_wc_print_notices( bool $clear_notices = true ) {
	/**
	 * Filters WooCommerce notices before display
	 *
	 * @since 8.2.19
	 *
	 * @param array $all_notices
	 */
	$all_notices  = apply_filters( 'cfw_wc_print_notices', WC()->session->get( 'wc_notices', [] ) );
	$notice_types = cfw_apply_filters( 'woocommerce_notice_types', [ 'error', 'success', 'notice' ] );
	$notices      = [];

	foreach ( $notice_types as $notice_type ) {
		if ( count( $all_notices[ $notice_type ] ?? [] ) > 0 ) {
			$notices[ $notice_type ] = $all_notices[ $notice_type ];
		}
	}

	$type_class_mapping = [
		'error'   => 'cfw-alert-error',
		'notice'  => 'cfw-alert-info',
		'success' => 'cfw-alert-success',
	];

	$used_alert_ids = [];

	if ( $clear_notices ) {
		wc_clear_notices();
	}

	// DO NOT REMOVE PLACEHOLDER BELOW
	// It is a template for new alerts
	?>
	<div id="cfw-alert-placeholder">
		<div class="cfw-alert">
			<div class="message"></div>
		</div>
	</div>

	<div id="cfw-alert-container" class="woocommerce-notices-wrapper" aria-live="polite">
		<?php if ( ! empty( $notices ) ) : ?>
			<?php foreach ( $notices as $type => $messages ) : ?>
				<?php
				foreach ( $messages as $message ) :
					// In WooCommerce 3.9+, messages can be an array with two properties:
					// - notice
					// - data
					$message = $message['notice'] ?? $message;

					$alert_id = md5( $message . $type );

					if ( in_array( $alert_id, $used_alert_ids, true ) || empty( $message ) ) {
						continue;
					}

					?>

					<?php $used_alert_ids[] = $alert_id; ?>
					<div id="cfw-alert-<?php echo esc_attr( $alert_id ); ?>" class="cfw-alert <?php echo esc_attr( $type_class_mapping[ $type ] ); ?>">
						<div class="message">
							<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Notices with wrap
 */
function cfw_wc_print_notices_with_wrap() {
	/**
	 * Fires before printing notices
	 *
	 * @since 9.0.0
	 */
	do_action( 'cfw_before_print_notices' );

	cfw_auto_wrap( 'cfw_wc_print_notices' );
}

/**
 * Payment Request buttons (aka Express Checkout)
 */
function cfw_payment_request_buttons() {
	if ( SettingsManager::instance()->get_setting( 'disable_express_checkout' ) === 'yes' ) {
		return;
	}

	if ( ! has_action( 'cfw_payment_request_buttons' ) ) {
		return;
	}

	?>
	<div id="cfw-payment-request-buttons" style="position: absolute; opacity: 0; pointer-events: none;">
		<h2><?php esc_html_e( 'Express checkout', 'checkout-wc' ); ?></h2>
		<?php
		/**
		 * Hook for adding payment request buttons
		 *
		 * @since 3.0.0
		 */
		do_action( 'cfw_payment_request_buttons' );
		?>
	</div>
	<?php
	cfw_add_separator();
}

/**
 * Customer information tab heading
 */
function cfw_customer_info_tab_heading() {
	?>
	<?php if ( cfw_enable_accessibility_improvements() ) : ?>
	<h2 id="cfw-customer-info-heading" class="cfw-panel-heading" tabindex="-1">
		<?php
		/**
		 * Filters customer info tab heading
		 *
		 * @param string $customer_info_heading Customer info tab heading
		 *
		 * @since 2.0.0
		 */
		echo wp_kses_post( apply_filters( 'cfw_customer_information_heading', __( 'Information', 'checkout-wc' ) ) );

		?>
	</h2>
	<?php else : ?>
	<h3 id="cfw-customer-info-heading" class="cfw-panel-heading" tabindex="-1">
		<?php
		/**
		 * Filters customer info tab heading
		 *
		 * @param string $customer_info_heading Customer info tab heading
		 *
		 * @since 2.0.0
		 */
		echo wp_kses_post( apply_filters( 'cfw_customer_information_heading', __( 'Information', 'checkout-wc' ) ) );
		?>
	</h3>
	<?php endif; ?>
	<?php
}

/**
 * Order review tab heading
 */
function cfw_order_review_tab_heading() {
	?>
	<?php if ( cfw_enable_accessibility_improvements() ) : ?>
	<h2 id="cfw-order-review-heading" class="cfw-panel-heading" tabindex="-1">
		<?php
		/**
		 * Filters order review tab heading
		 *
		 * @param string $order_review_tab_heading Order review tab heading
		 *
		 * @since 2.0.0
		 */
		echo wp_kses_post( apply_filters( 'cfw_order_review_tab_heading', __( 'Order review', 'checkout-wc' ) ) );

		?>
	</h2>
	<?php else : ?>
	<h3 id="cfw-order-review-heading" class="cfw-panel-heading" tabindex="-1">
		<?php
		/**
		 * Filters order review tab heading
		 *
		 * @param string $order_review_tab_heading Order review tab heading
		 *
		 * @since 2.0.0
		 */
		echo wp_kses_post( apply_filters( 'cfw_order_review_tab_heading', __( 'Order review', 'checkout-wc' ) ) );
		?>
	</h3>
	<?php endif; ?>
	<?php
}

function cfw_customer_info_tab_account() {
	?>
	<div id="cfw-account-details" class="cfw-module">
		<?php
		/**
		 * Fires before account details on customer info tab
		 *
		 * @since 7.0.0
		 */
		do_action( 'cfw_before_customer_info_account_details' );

		cfw_maybe_show_already_have_an_account_text();
		cfw_maybe_show_email_field();
		cfw_account_password_field_slide();
		cfw_create_account_checkbox();
		cfw_maybe_show_welcome_back_text();

		/**
		 * Fires before account details on customer info tab
		 *
		 * @since 7.0.0
		 */
		do_action( 'cfw_after_customer_info_account_details' );
		?>
	</div>
	<?php
}

function cfw_customer_info_tab_account_fields() {
	cfw_output_account_checkout_fields( WC()->checkout() );
}

function cfw_maybe_show_already_have_an_account_text() {
	if ( ! cfw_is_login_at_checkout_allowed() ) {
		return;
	}

	if ( is_user_logged_in() ) {
		return;
	}

	?>
	<div class="cfw-have-acc-text cfw-small <?php echo WC()->checkout()->is_registration_required() ? 'account-does-not-exist-text' : ''; ?>">
		<?php
		/**
		 * Fires before enhanced login prompt
		 *
		 * @since 3.0.0
		 */
		do_action( 'cfw_before_enhanced_login_prompt' );
		?>

		<span>
			<?php
			/**
			 * Filters already have account text
			 *
			 * @param string $already_have_account_text Already have an account text
			 *
			 * @since 2.0.0
			 */
			echo wp_kses_post( apply_filters( 'cfw_already_have_account_text', __( 'Already have an account with us?', 'checkout-wc' ) ) );
			?>
		</span>

		<a id="cfw-login-modal-trigger" href="#">
			<?php
			/**
			 * Filters login faster text
			 *
			 * @param string $login_faster_text Login faster text
			 *
			 * @since 2.0.0
			 */
			echo wp_kses_post( apply_filters( 'cfw_login_faster_text', esc_html__( 'Log in.', 'checkout-wc' ) ) );
			?>
		</a>

		<?php
		/**
		 * Fires after enhanced login prompt
		 *
		 * @since 2.0.0
		 */
		do_action( 'cfw_after_enhanced_login_prompt' );
		?>
	</div>

	<?php if ( WC()->checkout()->is_registration_required() ) : ?>
	<div class="cfw-have-acc-text cfw-small account-exists-text">
		<span>
			<?php
			echo cfw_apply_filters( 'woocommerce_registration_error_email_exists', wp_kses_post( __( 'An account is already registered with your email address. <a href="#" class="showlogin">Please log in.</a>', 'woocommerce' ) ), '' );

 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
		</span>
	</div>
	<?php endif; ?>
	<?php
}

function cfw_maybe_show_email_field() {
	/**
	 * Shows the email field if the user is not logged in and the setting is enabled
	 *
	 * @param bool $show_email_field Whether to show the email field
	 *
	 * @since 7.0.0
	 */
	if ( is_user_logged_in() && apply_filters( 'cfw_hide_email_field_for_logged_in_users', true ) ) {
		// We aren't using woocommerce_form_field here because WooCommerce Wholesale Lead Capture is evil
		// Added form-row for ticket https://secure.helpscout.net/conversation/2554754591/19213/
		printf( '<div class="form-row"><input type="hidden" name="billing_email" id="billing_email" value="%s"></div>', esc_html( wp_get_current_user()->user_email ) );

		return;
	}

	$billing_fields = WC()->checkout()->get_checkout_fields( 'billing' );
	$email_field    = $billing_fields['billing_email'];
	$value          = WC()->checkout()->get_value( 'billing_email' );

	woocommerce_form_field( 'billing_email', $email_field, $value );

	/**
	 * Fires after email field output
	 *
	 * @since 3.0.0
	 */
	do_action( 'cfw_checkout_after_email' );
}

function cfw_account_password_field_slide() {
	if ( is_user_logged_in() || ! WC()->checkout()->is_registration_enabled() ) {
			return;
	}

	?>
	<div id="cfw-account-password-slide" class="cfw-input-wrap-row">
		<?php
		if ( SettingsManager::instance()->get_setting( 'registration_style' ) === 'woocommerce' ) :
			if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) {
				woocommerce_form_field(
					'account_username',
					[
						'type'              => 'text',
						'label'             => __( 'Account username', 'woocommerce' ),
						'required'          => true,
						'placeholder'       => esc_attr__( 'Username', 'woocommerce' ),
						'custom_attributes' => [
							'data-parsley-trigger' => 'keyup change focusout',
						],
						'autocomplete'      => 'username',
					]
				);
			}

			woocommerce_form_field(
				'account_password',
				[
					'type'              => 'password',
					'label'             => __( 'Create account password', 'woocommerce' ),
					'required'          => true,
					'placeholder'       => __( 'Create account password', 'woocommerce' ),
					'custom_attributes' => [
						'data-parsley-trigger' => 'keyup change focusout',
					],
					'autocomplete'      => 'new-password',
				]
			);
		endif;
		?>
	</div>
	<?php
}

function cfw_create_account_checkbox() {
	if ( is_user_logged_in() || ! WC()->checkout()->is_registration_enabled() ) {
		return;
	}

	?>
	<div class="cfw-input-wrap cfw-check-input">
		<?php if ( ! WC()->checkout()->is_registration_required() && WC()->checkout()->is_registration_enabled() ) : ?>
			<input type="checkbox" id="createaccount" class="cfw-create-account-checkbox" name="createaccount" />
			<label class="cfw-small" for="createaccount">
				<?php
				/**
				 * Filters create account checkbox site name
				 *
				 * @param string $create_account_site_name Create account checkbox site name
				 *
				 * @since 2.0.0
				 */
				$create_account_site_name = apply_filters( 'cfw_create_account_site_name', get_bloginfo( 'name' ) );

				printf(
					/**
					 * Filters create account checkbox label
					 *
					 * @param string $create_account_checkbox_label Create account checkbox label
					 *
					 * @since 2.0.0
					 */
					apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'cfw_create_account_checkbox_label',
						/* translators: %s: site name */
						esc_html__( 'Create %s shopping account.', 'checkout-wc' )
					),
					wp_kses_post( $create_account_site_name )
				);
				?>
			</label>
		<?php elseif ( WC()->checkout()->is_registration_required() ) : ?>
			<span class="cfw-small account-does-not-exist-text">
				<?php
				/**
				 * Filters create account statement
				 *
				 * @param string $create_account_statement Create account statement
				 *
				 * @since 2.0.0
				 */
				echo wp_kses_post( apply_filters( 'cfw_account_creation_statement', __( 'If you do not have an account, we will create one for you.', 'checkout-wc' ) ) );
				?>
			</span>
		<?php endif; ?>
	</div>
	<?php
}

function cfw_maybe_show_welcome_back_text() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	?>
	<div class="cfw-have-acc-text cfw-small">
		<?php
		$current_user = wp_get_current_user();

		if ( ! empty( $current_user->first_name ) && ! empty( $current_user->last_name ) ) {
			$welcome_back_name = $current_user->first_name . ' ' . $current_user->last_name;
		} else {
			$welcome_back_name = $current_user->display_name;
		}

		/**
		 * Filters welcome back statement customer name
		 *
		 * @param string $welcome_back_name Welcome back statement customer name
		 *
		 * @since 2.0.0
		 */
		$welcome_back_name = apply_filters( 'cfw_welcome_back_name', $welcome_back_name );

		/**
		 * Filters welcome back statement customer email
		 *
		 * @param string $welcome_back_email Welcome back statement customer email
		 *
		 * @since 2.0.0
		 */
		$welcome_back_email = apply_filters( 'cfw_welcome_back_email', wp_get_current_user()->user_email );

		$welcome_back_text = sprintf(
			/* translators: %1 is the customer's name, %2 is their email address */
			esc_html__( 'Welcome back, %1$s (%2$s).', 'checkout-wc' ),
			'<strong>' . $welcome_back_name . '</strong>',
			$welcome_back_email
		);

		/**
		 * Filters welcome back statement
		 *
		 * @param string $welcome_back_text Welcome back statement
		 *
		 * @since 7.1.10
		 */
		echo wp_kses_post( apply_filters( 'cfw_welcome_back_text', $welcome_back_text, $welcome_back_name, $welcome_back_email ) );

		/**
		 * Filters whether to show logout link
		 *
		 * @param bool $show_logout_link Show logout link
		 *
		 * @since 2.0.0
		 */
		if ( apply_filters( 'cfw_show_logout_link', false ) ) :
			?>
			<a href="<?php
			echo esc_attr( wp_logout_url( wc_get_checkout_url() ) );

			?>
 "><?php esc_html_e( 'Log out.', 'checkout-wc' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}

function cfw_maybe_output_login_modal_container() {
	?>
	<div id="cfw_login_modal"></div>
	<?php
}

/**
 * The address displayed on the Customer Info tab
 */
function cfw_customer_info_address() {
	/**
	 * Fires before customer info address module
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_before_customer_info_address' );
	?>

	<div id="cfw-customer-info-address" class="cfw-module <?php echo ! WC()->cart->needs_shipping() || wc_ship_to_billing_address_only() ? 'billing' : 'shipping'; ?>">
		<?php
		if ( WC()->cart->needs_shipping_address() ) {
			/**
			 * Fires before shipping address
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_checkout_before_shipping_address' );
		} else {
			/**
			 * Fires before billing address
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_checkout_before_billing_address' );
		}

		?>

		<?php if ( cfw_enable_accessibility_improvements() ) : ?>
		<h2>
			<?php if ( wc_ship_to_billing_address_only() && WC()->cart->needs_shipping() ) : ?>
				<?php
				/**
				 * Filters billing and shipping address heading
				 *
				 * @param string $billing_and_shipping_address_heading Billing and shipping address heading
				 *
				 * @since 2.0.0
				 */
				echo wp_kses_post( apply_filters( 'cfw_billing_shipping_address_heading', esc_html__( 'Billing and Shipping address', 'checkout-wc' ) ) );

				?>
			<?php elseif ( ! WC()->cart->needs_shipping() ) : ?>
				<?php
				/**
				 * Filters billing address heading
				 *
				 * @param string $billing_address_heading Billing address heading
				 *
				 * @since 2.0.0
				 */
				echo wp_kses_post( apply_filters( 'cfw_billing_address_heading', esc_html__( 'Billing address', 'checkout-wc' ) ) );
				?>
			<?php else : ?>
				<?php
				/**
				 * Filters shipping address heading
				 *
				 * @param string $shipping_address_heading Shipping address heading
				 *
				 * @since 2.0.0
				 */
				echo wp_kses_post( apply_filters( 'cfw_shipping_address_heading', esc_html__( 'Shipping address', 'checkout-wc' ) ) );
				?>
			<?php endif; ?>
		</h2>
		<?php else : ?>
		<h3>
			<?php if ( wc_ship_to_billing_address_only() && WC()->cart->needs_shipping() ) : ?>
				<?php
				/**
				 * Filters billing and shipping address heading
				 *
				 * @param string $billing_and_shipping_address_heading Billing and shipping address heading
				 *
				 * @since 2.0.0
				 */
				echo wp_kses_post( apply_filters( 'cfw_billing_shipping_address_heading', esc_html__( 'Billing and Shipping address', 'checkout-wc' ) ) );

				?>
			<?php elseif ( ! WC()->cart->needs_shipping() ) : ?>
				<?php
				/**
				 * Filters billing address heading
				 *
				 * @param string $billing_address_heading Billing address heading
				 *
				 * @since 2.0.0
				 */
				echo wp_kses_post( apply_filters( 'cfw_billing_address_heading', esc_html__( 'Billing address', 'checkout-wc' ) ) );
				?>
			<?php else : ?>
				<?php
				/**
				 * Filters shipping address heading
				 *
				 * @param string $shipping_address_heading Shipping address heading
				 *
				 * @since 2.0.0
				 */
				echo wp_kses_post( apply_filters( 'cfw_shipping_address_heading', esc_html__( 'Shipping address', 'checkout-wc' ) ) );
				?>
			<?php endif; ?>
		</h3>
		<?php endif; ?>

		<?php
		/**
		 * Fires after customer info address heading
		 *
		 * @since 2.0.0
		 */
		do_action( 'cfw_after_customer_info_address_heading' );

		if ( WC()->cart->needs_shipping() ) {
			/**
			 * Fires after customer info address shipping heading
			 *
			 * @since 4.0.4
			 */
			do_action( 'cfw_after_customer_info_shipping_address_heading' );
		} else {
			/**
			 * Fires after customer info address billing heading
			 *
			 * @since 4.0.4
			 */
			do_action( 'cfw_after_customer_info_billing_address_heading' );
		}

		?>

		<div class="cfw-customer-info-address-container cfw-parsley-shipping-details <?php cfw_address_class_wrap( WC()->cart->needs_shipping() ); ?>">
			<?php if ( ! WC()->cart->needs_shipping() || wc_ship_to_billing_address_only() ) : ?>
				<?php
				/**
				 * Fires before billing address inside billing address container
				 *
				 * @since 4.0.4
				 */
				do_action( 'cfw_start_billing_address_container' );

				cfw_output_billing_checkout_fields();

				/**
				 * Fires before billing address inside billing address container
				 *
				 * @since 4.0.4
				 */
				do_action( 'cfw_end_billing_address_container' );
				?>
			<?php else : ?>
				<?php
				/**
				 * Fires before shipping address inside shipping address container
				 *
				 * @since 4.0.4
				 */
				do_action( 'cfw_start_shipping_address_container' );

				cfw_output_shipping_checkout_fields();

				/**
				 * Fires after shipping address inside shipping address container
				 *
				 * @since 4.0.4
				 */
				do_action( 'cfw_end_shipping_address_container' );
				?>
			<?php endif; ?>
		</div>

		<?php
		if ( WC()->cart->needs_shipping() ) {
			/**
			 * Fires after shipping address
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_checkout_after_shipping_address' );
		} else {
			/**
			 * Fires after billing address
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_checkout_after_billing_address' );
		}

		?>
	</div>

	<?php
	/**
	 * Fires at the bottom of customer info address module after closing </div>
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_customer_info_address' );
}

/**
 * @return bool
 */
function cfw_show_shipping_tab() : bool {
	/**
	 * Filters whether to show shipping tab
	 *
	 * @param string $show_shipping_tab Show shipping tab
	 *
	 * @since 2.0.0
	 */
	return apply_filters( 'cfw_show_shipping_tab', WC()->cart && WC()->cart->needs_shipping() && SettingsManager::instance()->get_setting( 'skip_shipping_step' ) !== 'yes' ) === true;
}

/**
 * @return bool
 */
function cfw_show_shipping_total() : bool {
	/**
	 * Filters whether to show shipping total
	 *
	 * @param string $show_shipping_total Show shipping total
	 *
	 * @since 2.0.0
	 */
	return apply_filters( 'cfw_show_shipping_total', WC()->cart->needs_shipping() && wc_shipping_enabled() && WC()->cart->get_cart_contents() ) === true;
}

function cfw_mobile_cart_summary() {
	if ( SettingsManager::instance()->get_setting( 'enable_mobile_cart_summary' ) !== 'yes' ) {
		return;
	}

	?>
	<div class="cfw-tw">
		<div id="cfw-mobile-cart-summary" class="md:hidden">
			<h3>
				<?php esc_html_e( 'Your Cart', 'checkout-wc' ); ?>
			</h3>

			<div id="cfw-mobile-cart-table"></div>
			<div id="cfw-mobile-cart-coupons"></div>
			<div id="cfw-mobile-cart-summary-totals"></div>
		</div>
	</div>
	<?php
}

/**
 * Customer information tab nav
 *
 * Includes return to cart and next tab buttons
 */
function cfw_customer_info_tab_nav() {
	/**
	 * Fires before customer info tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_before_customer_info_tab_nav' );
	?>

	<div id="cfw-customer-info-action" class="cfw-bottom-controls">
		<div class="previous-button">
			<?php cfw_return_to_cart_link(); ?>
		</div>

		<?php cfw_continue_to_shipping_button(); ?>
		<?php cfw_continue_to_payment_button(); ?>
	</div>

	<?php
	/**
	 * Fires after customer info tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_customer_info_tab_nav' );
}

/**
 * Customer information tab nav
 *
 * Includes return to cart and next tab buttons
 *
 * @param bool $show_cart_return_link whether to show the return to cart link
 */
function cfw_payment_method_tab_review_nav( bool $show_cart_return_link = false ) {
	/**
	 * Fires before payment method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_before_payment_method_tab_nav' );

	$show_customer_information_tab = cfw_show_customer_information_tab();
	?>

	<div id="cfw-payment-method-action" class="cfw-bottom-controls">
		<div class="previous-button">
			<?php if ( $show_cart_return_link ) : ?>
				<?php
				cfw_return_to_cart_link();

				?>
			<?php elseif ( $show_customer_information_tab ) : ?>
				<?php cfw_return_to_customer_information_link(); ?>
			<?php endif; ?>

			<?php cfw_return_to_shipping_method_link(); ?>
		</div>

		<?php cfw_continue_to_order_review_button(); ?>
	</div>

	<?php
	/**
	 * Fires after payment method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_payment_method_tab_nav' );
}

/**
 * Shipping method tab address review section
 */
function cfw_shipping_method_address_review_pane() {
	?>
	<div id="cfw-shipping-method-review-pane"></div>
	<?php
}

/**
 * Shipping method tab heading (hidden heading for screen readers/focus management)
 */
function cfw_shipping_method_tab_heading() {
	?>
	<h2 id="cfw-shipping-method-heading" class="cfw-panel-heading visually-hidden" tabindex="-1"><?php echo wp_kses_post( apply_filters( 'cfw_breadcrumb_shipping_label', esc_html__( 'Shipping', 'checkout-wc' ) ) ); ?></h2>
	<?php
}

/**
 * Payment method tab address review section
 */
function cfw_payment_method_address_review_pane() {
	?>
	<div id="cfw-payment-method-review-pane"></div>
	<?php
}

/**
 * Payment method tab heading (hidden heading for screen readers/focus management)
 */
function cfw_payment_method_tab_heading() {
	?>
	<h2 id="cfw-payment-method-heading" class="cfw-panel-heading visually-hidden" tabindex="-1"><?php echo wp_kses_post( apply_filters( 'cfw_breadcrumb_payment_label', WC()->cart->needs_payment() ? esc_html__( 'Payment', 'checkout-wc' ) : esc_html__( 'Review', 'checkout-wc' ) ) ); ?></h2>
	<?php
}

function cfw_order_review_step_review_pane() {
	?>
	<div id="cfw-order-review-step-review-pane"></div>
	<?php
}

/**
 * @return string
 */
function cfw_get_review_pane_payment_method() : string {
	if ( WC()->cart->needs_payment() ) {
		$available_payment_methods = WC()->payment_gateways()->payment_gateways();

		$title = $available_payment_methods[ WC()->session->get( 'chosen_payment_method' ) ]->title ?? '';
	} else {
		$title = __( 'Free', 'woocommerce' );
	}

	if ( $title ) {
		$title .= '<p class="cfw-small cfw-padding-top cfw-light-gray">' . cfw_get_review_pane_billing_address( WC()->checkout() ) . '</p>';
	}

	return $title;
}

function cfw_order_review_step_totals_review_pane() {
	?>
	<div id="cfw-review-order-totals"></div>
	<?php
}

/**
 * Shipping method tab list of shipping methods
 */
function cfw_shipping_methods() {
	echo '<div id="cfw-shipping-packages-container"></div>';

	/**
	 * Fires after shipping packages component
	 *
	 * @since 9.0.8
	 */
	do_action( 'cfw_after_shipping_packages' );
}

/**
 * Shipping method tab navigation
 *
 * Includes previous and next tab buttons
 */
function cfw_shipping_method_tab_nav() {
	/**
	 * Fires before shipping method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_before_shipping_method_tab_nav' );
	?>

	<div id="cfw-shipping-action" class="cfw-bottom-controls">
		<div class="previous-button">
			<?php cfw_return_to_customer_information_link(); ?>
		</div>

		<?php cfw_continue_to_payment_button(); ?>
	</div>

	<?php
	/**
	 * Fires after shipping method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_shipping_method_tab_nav' );
}

/**
 * Payment method tab payments list
 *
 * Includes payment method tab heading
 *
 * @param bool $checkout_object the object to display payment methods for
 * @param bool $show_title whether to show the payment method tab heading
 */
function cfw_payment_methods( $checkout_object = false, $show_title = true ) {
	/**
	 * Fires before payment methods block
	 *
	 * @since 7.2.7
	 */
	do_action( 'cfw_before_payment_methods_block', $checkout_object, $show_title );

	echo cfw_get_payment_methods( $checkout_object, $show_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	/**
	 * Fires after the payment methods block
	 *
	 * @since 7.2.7
	 */
	do_action( 'cfw_after_payment_methods_block', $checkout_object, $show_title );
}

/**
 * Payment method tab billing address radio group
 */
function cfw_payment_tab_content_billing_address() {
	?>
	<!-- wrapper required for compatibility with Pont shipping for Woocommerce -->
	<div id="ship-to-different-address" class="cfw-force-hidden">
		<label>
			<input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address" value="1" <?php checked( WC()->cart->needs_shipping_address() ); ?> />
		</label>
	</div>
	<?php
	if ( count( cfw_get_billing_checkout_fields() ) === 0 ) {
		echo WC()->cart->needs_shipping_address() ? '<input type="hidden" name="bill_to_different_address" value="same_as_shipping" />' : '';

		return;
	}

	if ( WC()->cart->needs_shipping_address() ) :
		?>
		<?php if ( cfw_enable_accessibility_improvements() ) : ?>
		<h2 class="cfw-billing-address-heading">
			<?php
			/**
			 * Filters billing address heading on payment method tab
			 *
			 * @param string $billing_address_heading Billing address heading on payment method tab
			 *
			 * @since 3.0.0
			 */
			echo wp_kses_post( apply_filters( 'cfw_billing_address_heading', esc_html__( 'Billing address', 'checkout-wc' ) ) );

			?>
		</h2>
		<?php else : ?>
		<h3 class="cfw-billing-address-heading">
			<?php
			/**
			 * Filters billing address heading on payment method tab
			 *
			 * @param string $billing_address_heading Billing address heading on payment method tab
			 *
			 * @since 3.0.0
			 */
			echo wp_kses_post( apply_filters( 'cfw_billing_address_heading', esc_html__( 'Billing address', 'checkout-wc' ) ) );
			?>
		</h3>
		<?php endif; ?>

		<?php
		/**
		 * Fires after the billing address heading on the payment tab
		 *
		 * @since 5.3.2
		 */
		do_action( 'cfw_after_payment_information_address_heading' );
		?>

		<h4 class="cfw-billing-address-description cfw-small">
			<?php
			/**
			 * Filters billing address description
			 *
			 * @param string $billing_address_description Billing address description
			 *
			 * @since 3.0.0
			 */
			echo wp_kses_post( apply_filters( 'cfw_billing_address_description', esc_html__( 'Select the address that matches your card or payment method.', 'checkout-wc' ) ) );
			?>
		</h4>

		<?php cfw_billing_address_radio_group(); ?>
	<?php endif; ?>

	<?php
	/**
	 * Fires after payment method tab billing address
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_payment_tab_billing_address' );
}

/**
 * Payment method tab order notes
 *
 * This also handles any custom fields attached to order notes area
 */
function cfw_payment_tab_content_order_notes() {
	?>
	<div class="cfw-order-notes-container">
		<?php cfw_do_action( 'woocommerce_before_order_notes', WC()->checkout() ); ?>

		<?php if ( cfw_apply_filters( 'woocommerce_enable_order_notes_field', false ) ) : ?>

			<div class="cfw-order-notes-wrap">
				<?php
				/** Documented in functions.php */
				do_action( 'cfw_output_fieldset', WC()->checkout()->get_checkout_fields( 'order' ) );

 // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
				?>
			</div>

		<?php endif; ?>

		<div class="clear"></div>

		<?php cfw_do_action( 'woocommerce_after_order_notes', WC()->checkout() ); ?>
	</div>
	<?php
}

/**
 * Payment method tab terms and conditions
 */
function cfw_payment_tab_content_terms_and_conditions() {
	/**
	 * Fires before payment method terms and conditions output
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_before_payment_method_terms_checkbox' );

	wc_get_template( 'checkout/terms.php' );
}

/**
 * Payment method tab nav
 *
 * Includes previous tab and place order buttons
 *
 * @param bool $show_cart_return_link whether to show the return to cart link
 */
function cfw_payment_tab_nav( bool $show_cart_return_link = false ) {
	/**
	 * Fires before payment method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_before_payment_method_tab_nav' );
	cfw_do_action( 'woocommerce_review_order_before_submit' );

	$show_customer_information_tab = cfw_show_customer_information_tab();
	?>

	<div id="cfw-payment-action" class="cfw-bottom-controls">
		<div class="previous-button">
			<?php if ( $show_cart_return_link ) : ?>
				<?php
				cfw_return_to_cart_link();

				?>
			<?php elseif ( $show_customer_information_tab ) : ?>
				<?php cfw_return_to_customer_information_link(); ?>
			<?php endif; ?>

			<?php cfw_return_to_shipping_method_link(); ?>
		</div>

		<div class="cfw-place-order-wrap">
			<?php
			/**
			 * Fires after payment method tab navigation container
			 *
			 * @since 3.8.0
			 */
			do_action( 'cfw_payment_nav_place_order_button' );
			?>
		</div>
	</div>

	<?php
	/**
	 * Fires after payment method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_payment_method_tab_nav' );
}

/**
 * Order review tab nav
 *
 * Includes previous tab and place order buttons
 */
function cfw_order_review_tab_nav() {
	/**
	 * Fires before payment method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'woocommerce_review_order_before_submit' );
	?>

	<div id="cfw-payment-action" class="cfw-bottom-controls">
		<div class="previous-button">
			<?php cfw_return_to_payment_method_link(); ?>
		</div>

		<div class="cfw-place-order-wrap">
			<?php
			/**
			 * Fires in the order review tab place order button container.
			 *
			 * @since 4.0.0
			 */
			do_action( 'cfw_payment_nav_place_order_button' );
			?>
		</div>
	</div>

	<?php
	/**
	 * Fires after payment method tab navigation container
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_payment_method_tab_nav' );
}

/**
 * Payment method tab nav for one page checkout
 *
 * Includes place order button
 */
function cfw_payment_tab_nav_one_page_checkout() {
	cfw_payment_tab_nav( true );
}

/**
 * Cart list
 */
function cfw_cart_html() {
	// Discard output of this hook for now because
	// we are adding this for Free Gifts for WooCommerce
	// and we don't know if other plugins are using this hook
	// in a way that we don't prefer
	ob_start();
	cfw_do_action( 'woocommerce_review_order_before_cart_contents' );
	$output = ob_get_clean();

	/**
	 * Filters whether woocommerce_review_order_before_cart_contents hook is allowed to output
	 *
	 * @param bool $show_hook Whether to output hook
	 *
	 * @since 4.3.2
	 */
	echo wp_kses_post( apply_filters( 'cfw_show_review_order_before_cart_contents_hook', false ) ? '<div id="woocommerce_review_order_before_cart_contents">' . $output . '</div>' : '' );

	/**
	 * Before cart html table output
	 *
	 * @since 9.0.39
	 */
	do_action( 'cfw_cart_html_before_cart_container' );

	echo '<div id="cfw-cart"></div>';
}

/**
 * Coupon module
 *
 * @param bool $mobile whether or not the module is being displayed on mobile
 */
function cfw_coupon_module( bool $mobile = false ) {
	/**
	 * Fires before coupon module
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_before_coupon_module', $mobile );

	$id = $mobile ? 'cfw-coupons-mobile' : 'cfw-cart-summary-coupons';

	echo '<div id="' . esc_attr( $id ) . '"></div>';

	/**
	 * Fires after coupon module
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_after_coupon_module' );
}

function cfw_maybe_show_coupon_module() {
	if ( SettingsManager::instance()->get_setting( 'show_mobile_coupon_field' ) === 'yes' && SettingsManager::instance()->get_setting( 'enable_mobile_cart_summary' ) !== 'yes' ) {
		cfw_coupon_module( true );
	}
}

/**
 * Cart summary totals
 */
function cfw_cart_summary_totals() {
	echo '<div id="cfw-cart-summary-totals"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Mobile totals
 */
function cfw_mobile_totals_container() {
	if ( SettingsManager::instance()->get_setting( 'enable_mobile_cart_summary' ) === 'yes' ) {
		return;
	}

	if ( SettingsManager::instance()->get_setting( 'enable_mobile_totals' ) !== 'yes' ) {
		return;
	}

	if ( SettingsManager::instance()->get_setting( 'enable_order_review_step' ) === 'yes' ) {
		return;
	}

	echo '<div class="cfw-tw"><div id="cfw-mobile-totals" class="md:hidden"></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * The form attributes
 *
 * @param bool|mixed $id the form ID
 * @param bool $row whether to add row class
 * @param bool $action the form action
 */
function cfw_form_attributes( $id = false, bool $row = true, bool $action = true ) {
	$output = '';
	$format = '%s="%s" ';
	$id     = $id ? $id : 'checkout';

	$attributes = [
		'id'             => $id,
		'name'           => $id,
		'class'          => [ 'cfw-customer-info-active' ],
		'method'         => 'POST',
		'formnovalidate' => '', // this isn't something WooCommerce core adds - maybe we added it for Parsley.js?
		'novalidate'     => 'novalidate',
		'enctype'        => 'multipart/form-data',
	];

	if ( 'order_review' !== $id ) {
		$attributes['class'][]            = 'woocommerce-checkout';
		$attributes['class'][]            = 'checkout';
		$attributes['data-parsley-focus'] = 'first';
	}

	if ( $row ) {
		$attributes['class'][] = 'row';
	}

	if ( $action ) {
		$attributes['action'] = esc_url( wc_get_checkout_url() );
	}

	/**
	 * Filters the form attributes
	 *
	 * @since 6.1.7
	 *
	 * @param array $attributes The form attributes
	 * @param string $id The form ID
	 * @return array
	 */
	$attributes = apply_filters( 'cfw_form_attributes', $attributes, $id );

	foreach ( $attributes as $key => $value ) {
		if ( is_array( $value ) ) {
			$value = join( ' ', $value );
		}

		$output .= sprintf( $format, esc_html( $key ), esc_attr( $value ) );
	}

	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Render title with order number, checkbox graphic, and thank you statement.
 *
 * @param WC_Order $order the order object
 */
function cfw_thank_you_title( WC_Order $order ) {
	if ( $order->has_status( 'failed' ) ) :
		?>
		<div class="cfw-mb">
			<?php
			wc_add_notice( __( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ), 'error' );

			cfw_wc_print_notices_with_wrap();
			?>
			<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="cfw-secondary-btn"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a>
		</div>
	<?php endif; ?>
	<div class="title">
		<?php
		/**
		 * Filters thank you page heading icon
		 *
		 * @param string $cfw_thank_you_heading_icon Thank you page heading icon output
		 *
		 * @since 5.4.0
		 */
		echo apply_filters( 'cfw_thank_you_heading_icon', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" fill="none" stroke-width="2" class="cfw-checkmark"><path class="checkmark__circle" d="M25 49c13.255 0 24-10.745 24-24S38.255 1 25 1 1 11.745 1 25s10.745 24 24 24z"></path><path class="checkmark__check" d="M15 24.51l7.307 7.308L35.125 19"></path></svg>', $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<h5>
			<?php
			$title = sprintf(
				/* translators: %s is the order number */
				esc_html__( 'Order %s', 'checkout-wc' ),
				$order->get_order_number()
			);

			/**
			 * Filters thank you page heading title
			 *
			 * @since 3.0.0
			 *
			 * @param string $thank_you_title Thank you page heading title
			 */
			echo wp_kses_post( apply_filters( 'cfw_thank_you_title', $title ) );
			?>
		</h5>
		<h4>
			<?php

			$subtitle = sprintf(
				/* translators: %s is the billing first name */
				esc_html__( 'Thank you %s!', 'checkout-wc' ),
				$order->get_billing_first_name()
			);

			/**
			 * Filters thank you page heading subtitle
			 *
			 * @since 3.0.0
			 *
			 * @param string $thank_you_subtitle Thank you page heading subtitle
			 */
			echo wp_kses_post( apply_filters( 'cfw_thank_you_subtitle', $subtitle ) );
			?>
		</h4>
	</div>
	<?php
}

/**
 * Thank you page section close
 */
function cfw_thank_you_section_end() {
	?>
	</section>
	<?php
}

/**
 * Thank you page order status row
 *
 * Shows progression of order through statuses.
 *
 * @param WC_Order $order the order object
 * @param array $order_statuses the order statuses
 */
function cfw_thank_you_order_status_row( WC_Order $order, array $order_statuses ) {
	?>
	<div class="inner status-row">
		<?php if ( $order->needs_shipping_address() && function_exists( 'wc_order_status_manager' ) ) : ?>
			<ul class="status-steps">
				<?php
				$count = 0;

				?>
				<?php
				foreach ( $order_statuses as $order_status ) :
					$order_status = new WC_Order_Status_Manager_Order_Status( $order_status );

					?>
					<li class="status-step <?php echo $order->get_status() === $order_status->get_slug() ? 'status-step-selected' : ''; ?>">
						<i class="<?php echo esc_attr( $order_status->get_icon() ); ?>"></i>

						<span class="title">
							<?php echo wp_kses_post( wc_get_order_status_name( $order_status->get_slug() ) ); ?>
						</span>

						<span class="date">
							<?php
							$date = cfw_order_status_date( $order->get_id(), wc_get_order_status_name( $order_status->get_slug() ) );

							if ( $date ) {
								echo wp_kses_post( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
							} elseif ( 0 === $count ) {
								echo wp_kses_post( date_i18n( get_option( 'date_format' ), strtotime( $order->get_date_created() ) ) );
							}

							?>
						</span>
					</li>
					<?php $count++; ?>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( $order->needs_shipping_address() ) : ?>
			<ul class="status-steps">
				<?php $count = 0; ?>
				<?php foreach ( $order_statuses as $order_status ) : ?>
					<?php
					/**
					 * Filters thank you status icon class
					 *
					 * @since 3.0.0
					 *
					 * @param string $thank_you_status_icon Thank you status icon class
					 */
					$thank_you_status_icon = apply_filters( 'cfw_thank_you_status_icon_' . $order_status, 'fa fa-chevron-circle-right' );

					?>
					<li class="status-step <?php echo $order->get_status() === $order_status ? 'status-step-selected status-step-current' : ''; ?>">
						<i class="<?php echo esc_attr( $thank_you_status_icon ); ?>"></i>

						<span class="title">
							<?php echo wp_kses_post( wc_get_order_status_name( $order_status ) ); ?>
						</span>

						<span class="date">
							<?php
							$date = cfw_order_status_date( $order->get_id(), wc_get_order_status_name( $order_status ) );

							if ( $date ) {
								echo wp_kses_post( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
							} elseif ( 0 === $count ) {
								echo wp_kses_post( date_i18n( get_option( 'date_format' ), strtotime( $order->get_date_created() ) ) );
							}

							?>
						</span>
					</li>
					<?php $count++; ?>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<h3><?php esc_html_e( 'Order status', 'checkout-wc' ); ?></h3>
			<p><?php echo wp_kses_post( wc_get_order_status_name( $order->get_status() ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Thank you page map element
 *
 * @param WC_Order $order the order object
 */
function cfw_thank_you_map( WC_Order $order ) {
	if ( $order->needs_shipping_address() ) :
		?>
		<?php if ( PlanManager::can_access_feature( 'enable_map_embed' ) ) : ?>
		<div id="map"></div>
			<?php
		endif;

		?>

		<?php cfw_maybe_output_tracking_numbers( $order ); ?>
		<?php
	endif;
}

/**
 * Thank you page order updates section
 *
 * @param WC_Order $order the order object
 */
function cfw_thank_you_order_updates( WC_Order $order ) {
	?>
	<h3>
		<?php
		/**
		 * Filters order updates heading
		 *
		 * @since 3.0.0
		 *
		 * @param string $order_updates_heading Thank you page order updates heading
		 */
		echo wp_kses_post( apply_filters( 'cfw_order_updates_heading', __( 'Order updates', 'checkout-wc' ), $order ) );
		?>
	</h3>
	<?php
	/**
	 * Filters order updates text
	 *
	 * @since 3.0.0
	 *
	 * @param string $order_updates_text Thank you page order updates text
	 */
	echo wp_kses( wpautop( apply_filters( 'cfw_order_updates_text', __( 'You’ll get shipping and delivery updates by email.', 'checkout-wc' ), $order ) ), cfw_get_allowed_html() );

	/**
	 * Fires after the order updates text is output
	 *
	 * @since 7.2.7
	 */
	do_action( 'cfw_after_thank_you_order_updates_text', $order );
}

/**
 * @param WC_Order $order the order object
 * @param array $order_statues the order statuses
 * @param bool $show_downloads whether to show downloads section
 * @param array $downloads the downloads
 */
function cfw_thank_you_downloads( $order, $order_statues, $show_downloads, $downloads ) {
	?>
	<h3 class="woocommerce-order-downloads__title"><?php esc_html_e( 'Downloads', 'woocommerce' ); ?></h3>

	<table class="woocommerce-table woocommerce-table--order-downloads shop_table shop_table_responsive order_details">
		<thead>
		<tr>
			<?php foreach ( wc_get_account_downloads_columns() as $column_id => $column_name ) : ?>
				<th class="<?php
				echo esc_attr( $column_id );

				?>
 "><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
			<?php endforeach; ?>
		</tr>
		</thead>

		<?php foreach ( $downloads as $download ) : ?>
			<tr>
				<?php foreach ( wc_get_account_downloads_columns() as $column_id => $column_name ) : ?>
					<td class="<?php
					echo esc_attr( $column_id );

					?>
 " data-title="<?php echo esc_attr( $column_name ); ?>">
						<?php
						if ( has_action( 'woocommerce_account_downloads_column_' . $column_id ) ) {
							cfw_do_action( 'woocommerce_account_downloads_column_' . $column_id, $download );
						} else {
							switch ( $column_id ) {
								case 'download-product':
									if ( $download['product_url'] ) {
										echo '<a href="' . esc_url( $download['product_url'] ) . '">' . esc_html( $download['product_name'] ) . '</a>';
									} else {
										echo esc_html( $download['product_name'] );
									}

									break;
								case 'download-file':
									echo '<a href="' . esc_url( $download['download_url'] ) . '" class="woocommerce-MyAccount-downloads-file alt">' . esc_html( $download['download_name'] ) . '</a>';
									break;
								case 'download-remaining':
									echo is_numeric( $download['downloads_remaining'] ) ? esc_html( $download['downloads_remaining'] ) : esc_html__( '&infin;', 'woocommerce' );
									break;
								case 'download-expires':
									if ( ! empty( $download['access_expires'] ) ) {
										echo '<time datetime="' . esc_attr( date( 'Y-m-d', strtotime( $download['access_expires'] ) ) ) . '" title="' . esc_attr( strtotime( $download['access_expires'] ) ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $download['access_expires'] ) ) ) . '</time>'; // phpcs:ignore
									} else {
										esc_html_e( 'Never', 'woocommerce' );
									}

									break;
							}
						}

						?>
					</td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}

/**
 * Thank you page customer information
 *
 * @param WC_Order $order the WooCommerce order
 */
function cfw_thank_you_customer_information( WC_Order $order ) {
	$payment_method_title = $order->get_payment_method_title();
	?>
	<h3><?php esc_html_e( 'Information', 'checkout-wc' ); ?></h3>

	<?php
	/**
	 * Fires before thank you customer information output (after Information heading)
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order The order object
	 */
	do_action( 'cfw_before_thank_you_customer_information', $order );
	?>

	<div class="row">
		<div class="col">
			<h6><?php esc_html_e( 'Contact information', 'checkout-wc' ); ?></h6>
			<p><?php echo wp_kses_post( $order->get_billing_email() ); ?></p>
		</div>
		<?php if ( ! empty( $payment_method_title ) ) : ?>
			<div class="col">
				<h6><?php
				esc_html_e( 'Payment', 'checkout-wc' );

				?>
 </h6>
				<p><?php echo wp_kses_post( $payment_method_title ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<div class="row">
		<?php if ( $order->needs_shipping_address() ) : ?>
			<div class="col-lg-6">
				<?php if ( wc_ship_to_billing_address_only() ) : ?>
					<h6>
						<?php
						/** This action is documented earlier in this file */
						echo wp_kses_post( apply_filters( 'cfw_billing_shipping_address_heading', __( 'Billing and Shipping address', 'checkout-wc' ) ) );

 // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
						?>
						<address>
							<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>
						</address>
					</h6>
				<?php else : ?>
					<h6>
						<?php
						/** This action is documented earlier in this file */
						echo wp_kses_post( apply_filters( 'cfw_shipping_address_heading', esc_html__( 'Shipping address', 'checkout-wc' ) ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
						?>
					</h6>

					<address>
						<?php echo wp_kses_post( $order->get_formatted_shipping_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>
					</address>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! wc_ship_to_billing_address_only() ) : ?>
			<div class="col-lg-6">
				<h6>
					<?php
					/** This action is documented earlier in this file */
					echo wp_kses_post( apply_filters( 'cfw_billing_address_heading', esc_html__( 'Billing address', 'checkout-wc' ) ) );

 // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
					?>
				</h6>
				<address>
					<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>
				</address>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $order->needs_shipping_address() ) : ?>
		<div class="row">
			<div class="col-lg-6">
				<h6><?php
				esc_html_e( 'Shipping', 'checkout-wc' );

				?>
 </h6>
				<p>
					<?php echo wp_kses_post( $order->get_shipping_method() ); ?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<div class="clear"></div>

	<?php
	cfw_do_action( 'woocommerce_order_details_after_customer_details', $order );
	cfw_do_action( 'woocommerce_order_details_after_order_table', $order );
}

/**
 * Renders the buttons beneath the order details on the
 * thank you page
 */
function cfw_thank_you_bottom_controls() {
	/**
	 * Filters thank you page continue shopping button text
	 *
	 * @since 3.0.0
	 *
	 * @param string $cfw_thank_you_continue_shopping_text Thank you page continue shopping button text
	 */
	$cfw_thank_you_continue_shopping_text = apply_filters( 'cfw_thank_you_continue_shopping_text', esc_html__( 'Continue shopping', 'woocommerce' ) );
	?>
	<div id="cfw-thank-you-action" class="cfw-bottom-controls">
		<?php
		$return_to = cfw_apply_filters( 'woocommerce_continue_shopping_redirect', wc_get_page_permalink( 'shop' ) );
		$message   = sprintf( '<a href="%s" tabindex="1" class="cfw-primary-btn cfw-next-tab">%s</a>', esc_url( $return_to ), $cfw_thank_you_continue_shopping_text );
		?>
		<!--- Placeholder -->
		<div></div>
		<?php echo wp_kses( $message, cfw_get_allowed_html() ); ?>
	</div>
	<?php
}

/**
 * Thank you page cart summary content
 *
 * @param WC_Order $order the WooCommerce order
 */
function cfw_thank_you_cart_summary_content( WC_Order $order ) {
	if ( count( $order->get_items() ) > 0 ) {
		echo cfw_get_order_item_summary_table( $order ); // phpcs:ignore
	}
}

/**
 * Order pay heading
 */
function cfw_order_pay_heading() {
	?>
	<h3><?php echo esc_html__( 'Pay for order', 'woocommerce' ); ?></h3>
	<?php
}

/**
 * Order pay login form
 *
 * @param WC_Order $order the WooCommerce order
 */
function cfw_order_pay_login_form( WC_Order $order ) {
	?>
	<form <?php cfw_form_attributes( 'login_form', false, false ); ?>>
		<?php cfw_do_action( 'woocommerce_login_form_start' ); ?>

		<?php
		woocommerce_form_field(
			'username',
			[
				'label'        => esc_html__( 'Email', 'woocommerce' ),
				'type'         => 'text',
				'required'     => true,
				'autocomplete' => 'username',
			]
		);

		woocommerce_form_field(
			'password',
			[
				'label'        => esc_html__( 'Password', 'woocommerce' ),
				'type'         => 'password',
				'required'     => true,
				'autocomplete' => 'current-password',
			]
		);
		?>

		<div class="clear"></div>

		<?php cfw_do_action( 'woocommerce_login_form' ); ?>

		<p class="form-row">
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<input type="hidden" name="redirect" value="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" />

			<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Login', 'woocommerce' ); ?>"><?php esc_html_e( 'Login', 'woocommerce' ); ?></button>

			<span class="login-optional cfw-small">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
			</span>
		</p>

		<div class="clear"></div>

		<?php cfw_do_action( 'woocommerce_login_form_end' ); ?>
	</form>
	<?php
}

/**
 * Order Pay payment form
 *
 * @param WC_Order $order the WooCommerce order
 * @param array $available_gateways the available gateways
 * @param string $order_button_text the order button text
 * @param bool $call_receipt_hook whether to call the receipt hook
 */
function cfw_order_pay_payment_form( WC_Order $order, array $available_gateways, $order_button_text, $call_receipt_hook ) {
	?>
	<form <?php cfw_form_attributes( 'order_review', false, false ); ?>>
		<?php
		// Some gateways need this when they use order-pay
		// to take payment right after checkout
		if ( ! empty( $call_receipt_hook ) ) :
			?>
			<?php
			cfw_do_action( 'woocommerce_receipt_' . $order->get_payment_method(), $order->get_id() );

			?>
		<?php else : ?>
			<?php cfw_payment_methods( $order, false ); ?>

			<?php wc_get_template( 'checkout/terms.php' ); ?>

			<?php
			/**
			 * Fires before order pay submit
			 *
			 * @since 10.2.0
			 */
			do_action( 'cfw_before_order_pay_submit' );
			?>

			<div id="cfw-payment-action" class="cfw-bottom-controls">
				<div class="previous-button"></div>

				<input type="hidden" name="woocommerce_pay" value="1" />

				<div class="place-order" id="cfw-place-order">
					<?php cfw_do_action( 'woocommerce_pay_order_before_submit' ); ?>

                    <?php echo cfw_apply_filters( 'woocommerce_pay_order_button_html', '<button type="submit" class="cfw-primary-btn cfw-next-tab validate" id="place_order" formnovalidate="formnovalidate" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // @codingStandardsIgnoreLine?>

					<?php cfw_do_action( 'woocommerce_pay_order_after_submit' ); ?>

					<?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
				</div>
			</div>
		<?php endif; ?>
	</form>
	<?php
}

/**
 * @param WC_Order $order the WooCommerce order
 * @param bool $call_receipt_hook whether to call the receipt hook
 * @param array $available_gateways the available gateways
 * @param string $order_button_text the order button text
 */
function cfw_order_pay_form( WC_Order $order, $call_receipt_hook, $available_gateways, $order_button_text ) {
	if ( ! current_user_can( 'pay_for_order', $order->get_id() ) && ! is_user_logged_in() ) { // phpcs:ignore
		cfw_order_pay_login_form( $order );
	} elseif ( $call_receipt_hook ) {
		wc_get_template( 'checkout/order-receipt.php', [ 'order' => $order ] );
	} else {
		cfw_order_pay_payment_form( $order, $available_gateways, $order_button_text, $call_receipt_hook );
	}
}

/**
 * Thank you page cart summary content
 *
 * @param WC_Order $order the WooCommerce order
 */
function cfw_order_pay_cart_summary_content( WC_Order $order ) {
	if ( count( $order->get_items() ) > 0 ) {
		echo cfw_get_order_item_summary_table( $order ); // phpcs:ignore
	}
}

function cfw_thank_you_section_start_order_status() {
	cfw_thank_you_section_start( 'cfw-order-status' );
}

/**
 * @param WC_Order $order the WooCommerce order
 */
function cfw_thank_you_order_updates_wrapped( WC_Order $order ) {
	if ( $order->needs_shipping_address() ) {
		cfw_thank_you_section_auto_wrap( 'cfw_thank_you_order_updates', 'cfw-order-updates', [ $order ] );
	}
}

function cfw_thank_you_downloads_wrapped( $order, $order_statues, $show_downloads, $downloads ) {
	if ( $show_downloads ) {
		cfw_thank_you_section_auto_wrap( 'cfw_thank_you_downloads', 'woocommerce-order-downloads', [ $order, $order_statues, $show_downloads, $downloads ] );
	}
}

function cfw_thank_you_customer_information_wrapped( $order ) {
	cfw_thank_you_section_auto_wrap( 'cfw_thank_you_customer_information', 'cfw-customer-information', [ $order ] );
}

function cfw_cart_summary_mobile_header_display( WC_Order $order ) {
	cfw_cart_summary_mobile_header( $order->get_formatted_order_total() );
}

/**
 * Get the checkout tabs
 *
 * @return array
 */
function cfw_get_checkout_tabs() : array {
	/**
	 * Filters the checkout tabs
	 *
	 * @since 7.0.0
	 *
	 * @param array $tabs The checkout tabs
	 */
	$tabs = apply_filters(
		'cfw_get_checkout_tabs',
		[
			'cfw-customer-info'   => [
				/**
				 * Filters the breadcrumb customer info label.
				 *
				 * @since 7.0.0
				 *
				 * @param string $label the breadcrumb customer info label
				 */
				'label'            => apply_filters( 'cfw_breadcrumb_customer_info_label', esc_html__( 'Information', 'checkout-wc' ) ),
				'classes'          => [],
				'priority'         => 20,
				'enabled'          => cfw_show_customer_information_tab(),
				'display_callback' => function() {

					/**
					 * Outputs customer info tab content
					 *
					 * @since 2.0.0
					 */
					do_action( 'cfw_checkout_customer_info_tab' );
				},
			],
			'cfw-shipping-method' => [
				/**
				 * Filters the breadcrumb shipping label.
				 *
				 * @since 7.0.0
				 *
				 * @param string $label the breadcrumb shipping label
				 */
				'label'            => apply_filters( 'cfw_breadcrumb_shipping_label', esc_html__( 'Shipping', 'checkout-wc' ) ),
				'classes'          => [],
				'priority'         => 30,
				'enabled'          => true,
				'display_callback' => function() {
					/**
					 * Outputs customer info tab content
					 *
					 * @since 2.0.0
					 */
					do_action( 'cfw_checkout_shipping_method_tab' );
				},
			],
			'cfw-payment-method'  => [
				/**
				 * Filters the breadcrumb payment label.
				 *
				 * @since 7.0.0
				 *
				 * @param string $label the breadcrumb payment label
				 */
				'label'            => apply_filters( 'cfw_breadcrumb_payment_label', WC()->cart->needs_payment() ? esc_html__( 'Payment', 'checkout-wc' ) : esc_html__( 'Review', 'checkout-wc' ) ),
				'classes'          => [ 'woocommerce-checkout-payment' ],
				'priority'         => 40,
				'enabled'          => true,
				'display_callback' => function() {
					/**
					 * Outputs customer info tab content
					 *
					 * @since 2.0.0
					 */
					do_action( 'cfw_checkout_payment_method_tab' );
				},
			],
		]
	);

	uasort( $tabs, 'cfw_uasort_by_priority_comparison' );

	return $tabs;
}
function cfw_output_checkout_tabs() {
	?>
	<?php foreach ( cfw_get_checkout_tabs() as $tab_id => $tab ) : ?>
		<?php
		if ( ! $tab['enabled'] ) {
			$tab['classes'][] = 'cfw-force-hidden';
		}

		?>
		<div id="<?php echo esc_attr( $tab_id ); ?>" class="cfw-panel <?php echo esc_attr( join( ' ', $tab['classes'] ) ); ?>">
			<?php
			cfw_set_current_tab( $tab_id );

			call_user_func( $tab['display_callback'] );
			?>
		</div>
	<?php endforeach; ?>
	<?php
}

function cfw_set_current_tab( string $tab ) {
	global $cfw_current_tab;
	$cfw_current_tab = $tab;
}

function cfw_get_current_tab() : string {
	global $cfw_current_tab;
	return (string) $cfw_current_tab;
}

function cfw_lost_password_modal_container() {
	echo '<div id="cfw_lost_password_container"></div>';
}

function cfw_maybe_output_footer_nav_menu() {
	$location = 'cfw-footer-menu';

	if ( has_nav_menu( $location ) ) {
		wp_nav_menu(
			[
				'theme_location' => $location,
			]
		);
	}
}

function cfw_output_empty_cart_message() {
	/**
	 * Fires before the empty cart message is output.
	 *
	 * @since 6.2.0
	 *
	 * @param string $message the message
	 */
	$message = apply_filters( 'cfw_empty_side_cart_heading', __( 'Your Cart is Empty', 'checkout-wc' ) );
	?>
	<p id="cfw_empty_side_cart_message">
		<?php echo wp_kses_post( $message ); ?>
	</p>
	<?php
}

function cfw_footer_content() {
	$footer_text = SettingsManager::instance()->get_setting( 'footer_text', [ cfw_get_active_template()->get_slug() ] );
	?>
	<div id="cfw-store-policies-container"></div>
	<p>
		<?php
		if ( ! empty( wp_strip_all_tags( $footer_text ) ) ) {
			echo do_shortcode( $footer_text );
		} else {
			?>
			Copyright &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>, <?php echo wp_kses_post( get_bloginfo( 'name' ) ); ?>. All rights reserved.
			<?php
		}

		?>
	</p>
	<?php
}
