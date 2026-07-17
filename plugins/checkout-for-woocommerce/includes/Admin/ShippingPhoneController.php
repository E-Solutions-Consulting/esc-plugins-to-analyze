<?php

namespace Objectiv\Plugins\Checkout\Admin;

use WC_Data_Exception;

class ShippingPhoneController {
	public function __construct() {}

	public function init() {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'shipping_phone_display_admin_order_meta' ), 10, 1 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_shipping_phone' ) );
	}

	public function shipping_phone_display_admin_order_meta( $order ) {
		if ( version_compare( WC()->version, '5.6.0', '<' ) ) {
			$shipping_phone = get_post_meta( $order->get_id(), '_shipping_phone', true );

			if ( empty( $shipping_phone ) ) {
				return;
			}

			/**
			 * Filter whether to enable editable shipping phone field in admin
			 *
			 * @since 3.0.0
			 *
			 * @param bool $enable_editable_admin_phone_field True show editable field, false show label
			 */
			if ( apply_filters( 'cfw_enable_editable_admin_shipping_phone_field', true ) ) {
				$field                = array();
				$field['placeholder'] = __( 'Phone', 'woocommerce' );
				$field['label']       = __( 'Phone', 'woocommerce' );
				$field['value']       = $shipping_phone;
				$field['name']        = '_cfw_shipping_phone';
				$field['id']          = 'cfw_shipping_phone';

				woocommerce_wp_text_input( $field );
			} else {
				?>
				<p>
					<strong>
						<?php esc_html_e( 'Phone' ); ?>
					</strong>
					<br />
					<a href="tel:<?php echo esc_attr( $shipping_phone ); ?>">
						<?php echo esc_html( $shipping_phone ); ?>
					</a>
				</p>
				<?php
			}
		}
	}

	/**
	 * Save shipping phone
	 *
	 * @param int $order_id The order ID.
	 *
	 * @throws WC_Data_Exception If the order cannot be updated.
	 */
	public function save_shipping_phone( int $order_id ) {
		if ( isset( $_POST['_cfw_shipping_phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order = wc_get_order( $order_id );

			if ( version_compare( WC()->version, '5.6.0', '<' ) ) {
				$order->update_meta_data( '_shipping_phone', sanitize_text_field( wp_unslash( $_POST['_cfw_shipping_phone'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$order->set_shipping_phone( sanitize_text_field( wp_unslash( $_POST['_cfw_shipping_phone'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			$order->save();
		}
	}
}
