<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

use Objectiv\Plugins\Checkout\Adapters\OrderItemFactory;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\FormFieldAugmenter;
use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;
use Objectiv\Plugins\Checkout\Interfaces\ItemInterface;
use Objectiv\Plugins\Checkout\Loaders\Content;
use Objectiv\Plugins\Checkout\Loaders\Redirect;
use Objectiv\Plugins\Checkout\Managers\AssetManager;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Model\CartItem;
use Objectiv\Plugins\Checkout\Model\OrderItem;
use Objectiv\Plugins\Checkout\Model\RulesProcessor;
use Objectiv\Plugins\Checkout\Model\Template;

use function WordpressEnqueueChunksPlugin\registerScripts as cfwRegisterChunkedScripts;

function cfw_output_fieldset( array $fieldset ) {
	if ( empty( $fieldset ) ) {
		return;
	}

	$row_open  = '<div class="row cfw-input-wrap-row">' . PHP_EOL;
	$row_close = '</div>' . PHP_EOL;
	$count     = 0;
	$max       = 12;

	echo $row_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	foreach ( $fieldset as $key => $field ) {
		/**
		 * Filters fieldset field args
		 *
		 * @param array $field Field args
		 * @param string $key Field key
		 *
		 * @since 7.0.0
		 */
		$field            = apply_filters( 'cfw_pre_output_fieldset_field_args', $field, $key );
		$field['columns'] = $field['columns'] ?? 12;
		$field['type']    = $field['type'] ?? 'text';

		if ( ( $count + $field['columns'] > $max || $count === $max ) && 'hidden' !== $field['type'] && ! in_array( 'hidden', $field['class'] ?? [], true ) ) {
			echo $row_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $row_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$count = 0;
		}

		woocommerce_form_field( $key, $field, WC()->checkout()->get_value( $key ) );
		$count += $field['columns'];
	}

	echo $row_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Check whether WooCommerce HPOS is enabled.
 *
 * @return bool
 */
function cfw_is_hpos_enabled() : bool {
	$order_util_class = 'Automattic\\WooCommerce\\Utilities\\OrderUtil';

	if ( ! is_callable( [ $order_util_class, 'custom_orders_table_usage_is_enabled' ] ) ) {
		return false;
	}

	return (bool) call_user_func( [ $order_util_class, 'custom_orders_table_usage_is_enabled' ] );
}

/**
 * @param WC_Checkout $checkout the checkout object
 */
function cfw_output_account_checkout_fields( WC_Checkout $checkout ) {
	if ( is_user_logged_in() || ! $checkout->is_registration_enabled() ) {
		return;
	}

	/**
	 * Filters account address checkout fields
	 *
	 * @param array $account_checkout_fields Account checkout fields
	 *
	 * @since 2.0.0
	 */
	$account_checkout_fields = apply_filters( 'cfw_get_account_checkout_fields', $checkout->get_checkout_fields( 'account' ) );

	// Handled in cfw_account_password_field_slide()
	unset( $account_checkout_fields['account_password'], $account_checkout_fields['account_username'] );

	/**
	 * Filter the account checkout fields.
	 *
	 * @since 7.2.1
	 *
	 * @param array $field_set The checkout fieldset
	 * @param string $type The fieldset type
	 */
	do_action( 'cfw_output_fieldset', $account_checkout_fields, 'account' );

	cfw_do_action( 'woocommerce_after_checkout_registration_form', $checkout );
}

/**
 * Get the shipping fields
 *
 * @return array
 */
function cfw_get_shipping_checkout_fields() : array {
	/**
	 * Filters shipping address checkout fields
	 *
	 * @param array $shipping_checkout_fields Shipping address checkout fields
	 *
	 * @since 2.0.0
	 */
	return apply_filters( 'cfw_get_shipping_checkout_fields', (array) WC()->checkout()->get_checkout_fields( 'shipping' ) );
}

function cfw_output_shipping_checkout_fields() {
	/** This action is documented earlier in this file */
	do_action( 'cfw_output_fieldset', cfw_get_shipping_checkout_fields(), 'shipping' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
}

/**
 * Get the billing fields
 *
 * @return array
 */
function cfw_get_billing_checkout_fields() : array {
	/**
	 * Filters billing address checkout fields
	 *
	 * @param array $billing_checkout_fields Billing address checkout fields
	 *
	 * @since 2.0.0
	 */
	$billing_checkout_fields = apply_filters( 'cfw_get_billing_checkout_fields', (array) WC()->checkout()->get_checkout_fields( 'billing' ) );

	// Email field is handled separately
	unset( $billing_checkout_fields['billing_email'] );

	return $billing_checkout_fields;
}

function cfw_output_billing_checkout_fields() {
	$billing_checkout_fields = cfw_get_billing_checkout_fields();

	/** This filter is documented in functions/functions.php */
	if ( ! WC()->cart->needs_shipping_address() || apply_filters( 'cfw_force_display_billing_address', false ) ) { // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
		/** This action is documented earlier in this file */
		do_action( 'cfw_output_fieldset', $billing_checkout_fields, 'billing' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment

		return;
	}

	$shipping_checkout_fields = cfw_get_shipping_checkout_fields();
	$billing_fields_in_common = cfw_get_common_billing_fields( $billing_checkout_fields, $shipping_checkout_fields );

	/** This action is documented earlier in this file */
	do_action( 'cfw_output_fieldset', $billing_fields_in_common, 'billing' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
}

/**
 * Filter billing fields down to only fields that match shipping fields
 *
 * @param array $billing_fields the billing fields
 * @param array $shipping_fields the shipping fields
 * @return array
 */
function cfw_get_common_billing_fields( array $billing_fields, array $shipping_fields ) : array {
	$keys = [];

	foreach ( array_keys( $shipping_fields ) as $key ) {
		$keys[ str_replace( 'shipping_', 'billing_', $key ) ] = true;
	}

	return array_intersect_key( $billing_fields, $keys );
}

/**
 * Filter billing fields down to only unique fields that aren't also shipping fields
 *
 * @param array $billing_fields the billing fields
 * @param array $shipping_fields the shipping fields
 * @return array
 */
function cfw_get_unique_billing_fields( array $billing_fields, array $shipping_fields ) : array {
	$keys = [];

	foreach ( array_keys( $shipping_fields ) as $key ) {
		$keys[ str_replace( 'shipping_', 'billing_', $key ) ] = true;
	}

	$unique_fields = array_diff_key( $billing_fields, $keys );

	/**
	 * Filters the unique billing fields.
	 *
	 * @since 7.2.1
	 *
	 * @param array $unique_fields the unique billing fields
	 */
	return apply_filters( 'cfw_unique_billing_fields', $unique_fields );
}

function cfw_maybe_output_extra_billing_fields() {
	if ( ! WC()->cart->needs_shipping_address() ) {
		return;
	}

	$unique_fields = cfw_get_unique_billing_fields( cfw_get_billing_checkout_fields(), cfw_get_shipping_checkout_fields() );

	if ( ! empty( $unique_fields ) ) {
		/** This action is documented earlier in this file */
		do_action( 'cfw_output_fieldset', $unique_fields, 'billing_unique' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
	}
}

function cfw_get_review_pane_shipping_address_label() : string {
	if ( ! wc_ship_to_billing_address_only() ) {
		$ship_to_label = __( 'Ship to', 'checkout-wc' );
	} else {
		$ship_to_label = __( 'Address', 'woocommerce' );
	}

	/**
	 * Filters ship to label in review pane
	 *
	 * @param string $ship_to_label Ship to label
	 *
	 * @since 3.0.0
	 */
	return apply_filters( 'cfw_ship_to_label', $ship_to_label );
}

/**
 * @param WC_Checkout $checkout checkout object
 * @return string
 */
function cfw_get_review_pane_shipping_address( WC_Checkout $checkout ) : string {
	$formatted_address = WC()->countries->get_formatted_address(
		/**
		 * Filters review pane shipping address
		 *
		 * @param array $shipping_details_address Review pane shipping address
		 *
		 * @since 2.0.0
		 */
		apply_filters(
			'cfw_get_shipping_details_address',
			cfw_get_posted_address_fields( wc_ship_to_billing_address_only() ? 'billing' : 'shipping' ),
			$checkout
		),
		', '
	);

	/**
	 * Filters review pane formatted shipping address
	 *
	 * @param string $formatted_address Formatted shipping address
	 *
	 * @since 7.3.0
	 */
	$formatted_address = apply_filters( 'cfw_get_review_pane_shipping_address', $formatted_address );

	// Cleanup address formats that weren't used
	return cfw_cleanup_formatted_address( $formatted_address );
}

/**
 * @param WC_Checkout $checkout checkout object
 * @return string
 */
function cfw_get_review_pane_billing_address( WC_Checkout $checkout ) : string {
	$formatted_address = WC()->countries->get_formatted_address(
		/**
		 * Filters review pane billing address
		 *
		 * @param array $billing_details_address Review pane billing address
		 *
		 * @since 2.0.0
		 */
		apply_filters(
			'cfw_get_review_pane_billing_address',
			cfw_get_posted_address_fields(),
			$checkout
		),
		', '
	);

	return cfw_cleanup_formatted_address( $formatted_address );
}

function cfw_cleanup_formatted_address( string $address ) : string {
	// Cleanup address formats that weren't used
	return preg_replace( '/{[^\s]+}(,|\s)/', '', $address );
}

/**
 * @param string $fieldset fieldset to get fields for
 * @return array
 */
function cfw_get_posted_address_fields( string $fieldset = 'billing' ) : array {
	$short_prefix         = 'billing' === $fieldset ? '' : 's_';
	$long_prefix          = 'billing' === $fieldset ? 'billing_' : 'shipping_';
	$known_address_fields = WC()->countries->get_default_address_fields();

	$address = [];

	$post_data = [];
	if ( ! empty( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		parse_str( wp_unslash( $_POST['post_data'] ?? '' ), $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
	}

	foreach ( $known_address_fields as $key => $field ) {
		$address[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $short_prefix . $key ] ?? $post_data[ $long_prefix . $key ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	return $address;
}

function cfw_get_cart_shipping_data() : array {
	$packages      = WC()->shipping()->get_packages();
	$shipping_data = [];

	foreach ( $packages as $i => $package ) {
		$product_names = [];

		if ( count( $packages ) > 1 ) {
			foreach ( $package['contents'] as $item_id => $values ) {
				$product_names[ $item_id ] = $values['data']->get_name() . ' &times;' . $values['quantity'];
			}

			$product_names = cfw_apply_filters( 'woocommerce_shipping_package_details_array', $product_names, $package );
		}

		/**
		 * Filter the available shipping methods displayed on checkout page
		 *
		 * @since 8.0.0
		 *
		 * @param array $package The shipping package
		 * @param int $i The package index
		 * @param array $rates The shipping rates
		 */
		$available_methods   = apply_filters( 'cfw_available_shipping_methods', $package['rates'], $package, $i );
		$chosen_method       = WC()->session->chosen_shipping_methods[ $i ] ?? '';
		$chosen_method_found = false;

		foreach ( $available_methods as $available_method ) {
			if ( $available_method->id === $chosen_method ) {
				$chosen_method_found = true;
				break;
			}
		}

		if ( ! $chosen_method_found ) {
			reset( $available_methods );
			$first_method = current( $available_methods );

			if ( is_object( $first_method ) && method_exists( $first_method, 'get_id' ) ) {
				$chosen_method            = $first_method->get_id();
				$new_chosen_methods       = WC()->session->get( 'chosen_shipping_methods' );
				$new_chosen_methods[ $i ] = $chosen_method;
				WC()->session->set( 'chosen_shipping_methods', $new_chosen_methods );
				WC()->cart->calculate_totals();
			}
		}

		$package_details = implode( ', ', $product_names );
		$package_name    = cfw_apply_filters(
			'woocommerce_shipping_package_name',
			sprintf(
			/* translators: %d: shipping package number */
				_nx( 'Shipping', 'Shipping %d', ( $i + 1 ), 'shipping packages', 'woocommerce' ),
				( $i + 1 )
			),
			$i,
			$package
		);

		$formatted_methods = [];
		$chosen_method_obj = null;

		foreach ( $available_methods as $method ) {
			$label = wc_cart_totals_shipping_method_label( $method );
			$label = str_replace( ': <span', '<span', $label ); // Adjust label formatting

			if ( $method->id === $chosen_method ) {
				$chosen_method_obj = $method;
			}

			// Label without price: use everything before the first <span (method name only).
			$span_pos  = stripos( $label, '<span' );
			$label_raw = $span_pos !== false
				? trim( wp_strip_all_tags( substr( $label, 0, $span_pos ) ) )
				: trim( wp_strip_all_tags( $label ) );

			$formatted_methods[] = [
				'id'          => $method->id,
				'sanitizedId' => sanitize_title( $method->id ),
				'label'       => $label,
				'label_raw'   => $label_raw,
				'checked'     => $method->id === $chosen_method,
				'actions'     => [
					'woocommerce_after_shipping_rate' => cfw_get_action_output( 'woocommerce_after_shipping_rate', $method, $i ),
				],
			];
		}

		$chosen_total = null;
		if ( $chosen_method_obj ) {
			$chosen_total = WC()->cart->display_prices_including_tax()
				? (float) $chosen_method_obj->cost + (float) $chosen_method_obj->get_shipping_tax()
				: (float) $chosen_method_obj->cost;
		}

		$shipping_data[] = [
			'index'            => $i,
			'packageName'      => $package_name,
			'packageDetails'   => $package_details,
			'availableMethods' => $formatted_methods,
			'chosenMethod'     => $chosen_method,
			'total'            => $chosen_total,
		];
	}

	return $shipping_data;
}

function cfw_get_payment_methods_html() {
	/**
	 * Fires before payment methods html is fetched
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_get_payment_methods_html' );

	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	/**
	 * Filter whether to ensure a payment method is selected
	 *
	 * @param array $ensure_selected_payment_method Whether to ensure a payment method is selected
	 *
	 * @since 8.2.11
	 */
	cfw_set_current_gateway( $available_gateways, apply_filters( 'cfw_ensure_selected_payment_method', true ) );

	remove_filter(
		'woocommerce_form_field_args',
		[
			FormFieldAugmenter::instance(),
			'calculate_columns',
		],
		100000
	);
	remove_filter(
		'woocommerce_form_field',
		[
			FormFieldAugmenter::instance(),
			'remove_extraneous_field_classes',
		],
		100000
	);

	ob_start();
	?>
	<ul class="wc_payment_methods payment_methods methods cfw-radio-reveal-group">
		<?php
		/**
		 * Fires at start of payment methods UL
		 *
		 * @since 2.0.0
		 */
		do_action( 'cfw_payment_methods_ul_start' );

		if ( ! empty( $available_gateways ) ) {
			$count = 0;
			foreach ( $available_gateways as $gateway ) {
				// Prevent fatal errors when no gateway is available
				// OR when gateway isn't actually a gateway
				if ( is_a( $gateway, 'stdClass' ) ) {
					continue;
				}

				/**
				 * Filters whether to show gateway in list of gateways
				 *
				 * @param bool $show Show gateway output
				 *
				 * @since 2.0.0
				 */
				if ( apply_filters( "cfw_show_gateway_{$gateway->id}", true ) ) :
					/**
					 * Filters gateway order button text
					 *
					 * @param string $gateway_order_button_text The gateway order button text
					 *
					 * @since 2.0.0
					 */
					$gateway_order_button_text = apply_filters( 'cfw_gateway_order_button_text', $gateway->order_button_text, $gateway );

					/**
					 * Filters gateway order button text
					 *
					 * @param string $icons The gateway icon HTML
					 * @param WC_Payment_Gateway
					 *
					 * @since 2.0.0
					 */
					$icons = apply_filters( 'cfw_get_gateway_icons', $gateway->get_icon(), $gateway );

					$title           = $gateway->get_title();
					$is_active_class = $gateway->chosen ? 'cfw-active' : '';

					/**
					 * Filters the class attribute of the payment method list item.
					 *
					 * @param WC_Payment_Gateway $gateway the payment gateway object
					 * @param string $li_class_attribute the payment method list item class attribute
					 *
					 * @since 2.0.0
					 * @since 10.1.0 Added $gateway argument.
					 */
					$li_class_attribute = apply_filters( 'cfw_payment_method_li_class', "wc_payment_method cfw-radio-reveal-li {$is_active_class} payment_method_{$gateway->id}", $gateway );
					?>
					<li class="<?php echo esc_attr( $li_class_attribute ); ?>">
						<div class="payment_method_title_wrap cfw-radio-reveal-title-wrap">
							<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio"
									class="input-radio" name="payment_method"
									value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?>
									data-order_button_text="<?php echo esc_attr( $gateway_order_button_text ); ?>"/>

							<label class="payment_method_label cfw-radio-reveal-label"
									for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
								<div>
									<?php if ( $title ) : ?>
										<span class="payment_method_title cfw-radio-reveal-title">
										<?php
										echo wp_kses_post( $title );

										?>
									</span>
									<?php endif; ?>

									<?php if ( $icons ) : ?>
										<span class="payment_method_icons">
										<?php
										echo $icons;

 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
									</span>
									<?php endif; ?>
								</div>
							</label>
						</div>
						<?php
						/**
						 * Filters whether to show gateway content
						 *
						 * @param bool $show Show gateway content
						 *
						 * @since 2.0.0
						 */
						if ( apply_filters( "cfw_payment_gateway_{$gateway->id}_content", $gateway->has_fields() || $gateway->get_description() ) ) :
							?>
							<div class="payment_box payment_method_<?php
							echo esc_attr( $gateway->id );

							?>
 cfw-radio-reveal-content" <?php echo ! $gateway->chosen ? 'style="display:none;"' : ''; ?>>
								<?php
								ob_start();

								$gateway->payment_fields();

								$field_html = ob_get_clean();

								/**
								 * Gateway Compatibility Patches
								 */
								// Expiration field fix
								$field_html = str_ireplace( 'js-sv-wc-payment-gateway-credit-card-form-expiry', 'js-sv-wc-payment-gateway-credit-card-form-expiry  wc-credit-card-form-card-expiry', $field_html );
								$field_html = str_ireplace( 'js-sv-wc-payment-gateway-credit-card-form-account-number', 'js-sv-wc-payment-gateway-credit-card-form-account-number  wc-credit-card-form-card-number', $field_html );

								// Credit Card Field Placeholders
								$field_html = str_ireplace( '•••• •••• •••• ••••', 'Card Number', $field_html );
								$field_html = str_ireplace( '&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;', 'Card Number', $field_html );

								/**
								 * Filters gateway payment field output HTML
								 *
								 * @param string $gateway_output Payment gateway output HTML
								 *
								 * @since 2.0.0
								 */
								echo apply_filters( "cfw_payment_gateway_field_html_{$gateway->id}", $field_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</div>
						<?php endif; ?>
					</li>

					<?php
				else :
					/**
					 * Fires after payment method LI to allow alternate / additional output
					 *
					 * @since 2.0.0
					 */
					do_action_ref_array( "cfw_payment_gateway_list_{$gateway->id}_alternate", [ $count ] );
				endif;

				$count++;
			}
		} else {
			echo '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info">' . cfw_apply_filters( 'woocommerce_no_available_payment_methods_message', __( 'Sorry, it seems that there are no available payment methods for your location. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Fires after bottom of payment methods UL
		 *
		 * @since 2.0.0
		 */
		do_action( 'cfw_payment_methods_ul_end' );
		?>
	</ul>
	<?php

	add_filter( 'woocommerce_form_field_args', [ FormFieldAugmenter::instance(), 'calculate_columns' ], 100000 );
	add_filter(
		'woocommerce_form_field',
		[
			FormFieldAugmenter::instance(),
			'remove_extraneous_field_classes',
		],
		100000,
		1
	);

	return ob_get_clean();
}

function cfw_get_order_item_summary_table( WC_Order $order ) : string {
	$items = OrderItemFactory::get( $order );
	ob_start();
	?>
	<table id="cfw-cart" class="cfw-module cfw-cart-table">
		<?php
		/** @var OrderItem $item */
		foreach ( $items as $item ) :
			$item_thumb = $item->get_thumbnail();

			?>
			<tr class="cart-item-row cart-item-<?php echo esc_attr( $item->get_item_key() ); ?> <?php echo esc_attr( $item->get_row_class() ); ?>">
				<?php if ( $item_thumb ) : ?>
					<td class="cfw-cart-item-image">
						<div class="cfw-cart-item-image-wrap">
							<?php
							echo wp_kses_post( $item_thumb );

							?>

							<span class="cfw-cart-item-quantity-bubble">
								<?php echo wp_kses_post( $item->get_quantity() ); ?>
							</span>
						</div>
					</td>
				<?php endif; ?>

				<th class="cfw-cart-item-description" <?php echo empty( $item_thumb ) ? 'colspan="2"' : ''; ?> >
					<div class="cfw-cart-item-title">
						<?php
						/**
						 * Filters whether to link cart items to products
						 *
						 * @param bool $link_cart_items Link cart items to products
						 *
						 * @since 1.0.0
						 */
						if ( apply_filters( 'cfw_link_cart_items', SettingsManager::instance()->get_setting( 'cart_item_link' ) === 'enabled' ) ) :
							?>
							<a target="_blank" href="<?php
							echo esc_attr( $item->get_url() );

							?>
 ">
								<?php echo wp_kses_post( $item->get_title() ); ?>
							</a>
						<?php else : ?>
							<?php echo wp_kses_post( $item->get_title() ); ?>
						<?php endif; ?>
					</div>
					<?php
					cfw_display_item_data( $item );

					/**
					 * Fires after cart item data output
					 *
					 * @param array $item ->get_raw_item() Raw item data
					 * @param string $item ->get_item_key() Item key
					 * @param OrderItem $item Order item object
					 *
					 * @since 7.1.3
					 */
					do_action( 'cfw_order_item_after_data', $item->get_raw_item(), $item->get_item_key(), $item );
					?>
				</th>

				<td class="cfw-cart-item-quantity visually-hidden">
					<?php echo wp_kses_post( $item->get_quantity() ); ?>
				</td>

				<td class="cfw-cart-item-subtotal">
					<?php
					/** This filter documented in elsewhere in this file */
					do_action( 'cfw_before_cart_item_subtotal', $item ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
					?>
					<?php echo wp_kses_post( $item->get_subtotal() ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
	$return = ob_get_clean();

	/**
	 * Filters order cart HTML output
	 *
	 * @param string $order_cart_html Order cart HTML output
	 *
	 * @since 1.0.0
	 */
	$return = apply_filters_deprecated( 'cfw_order_cart_html', [ $return ], 'CheckoutWC 5.4.0', 'cfw_items_summary_table_html' );

	/** This filter is documented elsewhere in this file */
	return (string) apply_filters( 'cfw_items_summary_table_html', $return, 'order' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
}

function cfw_display_item_data( ItemInterface $item ) {
	$output = $item->get_formatted_data();

	if ( $output ) {
		echo '<div class="cfw-cart-item-data">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

function cfw_all_packages_have_available_shipping_methods( array $packages ) : bool {
	foreach ( $packages as $i => $package ) {
		/** Documented in cfw_get_cart_shipping_data() */
		$package_rates = apply_filters( 'cfw_available_shipping_methods', $package['rates'], $package, $i ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment

		if ( empty( $package_rates ) ) {
			return false;
		}
	}

	return true;
}

function cfw_get_shipping_total() : string {
	$small_format = '<span class="cfw-small">%s</span>';

	$packages                                     = WC()->shipping()->get_packages();
	$all_packages_have_available_shipping_methods = cfw_all_packages_have_available_shipping_methods( $packages );
	$has_calculated_shipping                      = WC()->customer->has_calculated_shipping();
	$address_required                             = get_option( 'woocommerce_shipping_cost_requires_address' ) === 'yes';
	$missing_address                              = $address_required && ! $has_calculated_shipping;

	// When address is required but not provided, show appropriate message regardless of method availability
	if ( $missing_address || ( ! $all_packages_have_available_shipping_methods && ! $has_calculated_shipping ) ) {
		/**
		 * Filters shipping total address required text
		 *
		 * @param string $address_required_text Shipping total address required text
		 *
		 * @since 2.0.0
		 */
		return sprintf( $small_format, ! is_checkout() ? apply_filters( 'cfw_shipping_total_address_required_text', esc_html__( 'Shipping costs are calculated during checkout.', 'woocommerce' ) ) : __( 'Enter your address to view shipping options.', 'woocommerce' ) );
	}

	if ( ! $all_packages_have_available_shipping_methods ) {
		/**
		 * Filters shipping total text when no shipping methods are available
		 *
		 * @param string $new_shipping_total_not_available_text Shipping total text when no shipping methods are available
		 *
		 * @since 2.0.0
		 */
		return sprintf( $small_format, apply_filters( 'cfw_shipping_total_not_available_text', __( 'No shipping methods available', 'checkout-wc' ) ) );
	}

	if ( has_filter( 'woocommerce_shipping_chosen_method' ) && ! cfw_all_packages_have_selected_shipping_methods( $packages ) ) {
		/**
		 * Filters shipping total text when no shipping methods are available
		 *
		 * @param string $new_shipping_total_not_available_text Shipping total text when no shipping methods are available
		 *
		 * @since 2.0.0
		 */
		return apply_filters( 'cfw_no_shipping_method_selected_message', '' );
	}

	$total = cfw_sum_packages_shipping_total( $packages, WC()->session, WC()->cart );

	if ( 0 < $total ) {
		return wc_price( $total );
	}

	/**
	 * Filters the text displayed when free shipping is available.
	 *
	 * @since 5.0.0
	 *
	 * @param string $text the text to display
	 * @return string
	 */
	return apply_filters( 'cfw_shipping_free_text', __( 'Free!', 'woocommerce' ) );
}

function cfw_all_packages_have_selected_shipping_methods( $packages ) : bool {
	foreach ( $packages as $i => $package ) {
		$default = wc_get_default_shipping_method_for_package( $i, $package, false );
		$session = WC()->session->chosen_shipping_methods[ $i ] ?? false;

		if ( false === $default && false === $session ) {
			return false;
		}
	}

	return true;
}

function cfw_calculate_packages_shipping( array $packages, $wc_session, $wc_cart ) {
	_deprecated_function( __METHOD__, '10.3.7', 'cfw_sum_packages_shipping_total' );

	return cfw_sum_packages_shipping_total( $packages, $wc_session, $wc_cart );
}

function cfw_sum_packages_shipping_total( array $packages, $wc_session, $wc_cart ) {
	$total = 0;

	foreach ( $packages as $i => $package ) {
		$chosen_method     = $wc_session->chosen_shipping_methods[ $i ] ?? '';
		$available_methods = empty( $package['rates'] ) ? [] : $package['rates'];

		foreach ( $available_methods as $method ) {
			if ( (string) $method->id !== (string) $chosen_method ) { // WC_Shipping_Method::id is defined as a string type, so we need to make sure we're comparing it as a string
				continue;
			}

			if ( 0 >= $method->cost ) {
				continue;
			}

			$total += $method->cost;

			if ( $wc_cart->display_prices_including_tax() ) {
				$total += $method->get_shipping_tax();
			}
		}
	}

	return $total;
}

/**
 * @param WC_Order $order the order object
 */
function cfw_order_totals_html( WC_Order $order ) {
	echo cfw_get_order_totals_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * @param WC_Order $order the order object
 * @return mixed|void
 */
function cfw_get_order_totals_html( WC_Order $order ) {
	$totals = $order->get_order_item_totals();

	ob_start();

	/**
	 * Filters order totals element ID
	 *
	 * @param string $order_totals_list_element_id Order totals element ID
	 *
	 * @since 2.0.0
	 */
	$order_totals_list_element_id = apply_filters( 'cfw_template_cart_el', 'cfw-totals-list' );
	?>
	<div id="<?php echo esc_attr( $order_totals_list_element_id ); ?>" class="cfw-module cfw-totals-list">
		<table class="cfw-module">
			<?php
			/**
			 * Fires at start of cart summary totals table
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_before_cart_summary_totals' );
			?>

			<?php
			foreach ( $totals as $key => $total ) :
				if ( 'payment_method' === $key ) {
					continue;
				}

				?>
				<tr class="cart-subtotal <?php echo ( 'order_total' === $key ) ? 'order-total' : ''; ?>">
					<th><?php echo wp_kses_post( $total['label'] ); ?></th>
					<td><?php echo wp_kses_post( $total['value'] ); ?></td>
				</tr>
			<?php endforeach; ?>

			<?php cfw_do_action( 'woocommerce_review_order_after_order_total' ); ?>

			<?php
			/**
			 * Fires at end of cart summary totals table before </table> tag
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_after_cart_summary_totals' );
			?>
		</table>
	</div>
	<?php

	/**
	 * Filters order totals HTML
	 *
	 * @param string $order_totals_html Cart totals HTML
	 *
	 * @since 2.0.0
	 */
	return apply_filters( 'cfw_order_totals_html', ob_get_clean() );
}

function cfw_address_class_wrap( $shipping = true ) {
	// If __field-wrapper class isn't there, Amazon Pay nukes our address fields :-(
	$result = 'woocommerce-billing-fields woocommerce-billing-fields__field-wrapper';

	if ( true === $shipping ) {
		$result = 'woocommerce-shipping-fields woocommerce-shipping-fields__field-wrapper';
	}

	echo esc_attr( $result );
}

function cfw_get_place_order( $order_button_text = false ) {
	ob_start();

	$order_button_text = ! $order_button_text ? cfw_apply_filters( 'woocommerce_order_button_text', __( 'Complete Order', 'checkout-wc' ) ) : $order_button_text;

	/**
	 * Filters place order button container classes
	 *
	 * @param array $place_order_button_container_classes Place order button container classes
	 *
	 * @since 2.0.0
	 */
	$place_order_button_container_class = join( ' ', apply_filters( 'cfw_place_order_button_container_classes', [ 'place-order' ] ) );
	?>
	<div class="<?php echo esc_attr( $place_order_button_container_class ); ?>"
		data-total="<?php echo esc_attr( WC()->cart->get_total( 'checkoutwc' ) ); ?>" id="cfw-place-order">
		<?php echo cfw_apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="cfw-primary-btn cfw-next-tab validate" name="woocommerce_checkout_place_order" id="place_order" formnovalidate="formnovalidate" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '"><span class="cfw-button-text">' . esc_html( $order_button_text ) . '</span></button>' ); // @codingStandardsIgnoreLine?>

		<?php cfw_do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
	<?php
	if ( ! wp_doing_ajax() ) {
		cfw_do_action( 'woocommerce_review_order_after_payment' );
	}

	return ob_get_clean();
}

function cfw_place_order( $order_button_text = false ) {
	echo cfw_get_place_order( $order_button_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function cfw_enable_accessibility_improvements() : bool {
	return (bool) apply_filters( 'cfw_enable_accessibility_improvements', false );
}

function cfw_get_payment_methods( $the_object = false, $show_title = true ) {
	$payment_methods_html = cfw_get_payment_methods_html();

	$the_object = ! $the_object ? WC()->cart : $the_object;

	ob_start();
	?>
	<div id="cfw-billing-methods" class="cfw-module cfw-accordion">
		<?php
		/**
		 * Fires above the payment method heading
		 *
		 * @since 5.1.1
		 */
		do_action( 'cfw_before_payment_method_heading' );

		if ( $show_title ) :
			?>
			<?php if ( cfw_enable_accessibility_improvements() ) : ?>
			<h2>
				<?php
				/**
				 * Filters payment methods heading
				 *
				 * @param string $payment_methods_heading Payment methods heading
				 *
				 * @since 2.0.0
				 */
				echo apply_filters( 'cfw_payment_method_heading', esc_html__( 'Payment', 'checkout-wc' ) );

 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</h2>
			<?php else : ?>
			<h3>
				<?php
				/**
				 * Filters payment methods heading
				 *
				 * @param string $payment_methods_heading Payment methods heading
				 *
				 * @since 2.0.0
				 */
				echo apply_filters( 'cfw_payment_method_heading', esc_html__( 'Payment', 'checkout-wc' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</h3>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		/**
		 * Fires after payment methods heading and before transaction are encrypted statement
		 *
		 * @since 2.0.0
		 */
		do_action( 'cfw_checkout_before_payment_methods' );
		?>

		<?php if ( $the_object->needs_payment() ) : ?>
			<div class="cfw-payment-method-information-wrap">
				<h4 class="cfw-small secure-notice">
					<?php
					/**
					 * Filters payment methods transactions are encrypted statement
					 *
					 * @param string $transactions_encrypted_statement Payment methods transactions are encrypted statement
					 *
					 * @since 2.0.0
					 */
					echo apply_filters( 'cfw_transactions_encrypted_statement', esc_html__( 'All transactions are secure and encrypted.', 'checkout-wc' ) );

 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</h4>

				<div class="cfw-payment-methods-wrap">
					<div id="payment" class="woocommerce-checkout-payment">
						<?php echo $payment_methods_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</div>
		<?php else : ?>
			<div class="cfw-no-payment-method-wrap">
				<span class="cfw-small">
					<?php
					/**
					 * Filters no payment required text
					 *
					 * @param string $no_payment_required_text No payment required text
					 *
					 * @since 2.0.0
					 */
					echo apply_filters( 'cfw_no_payment_required_text', esc_html__( 'Your order is free. No payment is required.', 'checkout-wc' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</span>
			</div>
		<?php endif; ?>

		<?php
		/**
		 * Fires at end of payment methods container before </div> tag
		 *
		 * @since 2.0.0
		 */
		do_action( 'cfw_checkout_after_payment_methods' );
		?>
	</div>
	<?php

	return ob_get_clean();
}

function cfw_billing_address_radio_group() {
	/**
	 * Fires before billing address radio group is output
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_before_billing_address' );

	/**
	 * Filters default billing address radio selection
	 *
	 * @param string $default Default billing address radio selection
	 *
	 * @since 8.2.28
	 */
	$default = apply_filters( 'cfw_default_billing_address_radio_selection', 'same_as_shipping' );

	/**
	 * Filters the label for the same as shipping address radio
	 *
	 * @param string $same_as_shipping_label The label for the same as shipping address radio
	 *
	 * @since 9.0.0
	 */
	$same_as_shipping_label = apply_filters( 'cfw_billing_address_same_as_shipping_label', __( 'Same as shipping address', 'checkout-wc' ) );

	/**
	 * Filters the label for the different billing address radio
	 *
	 * @param string $different_billing_address_label The label for the different billing address radio
	 *
	 * @since 9.0.0
	 */
	$different_billing_address_label = apply_filters( 'cfw_billing_address_different_address_label', __( 'Use a different billing address', 'checkout-wc' ) );

	/**
	 * Filters whether to force displaying the billing address (no accordion)
	 *
	 * @param bool $force_display_billing_address Force displaying billing address
	 *
	 * @since 2.0.0
	 */
	if ( ! apply_filters( 'cfw_force_display_billing_address', SettingsManager::instance()->get_setting( 'force_different_billing_address' ) === 'yes' ) ) :
		?>
		<div id="cfw-shipping-same-billing" class="cfw-module cfw-accordion">
			<ul class="cfw-radio-reveal-group">
				<li class="cfw-radio-reveal-li cfw-no-reveal">
					<div class="cfw-radio-reveal-title-wrap">
						<input type="radio" name="bill_to_different_address" id="billing_same_as_shipping_radio"
								value="same_as_shipping" <?php
								checked( $default, 'same_as_shipping' );

								?>
 />

						<label for="billing_same_as_shipping_radio" class="cfw-radio-reveal-label">
							<div>
								<span
									class="cfw-radio-reveal-title"><?php echo wp_kses_post( $same_as_shipping_label ); ?></span>
							</div>
						</label>

						<?php
						/**
						 * Fires after same as shipping address label
						 *
						 * @since 2.0.0
						 */
						do_action( 'cfw_after_same_as_shipping_address_label' );
						?>
					</div>
				</li>
				<li class="cfw-radio-reveal-li">
					<div class="cfw-radio-reveal-title-wrap">
						<input type="radio" name="bill_to_different_address" id="shipping_dif_from_billing_radio"
								value="different_from_shipping" <?php checked( $default, 'different_from_shipping' ); ?> />

						<label for="shipping_dif_from_billing_radio" class="cfw-radio-reveal-label">
							<div>
								<span
									class="cfw-radio-reveal-title"><?php echo wp_kses_post( $different_billing_address_label ); ?></span>
							</div>
						</label>
					</div>
					<div id="cfw-billing-fields-container"
						class="cfw-radio-reveal-content <?php cfw_address_class_wrap( false ); ?>"
						style="display: none">
						<?php
						/**
						 * Fires before billing address inside billing address container
						 *
						 * @since 2.0.0
						 */
						do_action( 'cfw_start_billing_address_container' );

						cfw_output_billing_checkout_fields();

						/**
						 * Fires after billing address inside billing address container
						 *
						 * @since 2.0.0
						 */
						do_action( 'cfw_end_billing_address_container' );
						?>
					</div>
				</li>
			</ul>
		</div>
	<?php else : ?>
		<input type="hidden" name="bill_to_different_address" id="billing_same_as_shipping_radio"
				value="different_from_shipping"/>
		<div class="cfw-module <?php cfw_address_class_wrap( false ); ?>">
			<?php
			/**
			 * Fires before billing address inside billing address container
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_start_billing_address_container' );

			cfw_output_billing_checkout_fields();

			/**
			 * Fires after billing address inside billing address container
			 *
			 * @since 2.0.0
			 */
			do_action( 'cfw_end_billing_address_container' );
			?>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Fires after billing address
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_checkout_after_billing_address' );
}

/**
 * Get all approved WooCommerce order notes.
 *
 * @param int|string $order_id the order ID
 * @param string $status_search the status to search for
 * @return bool|string
 */
function cfw_order_status_date( $order_id, string $status_search ) {
	remove_filter( 'comments_clauses', [ 'WC_Comments', 'exclude_order_comments' ] );

	$notes = get_comments(
		[
			'post_id' => $order_id,
			'orderby' => 'comment_ID',
			'order'   => 'DESC',
			'approve' => 'approve',
			'type'    => 'order_note',
		]
	);

	add_filter( 'comments_clauses', [ 'WC_Comments', 'exclude_order_comments' ] );

	$pattern = sprintf(
		/* translators: %1$s: Old order status, %2$s: New order status */
		__( 'Order status changed from %1$s to %2$s.', 'woocommerce' ),
		'X',
		$status_search
	);

	$pieces         = explode( ' ', $pattern );
	$last_two_words = implode( ' ', array_splice( $pieces, -2 ) );

	foreach ( $notes as $note ) {
		if ( false !== stripos( $note->comment_content, $last_two_words ) ) {
			return $note->comment_date_gmt;
		}
	}

	return false;
}

/**
 * @param WC_Order $order the order object
 */
function cfw_maybe_output_tracking_numbers( $order ) {
	$output = '';

	if ( defined( 'WC_SHIPMENT_TRACKING_VERSION' ) ) {
		$tracking_items = WC_Shipment_Tracking_Actions::get_instance()->get_tracking_items( $order->get_id(), true );
		$label_suffix   = __( 'Tracking Number:', 'woocommerce-shipment-tracking' );

		foreach ( $tracking_items as $tracking_item ) {
			/**
			 * Filters tracking link header on thank you page
			 *
			 * @param string $shipment_tracking_header Tracking link header
			 * @param string $tracking_provider The shipping provider for tracking link
			 *
			 * @since 3.14.0
			 */
			$output .= apply_filters( 'cfw_thank_you_shipment_tracking_header', "<h4>{$tracking_item['formatted_tracking_provider']} {$label_suffix}</h4>", $tracking_item['formatted_tracking_provider'] );

			/**
			 * Filters tracking link output on thank you page
			 *
			 * @param string $shipment_tracking_link Tracking link output
			 * @param string $tracking_link The tracking link
			 * @param string $tracking_number The tracking number
			 *
			 * @since 3.14.0
			 */
			$output .= apply_filters( 'cfw_thank_you_shipment_tracking_link', "<p><a class=\"tracking-number\" target=\"_blank\" href=\"{$tracking_item['formatted_tracking_link']}\">{$tracking_item['tracking_number']}</a></p>", $tracking_item['formatted_tracking_link'], $tracking_item['tracking_number'] );
		}
	} elseif ( function_exists( 'wc_advanced_shipment_tracking' ) ) {
		ob_start();
		$wc_advanced_shipment_tracking_actions = WC_Advanced_Shipment_Tracking_Actions::get_instance();
		$wc_advanced_shipment_tracking_actions->show_tracking_info_order( $order->get_id() );

		$output = ob_get_clean();
	} elseif ( has_filter( 'cfw_thank_you_tracking_numbers' ) ) {
		/**
		 * Filter to handle custom shipment tracking links output on thank you page
		 *
		 * @param WC_Order $order The order object
		 * @param string $custom_tracking_numbers_output The tracking numbers output
		 *
		 * @since 3.0.0
		 */
		$output = apply_filters( 'cfw_thank_you_tracking_numbers', '', $order );
	}

	if ( ! empty( $output ) ) {
		echo '<div class="inner cfw-padded">';

		/**
		 * Filter tracking numbers output on thank you page
		 *
		 * @param WC_Order $order The order object
		 * @param string $tracking_numbers_output The tracking numbers output HTML
		 *
		 * @since 3.0.0
		 */
		echo wp_kses( apply_filters( 'cfw_maybe_output_tracking_numbers', $output, $order ), cfw_get_allowed_html() );

		echo '</div>';
	}
}

function cfw_return_to_cart_link() {
	/**
	 * Filters whether to show the return to cart link
	 *
	 * @since 6.0.0
	 *
	 * @param bool $show Whether to show the return to cart link
	 */
	if ( ! apply_filters( 'cfw_show_return_to_cart_link', true ) ) {
		return;
	}

	/**
	 * Filter return to cart link URL
	 *
	 * @param string $return_to_cart_link_url Return to cart link URL
	 *
	 * @since 2.0.0
	 */
	$return_to_cart_link_url = apply_filters( 'cfw_return_to_cart_link_url', wc_get_cart_url() );

	/**
	 * Filter return to cart link text
	 *
	 * @param string $return_to_cart_link_text Return to cart link text
	 *
	 * @since 2.0.0
	 */
	$return_to_cart_link_text = apply_filters( 'cfw_return_to_cart_link_text', esc_html__( 'Return to cart', 'checkout-wc' ) );

	/**
	 * Filter return to cart link
	 *
	 * @param string $cart_link Return to cart link
	 *
	 * @since 2.0.0
	 */
	echo apply_filters( 'cfw_return_to_cart_link', sprintf( '<a href="%s" class="cfw-prev-tab">« %s</a>', esc_attr( $return_to_cart_link_url ), $return_to_cart_link_text ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * @param string $label the pre-translated button label
 * @param array $classes any extra classes to add
 */
function cfw_continue_to_shipping_button( string $label = '', array $classes = [] ) {
	$new_classes = array_merge(
		[
			'cfw-primary-btn' => 'cfw-primary-btn',
			'cfw-next-tab',
			'cfw-continue-to-shipping-btn',
		],
		$classes
	);

	if ( in_array( 'cfw-secondary-btn', $classes, true ) ) {
		unset( $new_classes['cfw-primary-btn'] );
	}

	/**
	 * Filter continue to shipping method button label
	 *
	 * @param string $continue_to_shipping_method_label Continue to shipping method button label
	 *
	 * @since 3.0.0
	 */
	$continue_to_shipping_method_label = ! empty( $label ) ? $label : apply_filters( 'cfw_continue_to_shipping_method_label', esc_html__( 'Continue to shipping', 'checkout-wc' ) );

	/**
	 * Filter continue to shipping method button
	 *
	 * @param string $shipping_method_button Continue to shipping method button
	 *
	 * @since 3.0.0
	 */
	if ( cfw_enable_accessibility_improvements() ) {
		echo apply_filters( 'cfw_continue_to_shipping_button', sprintf( '<button type="button" data-tab="#cfw-shipping-method" class="%s"><span class="cfw-button-text">%s</span></button>', esc_attr( join( ' ', $new_classes ) ), $continue_to_shipping_method_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo apply_filters( 'cfw_continue_to_shipping_button', sprintf( '<a href="javascript:" data-tab="#cfw-shipping-method" class="%s"><span class="cfw-button-text">%s</span></a>', esc_attr( join( ' ', $new_classes ) ), $continue_to_shipping_method_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * @param array $args button options such as label or classes
 */
function cfw_continue_to_payment_button( array $args = [] ) {
	$defaults = [
		'classes' => [
			'cfw-primary-btn' => 'cfw-primary-btn',
			'cfw-next-tab',
			'cfw-continue-to-payment-btn',
		],
		'label'   => WC()->cart->needs_payment() ? esc_html__( 'Continue to payment', 'checkout-wc' ) : esc_html__( 'Review order', 'checkout-wc' ),
	];

	$args = wp_parse_args( $args, $defaults );

	if ( in_array( 'cfw-secondary-btn', $args['classes'], true ) ) {
		unset( $args['classes']['cfw-primary-btn'] );
	}

	/**
	 * Filter continue to payment method button label
	 *
	 * @param string $continue_to_payment_method_label Continue to payment method button label
	 *
	 * @since 3.0.0
	 */
	$continue_to_payment_method_label = apply_filters( 'cfw_continue_to_payment_method_label', $args['label'] );

	/**
	 * Filter continue to payment method button
	 *
	 * @param string $payment_method_button Continue to payment method button
	 *
	 * @since 3.0.0
	 */
	if ( cfw_enable_accessibility_improvements() ) {
		echo apply_filters( 'cfw_continue_to_payment_button', sprintf( '<button type="button" data-tab="#cfw-payment-method" class="%s"><span class="cfw-button-text">%s</span></button>', esc_attr( join( ' ', $args['classes'] ) ), $continue_to_payment_method_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo apply_filters( 'cfw_continue_to_payment_button', sprintf( '<a href="javascript:" data-tab="#cfw-payment-method" class="%s"><span class="cfw-button-text">%s</span></a>', esc_attr( join( ' ', $args['classes'] ) ), $continue_to_payment_method_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

function cfw_continue_to_order_review_button() {
	/**
	 * Filter continue to order review button label
	 *
	 * @param string $continue_to_order_review_label Continue to order review button label
	 *
	 * @since 3.0.0
	 */
	$continue_to_order_review_label = apply_filters( 'cfw_continue_to_order_review_label', esc_html__( 'Review order', 'checkout-wc' ) );

	/**
	 * Filter continue to order review button
	 *
	 * @param string $order_review_button Continue to order review button
	 *
	 * @since 3.0.0
	 */
	if ( cfw_enable_accessibility_improvements() ) {
		echo apply_filters( 'cfw_continue_to_order_review_button', sprintf( '<button type="button" data-tab="#cfw-order-review" class="cfw-primary-btn cfw-next-tab cfw-continue-to-order-review-btn">%s</button>', $continue_to_order_review_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo apply_filters( 'cfw_continue_to_order_review_button', sprintf( '<a href="javascript:" data-tab="#cfw-order-review" class="cfw-primary-btn cfw-next-tab cfw-continue-to-order-review-btn">%s</a>', $continue_to_order_review_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

function cfw_return_to_customer_information_link() {
	/**
	 * Filter return to customer information tab label
	 *
	 * @param string $return_to_customer_info_label Return to customer information tab label
	 *
	 * @since 3.0.0
	 */
	$return_to_customer_info_label = apply_filters( 'cfw_return_to_customer_info_label', esc_html__( 'Return to information', 'checkout-wc' ) );

	/**
	 * Filter return to customer information tab link
	 *
	 * @param string $return_to_customer_info_link Return to customer information tab link
	 *
	 * @since 3.0.0
	 */
	echo apply_filters( 'cfw_return_to_customer_information_link', sprintf( '<a href="javascript:" data-tab="#cfw-customer-info" class="cfw-prev-tab cfw-return-to-information-btn">« %s</a>', $return_to_customer_info_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function cfw_return_to_shipping_method_link() {
	/**
	 * Filter return to shipping method tab label
	 *
	 * @param string $return_to_shipping_method_label Return to shipping method tab label
	 *
	 * @since 3.0.0
	 */
	$return_to_shipping_method_label = apply_filters( 'cfw_return_to_shipping_method_label', esc_html__( 'Return to shipping', 'checkout-wc' ) );

	/**
	 * Filter return to shipping method tab link
	 *
	 * @param string $return_to_shipping_method_link Return to shipping method tab link
	 *
	 * @since 3.0.0
	 */
	echo apply_filters( 'cfw_return_to_shipping_method_link', sprintf( '<a href="javascript:" data-tab="#cfw-shipping-method" class="cfw-prev-tab cfw-return-to-shipping-btn">« %s</a>', $return_to_shipping_method_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function cfw_return_to_payment_method_link() {
	/**
	 * Filter return to payment method tab label
	 *
	 * @param string $return_to_payment_method_label Return to payment method tab label
	 *
	 * @since 3.0.0
	 */
	$return_to_payment_method_label = apply_filters( 'cfw_return_to_payment_method_label', esc_html__( 'Return to payment', 'checkout-wc' ) );

	/**
	 * Filter return to payment method tab link
	 *
	 * @param string $return_to_payment_method_link Return to payment method tab link
	 *
	 * @since 3.0.0
	 */
	echo apply_filters( 'cfw_return_to_payment_method_link', sprintf( '<a href="javascript:" data-tab="#cfw-payment-method" class="cfw-prev-tab cfw-return-to-payment-btn">« %s</a>', $return_to_payment_method_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * @return bool
 */
function cfw_show_customer_information_tab() : bool {
	/**
	 * Filters whether to show customer information tab
	 *
	 * @param bool $show_customer_information_tab Show customer information tab
	 *
	 * @since 3.0.0
	 */
	return apply_filters( 'cfw_show_customer_information_tab', true );
}

function cfw_get_breadcrumbs() : array {
	$tabs = cfw_get_checkout_tabs();

	$default_breadcrumbs = [
		'cart' => [
			/**
			 * Filters breadcrumb cart link URL
			 *
			 * @param string $breadcrumb_cart_link_url Breadcrumb cart link URL
			 *
			 * @since 3.0.0
			 */
			'href'     => apply_filters( 'cfw_breadcrumb_cart_url', wc_get_cart_url() ),

			/**
			 * Filters breadcrumb cart link label
			 *
			 * @param string $breadcrumb_cart_link_label Breadcrumb cart link label
			 *
			 * @since 3.0.0
			 */
			'label'    => apply_filters( 'cfw_breadcrumb_cart_label', esc_html__( 'Cart', 'woocommerce' ) ),
			'priority' => 10,
			'classes'  => [],
		],
	];

	$first_tab_key = key( $tabs );

	foreach ( $tabs as $tab_id => $tab ) {
		$classes = $tab['enabled'] ? [] : [ 'cfw-force-hidden' ];

		if ( $tab_id === $first_tab_key ) {
			$classes[] = 'cfw-default-tab';
		}

		$default_breadcrumbs[ $tab_id ] = [
			'href'     => "#{$tab_id}",
			'label'    => $tab['label'],
			'priority' => $tab['priority'],
			'classes'  => $classes,
		];
	}

	/**
	 * Filters breadcrumbs
	 *
	 * @param string $breadcrumbs Breadcrumbs
	 *
	 * @since 3.0.0
	 */
	$breadcrumbs = apply_filters( 'cfw_breadcrumbs', $default_breadcrumbs );

	// Order by priority
	uasort( $breadcrumbs, 'cfw_uasort_by_priority_comparison' );

	return $breadcrumbs;
}

function cfw_breadcrumb_navigation() {
	/**
	 * Fires before breadcrumb navigation is output
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_before_breadcrumb_navigation' );
	?>
	<ul id="cfw-breadcrumb" class="etabs">
		<?php
		foreach ( cfw_get_breadcrumbs() as $id => $breadcrumb ) :
			$classes = $breadcrumb['classes'] ?? [];

			?>
			<li class="<?php echo ( 'cart' !== $id ) ? 'tab' : ''; ?> <?php echo esc_attr( $id ); ?> <?php echo esc_attr( join( ' ', $classes ) ); ?>">
				<a href="<?php echo esc_attr( $breadcrumb['href'] ); ?>"
					class="cfw-small"><?php echo esc_html( $breadcrumb['label'] ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php

	/**
	 * Fires after breadcrumb navigation is output
	 *
	 * @since 2.0.0
	 */
	do_action( 'cfw_after_breadcrumb_navigation' );
}

/**
 * User to sort breadcrumbs based on priority with uasort.
 *
 * @param array $a first value to compare
 * @param array $b second value to compare
 * @return int
 *
 * @since 3.5.1
 */
function cfw_uasort_by_priority_comparison( array $a, array $b ) : int {
	/*
	 * We are not guaranteed to get a priority
	 * setting. So don't compare if they don't
	 * exist.
	 */
	if ( ! isset( $a['priority'], $b['priority'] ) ) {
		return 0;
	}

	return wc_uasort_comparison( $a['priority'], $b['priority'] );
}

function cfw_main_container_classes( $context = 'checkout' ) {
	$classes = [];

	$classes[] = 'container';
	$classes[] = 'context-' . $context;
	$classes[] = 'checkoutwc';

	if ( is_admin_bar_showing() ) {
		$classes[] = 'admin-bar';
	}

	$active_template = cfw_get_active_template();
	if ( $active_template && SettingsManager::instance()->get_setting( 'label_style', [ $active_template->get_slug() ] ) === 'normal' ) {
		$classes[] = 'cfw-label-style-normal';
	}

	/**
	 * Filters main container classes
	 *
	 * @param string $classes Main container classes
	 *
	 * @since 3.0.0
	 */
	return apply_filters( "cfw_{$context}_main_container_classes", join( ' ', $classes ) );
}

function cfw_count_filters( $filter ) : int {
	global $wp_filter;
	$count = 0;

	if ( isset( $wp_filter[ $filter ] ) ) {
		foreach ( $wp_filter[ $filter ]->callbacks as $callbacks ) {
			$count += (int) count( $callbacks );
		}
	}

	return $count;
}

/**
 * @return bool
 */
function cfw_is_checkout() : bool {
	/**
	 * Filter cfw_is_checkout()
	 *
	 * @param bool $is_checkout Whether we are on the checkout page
	 *
	 * @since 3.0.0
	 */
	return apply_filters(
		'cfw_is_checkout',
		( function_exists( 'is_checkout' ) && is_checkout() )
		&& ! is_order_received_page()
		&& ! is_checkout_pay_page()
	);
}

/**
 * @return bool
 */
function cfw_is_checkout_pay_page() : bool {
	/**
	 * Filter is_checkout_pay_page()
	 *
	 * @param bool $is_checkout_pay_page Whether we are on the checkout pay page
	 *
	 * @since 3.0.0
	 */
	return apply_filters(
		'cfw_is_checkout_pay_page',
		function_exists( 'is_checkout_pay_page' )
		&& is_checkout_pay_page()
		&& cfw_get_active_template()->supports( 'order-pay' )
		&& PlanManager::can_access_feature( 'enable_order_pay' )
	);
}

/**
 * @return bool
 */
function cfw_is_order_received_page() : bool {
	/**
	 * Filter is_order_received_page()
	 *
	 * @param bool $is_order_received_page Whether we are on the order received page
	 *
	 * @since 3.0.0
	 */
	return apply_filters(
		'cfw_is_order_received_page',
		function_exists( 'is_order_received_page' )
		&& is_order_received_page()
		&& cfw_get_active_template()->supports( 'order-received' )
		&& PlanManager::can_access_feature( 'enable_thank_you_page', 'plus' )
	);
}

/**
 * Whether the current request is the checkout page loaded in the Checkout Editor preview iframe.
 *
 * @return bool
 */
function cfw_is_editor_preview() : bool {
	if ( is_admin() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['cfw-editor-preview'] ) || $_GET['cfw-editor-preview'] !== '1' ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['_cfw_preview_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_cfw_preview_nonce'] ) ), 'cfw-editor-preview' ) ) {
		return false;
	}

	return current_user_can( 'cfw_manage_pages' );
}

/**
 * @return bool
 */
function is_cfw_page() : bool {
	return cfw_is_checkout() || cfw_is_checkout_pay_page() || cfw_is_order_received_page();
}

/**
 * Determines whether CheckoutWC templates can load on the frontend
 *
 * @return bool
 */
function cfw_is_enabled() : bool {
	$templates_enabled   = ! cfw_templates_disabled();
	$is_admin            = current_user_can( 'manage_options' );
	$user_can_access     = $templates_enabled || $is_admin;
	$forcefully_disabled = defined( 'CFW_DISABLE_TEMPLATES' ) || isset( $_COOKIE['CFW_DISABLE_TEMPLATES'] ) || isset( $_GET['bypass-cfw'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	return $user_can_access && ! $forcefully_disabled;
}

/**
 * Get phone field setting
 *
 * @return bool
 */
function cfw_is_phone_fields_enabled() : bool {
	return 'hidden' !== get_option( 'woocommerce_checkout_phone_field', 'required' );
}

/**
 * Match new guest order to existing account if it exists
 *
 * @param int $order_id the order id
 */
function cfw_maybe_match_new_order_to_user_account( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		wc_get_logger()->debug( "CheckoutWC: Invalid order ID {$order_id} passed to cfw_maybe_match_new_order_to_user_account", [ 'source' => 'checkout-wc' ] );

		return;
	}

	if ( ! is_object( $order ) || ! method_exists( $order, 'get_user' ) ) {
		wc_get_logger()->debug( "CheckoutWC: Order object for ID {$order_id} does not support get_user()", [ 'source' => 'checkout-wc' ] );

		return;
	}

	$user = $order->get_user();

	if ( ! $user ) {
		$user_data = get_user_by( 'email', $order->get_billing_email() );

		if ( ! empty( $user_data->ID ) ) {
			try {
				$order->set_customer_id( $user_data->ID );
				$order->save();
				wc_get_logger()->info( "CheckoutWC: Matched guest order {$order_id} to existing user {$user_data->ID}", [ 'source' => 'checkout-wc' ] );
			} catch ( WC_Data_Exception $e ) {
				/* translators: 1: order ID, 2: customer ID - Error message logged when order matching to customer fails */
				wc_get_logger()->error( sprintf( __( 'CheckoutWC: Error matching %1$d to customer %2$d', 'checkout-wc' ), $order_id, $user_data->ID ), [ 'source' => 'checkout-wc' ] );
			}
		} else {
			wc_get_logger()->info( "CheckoutWC: No existing user found for guest order {$order_id} with email {$order->get_billing_email()}", [ 'source' => 'checkout-wc' ] );
		}
	}
}

/**
 * Match old guest orders to new account if they exist
 *
 * @param int $user_id the user ID
 */
function cfw_maybe_link_orders_at_registration( $user_id ) {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		wc_get_logger()->error( "CheckoutWC: Invalid user ID {$user_id} in cfw_maybe_link_orders_at_registration", [ 'source' => 'checkout-wc' ] );
		return;
	}

	wc_get_logger()->info( "CheckoutWC: Linking past guest orders for user {$user_id} ({$user->user_email})", [ 'source' => 'checkout-wc' ] );

	// Count orders before linking
	$orders_before = wc_get_orders(
		[
			'billing_email' => $user->user_email,
			'customer_id'   => 0,
			'limit'         => -1,
			'return'        => 'ids',
		]
	);

	wc_update_new_customer_past_orders( $user_id );

	// Count orders after linking to see how many were linked
	$orders_after = wc_get_orders(
		[
			'billing_email' => $user->user_email,
			'customer_id'   => 0,
			'limit'         => -1,
			'return'        => 'ids',
		]
	);

	$linked_count = count( $orders_before ) - count( $orders_after );
	wc_get_logger()->info( "CheckoutWC: Linked {$linked_count} past guest orders to user {$user_id}", [ 'source' => 'checkout-wc' ] );
}

function cfw_get_plugin_template_path() : string {
	return CFW_PATH_BASE . '/templates';
}

function cfw_get_user_template_path() : string {
	return get_stylesheet_directory() . '/checkout-wc';
}

function cfw_get_active_template() : Template {
	$cfw_preview = null;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['cfw-preview'] ) ) {
		$cfw_preview = sanitize_text_field( wp_unslash( $_GET['cfw-preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	} elseif ( isset( $_COOKIE['cfw-preview'] ) ) {
		$cfw_preview = sanitize_text_field( wp_unslash( $_COOKIE['cfw-preview'] ) );
	}

	$active_template_slug = sanitize_text_field( $cfw_preview ?? SettingsManager::instance()->get_setting( 'active_template' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$templates            = cfw_get_available_templates();

	$slug = ! isset( $templates[ $active_template_slug ] ) || ! PlanManager::has_premium_plan_or_higher() ? 'default' : $active_template_slug;

	return new Template( $slug );
}

/**
 * @return Template[]
 */
function cfw_get_available_templates() : array {
	return Template::get_all_available();
}

function cfw_frontend() {
	// Enqueue Assets
	( new AssetManager() )->init();

	if ( ! is_cfw_page() ) {
		return;
	}

	// Output Templates
	if ( SettingsManager::instance()->get_setting( 'template_loader' ) === 'content' ) {
		Content::checkout();
		Content::order_pay();
		Content::order_received();

		return;
	}

	add_action(
		'template_redirect',
		/**
		 * @throws WC_Data_Exception
		 */
		function() {
			Redirect::template_redirect();
		},
		/**
		 * Filters CheckoutWC template redirect priority
		 *
		 * @param int $priority The priority of the template_redirect action
		 * @return int
		 *
		 * @since 2.0.0
		 */
		apply_filters( 'cfw_template_redirect_priority', 11 )
	);
}

/**
 * @return bool|WC_Order|WC_Order_Refund
 */
function cfw_get_order_received_order() {
	global $wp;

	$order_id = $wp->query_vars['order-received'] ?? null;
	$order    = false;

	if ( ! $order_id ) {
		$session_data = WC()->session->get( 'cfw_post_purchase_data', null );
		$order_id     = cfw_apply_filters( 'woocommerce_thankyou_order_id', absint( $session_data['order_id'] ) );

		return wc_get_order( $order_id );
	}

	$order_id  = cfw_apply_filters( 'woocommerce_thankyou_order_id', absint( $order_id ) );
	$order_key = cfw_apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $order_id > 0 ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			$order = false;
		}
	}

	return $order;
}

/**
 * @return bool
 */
function cfw_is_thank_you_page_active() : bool {
	return PlanManager::can_access_feature( 'enable_thank_you_page', 'plus' );
}

/**
 * @return false|string
 */
function cfw_get_logo_url() {
	/**
	 * Filters header logo attachment ID
	 *
	 * @param int $logo_attachment_id The logo attachment ID
	 *
	 * @since 8.2.23
	 */
	$logo_attachment_id = apply_filters( 'cfw_get_logo_attachment_id', SettingsManager::instance()->get_setting( 'logo_attachment_id', [ cfw_get_active_template()->get_slug() ] ) );

	return wp_get_attachment_url( $logo_attachment_id );
}

function cfw_logo() {
	/**
	 * Filters header logo / title link URL
	 *
	 * @param string $url The link URL
	 *
	 * @since 3.0.0
	 */
	$url = apply_filters( 'cfw_header_home_url', get_home_url() );

	/**
	 * Filters header logo / title link URL
	 *
	 * @param string $url The link URL
	 *
	 * @since 5.3.0
	 */
	$blog_name = apply_filters( 'cfw_header_blog_name', get_bloginfo( 'name' ) );

	$logo_url = cfw_get_logo_url();
	?>
	<div class="cfw-logo">
		<a title="<?php echo esc_attr( html_entity_decode( $blog_name, ENT_QUOTES ) ); ?>"
			href="<?php echo esc_attr( $url ); ?>" class="<?php echo ! empty( $logo_url ) ? 'logo' : ''; ?>">
			<?php if ( empty( $logo_url ) ) : ?>
				<?php
				echo html_entity_decode( $blog_name, ENT_QUOTES );

 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
			<?php endif; ?>
		</a>
	</div>
	<?php
}

/**
 * Add WP theme styles to list of blocked style handles.
 *
 * @param array $styles the list of style handles
 * @return array
 */
function cfw_remove_theme_styles( $styles ) : array {
	global $wp_styles;

	$theme_directory_uri = get_theme_root_uri();
	$theme_directory_uri = str_replace(
		[
			'http:',
			'https:',
		],
		'',
		$theme_directory_uri
	); // handle both http/https/and relative protocol URLs

	foreach ( $wp_styles->registered as $wp_style ) {
		if ( ! empty( $wp_style->src ) && stripos( $wp_style->src, $theme_directory_uri ) !== false && stripos( $wp_style->src, '/checkout-wc/' ) === false ) {
			$styles[] = $wp_style->handle;
		}
	}

	return $styles;
}

/**
 * Add WP theme styles to list of blocked style handles.
 *
 * @param array $scripts list of scripts handles to remove
 * @return array
 */
function cfw_remove_theme_scripts( $scripts ) : array {
	global $wp_scripts;

	$theme_directory_uri = get_theme_root_uri();
	$theme_directory_uri = str_replace(
		[
			'http:',
			'https:',
		],
		'',
		$theme_directory_uri
	); // handle both http/https/and relative protocol URLs

	foreach ( $wp_scripts->registered as $wp_script ) {
		if ( ! empty( $wp_script->src ) && stripos( $wp_script->src, $theme_directory_uri ) !== false && stripos( $wp_script->src, '/checkout-wc/' ) === false ) {
			$scripts[] = $wp_script->handle;
		}
	}

	return $scripts;
}

/**
 * For gateways that add buttons above checkout form
 *
 * @param string $separator_class the class to add to the separator
 * @param string $id the id to add to the separator
 * @param string $style the style to add to the separator
 */
function cfw_add_separator( string $separator_class = '', string $id = '', string $style = '' ) {
	if ( ! defined( 'CFW_PAYMENT_BUTTON_SEPARATOR' ) ) {
		define( 'CFW_PAYMENT_BUTTON_SEPARATOR', true );
	} else {
		return;
	}

	?>
	<div id="payment-info-separator-wrap" class="<?php echo esc_attr( $separator_class ); ?>">
		<p <?php echo ( $id ) ? 'id="' . esc_attr( $id ) . '"' : ''; ?>
			style="<?php echo ! empty( $style ) ? esc_attr( "{$style};" ) : ''; ?>display: none" class="pay-button-separator">
			<span>
				<?php
				/**
				 * Filters payment request button separator text
				 *
				 * @param string $separator_label The separator label (default: Or)
				 *
				 * @since 2.0.0
				 */
				echo esc_html( apply_filters( 'cfw_express_pay_separator_text', __( 'Or', 'checkout-wc' ) ) );
				?>
			</span>
		</p>
	</div>
	<?php
}

/**
 * @param string $hook the hook to get the instance of
 * @param string $function_name the function name to get the instance of
 * @param int $priority the priority to get the instance of
 * @return false|mixed
 */
function cfw_get_hook_instance_object( string $hook, string $function_name, int $priority = 10 ) {
	global $wp_filter;

	$existing_hooks = $wp_filter[ $hook ] ?? false;

	if ( ! $existing_hooks ) {
		return false;
	}

	if ( isset( $existing_hooks[ $priority ] ) ) {
		foreach ( $existing_hooks[ $priority ] as $key => $callback ) {
			if ( false !== stripos( $key, $function_name ) ) {
				// Check if it's an array before accessing index (fixes Closure compatibility)
				if ( is_array( $callback['function'] ) ) {
					return $callback['function'][0];
				}

				// If it's a Closure or other callable, there's no object instance to return
				return false;
			}
		}
	}

	return false;
}

/**
 * @return bool
 */
function cfw_is_login_at_checkout_allowed() : bool {
	return 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' );
}

/**
 * @param array $cart_data the cart data to update
 * @param bool $refresh_totals whether to refresh the totals
 * @param string $context the context
 * @return bool
 */
function cfw_update_cart( array $cart_data, bool $refresh_totals = true, $context = 'side_cart' ) : bool {
	$cart_updated = false;

	try {
		if ( WC()->cart->is_empty() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

			$_product = $values['data'];

			// Skip product if no updated quantity was posted.
			if ( ! isset( $cart_data[ $cart_item_key ] ) || ! isset( $cart_data[ $cart_item_key ]['qty'] ) ) {
				continue;
			}

			// Sanitize.
			$quantity = cfw_apply_filters( 'woocommerce_stock_amount_cart_item', wc_stock_amount( preg_replace( '/[^0-9\.]/', '', $cart_data[ $cart_item_key ]['qty'] ) ), $cart_item_key );

			// Type safe comparison to prevent issues: https://secure.helpscout.net/conversation/2615606748/19839/
			if ( '' === $quantity || (float) $quantity === (float) $values['quantity'] ) {
				continue;
			}

			// Update cart validation.
			// Don't run this for removals
			$passed_validation = '0' !== strval( $quantity ) ? cfw_apply_filters( 'woocommerce_update_cart_validation', true, $cart_item_key, $values, $quantity ) : true;

			// is_sold_individually.
			if ( $_product->is_sold_individually() && $quantity > 1 ) {
				/* Translators: %s Product title. */
				wc_add_notice( sprintf( __( 'You can only have 1 %s in your cart.', 'woocommerce' ), $_product->get_name() ), 'error' );
				$passed_validation = false;
			}

			if ( ! $passed_validation ) {
				continue;
			}

			if ( '0' === strval( $quantity ) ) {
				WC()->cart->remove_cart_item( $cart_item_key );
			} else {
				WC()->cart->set_quantity( $cart_item_key, $quantity, false );
			}

			$cart_updated = true;
		}
	} catch ( Exception $e ) {
		return false;
	}

	// Trigger action - let 3rd parties update the cart if they need to and update the $cart_updated variable.
	// https://github.com/woocommerce/woocommerce/blob/d56c79605c6de8db0db76c5ad27d789ee4a63175/plugins/woocommerce/includes/class-wc-form-handler.php#L696
	$cart_updated = cfw_apply_filters( 'woocommerce_update_cart_action_cart_updated', $cart_updated );

	if ( $cart_updated && $refresh_totals ) {
		// Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
		// Without these lines, changes aren't always saved
		try {
			WC()->cart->calculate_totals();
		} catch ( Throwable $e ) {
			wc_get_logger()->error( 'Could not calculate shipping and/or totals after cart update: ' . $e->getMessage(), [ 'source' => 'checkout-wc' ] );
		}
	}

	if ( WC()->cart->get_cart_contents_count() === 0 ) {
		// Clear the cart removed cart contents if empty
		// This allows auto-add items to come back
		WC()->cart->removed_cart_contents = [];
		WC()->session->set( 'removed_cart_contents', null );
	}

	/**
	 * Fires after the cart is updated
	 *
	 * @param bool $cart_updated Whether the cart was updated
	 * @param string $context The context of the cart update
	 *
	 * @since 6.1.7
	 */
	do_action( 'cfw_cart_updated', $cart_updated, $context );

	return true;
}

function cfw_get_cart_item_quantity_control( array $cart_item, string $cart_item_key, WC_Product $product ) : string {
	if ( empty( $cart_item_key ) ) {
		return '';
	}

	/**
	 * Get the output of the cart quantity control to determine if it's being modified
	 *
	 * Output filtering is required because some very stupid YITH plugins echo on the filter instead of returning something.
	 */
	$defaults = [
		'input_id'     => uniqid( 'quantity_' ),
		'input_name'   => 'quantity',
		'input_value'  => '1',
		'classes'      => cfw_apply_filters(
			'woocommerce_quantity_input_classes',
			[
				'input-text',
				'qty',
				'text',
			],
			$product
		),
		'max_value'    => cfw_apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
		'min_value'    => cfw_apply_filters( 'woocommerce_quantity_input_min', 0, $product ),
		'step'         => cfw_apply_filters( 'woocommerce_quantity_input_step', 1, $product ),
		'pattern'      => cfw_apply_filters( 'woocommerce_quantity_input_pattern', has_filter( 'woocommerce_stock_amount', 'intval' ) ? '[0-9]*' : '' ),
		'inputmode'    => cfw_apply_filters( 'woocommerce_quantity_input_inputmode', has_filter( 'woocommerce_stock_amount', 'intval' ) ? 'numeric' : '' ),
		'product_name' => $product->get_title(),
		'placeholder'  => cfw_apply_filters( 'woocommerce_quantity_input_placeholder', '', $product ),
		// When autocomplete is enabled in firefox, it will overwrite actual value with what user entered last. So we default to off.
		// See @link https://github.com/woocommerce/woocommerce/issues/30733.
		'autocomplete' => cfw_apply_filters( 'woocommerce_quantity_input_autocomplete', 'off', $product ),
	];

	$args = cfw_apply_filters( 'woocommerce_quantity_input_args', $defaults, $product );

	$max_quantity = cfw_apply_filters( 'cfw_cart_item_quantity_max_value', $args['max_value'] > 0 ? $args['max_value'] : PHP_INT_MAX, $cart_item, $cart_item_key );
	$at_max       = $cart_item['quantity'] >= $max_quantity || $product->is_sold_individually();
	$at_min       = $cart_item['quantity'] <= $args['min_value'];

	/**
	 * Filters cart item minimum quantity
	 *
	 * @param array $cart_item The cart item
	 * @param string $cart_item_key The cart item key
	 * @param int $min_quantity Cart item minimum quantity
	 *
	 * @since 2.0.0
	 */
	$min_quantity = apply_filters( 'cfw_cart_item_quantity_min_value', $args['min_value'], $cart_item, $cart_item_key );

	/**
	 * Filters cart item quantity step
	 *
	 * Determines how much to increment or decrement by
	 *
	 * @param array $cart_item The cart item
	 * @param string $cart_item_key The cart item key
	 * @param int $quantity_step Cart item quantity step amount
	 *
	 * @since 2.0.0
	 */
	$quantity_step = apply_filters( 'cfw_cart_item_quantity_step', $args['step'], $cart_item, $cart_item_key );

	ob_start();
	?>
	<input type="hidden" data-key="<?php echo esc_attr( $cart_item_key ); ?>"
			data-min-value="<?php echo esc_attr( $min_quantity ); ?>"
			data-step="<?php echo esc_attr( $quantity_step ); ?>"
			data-max-quantity="<?php echo esc_attr( $max_quantity ); ?>" class="cfw-edit-item-quantity-value"
			value="<?php echo esc_attr( $cart_item['quantity'] ); ?>"/>
	<?php

	$hidden_input = ob_get_clean();

	/**
	 * Filters whether to disable side cart item quantity control
	 *
	 * @param array $cart_item The cart item
	 * @param string $cart_item_key The cart item key
	 * @param int $disable_side_cart_item_quantity_control Whether to disable cart editing
	 *
	 * @since 8.0.0
	 */
	$disable_side_cart_item_quantity_control = apply_filters( 'cfw_disable_side_cart_item_quantity_control', false, $cart_item, $cart_item_key );

	if ( cfw_cart_quantity_input_has_override( $cart_item, $cart_item_key, $product ) || $disable_side_cart_item_quantity_control ) {
		return $hidden_input;
	}

	ob_start();
	?>
	<div class="cfw-edit-item-quantity-control-wrap">
		<div class="cfw-quantity-stepper">
			<?php echo $hidden_input; // phpcs:ignore?>
			<button aria-label="<?php esc_attr_e( 'Decrement', 'checkout-wc' ); ?>"
					class="cfw-quantity-stepper-btn-minus <?php echo $at_min ? 'cfw-disabled' : ''; ?>">
				<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" viewBox="0 0 384 512">
					<path
						d="M376 232H8c-4.42 0-8 3.58-8 8v32c0 4.42 3.58 8 8 8h368c4.42 0 8-3.58 8-8v-32c0-4.42-3.58-8-8-8z"/>
				</svg>
			</button>
			<a data-quantity="<?php echo esc_attr( $cart_item['quantity'] ); ?>"
				class="cfw-quantity-stepper-value-label <?php echo $at_max ? '' : 'cfw-quantity-bulk-edit'; ?>"
				aria-label="<?php _e( 'Edit', 'woocommerce' ); ?>">
				<?php echo esc_html( $cart_item['quantity'] ); ?>
			</a>
			<button aria-label="<?php esc_attr_e( 'Increment', 'checkout-wc' ); ?>"
					class="cfw-quantity-stepper-btn-plus <?php echo $at_max ? 'cfw-disabled' : ''; ?>">
				<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" viewBox="0 0 384 512">
					<path
						d="M376 232H216V72c0-4.42-3.58-8-8-8h-32c-4.42 0-8 3.58-8 8v160H8c-4.42 0-8 3.58-8 8v32c0 4.42 3.58 8 8 8h160v160c0 4.42 3.58 8 8 8h32c4.42 0 8-3.58 8-8V280h160c4.42 0 8-3.58 8-8v-32c0-4.42-3.58-8-8-8z"/>
				</svg>
			</button>
		</div>
	</div>

	<?php
	return (string) ob_get_clean();
}

function cfw_cart_quantity_input_has_override( array $cart_item, string $cart_item_key, WC_Product $product ) : bool {
	/**
	 * Get the output of the cart quantity control to determine if it's being modified
	 *
	 * Output filtering is required because some very stupid YITH plugins echo on the filter instead of returning something.
	 */
	$product_quantity = woocommerce_quantity_input(
		[
			'input_name'   => "cart[{$cart_item_key}][qty]",
			'input_value'  => $cart_item['quantity'],
			/**
			 * Filters cart item quantity control max value
			 *
			 * @param string $cart_item_key The cart item key
			 * @param int $max_value The max value
			 *
			 * @since 8.2.18
			 */
			'max_value'    => apply_filters( 'cfw_cart_item_quantity_max_value', $product->get_max_purchase_quantity(), $cart_item, $cart_item_key ),
			'min_value'    => cfw_apply_filters( 'cfw_cart_item_quantity_min_value', '0', $cart_item, $cart_item_key ),
			'product_name' => $product->get_name(),
		],
		$product,
		false
	);

	ob_start();

	$woocommerce_core_cart_quantity = cfw_apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // PHPCS: XSS ok.

	$filter_output = ob_get_clean();

	if ( ! empty( $filter_output ) ) {
		$woocommerce_core_cart_quantity = $filter_output;
	}

	/**
	 * Filters whether the cart quantity input has been overridden
	 *
	 * @param bool $has_override Whether the cart quantity input has been overridden
	 *
	 * @since 8.2.18
	 */
	return apply_filters( 'cfw_cart_quantity_input_has_override', $woocommerce_core_cart_quantity !== $product_quantity, $cart_item_key );
}

function cfw_get_woocommerce_notices( $clear_notices = true ) : array {
	/**
	 * Filters WooCommerce notices before display
	 *
	 * @param array $all_notices
	 *
	 * @since 8.2.23
	 */
	$all_notices = apply_filters( 'cfw_get_woocommerce_notices', WC()->session->get( 'wc_notices', [] ) );

	// Filter out empty messages
	foreach ( $all_notices as $key => $notice ) {
		if ( empty( array_filter( $notice ) ) ) {
			unset( $all_notices[ $key ] );
		}
	}

	/** This filter is documented in woocommerce/includes/wc-notice-functions.php */
	$notice_types = cfw_apply_filters( 'woocommerce_notice_types', [ 'error', 'success', 'notice' ] );
	$notices      = [];

	foreach ( $notice_types as $notice_type ) {
		if ( count( $all_notices[ $notice_type ] ?? [] ) > 0 && isset( $all_notices[ $notice_type ] ) ) {
			$notices[ $notice_type ] = [];

			// In WooCommerce 3.9+, messages can be an array with two properties:
			// - notice
			// - data
			foreach ( $all_notices[ $notice_type ] as $notice ) {
				$notices[ $notice_type ][] = [
					'notice' => $notice['notice'] ?? $notice,
					'data'   => $notice['data'] ?? null,
				];
			}
		}
	}

	if ( $clear_notices ) {
		wc_clear_notices();
	}

	return $notices;
}

function cfw_get_variation_id_from_attributes( $product, $default_attributes ) : ?int {
	$variation_id = null;

	$variations = $product->get_available_variations();

	foreach ( $variations as $variation ) {
		$new_default_attributes = [];

		foreach ( $default_attributes as $key => $value ) {
			if ( stripos( $key, 'attribute_' ) === false ) {
				$key = "attribute_{$key}";
			}

			$new_default_attributes[ $key ] = $value;
		}

		ksort( $variation['attributes'] );
		ksort( $new_default_attributes );

		if ( $variation['attributes'] === $new_default_attributes ) {
			$variation_id = $variation['variation_id'];
			break;
		}
	}

	return $variation_id;
}

function cfw_get_allowed_html() : array {
	$allowed_html = wp_kses_allowed_html( 'post' );

	$allowed_html = array_merge(
		$allowed_html,
		[
			'form'   => [
				'action'         => true,
				'accept'         => true,
				'accept-charset' => true,
				'enctype'        => true,
				'method'         => true,
				'name'           => true,
				'target'         => true,
			],

			'input'  => [
				'type'        => true,
				'id'          => true,
				'placeholder' => true,
				'name'        => true,
				'value'       => true,
				'checked'     => true,
			],

			'button' => [
				'type'  => true,
				'class' => true,
				'label' => true,
			],

			'svg'    => [
				'hidden'    => true,
				'role'      => true,
				'focusable' => true,
				'xmlns'     => true,
				'width'     => true,
				'height'    => true,
				'viewbox'   => true,
			],
			'path'   => [
				'd' => true,
			],
			'bdi',
		]
	);

	return array_map( '_wp_add_global_attributes', $allowed_html );
}

/**
 * Return suggested products
 *
 * Uses WooCommerce native cross-sells feature, otherwise is able to fall back to random products.
 *
 * Cache results based on cart contents.
 *
 * @param int $limit the number of products to return
 * @param bool $random_fallback whether to fall back to random products
 * @return array An array of WC_Product objects
 */
function cfw_get_suggested_products( int $limit = 3, bool $random_fallback = false ) : array {
	// Get array of products in the cart
	$cart_item_ids = [];

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = $cart_item['data'];

		$cart_item_ids[] = $product->get_id();
	}

	// Get hash of $cart_item_ids
	$cart_item_ids_hash = md5( implode( ',', $cart_item_ids ) );

	$cross_sells = get_transient( 'cfw_suggested_products_' . $cart_item_ids_hash );

	if ( empty( $cross_sells ) ) {
		$cross_sells = [];

		$cross_sell_ids = array_slice( WC()->cart->get_cross_sells(), 0, $limit );

		foreach ( $cross_sell_ids as $cross_sell_id ) {
			$cross_sell_product = wc_get_product( $cross_sell_id );

			if ( ! $cross_sell_product ) {
				continue;
			}

			if ( $cross_sell_product->get_status() !== 'publish' ) {
				continue;
			}

			// Protect against out of stock products
			if ( ! $cross_sell_product || $cross_sell_product->get_stock_status( 'edit' ) !== 'instock' ) {
				continue;
			}

			$cross_sells[] = $cross_sell_product;
		}

		if ( count( $cross_sells ) === 0 && $random_fallback ) {
			$random_products = wc_get_products(
				[
					'limit'        => $limit,
					'exclude'      => $cart_item_ids,
					'status'       => 'publish',
					'orderby'      => 'rand',
					'stock_status' => 'instock',
				]
			);

			// Ensure we always have an array
			$cross_sells = is_array( $random_products ) ? $random_products : [ $random_products ];
		}

		// Clean up non-products
		foreach ( $cross_sells as $key => $cross_sell ) {
			if ( ! $cross_sell instanceof WC_Product ) {
				unset( $cross_sells[ $key ] );
			}
		}

		/**
		 * Filter suggested products
		 *
		 * @param WC_Product[] $cross_sells The suggested products
		 *
		 * @since 9.0.0
		 */
		$cross_sells = apply_filters( 'cfw_get_suggested_products', $cross_sells );

		// If transient doesn't exist, create it
		set_transient( 'cfw_suggested_products_' . $cart_item_ids_hash, $cross_sells, 60 * 60 * 24 );
	}

	/**
	 * Filter suggested products
	 *
	 * @param array $cross_sells
	 * @param int $limit
	 * @param bool $random_fallback
	 * @return WC_Product[] The suggested products
	 *
	 * @since 8.0.0
	 */
	return apply_filters( 'cfw_get_suggested_products', $cross_sells, $limit, $random_fallback );
}

function cfw_get_email_stylesheet() : string {
	ob_start();
	?>
	/* -------------------------------------
	GLOBAL RESETS
	------------------------------------- */

	/*All the styling goes here*/

	img {
	border: none;
	-ms-interpolation-mode: bicubic;
	max-width: 100%;
	}

	body {
	background-color: #f6f6f6;
	font-family: sans-serif;
	-webkit-font-smoothing: antialiased;
	font-size: 14px;
	line-height: 1.4;
	margin: 0;
	padding: 0;
	-ms-text-size-adjust: 100%;
	-webkit-text-size-adjust: 100%;
	}

	table {
	border-collapse: separate;
	mso-table-lspace: 0pt;
	mso-table-rspace: 0pt;
	width: 100%;
	}

	table td {
	font-family: sans-serif;
	font-size: 14px;
	vertical-align: top;
	}

	/* -------------------------------------
	BODY & CONTAINER
	------------------------------------- */
	.body {
	background-color: #ffffff;
	width: 100%;
	}

	/* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
	.container {
	display: block;
	max-width: 580px;
	width: 100%;
	}

	/* This should also be a block element, so that it will fill 100% of the .container */
	.content {
	box-sizing: border-box;
	display: block;
	margin: 0 auto;
	max-width: 580px;
	padding: 10px;
	}

	/* -------------------------------------
	HEADER, FOOTER, MAIN
	------------------------------------- */
	.main {
	background: #ffffff;
	border-radius: 3px;
	width: 100%;
	}

	.wrapper {
	box-sizing: border-box;
	}

	.content-block {
	padding-bottom: 10px;
	padding-top: 10px;
	}

	.footer {
	clear: both;
	margin-top: 10px;
	width: 100%;
	}

	.footer td,
	.footer p,
	.footer span,
	.footer a {
	color: #999999;
	font-size: 12px;
	}

	/* -------------------------------------
	TYPOGRAPHY
	------------------------------------- */
	h1,
	h2,
	h3,
	h4 {
	color: #000000;
	font-family: sans-serif;
	font-weight: 400;
	line-height: 1.4;
	margin: 0;
	margin-bottom: 30px;
	}

	h1 {
	font-size: 35px;
	font-weight: 300;
	text-align: center;
	text-transform: capitalize;
	}

	p,
	ul,
	ol {
	font-family: sans-serif;
	font-size: 14px;
	font-weight: normal;
	margin: 0;
	margin-bottom: 15px;
	}

	p li,
	ul li,
	ol li {
	list-style-position: inside;
	margin-left: 5px;
	}

	a {
	color: #3498db;
	text-decoration: underline;
	}

	/* -------------------------------------
	BUTTONS
	------------------------------------- */
	.btn {
	box-sizing: border-box;
	width: 100%;
	}

	.btn > tbody > tr > td {
	padding-bottom: 15px;
	}

	.btn table {
	width: auto;
	}

	.btn table td {
	background-color: #ffffff;
	border-radius: 5px;
	text-align: center;
	}

	.btn a {
	background-color: #ffffff;
	border: solid 1px #3498db;
	border-radius: 5px;
	box-sizing: border-box;
	color: #3498db;
	cursor: pointer;
	display: inline-block;
	font-size: 14px;
	font-weight: bold;
	margin: 0;
	padding: 12px 25px;
	text-decoration: none;
	text-transform: capitalize;
	}

	.btn-primary table td {
	background-color: #3498db;
	}

	.btn-primary a {
	background-color: #3498db;
	border-color: #3498db;
	color: #ffffff;
	}

	/* -------------------------------------
	OTHER STYLES THAT MIGHT BE USEFUL
	------------------------------------- */
	.last {
	margin-bottom: 0;
	}

	.first {
	margin-top: 0;
	}

	.align-center {
	text-align: center;
	}

	.align-right {
	text-align: right;
	}

	.align-left {
	text-align: left;
	}

	.clear {
	clear: both;
	}

	.mt0 {
	margin-top: 0;
	}

	.mb0 {
	margin-bottom: 0;
	}

	.preheader {
	color: transparent;
	display: none;
	height: 0;
	max-height: 0;
	max-width: 0;
	opacity: 0;
	overflow: hidden;
	mso-hide: all;
	visibility: hidden;
	width: 0;
	}

	.powered-by a {
	text-decoration: none;
	}

	hr {
	border: 0;
	border-bottom: 1px solid #f6f6f6;
	margin: 20px 0;
	}

	/* -------------------------------------
	RESPONSIVE AND MOBILE FRIENDLY STYLES
	------------------------------------- */
	@media only screen and (max-width: 620px) {
	table.body h1 {
	font-size: 28px !important;
	margin-bottom: 10px !important;
	}

	table.body p,
	table.body ul,
	table.body ol,
	table.body td,
	table.body span,
	table.body a {
	font-size: 16px !important;
	}

	table.body .wrapper,
	table.body .article {
	padding: 10px !important;
	}

	table.body .content {
	padding: 0 !important;
	}

	table.body .container {
	padding: 0 !important;
	width: 100% !important;
	}

	table.body .main {
	border-left-width: 0 !important;
	border-radius: 0 !important;
	border-right-width: 0 !important;
	}

	table.body .btn table {
	width: 100% !important;
	}

	table.body .btn a {
	width: 100% !important;
	}

	table.body .img-responsive {
	height: auto !important;
	max-width: 100% !important;
	width: auto !important;
	}
	}

	/* -------------------------------------
	PRESERVE THESE STYLES IN THE HEAD
	------------------------------------- */
	@media all {
	.ExternalClass {
	width: 100%;
	}

	.ExternalClass,
	.ExternalClass p,
	.ExternalClass span,
	.ExternalClass font,
	.ExternalClass td,
	.ExternalClass div {
	line-height: 100%;
	}

	.apple-link a {
	color: inherit !important;
	font-family: inherit !important;
	font-size: inherit !important;
	font-weight: inherit !important;
	line-height: inherit !important;
	text-decoration: none !important;
	}

	#MessageViewBody a {
	color: inherit;
	text-decoration: none;
	font-size: inherit;
	font-family: inherit;
	font-weight: inherit;
	line-height: inherit;
	}

	.btn-primary table td:hover {
	background-color: #34495e !important;
	}

	.btn-primary a:hover {
	background-color: #34495e !important;
	border-color: #34495e !important;
	}
	}
	<?php
	$styles = [
		'table' => [
			'style'     => 'color: #636363; border: 1px solid #e5e5e5;',
			'attribute' => 'align=left',
		],
	];

	/**
	 * Filter the cart table styles
	 *
	 * @param array $styles
	 *
	 * @since 8.0.0
	 * @since 10.1.0 Style attribute is deprecated.
	 */
	$table_styles_filter_output = apply_filters( 'cfw_cart_table_styles', $styles );

	$style_attribute = $table_styles_filter_output['table']['style'] ?? '';

	if ( ! empty( $style_attribute ) ) {
		$style_attribute = '#cfw_acr_cart_products_table tr th { ' . $style_attribute . ' }';
	}

	/**
	 * Filter the cart table custom styles
	 *
	 * @param string $styles
	 *
	 * @since 10.0.2
	 */
	$custom_styles = apply_filters( 'cfw_acr_email_custom_css', '' );

	// Custom styles come last so that they override the deprecated styles attribute
	return ob_get_clean() . $style_attribute . $custom_styles;
}

function cfw_get_email_template( $subject, $preheader, $content ) {
	ob_start();
	?>
	<!doctype html>
	<html>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
		<title><?php echo esc_html( $subject ); ?></title>
	</head>
	<body>
	<?php echo cfw_get_email_body( $preheader, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</body>
	</html>
	<?php

	return ob_get_clean();
}

function cfw_get_email_body( $preheader, $content ) {
	ob_start();
	?>
	<span class="preheader"><?php echo esc_html( $preheader ); ?></span>
	<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body">
		<tr>
			<td>&nbsp;</td>
			<td class="container">
				<div class="content">
					<table role="presentation" class="main">
						<tr>
							<td class="wrapper">
								<table role="presentation" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td>
											<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
					<div class="footer">
						<table role="presentation" border="0" cellpadding="0" cellspacing="0">
							<tr>
								<td class="content-block">
									<?php if ( stripos( $content, '{{unsubscribe_url}}' ) === false ) : ?>
										<a href="{{unsubscribe_url}}"><?php
										esc_html_e( 'Unsubscribe', 'checkout-wc' );

										?>
 </a>.
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				</div>
			</td>
			<td>&nbsp;</td>
		</tr>
	</table>
	<?php

	return ob_get_clean();
}

function cfw_wc_wrap_message( $email_heading, $message ) {
	// Buffer.
	ob_start();

	cfw_do_action( 'woocommerce_email_header', $email_heading, null );

	echo $message; // phpcs:ignore

	cfw_do_action( 'woocommerce_email_footer', null );

	// Get contents.
	return ob_get_clean();
}

/**
 * Remove product from the cart
 *
 * @param int $needle_product_id product ID to remove from cart
 * @param int $quantity_to_remove quantity to remove from cart
 * @return bool
 */
function cfw_remove_product_from_cart( int $needle_product_id, int $quantity_to_remove = -1 ) : bool {
	$needle_product = wc_get_product( $needle_product_id );

	if ( ! $needle_product ) {
		return false;
	}

	$quantity_to_remove = $quantity_to_remove < 0 ? PHP_INT_MAX : $quantity_to_remove;

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$cart_item_variation_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
		$cart_item_parent_id    = $cart_item_variation_id ? wp_get_post_parent_id( $cart_item_variation_id ) : 0;
		$possible_ids           = [ $cart_item_parent_id, $cart_item_variation_id, $cart_item['product_id'] ];
		$in_cart                = in_array( $needle_product_id, $possible_ids, true );

		if ( ! $in_cart ) {
			continue;
		}

		$new_quantity = $cart_item['quantity'] - $quantity_to_remove;

		if ( $new_quantity <= 0 ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		} else {
			cfw_update_cart(
				[
					$cart_item_key => [
						'qty' => $new_quantity,
					],
				]
			);
		}

		return true;
	}

	return false;
}

function cfw_register_scripts( array $scripts = [] ) : bool {
	$result = true;

	try {
		cfwRegisterChunkedScripts( $scripts );
		cfw_set_script_translations_for_scripts( $scripts );
	} catch ( Exception $e ) {
		wc_get_logger()->error( 'Error loading asset: ' . $e->getMessage(), [ 'source' => 'checkout-wc' ] );
		$result = false;
	}

	return $result;
}

function cfw_set_script_translations_for_scripts( $scripts ) {
	foreach ( $scripts as $handle ) {
		if ( ! function_exists( 'wp_set_script_translations' ) ) {
			break;
		}

		// Special handling
		if ( in_array( $handle, [ 'checkout', 'order-pay', 'thank-you' ], true ) ) {
			$handle = 'woocommerce';
		} else {
			$handle = 'cfw-' . $handle;
		}

		wp_set_script_translations(
			$handle,
			'checkout-wc',
			CFW_PATH_BASE . 'i18n/languages'
		);
	}
}

/**
 * Customized version of WC()->payment_gateways()->set_current_gateway()
 *
 * @param array $gateways array of gateways
 * @param bool $choose_first_if_none whether to choose the first gateway if none is set
 * @return void
 */
function cfw_set_current_gateway( $gateways, bool $choose_first_if_none = true ) {
	// Be on the defensive.
	if ( ! is_array( $gateways ) || empty( $gateways ) ) {
		return;
	}

	$current_gateway = false;

	if ( WC()->session ) {
		$current = WC()->session->get( 'chosen_payment_method' );

		if ( $current && isset( $gateways[ $current ] ) ) {
			$current_gateway = $gateways[ $current ];
		}
	}

	if ( ! $current_gateway && $choose_first_if_none ) {
		$current_gateway = current( $gateways );
	}

	// Ensure we can make a call to set_current() without triggering an error.
	if ( $current_gateway && is_callable( [ $current_gateway, 'set_current' ] ) ) {
		$current_gateway->set_current();
	}
}

function cfw_get_item_discount_html( CartItem $item ) : string {
	// TODO: This should be in a compat class at some point, but it's really a strange use case
	add_filter( 'advanced_woo_discount_rules_modify_price_html', '__return_false' );

	$discount_html = cfw_apply_filters( 'woocommerce_cart_item_price', $item->get_product()->get_price_html(), $item->get_raw_item(), $item->get_raw_item()['key'] ); // PHPCS: XSS ok.

	/**
	 * Filters the discount HTML for a cart item
	 *
	 * @param string $discount_html The discount HTML
	 * @param array $raw_item The raw cart item
	 * @param WC_Product $product The product
	 *
	 * @since 4.0.0
	 */
	$discount_html = apply_filters( 'cfw_cart_item_discount', $discount_html, $item->get_raw_item(), $item->get_product() );

	if ( stripos( $discount_html, '<del' ) === false ) {
		$discount_html = '';
	}

	remove_filter( 'advanced_woo_discount_rules_modify_price_html', '__return_false' );

	return $discount_html;
}

function cfw_get_cart_items_data() : array {
	$items = [];

	foreach ( WC()->cart->get_cart() as $key => $raw_item ) {
		try {
			$item = new CartItem( $key, $raw_item );
		} catch ( Exception $e ) {
			cfw_debug_log( 'Error creating cart item: ' . $e->getMessage() );
			continue;
		}

		$product  = $item->get_product();
		$non_zero = $item->get_quantity();
		$visible  = cfw_apply_filters( 'woocommerce_checkout_cart_item_visible', true, $raw_item, $key );
		$include  = $product->exists() && $non_zero && $visible;

		if ( ! $include ) {
			continue;
		}

		$items[] = [
			'item_key'                                => $key,
			'thumbnail'                               => $item->get_thumbnail(),
			'quantity'                                => $item->get_quantity(),
			'title'                                   => $item->get_title(),
			'url'                                     => $item->get_url(),
			'subtotal'                                => $item->get_subtotal(),
			'subtotal_raw'                            => $item->get_subtotal_raw(),
			'hide_remove_item'                        => $item->get_hide_remove_item(),
			'row_class'                               => $item->get_row_class(),
			'data'                                    => $item->get_data(),
			'formatted_data'                          => $item->get_formatted_data(),
			'disable_cart_editing_at_checkout'        => $item->get_disable_cart_editing_at_checkout(),
			'disable_cart_editing'                    => $item->get_disable_cart_editing(),
			'disable_cart_variation_editing'          => $item->get_disable_cart_variation_editing(),
			'disable_cart_variation_editing_checkout' => $item->get_disable_cart_variation_editing_checkout(),
			'max_quantity'                            => $item->get_max_quantity(),
			'min_quantity'                            => $item->get_min_quantity(),
			'step'                                    => $item->get_step(),
			'product_title'                           => $item->get_product()->get_title(),
			'product_sku'                             => $item->get_product()->get_sku(),
			'product_id'                              => $item->get_product()->get_id(),
			'product_parent_id'                       => $item->get_product()->get_parent_id(),
			'has_quantity_override'                   => cfw_cart_quantity_input_has_override( $raw_item, $key, $product ),
			'discount_html'                           => cfw_get_item_discount_html( $item ),
			'actions'                                 => [
				/**
				 * Fires after cart item data output
				 *
				 * @since 2.0.0
				 */
				'cfw_cart_item_after_data'      => cfw_get_action_output( 'cfw_cart_item_after_data', $item->get_raw_item(), $item->get_item_key(), $item ),
				/**
				 * Fires before the cart item subtotal
				 *
				 * @since 2.0.0
				 */
				'cfw_before_cart_item_subtotal' => cfw_get_action_output( 'cfw_before_cart_item_subtotal', $item ),
				/**
				 * Fires after cart item row <tr/> is outputted
				 *
				 * @since 2.0.0
				 */
				'cfw_after_cart_item_row'       => cfw_get_action_output( 'cfw_after_cart_item_row', $item->get_raw_item(), $item->get_item_key() ),

				/**
				 * Fires after cart item data output
				 *
				 * @since 6.0.0
				 */
				'cfw_side_cart_item_after_data' => cfw_get_action_output( 'cfw_side_cart_item_after_data', $item->get_raw_item(), $item->get_item_key(), $item ),
			],
		];
	}

	return $items;
}

function cfw_get_cart_totals_data() : array {
	$coupons  = [];
	$fees     = [];
	$taxes    = [];
	$shipping = [];

	foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
		$coupons[] = [
			'code'  => $code,
			'class' => 'cart-discount coupon-' . sanitize_title( $code ),
			'label' => wc_cart_totals_coupon_label( $coupon, false ),
			'value' => cfw_get_function_output( 'wc_cart_totals_coupon_html', $coupon ),
		];
	}

	foreach ( WC()->cart->get_fees() as $fee ) {
		$fees[] = [
			'label' => esc_html( $fee->name ),
			'value' => cfw_get_function_output( 'wc_cart_totals_fee_html', $fee ),
		];
	}

	if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
		if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
			foreach ( WC()->cart->get_tax_totals() as $code => $tax ) {
				$taxes[] = [
					'class'     => 'tax-rate tax-rate-' . sanitize_title( $code ),
					'label'     => esc_html( $tax->label ),
					'raw_label' => $tax->label,
					'value'     => wp_kses_post( $tax->formatted_amount ),
				];
			}
		} else {
			$taxes[] = [
				'class'     => 'tax-total',
				'label'     => esc_html( WC()->countries->tax_or_vat() ),
				'raw_label' => WC()->countries->tax_or_vat(),
				'value'     => cfw_get_function_output( 'wc_cart_totals_taxes_total_html' ),
			];
		}
	}

	if ( cfw_show_shipping_total() ) {
		/**
		 * Whether to itemize shipping costs
		 *
		 * @since 10.1.7
		 *
		 * @param array $itemize_shipping_costs Whether to itemize shipping costs in totals (default: false)
		 */
		if ( apply_filters( 'cfw_totals_itemize_shipping_costs', false ) ) {
			$packages = WC()->shipping()->get_packages();

			foreach ( $packages as $i => $package ) {
				$chosen_method     = WC()->session->chosen_shipping_methods[ $i ] ?? '';
				$available_methods = empty( $package['rates'] ) ? [] : $package['rates'];

				foreach ( $available_methods as $method ) {
					if ( (string) $method->id !== (string) $chosen_method ) { // WC_Shipping_Method::id is defined as a string type, so we need to make sure we're comparing it as a string
						continue;
					}

					if ( 0 >= $method->cost ) {
						continue;
					}

					$shipping[] = [
						/**
						 * Filters cart totals shipping label
						 *
						 * @param string $cart_totals_shipping_label Cart totals shipping label
						 *
						 * @since 2.0.0
						 */
						'label' => cfw_apply_filters( 'woocommerce_shipping_package_name', sprintf( _nx( 'Shipping', 'Shipping %d', ( $i + 1 ), 'shipping packages', 'woocommerce' ), ( $i + 1 ) ), $i, $package ),
						'value' => WC()->cart->display_prices_including_tax() ? $method->cost + $method->get_shipping_tax() : $method->cost,
					];
				}
			}
		} else {
			$shipping[] = [
				/**
				 * Filters cart totals shipping label
				 *
				 * @param string $cart_totals_shipping_label Cart totals shipping label
				 *
				 * @since 2.0.0
				 */
				'label' => apply_filters( 'cfw_cart_totals_shipping_label', esc_html__( 'Shipping', 'woocommerce' ) ),
				'value' => cfw_get_shipping_total(),
			];
		}
	}

	$data = [
		'actions'  => [
			/**
			 * Fires at start of cart summary totals table
			 *
			 * @since 2.0.0
			 */
			'cfw_before_cart_summary_totals'              => cfw_get_action_output( 'cfw_before_cart_summary_totals' ),
			'woocommerce_review_order_before_order_total' => cfw_get_action_output( 'woocommerce_review_order_before_order_total' ),
			'woocommerce_review_order_after_order_total'  => cfw_get_action_output( 'woocommerce_review_order_after_order_total' ),
			'woocommerce_cart_totals_before_order_total'  => cfw_get_action_output( 'woocommerce_cart_totals_before_order_total' ),
			'woocommerce_cart_totals_after_order_total'   => cfw_get_action_output( 'woocommerce_cart_totals_after_order_total' ),
			/**
			 * Fires at end of cart summary totals table before </table> tag
			 *
			 * @since 2.0.0
			 */
			'cfw_after_cart_summary_totals'               => cfw_get_action_output( 'cfw_after_cart_summary_totals' ),
		],
		'subtotal' => [
			'label' => __( 'Subtotal', 'woocommerce' ),
			'value' => WC()->cart->get_cart_subtotal(),
		],
		'total'    => [
			'label' => __( 'Total', 'woocommerce' ),
			'value' => cfw_get_function_output( 'wc_cart_totals_order_total_html' ),
		],
		'coupons'  => $coupons,
		'fees'     => $fees,
		'taxes'    => $taxes,
		'quantity' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
		'shipping' => $shipping,
	];

	/**
	 * Filters the cart totals data
	 *
	 * @param array $data The cart totals data
	 *
	 * @since 10.1.0
	 */
	return apply_filters( 'cfw_get_cart_totals_data', $data );
}

function cfw_get_cart_actions_data() : array {
	/**
	 * Filters the cart actions data
	 *
	 * @param array $data The cart actions data
	 *
	 * @since 9.0.0
	 */
	return apply_filters(
		'cfw_get_cart_actions_data',
		[
			/**
			 * After cart html table output
			 *
			 * @since 4.3.4
			 */
			'cfw_after_cart_html'                      => cfw_get_action_output( 'cfw_after_cart_html' ),
			'woocommerce_review_order_before_shipping' => cfw_get_action_output( 'woocommerce_review_order_before_shipping' ),
			/**
			 * After shipping methods
			 *
			 * @since 4.3.4
			 */
			'cfw_after_shipping_methods'               => cfw_get_action_output( 'cfw_after_shipping_methods' ),
			'woocommerce_review_order_after_shipping'  => cfw_get_action_output( 'woocommerce_review_order_after_shipping' ),
			/**
			 * Whether to enable woocommerce_after_cart_totals hook for side cart
			 *
			 * @since 9.0.37
			 *
			 * @param bool $enable_side_cart_woocommerce_after_cart_totals_hook Whether to enable woocommerce_after_cart_totals hook for side cart
			 */
			'woocommerce_after_cart_totals'            => apply_filters( 'cfw_enable_side_cart_woocommerce_after_cart_totals_hook', false ) ? cfw_get_action_output( 'woocommerce_after_cart_totals' ) : '',
			'woocommerce_no_shipping_available_html'   => cfw_apply_filters( 'woocommerce_no_shipping_available_html', '<div class="cfw-alert cfw-alert-error"><div class="message">' . wpautop( esc_html__( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ) . '</div></div>' ),
		]
	);
}

function cfw_get_cart_static_actions_data() : array {
	/**
	 * Filters the cart actions data
	 *
	 * @param array $data The cart actions data
	 *
	 * @since 9.0.0
	 */
	return apply_filters(
		'cfw_get_cart_static_actions_data',
		[
			/**
			 * Fires at start of cart table
			 *
			 * @since 2.0.0
			 */
			'cfw_cart_html_table_start'            => cfw_get_action_output( 'cfw_cart_html_table_start' ),
			/**
			 * Fires at start of cart table
			 *
			 * @since 9.0.19
			 */
			'cfw_checkout_cart_html_table_start'   => cfw_get_action_output( 'cfw_checkout_cart_html_table_start' ),
			/**
			 * Fires at end of coupon module before closing </div> tag
			 *
			 * @since 2.0.0
			 */
			'cfw_coupon_module_end'                => cfw_get_action_output( 'cfw_coupon_module_end' ),
			/**
			 * Fires before shipping methods heading
			 *
			 * @since 2.0.0
			 */
			'cfw_checkout_before_shipping_methods' => cfw_get_action_output( 'cfw_checkout_before_shipping_methods' ),

			/**
			 * Fires before shipping method heading
			 *
			 * @since 10.1.0
			 */
			'cfw_before_shipping_method_heading'   => cfw_get_action_output( 'cfw_before_shipping_method_heading' ),

			/**
			 * Fires after shipping method heading
			 *
			 * @since 2.0.0
			 */
			'cfw_after_shipping_method_heading'    => cfw_get_action_output( 'cfw_after_shipping_method_heading' ),

			/**
			 * Fires after shipping methods html
			 *
			 * @since 2.0.0
			 */
			'cfw_checkout_after_shipping_methods'  => cfw_get_action_output( 'cfw_checkout_after_shipping_methods' ),
		]
	);
}

function cfw_get_function_output( $the_function, ...$args ) {
	ob_start();

	$the_function( ...$args );

	return ob_get_clean();
}

function cfw_get_review_data() : array {
	return [
		'contact'          => [
			'show'             => true,
			'label'            => null,
			/**
			 * Filters the contact value for the review pane
			 *
			 * @param string $content The contact value
			 *
			 * @since 7.0.0
			 */
			'content'          => apply_filters( 'cfw_review_pane_contact_value', WC()->checkout()->get_value( 'billing_email' ) ),
			'show_change_link' => true,
		],
		'shipping_address' => [
			'show'             => WC()->cart->needs_shipping(),
			'label'            => cfw_get_review_pane_shipping_address_label(),
			'content'          => cfw_get_review_pane_shipping_address( WC()->checkout() ),
			'show_change_link' => true,
		],
		'shipping_method'  => [
			/**
			 * Filters whether to show the shipping method tab in the review pane.
			 *
			 * @since 9.0.33
			 *
			 * @param bool $show whether to show the shipping method tab in the review pane
			 */
			'show'             => apply_filters( 'cfw_review_pane_show_shipping_method', WC()->cart->needs_shipping() ),
			'label'            => null,
			'content'          => join( ', ', cfw_get_chosen_shipping_method_labels() ),
			'show_change_link' => cfw_show_shipping_tab(),
		],
		'payment_method'   => [
			'show'             => true,
			'label'            => null,
			'content'          => cfw_get_review_pane_payment_method(),
			'show_change_link' => true,
		],
		'actions'          => [
			/**
			 * Fires after last review pane item on shipping step
			 *
			 * @since 9.0.37
			 */
			'cfw_after_shipping_step_review_pane'     => cfw_get_action_output( 'cfw_after_shipping_step_review_pane' ),
			/**
			 * Fires after last review pane item on payment step
			 *
			 * @since 9.0.37
			 */
			'cfw_after_payment_step_review_pane'      => cfw_get_action_output( 'cfw_after_payment_step_review_pane' ),
			/**
			 * Fires after last review pane item on order review step
			 *
			 * @since 9.0.37
			 */
			'cfw_after_order_review_step_review_pane' => cfw_get_action_output( 'cfw_after_order_review_step_review_pane' ),
		],
	];
}

/**
 * @throws Exception if the bumps data cannot be retrieved
 */
function cfw_get_order_bumps_data() : array {
	$data      = [];
	$locations = [
		'below_cart_items',
		'below_side_cart_items',
		'below_checkout_cart_items',
		'above_terms_and_conditions',
		'above_express_checkout',
		'bottom_information_tab',
		'bottom_shipping_tab',
		'below_complete_order_button',
		'complete_order',
		'post_purchase_one_click',
	];

	$bumps = BumpFactory::get_all( 'publish' );

	/**
	 * Filter order bumps before processing
	 *
	 * @param array $bumps Array of bump objects
	 *
	 * @since 11.0.0
	 */
	$bumps = apply_filters( 'cfw_get_order_bumps', $bumps );

	$count            = 0;
	$max_bumps        = (int) SettingsManager::instance()->get_setting( 'max_bumps' );
	$link_wrap        = '<div class="cfw-order-bump-image"><a target="_blank" href="%s">%s</a></div>';
	$auto_added_bumps = WC()->session->get( 'cfw_auto_added_bumps' ) ?? [];

	if ( $max_bumps < 0 ) {
		$max_bumps = 999;
	}

	foreach ( $locations as $location ) {
		foreach ( $bumps as $bump ) {
			if ( $count >= $max_bumps ) {
				break;
			}

			if ( ! $bump->can_be_displayed( $location ) ) {
				continue;
			}

			$offer_product    = $bump->get_offer_product();
			$thumb            = $offer_product->get_image( 'cfw_order_bump_thumb' );
			$wrapped_thumb    = $offer_product->is_visible() ? sprintf( $link_wrap, $offer_product->get_permalink(), $thumb ) : $thumb;
			$variation_parent = $offer_product->is_type( 'variable' ) && 0 === $offer_product->get_parent_id() && 'no' === get_post_meta( $bump->get_id(), 'cfw_ob_enable_auto_match', true );

			$data[] = [
				'id'               => $bump->get_id(),
				'offerProductId'   => $offer_product->get_id(),
				'wrappedThumb'     => wp_kses_post( $wrapped_thumb ),
				'offerDescription' => do_shortcode( $bump->get_offer_description() ),
				'offerLanguage'    => do_shortcode( $bump->get_offer_language() ),
				'offerPrice'       => wp_kses_post( $bump->get_offer_product_price() ),
				'variationParent'  => $variation_parent,
				'selected'         => ! in_array( $bump->get_id(), $auto_added_bumps, true ) && $bump->should_be_auto_added() && $bump->get_offer_product()->get_type() === 'variable',
				'location'         => $location,
			];

			$count++;

			// Remember auto added bumps
			if ( $bump->should_be_auto_added() ) {
				$auto_added_bumps[] = $bump->get_id();
				WC()->session->set( 'cfw_auto_added_bumps', $auto_added_bumps );
			}
		}
	}

	/**
	 * Filter order bumps data before returning
	 *
	 * @param array $data The order bumps data array
	 *
	 * @since 11.0.0
	 */
	return apply_filters( 'cfw_get_order_bumps_data', $data );
}

function cfw_get_action_output( string $action, ...$arg ) : string {
	ob_start();

	cfw_do_action( $action, ...$arg );

	return ob_get_clean();
}

function cfw_get_sendwp_admin_banner( $include_wrap = true ) {
	if ( function_exists( 'sendwp_get_server_url' ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="<?php echo $include_wrap ? 'bg-white shadow sm:rounded-lg mb-6' : ''; ?>">
		<div class="<?php echo $include_wrap ? 'px-4 py-5 sm:p-6' : ''; ?>">
			<h3 class="text-base font-semibold leading-6 text-gray-900">
				<?php _e( 'SendWP - Transactional Email', 'checkout-wc' ); ?>
			</h3>
			<div class="mt-2 sm:flex sm:items-start sm:justify-between">
				<div class="max-w-xl text-sm text-gray-500">
					<p class="mb-2">
						<?php _e( 'SendWP makes getting emails delivered as simple as a few clicks. So you can relax know those important emails are being delivered on time.', 'checkout-wc' ); ?>
					</p>
					<p class="mb-2">
						<?php _e( 'Try SendWP now and <strong>get your first month for just $1.</strong>', 'checkout-wc' ); ?>
					</p>
					<p>
						<a href="https://www.checkoutwc.com/documentation/abandoned-cart-recovery/" target="_blank"
							class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
							Learn More
						</a>
					</p>
					<p class="mt-2">
						<em>
							<?php _e( 'Note: SendWP is optional. You can use any transactional email service you prefer.' ); ?>
						</em>
					</p>
				</div>
				<div class="mt-5 sm:mt-0 sm:ml-6 sm:flex sm:flex-shrink-0 sm:items-center">
					<div class="text-center w-96">
						<div>
							<button type="button" id="cfw_sendwp_install_button"
									class="inline-flex items-center mb-2 px-6 py-3 border border-transparent text-lg shadow font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
								<?php _e( 'Connect to SendWP', 'checkout-wc' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

function cfw_get_chosen_shipping_method_labels() : array {
	// Chosen shipping methods
	$chosen_shipping_methods_labels = [];

	$packages = WC()->shipping()->get_packages();

	foreach ( $packages as $i => $package ) {
		$chosen_method     = WC()->session->get( 'chosen_shipping_methods' )[ $i ] ?? false;
		$available_methods = $package['rates'];

		if ( $chosen_method && $available_methods[ $chosen_method ] && method_exists( $available_methods[ $chosen_method ], 'get_label' ) ) {
			$chosen_shipping_methods_labels[] = $available_methods[ $chosen_method ]->get_label();
		}
	}

	/**
	 * Filters chosen shipping methods label
	 *
	 * @param string $chosen_shipping_methods_labels The chosen shipping methods
	 *
	 * @since 2.0.0
	 */
	return apply_filters( 'cfw_payment_method_address_review_shipping_method', $chosen_shipping_methods_labels );
}

function cfw_maybe_select_free_shipping_method( $cart_updated = false, $context = 'checkout', $was_free_shipping_available_pre_cart_update = false ) {
	if ( ! $cart_updated ) {
		return;
	}

	if ( $was_free_shipping_available_pre_cart_update ) {
		return;
	}

	if ( SettingsManager::instance()->get_setting( 'auto_select_free_shipping_method' ) !== 'yes' ) {
		return;
	}

	// Get chosen shipping methods, defaulting to an empty array if not set
	$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' ) ?? [];

	// Check if "free shipping" is already set
	if ( ! empty( $chosen_shipping_methods ) && strpos( $chosen_shipping_methods[0], 'free_shipping' ) !== false ) {
		return;
	}

	if ( 'add_to_cart' === $context ) {
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
	}

	$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' ) ?? [];

	$packages = WC()->shipping()->get_packages();

	if ( count( $packages ) === 0 ) {
		return;
	}

	// Guard against invalid rates array
	$rates = $packages[0]['rates'] ?? [];
	if ( ! is_array( $rates ) ) {
		return;
	}

	// Loop through shipping methods and select free shipping
	foreach ( $rates as $rate ) {
		if ( 'free_shipping' === $rate->method_id ) {
			// Set "Free shipping" method
			$chosen_shipping_methods[0] = $rate->id;
			WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

			return;
		}
	}
}

function cfw_is_free_shipping_available() : bool {
	if ( ! WC()->cart->needs_shipping() ) {
		return false;
	}

	WC()->cart->calculate_shipping();
	$packages = WC()->shipping()->get_packages();

	if ( ! is_array( $packages ) || count( $packages ) === 0 || ! is_array( $packages[0]['rates'] ) ) {
		return false;
	}

	$available_methods = $packages[0]['rates'];

	$free_shipping_available = false;
	foreach ( $available_methods as $method ) {
		if ( 'free_shipping' === $method->method_id ) {
			$free_shipping_available = true;
			break;
		}
	}

	return $free_shipping_available;
}

function cfw_get_current_admin_url() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

	if ( ! $uri ) {
		return '';
	}

	return remove_query_arg(
		[
			'_wpnonce',
			'_wc_notice_nonce',
			'wc_db_update',
			'wc_db_update_nonce',
			'wc-hide-notice',
		],
		admin_url( $uri )
	);
}

function cfw_get_login_form_html() {
	if ( ! cfw_is_login_at_checkout_allowed() ) {
		return '';
	}

	$redirect = wc_get_checkout_url();

	ob_start();
	?>
	<form id="cfw_login_modal_form" class="checkoutwc" method="post">
		<div id="cfw-login-alert-container" class="woocommerce-notices-wrapper"></div>
		<?php cfw_do_action( 'woocommerce_login_form_start' ); ?>

		<h3><?php esc_html_e( 'Welcome back', 'checkout-wc' ); ?></h3>

		<p class="cfw-mb">
			<span class="account-exists-text">
				<?php
				/**
				 * Filters the text for users who already have an account
				 *
				 * Default: It looks like you already have an account. Please enter your login details below.
				 *
				 * @since 9.0.34
				 *
				 * @param string $text The text
				 */
				echo apply_filters( 'cfw_login_form_account_exists_text', esc_html__( 'It looks like you already have an account. Please enter your login details below.', 'checkout-wc' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</span>
			<span class="account-does-not-exist-text">
				<?php
				/**
				 * Filters the text before the login form for users who have shopped with us before
				 *
				 * Default: If you have shopped with us before, please enter your login details below.
				 *
				 * @since 9.0.34
				 *
				 * @param string $text The text
				 */
				echo apply_filters( 'cfw_login_form_account_does_not_exist_text', esc_html__( 'If you have shopped with us before, please enter your login details below.', 'checkout-wc' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</span>
		</p>

		<?php
		woocommerce_form_field(
			'username',
			[
				'id'                => 'cfw_login_username',
				'label'             => __( 'Username or email address', 'woocommerce' ),
				'placeholder'       => __( 'Username or email address', 'woocommerce' ),
				'type'              => 'text',
				'autocomplete'      => 'username',
				'required'          => true,
				'custom_attributes' => [ 'data-parsley-trigger' => 'change focusout' ],
			],
			WC()->checkout()->get_value( 'username' )
		);

		woocommerce_form_field(
			'password',
			[
				'id'                => 'cfw_login_password',
				'label'             => __( 'Password', 'woocommerce' ),
				'placeholder'       => __( 'Password', 'woocommerce' ),
				'type'              => 'password',
				'autocomplete'      => 'current-password',
				'required'          => true,
				'custom_attributes' => [ 'data-parsley-trigger' => 'change focusout' ],
			]
		);

		cfw_do_action( 'woocommerce_login_form' );

		wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' );
		?>
		<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect ); ?>"/>

		<div class="cfw-login-modal-footer">
			<p class="form-row">
				<label
					class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme"
							type="checkbox" id="rememberme" value="forever"/>
					<span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
				</label>
			</p>
			<p class="lost_password">
				<?php
				/**
				 * Filters the link to the Lost Password page.
				 *
				 * @since 9.0.34
				 *
				 * @param string $link the link to the Lost Password page
				 */
				echo apply_filters( 'cfw_login_modal_last_password_link', sprintf( '<a id="cfw_lost_password_trigger" href="#cfw_lost_password_form_wrap" class="cfw-small">%s</a>', esc_html__( 'Lost your password?', 'woocommerce' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</p>
		</div>

		<div class="cfw-login-modal-navigation">
			<button type="submit" id="cfw-login-btn" class="cfw-primary-btn" name="login"
					value="<?php esc_attr_e( 'Login', 'woocommerce' ); ?>"><?php esc_html_e( 'Login', 'woocommerce' ); ?></button>

			<?php if ( ! WC()->checkout()->is_registration_required() ) : ?>
				<a id="cfw_login_modal_close" href="#">
					<?php
					/**
					 * Filters the text for the continue as guest button
					 *
					 * @since 9.0.34
					 *
					 * @param string $text The text
					 */
					echo apply_filters( 'cfw_login_form_continue_as_guest_button_text', esc_html__( 'Or continue as guest', 'checkout-wc' ) );

 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</a>
			<?php endif; ?>
		</div>

		<?php cfw_do_action( 'woocommerce_login_form_end' ); ?>
	</form>
	<?php
	return ob_get_clean();
}

function cfw_get_lost_password_form_html() {
	ob_start();
	?>
	<form method="post" target="_blank" id="cfw_lost_password_form" class="checkoutwc">
		<div id="cfw-lp-alert-placeholder"></div>
		<p style="margin-bottom: 1em">
			<?php echo cfw_apply_filters( 'woocommerce_lost_password_message', esc_html__( 'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.', 'woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>

		<?php
		woocommerce_form_field(
			'user_login',
			[
				'type'         => 'email',
				'required'     => true,
				'autocomplete' => 'email',
				'label'        => __( 'Email address', 'woocommerce' ),
				'placeholder'  => __( 'Email address', 'woocommerce' ),
			]
		);
		?>

		<div class="clear"></div>

		<?php cfw_do_action( 'woocommerce_lostpassword_form' ); ?>

		<p class="woocommerce-form-row form-row">
			<input type="hidden" name="wc_reset_password" value="true"/>
			<button type="submit" class="cfw-primary-btn"
					value="<?php esc_attr_e( 'Reset password', 'woocommerce' ); ?>"><?php esc_html_e( 'Reset password', 'woocommerce' ); ?></button>
		</p>

		<?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>

	</form>
	<?php
	return ob_get_clean();
}

function cfw_get_order_bump_product_form( $bump_id ) {
	$bump    = BumpFactory::get( $bump_id );
	$product = $bump->get_offer_product();

	if ( ! $product ) {
		return new WP_Error( 'product_not_found', __( 'Product not found', 'checkout-wc' ), [ 'status' => 404 ] );
	}

	if ( $product->is_type( 'variable' ) && 0 === $product->get_parent_id() ) {
		return cfw_get_order_bump_variable_product_form( $product, $bump );
	}

	return cfw_get_order_bump_regular_product_form( $product, $bump );
}

function cfw_get_order_bump_variable_product_form( WC_Product_Variable $variable_product, BumpInterface $bump ) {
	$selected_variation              = [];
	$cart_item                       = [];
	$cfw_ob_offer_cancel_button_text = $bump->get_offer_cancel_button_text();

	if ( empty( $cfw_ob_offer_cancel_button_text ) ) {
		$cfw_ob_offer_cancel_button_text = __( 'No thanks', 'checkout-wc' );
	}

	if ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cart_item          = WC()->cart->get_cart_item( sanitize_key( wp_unslash( $_GET['key'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_variation = $cart_item['variation'];
	}

	$selected_qty         = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 1;
	$available_variations = $variable_product->get_available_variations();

	foreach ( $available_variations as $key => $variation ) {
		$available_variations[ $key ]['price_html'] = $bump->get_offer_product_price( $variation['variation_id'] );
	}

	$variations_json = wp_json_encode( $available_variations );
	$variations_attr = function_exists( 'wc_esc_json' ) ? wc_esc_json( $variations_json ) : _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );
	$attributes      = $variable_product->get_variation_attributes();
	$image           = $variable_product->get_image( 'woocommerce_thumbnail', [ 'class' => 'wp-post-image' ] );
	$wrapper_classes = cfw_apply_filters(
		'woocommerce_single_product_image_gallery_classes',
		[
			'woocommerce-product-gallery',
			'woocommerce-product-gallery--' . ( $image ? 'with-images' : 'without-images' ),
			'images',
		]
	);
	ob_start();
	?>
	<div class="product">
		<form class="cfw-product-form-modal variations_form variable cfw-modal-order-bump-form container"
				action="<?php echo esc_url( cfw_apply_filters( 'woocommerce_add_to_cart_form_action', $variable_product->get_permalink() ) ); ?>"
				method="post" enctype='multipart/form-data'
				data-product_id="<?php echo esc_html( absint( $variable_product->get_id() ) ); ?>"
				data-product_variations="<?php echo $variations_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<input type="hidden" name="cfw_ob_id"
					value="<?php echo esc_attr( sanitize_key( $bump->get_id() ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">

			<div class="row product">
				<?php if ( ! empty( $image ) ) : ?>
					<div
						class="col-lg-6 col-sm-6 me-auto <?php
						echo implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) );

						?>
 ">
						<div class="cfw-product-form-modal-image-wrap woocommerce-product-gallery__image">
							<?php echo wp_kses_post( $image ); ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="variations col-lg-6 col-sm-6">
					<h4 class="cfw-product-form-modal-title cfw-mb">
						<?php echo wp_kses_post( $variable_product->get_name() ); ?>
					</h4>

					<p class="cfw-product-form-modal-price">
						<?php echo wp_kses_post( $bump->get_offer_product_price() ); ?>
					</p>

					<p>
						<?php echo wp_kses_post( $bump->get_offer_description() ); ?>
					</p>

					<?php foreach ( $attributes as $attribute_name => $options ) : ?>
						<div class="cfw-mb">
							<label class="cfw-small"
									for="<?php
									echo esc_attr( sanitize_title( $attribute_name ) );

									?>
 ">
								<?php echo wp_kses_post( wc_attribute_label( $attribute_name ) ); ?>
							</label>
							<br/>
							<?php
							wc_dropdown_variation_attribute_options(
								[
									'options'   => $options,
									'attribute' => $attribute_name,
									'product'   => $variable_product,
									'selected'  => $selected_variation[ 'attribute_' . sanitize_title( $attribute_name ) ] ?? false,
								]
							);
							?>
						</div>
					<?php endforeach; ?>

					<p>
						<button type="submit" name="add-to-cart"
								value="<?php echo esc_attr( $variable_product->get_id() ); ?>"
								class="cfw-primary-btn single_add_to_cart_button button">
							<?php echo wp_kses_post( $bump->get_offer_language() ); ?>
						</button>
					</p>
					<a href="javascript:" class="cfw-bump-reject">
						<?php echo wp_kses_post( do_shortcode( $cfw_ob_offer_cancel_button_text ) ); ?>
					</a>
				</div>
			</div>
			<?php
			global $product;
			$current_product = $product;
			$product         = $variable_product;
			?>
			<div class="single_variation_wrap">
				<?php cfw_do_action( 'woocommerce_single_variation' ); ?>
			</div>
			<?php
			woocommerce_quantity_input(
				[
					'min_value'   => cfw_apply_filters( 'woocommerce_quantity_input_min', $variable_product->get_min_purchase_quantity(), $variable_product ),
					'max_value'   => cfw_apply_filters( 'woocommerce_quantity_input_max', $variable_product->get_max_purchase_quantity(), $variable_product ),
					'input_value' => $selected_qty ? wc_stock_amount( wp_unslash( $selected_qty ) ) : $variable_product->get_min_purchase_quantity(),
					'classes'     => [ 'cfw-hidden' ],
				],
				$variable_product
			);
			$product = $current_product;
			?>
			<input type="hidden" name="variation_id" class="variation_id" value="0"/>
		</form>
	</div>
	<?php

	/**
	 * Action after modal order bump variable product form.
	 *
	 * @param WC_Product $variable_product
	 * @param BumpInterface $bump
	 *
	 * @since 8.2.18
	 */
	do_action( 'cfw_after_modal_order_bump_variable_product_form', $variable_product, $bump );

	return ob_get_clean();
}

function cfw_get_order_bump_regular_product_form( WC_Product $product, BumpInterface $bump ) {
	ob_start();
	$image                           = $product->get_image();
	$cfw_ob_offer_cancel_button_text = $bump->get_offer_cancel_button_text();

	if ( empty( $cfw_ob_offer_cancel_button_text ) ) {
		$cfw_ob_offer_cancel_button_text = __( 'No thanks', 'checkout-wc' );
	}

	?>
	<form class="cfw-product-form-modal variations_form cfw-modal-order-bump-form container"
			action="<?php echo esc_url( cfw_apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
			method="post" enctype='multipart/form-data'>
		<input type="hidden" name="cfw_ob_id"
				value="<?php echo esc_attr( sanitize_key( $bump->get_id() ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">

		<div class="row">
			<?php if ( ! empty( $image ) ) : ?>
				<div class="col-lg-6 col-sm-12 me-auto">
					<div class="cfw-product-form-modal-image-wrap">
						<?php
						echo wp_kses_post( $image );

						?>
					</div>
				</div>
			<?php endif; ?>
			<div class="col cfw-product-form-modal-content">
				<h4 class="cfw-product-form-modal-title cfw-mb">
					<?php echo wp_kses_post( $product->get_name() ); ?>
				</h4>

				<p>
					<?php echo wp_kses_post( $bump->get_offer_product_price() ); ?>
				</p>

				<p>
					<?php echo wp_kses_post( $bump->get_offer_description() ); ?>
				</p>

				<p>
					<button type="submit" name="add-to-cart"
							value="<?php echo esc_attr( $product->get_id() ); ?>"
							class="cfw-primary-btn single_add_to_cart_button button">
						<?php echo wp_kses_post( $bump->get_offer_language() ); ?>
					</button>
				</p>
				<a href="javascript:" class="cfw-bump-reject">
					<?php echo wp_kses_post( do_shortcode( $cfw_ob_offer_cancel_button_text ) ); ?>
				</a>
			</div>
		</div>
		<?php
		woocommerce_quantity_input(
			[
				'min_value'   => cfw_apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
				'max_value'   => cfw_apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
				'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				// WPCS: CSRF ok, input var ok.
				'classes'     => [ 'cfw-hidden' ],
			],
			$product
		);
		?>
	</form>
	<?php

	/**
	 * Action after modal order bump regular product form.
	 *
	 * @param WC_Product $product
	 * @param BumpInterface $bump
	 *
	 * @since 8.2.18
	 */
	do_action( 'cfw_after_modal_order_bump_regular_product_form', $product, $bump );

	return ob_get_clean();
}

function cfw_templates_disabled() : bool {
	$templates_enabled = SettingsManager::instance()->get_setting( 'enable' ) === 'yes';

	return ! $templates_enabled;
}

/**
 * Clean up HTML string to remove orphaned tags and other issues
 *
 * Note: The cleaned HTML is wrapped with <div></div>
 *
 * @param string $html the HTML to clean
 * @return string|string[]|null
 */
function cfw_clean_html( $html ) {
	$html = "<div>{$html}</div>";

	$doc = new DOMDocument();

	// Use libxml_use_internal_errors to suppress warnings from malformed HTML
	libxml_use_internal_errors( true );

	// Load HTML, specifying UTF-8 encoding
	$doc->loadHTML( mb_encode_numericentity( $html, [ 0x80, 0x10FFFF, 0, ~0 ], 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();

	// Save the cleaned HTML, removing the doctype and other unwanted tags
	$cleanedHtml = $doc->saveHTML( $doc->documentElement );

	// Remove the wrapping <div></div>
	return preg_replace( '/^\s*<div>(.*?)<\/div>\s*$/s', '$1', $cleanedHtml );
}

/**
 * Safely fetch trust badges
 *
 * @param bool $apply_rules whether to apply the display conditions
 * @return array
 */
function cfw_get_trust_badges( bool $apply_rules = true ) : array {
	// Sanity check - this will avoid mistakes
	if ( is_admin() && ! wp_doing_ajax() ) {
		$apply_rules = false;
	}

	$trust_badges = cfw_get_setting( 'trust_badges', null, [] );

	$badge_template = [
		'id'                  => 0,
		'image'               => '',
		'title'               => '',
		'subtitle'            => '',
		'description'         => '',
		'template'            => 'guarantee',
		'mode'                => 'html',
		'badge_attachment_id' => '0',
		'rules'               => [],
	];

	$badges = [];

	foreach ( $trust_badges as $badge ) {
		$sanitized_badge = array_merge( $badge_template, $badge );

		if ( $sanitized_badge === $badge_template ) {
			continue;
		}

		if ( ! isset( $badge['rules'] ) || ! is_array( $badge['rules'] ) ) {
			$badge['rules'] = [];
		}

		$rules_processor = new RulesProcessor( $badge['rules'] );

		if ( $apply_rules && ! $rules_processor->evaluate() ) {
			continue;
		}

		$badges[] = $sanitized_badge;
	}

	/**
	 * Filter to add additional trust badges (like WooCommerce reviews)
	 *
	 * @since 10.2.9
	 *
	 * @param array $badges Existing trust badges
	 * @param bool $apply_rules Whether to apply rules
	 */
	$badges = apply_filters( 'cfw_trust_badges', $badges, $apply_rules );

	return $badges;
}

function cfw_get_product_information_from_orders( $orders ) : array {
	if ( empty( $orders ) && ! is_array( $orders ) ) {
		return [];
	}

	$products = [];

	foreach ( $orders as $order ) {
		$order_id    = $order->get_id();
		$order_items = $order->get_items();

		if (
			$order_id
			&& ! empty( $order_items )
			&& is_array( $order_items ) ) {
			foreach ( $order_items as $item ) {
				$products[] = [
					'id'       => $item->get_product_id(),
					'var_id'   => $item->get_variation_id(),
					'quantity' => (int) $item->get_quantity(),
					'total'    => (float) $item->get_total(),
					'cats'     => array_map(
						function( $cat ) {
							return $cat->term_id;
						},
						wc_get_product_term_ids( $item->get_product_id(), 'product_cat' )
					),
				];
			}
		}
	}

	return $products;
}
