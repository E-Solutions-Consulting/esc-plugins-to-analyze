<?php
/**
 * Admin View: Offer Refund Metabox
 *
 * Displays one-click offer items with refund buttons.
 *
 * @var \WC_Order $order Order object (set in OfferRefundManager::render_metabox)
 *
 * @package Objectiv\Plugins\Checkout\Admin\Views
 * @since 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="cfw-offer-refund-metabox">
	<table class="cfw-offer-refund-table">
		<thead>
			<tr>
				<th class="cfw-offer-thumb"><?php esc_html_e( 'Product', 'checkout-wc' ); ?></th>
				<th class="cfw-offer-name"><?php esc_html_e( 'Name', 'checkout-wc' ); ?></th>
				<th class="cfw-offer-qty"><?php esc_html_e( 'Qty', 'checkout-wc' ); ?></th>
				<th class="cfw-offer-total"><?php esc_html_e( 'Total', 'checkout-wc' ); ?></th>
				<th class="cfw-offer-action"><?php esc_html_e( 'Action', 'checkout-wc' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$has_offers = false;

			foreach ( $order->get_items() as $item_id => $item ) {
				// Check if this is a one-click offer
				$is_offer = wc_get_order_item_meta( $item_id, '_cfw_one_click_upsell', true );

				if ( 'yes' !== $is_offer ) {
					continue;
				}

				$has_offers  = true;
				$is_refunded = wc_get_order_item_meta( $item_id, '_cfw_refunded', true );
				$bump_id     = wc_get_order_item_meta( $item_id, '_cfw_order_bump_id', true );
				$product     = $item->get_product();
				$item_total  = $item->get_total() + $item->get_total_tax();
				?>
				<tr data-item-id="<?php echo esc_attr( $item_id ); ?>">
					<td class="cfw-offer-thumb">
						<?php
						if ( $product ) {
							echo wp_kses_post( $product->get_image( array( 50, 50 ) ) );
						}
						?>
					</td>
					<td class="cfw-offer-name">
						<strong><?php echo esc_html( $item->get_name() ); ?></strong>
						<?php
						// Display variation attributes
						if ( $product && $product->is_type( 'variation' ) ) {
							echo '<div class="cfw-offer-variation">';
							foreach ( $item->get_meta_data() as $meta ) {
								if ( taxonomy_exists( $meta->key ) ) {
									$term = get_term_by( 'slug', $meta->value, $meta->key );
									echo '<div>' . esc_html( wc_attribute_label( $meta->key ) ) . ': ' . esc_html( $term ? $term->name : $meta->value ) . '</div>';
								}
							}
							echo '</div>';
						}
						?>
					</td>
					<td class="cfw-offer-qty">
						<?php echo esc_html( $item->get_quantity() ); ?>
					</td>
					<td class="cfw-offer-total">
						<?php echo wp_kses_post( wc_price( $item_total, array( 'currency' => $order->get_currency() ) ) ); ?>
					</td>
					<td class="cfw-offer-action">
						<?php if ( 'yes' === $is_refunded ) : ?>
							<span class="cfw-offer-refunded"><?php esc_html_e( 'Refunded', 'checkout-wc' ); ?></span>
						<?php else : ?>
							<button
								type="button"
								class="button cfw-refund-offer-button"
								data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
								data-bump-id="<?php echo esc_attr( $bump_id ); ?>"
								data-item-id="<?php echo esc_attr( $item_id ); ?>"
								data-product-name="<?php echo esc_attr( $item->get_name() ); ?>"
							>
								<?php esc_html_e( 'Refund', 'checkout-wc' ); ?>
							</button>
						<?php endif; ?>
					</td>
				</tr>
				<?php
			}

			if ( ! $has_offers ) :
				?>
				<tr>
					<td colspan="5" class="cfw-no-offers">
						<?php esc_html_e( 'No order bump payments found for this order.', 'checkout-wc' ); ?>
					</td>
				</tr>
				<?php
			endif;
			?>
		</tbody>
	</table>

	<?php if ( $has_offers ) : ?>
		<div class="cfw-offer-refund-note">
			<p>
				<strong><?php esc_html_e( 'Note:', 'checkout-wc' ); ?></strong>
				<?php esc_html_e( 'Refunding an offer will process the refund with the payment gateway and create a WooCommerce refund record.', 'checkout-wc' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>
