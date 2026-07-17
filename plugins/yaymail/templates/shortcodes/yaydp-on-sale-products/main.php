<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayMail\Utils\TemplateHelpers;

$products = isset( $args['products'] ) ? $args['products'] : '';
if ( empty( $products ) ) {
    return;
}

$color            = isset( $args['color'] ) ? $args['color'] : '#000000';
$background_color = isset( $args['background_color'] ) ? $args['background_color'] : '#ffffff';

$container_style = TemplateHelpers::get_style(
    [
        'width' => '100%',
        // 'background-color' => $background_color,
    ]
);

$products_wrapper_style = TemplateHelpers::get_style(
    [
        'width' => '100%',
    ]
);

$product_item_style = TemplateHelpers::get_style(
    [
        'width'      => 'calc(100%/3 - 30px)',
        'height'     => '100%',
        'display'    => 'inline-block',
        'text-align' => 'center',
        'padding'    => '10px',
    ]
);

$product_name_style = TemplateHelpers::get_style(
    [
        'margin'      => '0px',
        'margin-top'  => '5px',
        'font-weight' => 'bold',
        'color'       => $args['product_name_color'],
    ]
);

$prices_container_style = TemplateHelpers::get_style(
    [
        'margin-bottom' => '15px',
    ]
);

$sale_price_style = TemplateHelpers::get_style(
    [
        'color'       => $args['sale_price_color'],
        'font-weight' => 'bold',
    ]
);

$regular_price_style = TemplateHelpers::get_style(
    [
        'color'           => $args['regular_price_color'],
        'font-weight'     => 'bold',
        'text-decoration' => 'line-through',
    ]
);

$button_buy_now_style = TemplateHelpers::get_style(
    [
        'background-color' => $args['button_background_color'],
        'color'            => $args['button_text_color'],
        'font-size'        => '13px',
        'font-weight'      => 'normal',
        'line-height'      => '21px',
        'margin'           => '0',
        'padding'          => '10px 15px',
        'text-align'       => 'center',
        'text-decoration'  => 'none',
    ]
);

if ( ! function_exists( 'change_image_dimensions' ) ) {
    function change_image_dimensions( $html, $new_width, $new_height ) {
        $html = preg_replace( '/width="(\d+)"/', 'width="' . $new_width . '"', $html );
        $html = preg_replace( '/height="(\d+)"/', 'height="' . $new_height . '"', $html );

        return $html;
    }
}
?>

<table style="<?php echo esc_attr( $container_style ); ?>">
    <tbody>
        <tr style="vertical-align: baseline; text-align: center;">
            <?php
            foreach ( $products as $product ) {
                if ( $product instanceof WC_Product ) {
                    $product_image   = change_image_dimensions( $product->get_image(), '100%', '100%' );
                    $product_name    = $product->get_name();
                    $regular_price   = ! empty( $product->get_regular_price() ) ? $product->get_regular_price() : $product->get_price();
                    $sale_price      = $product->get_sale_price();
                    $currency_symbol = get_woocommerce_currency_symbol();
                    $product_link    = $product->get_permalink();
                    ?>

                    <!-- Product Display Template -->
                    <td class="yaymail-yaydp-on-sale-products" style="<?php echo esc_attr( $product_item_style ); ?>">
                        <!-- Product Image -->
                        <a href="<?php echo esc_url( $product_link ); ?>" target="_blank">
                            <?php echo wp_kses_post( $product_image ); ?>
                        </a>
                        <!-- End Product Image Section -->

                        <!-- Product Name -->
                        <div style="<?php echo esc_attr( $product_name_style ); ?>">
                            <?php echo wp_kses_post( $product_name ); ?>
                        </div>
                        <!-- End Product Name Section -->

                        <!-- Prices -->
                        <div style="<?php echo esc_attr( $prices_container_style ); ?>" class="yaymail-yaydp-on-sale-products__item-price">
                            <?php if ( ! empty( $sale_price ) ) : ?>
                                <span style="<?php echo esc_attr( $sale_price_style ); ?>" class="yaymail-item-sale-price">
                                            <?php echo wp_kses_post( $currency_symbol ); ?><?php echo wp_kses_post( $sale_price ); ?> -
                                </span>
                            <?php endif; ?>

                            <?php if ( ! empty( $regular_price ) ) : ?>
                                <span style="<?php echo esc_attr( empty( $sale_price ) ? $sale_price_style : $regular_price_style ); ?>" class="yaymail-item-regular-price">
                                    <?php echo wp_kses_post( $currency_symbol ); ?><?php echo wp_kses_post( $regular_price ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <!-- End Prices Section -->

                        <!-- 'Buy Now' Button -->
                        <a style="<?php echo esc_attr( $button_buy_now_style ); ?>" href="<?php echo esc_url( $product_link ); ?>" target="_blank">
                            <?php echo esc_html__( 'BUY NOW', 'yaymail' ); ?>
                        </a>
                        <!-- End 'Buy Now' Button Section -->

                    </td>
                    <!-- End Product Display Template -->

                    <?php
                }
            }
            ?>
        </tr>
    </tbody>
</table>
