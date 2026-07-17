<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class KlarnaPayment3 extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\WC_Klarna_Payments' ) && version_compare( WC_KLARNA_PAYMENTS_VERSION, '3.0.0', '>' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Klarna Payments',
					GatewaySupport::NOT_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	public function run() {
		add_action(
			'cfw_payment_gateway_list_klarna_payments_alternate',
			array(
				$this,
				'klarna_payments_content',
			),
			10,
			1
		);
		add_filter( 'cfw_show_gateway_klarna_payments', '__return_false' );

		if ( kp_is_order_pay_page() ) {
			$key      = wc_clean( wp_unslash( $_GET['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_id = wc_get_order_id_by_order_key( $key );
			$order    = wc_get_order( $order_id );

			// Create a new session as 'woocommerce_after_calculate_totals' is only triggered on the cart (and checkout) page.
			KP_WC()->session->get_session( $order );
		}
	}

	public function klarna_payments_content( $count ) {
		// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
		do_action( 'klarna_payments_template' );
		// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment

		if ( ! KP_WC()->session ) {
			return;
		}

		$payment_categories = KP_WC()->session->get_klarna_payment_method_categories();

		if ( is_array( $payment_categories ) ) {
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			$current_gateway    = WC()->session->get( 'chosen_payment_method' );

			// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
			foreach ( apply_filters( 'wc_klarna_payments_available_payment_categories', $payment_categories ) as $payment_category ) {
				if ( ! is_array( $payment_category ) ) {
					$payment_category = json_decode( wp_json_encode( $payment_category ), true );
				}
				$payment_category_id   = 'klarna_payments_' . $payment_category['identifier'];
				$payment_category_name = $payment_category['name'];
				$payment_category_icon = $payment_category['assets_urls']['standard'] ?? null;
				$kp                    = $available_gateways['klarna_payments'] ?? null;

				if ( ! $kp ) {
					continue;
				}

				$kp->id    = $payment_category_id;
				$kp->title = $payment_category_name;
				$kp->icon  = $payment_category_icon;
				?>
				<li class="wc_payment_method payment_method_<?php echo esc_attr( $kp->id ); ?> cfw-radio-reveal-li">
					<div class="payment_method_title_wrap cfw-radio-reveal-title-wrap">
						<input id="payment_method_<?php echo esc_attr( $kp->id ); ?>" type="radio" class="input-radio"
								name="payment_method"
								value="<?php echo esc_attr( $kp->id ); ?>" <?php echo ( ( empty( $current_gateway ) && 0 === $count ) || stripos( $current_gateway, 'klarna_payments' ) !== false ) ? 'checked' : ''; ?>
								data-order_button_text="<?php echo esc_attr( $kp->order_button_text ); ?>"/>

						<label class="payment_method_label cfw-radio-reveal-label"
								for="payment_method_<?php echo esc_attr( $kp->id ); ?>">
							<div>
								<span
									class="payment_method_title cfw-radio-reveal-title"><?php echo esc_attr( $kp->get_title() ); ?></span>

								<span class="payment_method_icons">
									<?php
									// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
									echo $kp->get_icon();
									// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</span>
							</div>
						</label>
					</div>
					<?php
					/**
					 * Filters whether to show custom klarna payment box HTML
					 *
					 * @param bool $show Whether to show custom payment box HTML
					 *
					 * @since 2.0.0
					 */
					if ( apply_filters( "cfw_payment_gateway_{$kp->id}_content", $kp->has_fields() || $kp->get_description() ) ) :
						?>
						<div class="payment_box payment_method_<?php echo esc_attr( $kp->id ); ?> cfw-radio-reveal-content">
							<?php
							ob_start();
							$kp->payment_fields();

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

							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
							/**
							 * Filters klarna payment gateway output
							 *
							 * @param string $output The gateway output
							 *
							 * @since 2.0.0
							 */
							echo apply_filters( "cfw_payment_gateway_field_html_{$kp->id}", $field_html );
							// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					<?php endif; ?>
				</li>
				<?php
			}
			// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
		}
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'KlarnaPayments',
			'params' => array(),
		);

		return $compatibility;
	}
}
