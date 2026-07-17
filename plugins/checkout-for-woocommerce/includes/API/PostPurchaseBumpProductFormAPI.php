<?php

namespace Objectiv\Plugins\Checkout\API;

use Exception;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;
use WP_REST_Request;

class PostPurchaseBumpProductFormAPI extends ModalOrderBumpProductFormAPI {
	protected $route = 'post-purchase-order-bump-upsell-product-form';

	/**
	 * Get the bumps
	 *
	 * @param WP_REST_Request $data The request data.
	 * @throws Exception If the bump cannot be retrieved.
	 */
	public function get_product_form( WP_REST_Request $data ) {
		$bump                                  = BumpFactory::get( $data->get_param( 'bump_id' ) );
		$this->cfw_ob_offer_cancel_button_text = $bump->get_offer_cancel_button_text();

		if ( empty( $this->cfw_ob_offer_cancel_button_text ) ) {
			$this->cfw_ob_offer_cancel_button_text = __( 'No thanks', 'checkout-wc' );
		}

		$product_form_html = cfw_get_order_bump_product_form( $bump->get_id() );

		if ( is_wp_error( $product_form_html ) ) {
			return $product_form_html;
		}

		return $this->wrap_product_form( $product_form_html ?? 'Error', $bump );
	}

	protected function wrap_product_form( string $html, BumpInterface $bump ) {
		$cfw_ob_offer_heading    = $bump->get_offer_heading();
		$cfw_ob_offer_subheading = $bump->get_offer_subheading();
		$order                   = cfw_get_order_received_order();

		if ( empty( $cfw_ob_offer_heading ) ) {
			$cfw_ob_offer_heading = sprintf(
				/* translators: %s: customer first name */
				__( "Wait %s! Here's an exclusive offer to compliment your order!", 'checkout-wc' ),
				$order->get_billing_first_name()
			);
		}

		if ( empty( $cfw_ob_offer_subheading ) ) {
			$cfw_ob_offer_subheading = __( 'Add this offer to your order and save!', 'checkout-wc' );
		}

		ob_start();
		?>
		<div class="cfw-order-bump-after-checkout-wrap">
			<div class="cfw-order-bumps-stepper-wrapper">
				<div class="stepper-item completed">
					<div class="step-counter"></div>
					<div class="step-name">
						<?php esc_html_e( 'Order Submitted', 'checkout-wc' ); ?>
					</div>
				</div>
				<div class="stepper-item completed">
					<div class="step-counter"></div>
					<div class="step-name">
						<?php esc_html_e( 'Special Offer', 'checkout-wc' ); ?>
					</div>
				</div>
				<div class="stepper-item">
					<div class="step-counter"></div>
					<div class="step-name">
						<?php esc_html_e( 'Order Received', 'checkout-wc' ); ?>
					</div>
				</div>
			</div>

			<h2>
				<?php echo wp_kses_post( do_shortcode( $cfw_ob_offer_heading ) ); ?>
			</h2>

			<h3>
				<?php echo wp_kses_post( do_shortcode( $cfw_ob_offer_subheading ) ); ?>
			</h3>

			<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return rest_ensure_response(
			array(
				'html' => ob_get_clean(),
			)
		);
	}
}
