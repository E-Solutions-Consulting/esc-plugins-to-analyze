<?php
/**
 * Order Bump Product Form Block Render
 *
 * @version 9.0.0
 * @package Block/OrderBumpProductForm
 */

global $post;

if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
	wc_load_cart();
}

$output = cfw_get_order_bump_product_form( $post->ID );

if ( ! is_wp_error( $output ) ) {
	echo '<div class="cfw-order-bump-offer-form-wrap cfw-grid">';
	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
} else {
	/* translators: Error message shown when order bump offer form fails to load */
	echo esc_html__( 'Could not load offer form.', 'checkout-wc' );
}
