<?php

namespace Objectiv\Plugins\Checkout\API;

use Objectiv\Plugins\Checkout\Model\Bumps\PreviewBump;
use WP_REST_Request;

/**
 * API endpoint for rendering order bump modal preview in admin editor.
 *
 * Uses the same rendering functions as production by creating a PreviewBump
 * instance with editor values.
 */
class OrderBumpPreviewAPI {
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'checkoutwc/v1',
					'order-bump-modal-preview',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'get_preview' ),
						'permission_callback' => function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		);
	}

	/**
	 * Render the order bump modal preview
	 *
	 * @param WP_REST_Request $request The request data.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_preview( WP_REST_Request $request ) {
		$display_location         = sanitize_text_field( $request->get_param( 'display_location' ) );
		$offer_product_id         = absint( $request->get_param( 'offer_product_id' ) );
		$offer_heading            = sanitize_text_field( $request->get_param( 'offer_heading' ) );
		$offer_subheading         = sanitize_text_field( $request->get_param( 'offer_subheading' ) );
		$offer_description        = wp_kses_post( $request->get_param( 'offer_description' ) );
		$offer_language           = sanitize_text_field( $request->get_param( 'offer_language' ) );
		$offer_cancel_button_text = sanitize_text_field( $request->get_param( 'offer_cancel_button_text' ) );

		// Validate display location
		if ( ! in_array( $display_location, array( 'complete_order', 'post_purchase_one_click' ), true ) ) {
			return new \WP_Error( 'invalid_location', __( 'Invalid display location', 'checkout-wc' ), array( 'status' => 400 ) );
		}

		// Get product
		$product = wc_get_product( $offer_product_id );
		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', __( 'Product not found', 'checkout-wc' ), array( 'status' => 404 ) );
		}

		// Create PreviewBump with editor values
		$preview_bump = new PreviewBump(
			array(
				'display_location'         => $display_location,
				'offer_heading'            => $offer_heading,
				'offer_subheading'         => $offer_subheading,
				'offer_description'        => $offer_description,
				'offer_language'           => $offer_language,
				'offer_cancel_button_text' => $offer_cancel_button_text,
			),
			$product
		);

		// Use the same rendering functions as production
		if ( $product->is_type( 'variable' ) && 0 === $product->get_parent_id() ) {
			$product_form_html = cfw_get_order_bump_variable_product_form( $product, $preview_bump );
		} else {
			$product_form_html = cfw_get_order_bump_regular_product_form( $product, $preview_bump );
		}

		// Wrap with heading/subheading/stepper
		$html = $this->wrap_product_form( $product_form_html, $preview_bump );

		return rest_ensure_response(
			array(
				'html' => $html,
			)
		);
	}

	/**
	 * Wrap the product form with heading, subheading, and optional stepper.
	 *
	 * @param string      $html The product form HTML.
	 * @param PreviewBump $bump The preview bump instance.
	 * @return string
	 */
	private function wrap_product_form( string $html, PreviewBump $bump ): string {
		$display_location = $bump->get_display_location();
		$offer_heading    = $bump->get_offer_heading();
		$offer_subheading = $bump->get_offer_subheading();

		// Set defaults based on display location
		if ( empty( $offer_heading ) ) {
			if ( 'complete_order' === $display_location ) {
				$offer_heading = __( 'Your order is almost complete...', 'checkout-wc' );
			} else {
				$offer_heading = __( "Wait %s! Here's an exclusive offer to compliment your order!", 'checkout-wc' );
			}
		}

		if ( empty( $offer_subheading ) ) {
			$offer_subheading = __( 'Add this offer to your order and save!', 'checkout-wc' );
		}

		ob_start();
		?>
		<div class="cfw-order-bump-after-checkout-wrap">
			<?php if ( 'complete_order' === $display_location ) : ?>
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
			<?php endif; ?>

			<h2>
				<?php echo wp_kses_post( do_shortcode( $offer_heading ) ); ?>
			</h2>

			<h3>
				<?php echo wp_kses_post( do_shortcode( $offer_subheading ) ); ?>
			</h3>

			<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
