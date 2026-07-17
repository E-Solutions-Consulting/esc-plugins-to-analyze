<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayMail\Utils\Helpers;

if ( ! isset( $args['order'] ) || ! ( Helpers::is_woocommerce_order( $args['order'] ) ) ) {
    return;
}

$order_instance   = $args['order'];
$shipping_address = apply_filters( 'yaymail_shipping_address_content', $order_instance->get_formatted_shipping_address(), $order_instance );
if ( ! wc_ship_to_billing_address_only() && $order_instance->needs_shipping_address() && ! empty( $shipping_address ) ) :
    $shipping_phone = $order_instance->get_shipping_phone();
    ?>
    <address>
            <?php echo wp_kses_post( $shipping_address ); ?>
            <?php if ( ! empty( $shipping_phone ) ) : ?>
            <br/>
            <a href='tel:<?php echo esc_attr( $shipping_phone ); ?>' style="font-family: inherit">
                <?php echo esc_html( $shipping_phone ); ?>
            </a>
        <?php endif; ?>
    </address>
        <?php
endif;
