<?php

defined( 'ABSPATH' ) || exit;

if ( empty( $order_data ) ) {
    return;
}
// Check if the order currency is different from the global WooCommerce currency
if ( $order_data->get_currency() !== get_option( 'woocommerce_currency' ) ) {
    foreach ( $item->get_meta_data() as $product_meta ) {
        // Check if the meta key is '_wcpdf_regular_price'
        // TODO: compatible with plugin => split filter
        if ( '_wcpdf_regular_price' !== $product_meta->key ) {
            continue;
        }
        // Get the regular price based on the cart display settings
        $product_regular_price = $product_meta->value[ get_option( 'woocommerce_tax_display_cart', 'excl' ) ];
        break;
    }
}

// Format the price based on the order's currency
$price = wc_price( $product_regular_price * $item->get_quantity(), [ 'currency' => $order_data->get_currency() ] );

if ( ! empty( $price ) && $price !== $order_data->get_formatted_line_subtotal( $item ) ) {
    ?>
        <del class="yaymail-product-regular-price" style="padding-right:5px"> <?php echo wp_kses_post( $price ); ?> </del>
    <?php
}

