<?php

namespace Objectiv\Plugins\Checkout\API;

use WC_Product;
use WC_Product_Variable;
use WP_REST_Request;

class GetVariationFormAPI {
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'checkoutwc/v1',
					'get-variation-form/(?P<product_id>\d{1,12})',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_form' ),
						'permission_callback' => function () {
							return true;
						},
					)
				);
			}
		);
	}

	public function get_form( WP_REST_Request $data ) {
		$product = wc_get_product( $data->get_param( 'product_id' ) );

		if ( ! is_a( $product, '\WC_Product_Variable' ) ) {
			return new \WP_Error( 'product_not_found', __( 'Product not found', 'checkout-wc' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'html' => $this->get_variable_product_form( $product ),
			)
		);
	}

	protected function get_variable_product_form( WC_Product_Variable $variable_product ) {
		$selected_variation = array();
		$cart_item          = array();

		if ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$cart_item          = WC()->cart->get_cart_item( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $cart_item ) && isset( $cart_item['variation_id'] ) ) {
				$selected_variation = $cart_item['variation'];
				$selected_variation_product = wc_get_product( $cart_item['variation_id'] );
			}
		}

		$selected_qty         = (float) ( $cart_item['quantity'] ?? 1 );
		$available_variations = $variable_product->get_available_variations();

		/**
		 * Filter available variations for variable products in the variation form API.
		 *
		 * This filter allows compatibility modules and other code to modify the available variations
		 * before they are used in the variation form.
		 *
		 * @since x.x.x
		 * @param array $available_variations Array of available variations.
		 * @param WC_Product_Variable $variable_product The variable product object.
		 * @param array $cart_item The cart item being edited, if any. Empty array if not editing.
		 */
		$available_variations = cfw_apply_filters(
			'cfw_available_variations',
			$available_variations,
			$variable_product,
			$cart_item
		);

		$variations_json      = wp_json_encode( $available_variations );
		$variations_attr      = function_exists( 'wc_esc_json' ) ? wc_esc_json( $variations_json ) : _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );
		$attributes           = $variable_product->get_variation_attributes();
		$image                = $variable_product->get_image( 'woocommerce_thumbnail', array( 'class' => 'wp-post-image' ) );

		// Set global $product so filters (e.g. woocommerce_single_product_image_gallery_classes) that expect it do not fatal.
		global $product;
		$product = $variable_product;

		$wrapper_classes      = cfw_apply_filters(
			'woocommerce_single_product_image_gallery_classes',
			array(
				'woocommerce-product-gallery',
				'woocommerce-product-gallery--' . ( $image ? 'with-images' : 'without-images' ),
				'images',
			)
		);

		ob_start();
		?>
		<div class="product">
			<form class="cfw-product-form-modal variations_form variable container" action="<?php echo esc_attr( cfw_apply_filters( 'woocommerce_add_to_cart_form_action', $variable_product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data' data-product_id="<?php echo esc_attr( absint( $variable_product->get_id() ) ); ?>" data-product_variations="<?php echo esc_attr( $variations_attr ); ?>">
				<div class="row">
					<?php if ( ! empty( $image ) ) : ?>
						<div class="col-lg-6 col-sm-6 me-auto <?php echo implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ); ?>">
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
							<?php
								if ( $selected_variation_product && is_a( $selected_variation_product, '\WC_Product_Variation' ) ) {
									echo wp_kses_post( $selected_variation_product->get_price_html() );
								} else {
									echo wp_kses_post( $variable_product->get_price_html() );
								}
							?>
						</p>

						<?php foreach ( $attributes as $attribute_name => $options ) : ?>
							<div class="row cfw-mb">
								<div class="col cfw-product-form-modal-content">
									<label class="cfw-small" for="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
										<?php echo wp_kses_post( wc_attribute_label( $attribute_name ) ); ?>
									</label>
									<br />
									<?php
									wc_dropdown_variation_attribute_options(
										array(
											'options'   => $options,
											'attribute' => $attribute_name,
											'product'   => $variable_product,
											'selected'  => $selected_variation[ 'attribute_' . sanitize_title( $attribute_name ) ] ?? false,
										)
									);
									?>
								</div>
							</div>
						<?php endforeach; ?>

						<div class="single_variation_wrap">
							<?php woocommerce_single_variation(); ?>
						</div>

						<?php if ( ! isset( $_GET['key'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<p>
							<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $variable_product->get_id() ); ?>" class="cfw-primary-btn single_add_to_cart_button button">
								<?php _e( 'Add to cart', 'woocommerce' ); ?>
							</button>
						</p>
						<?php else : ?>
							<input type="hidden" name="key" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
						<?php endif; ?>
					</div>
				</div>
				<?php
				woocommerce_quantity_input(
					array(
						'min_value'   => cfw_apply_filters( 'woocommerce_quantity_input_min', $variable_product->get_min_purchase_quantity(), $variable_product ),
						'max_value'   => cfw_apply_filters( 'woocommerce_quantity_input_max', $variable_product->get_max_purchase_quantity(), $variable_product ),
						'input_value' => $selected_qty ? wc_stock_amount( wp_unslash( $selected_qty ) ) : $variable_product->get_min_purchase_quantity(),
						'classes'     => array( 'cfw-hidden' ),
					),
					$variable_product
				);
				?>
				<input type="hidden" name="variation_id" class="variation_id" value="0" />
				<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $variable_product->get_id() ); ?>" />
			</form>
		</div>
		<?php

		/**
		 * Action after modal order bump variable product form.
		 *
		 * @since 8.2.18
		 * @param WC_Product $variable_product
		 */
		do_action( 'cfw_after_modal_variable_product_form', $variable_product );

		return ob_get_clean();
	}
}
