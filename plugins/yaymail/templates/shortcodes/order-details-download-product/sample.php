<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayMail\Utils\TemplateHelpers;

$template        = ! empty( $args['template'] ) ? $args['template'] : null;
$text_link_color = ! empty( $template ) ? $template->get_text_link_color() : YAYMAIL_COLOR_WC_DEFAULT;
$data            = isset( $args['element']['data'] ) ? $args['element']['data'] : [];
$is_placeholder  = isset( $args['is_placeholder'] ) ? $args['is_placeholder'] : false;

$settings           = yaymail_settings();
$direction          = yaymail_get_email_direction();
$show_product_image = isset( $settings['show_product_image'] ) ? boolval( $settings['show_product_image'] ) : false;


$product_title  = isset( $data['table_titles']['product_title'] ) ? $data['table_titles']['product_title'] : TemplateHelpers::get_content_as_placeholder( 'product_title', esc_html__( 'Products', 'woocommerce' ), $is_placeholder );
$expires_title  = isset( $data['table_titles']['expires_title'] ) ? $data['table_titles']['expires_title'] : TemplateHelpers::get_content_as_placeholder( 'expires_title', esc_html__( 'Expires', 'woocommerce' ), $is_placeholder );
$download_title = isset( $data['table_titles']['download_title'] ) ? $data['table_titles']['download_title'] : TemplateHelpers::get_content_as_placeholder( 'download_title', esc_html__( 'Download', 'woocommerce' ), $is_placeholder );

$table_heading_font_size = isset( $data['table_heading_font_size'] ) ? $data['table_heading_font_size'] : 14;
$table_content_font_size = isset( $data['table_content_font_size'] ) ? $data['table_content_font_size'] : 14;

$table_td_style = TemplateHelpers::get_style(
    [
        'padding'     => '12px',
        'text-align'  => yaymail_get_text_align(),
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'color'       => isset( $data['text_color'] ) ? $data['text_color'] : 'inherit',
        'border'      => isset( $data['border_color'] ) ? '1px solid ' . $data['border_color'] : 'inherit',
    ]
);

$table_link_style = TemplateHelpers::get_style(
    [
        'color'       => $text_link_color,
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
    ]
);
?>

<tbody style="<?php echo esc_attr( $table_td_style ); ?>">
    <tr style="<?php echo esc_attr( $table_td_style ); ?>">
        <th class="td yaymail-order-details-download-title--product" colspan="1" scope="col" style="<?php echo esc_attr( $table_td_style ); ?> font-size: <?php echo esc_attr( $table_heading_font_size ); ?>px;"><?php yaymail_kses_post_e( $product_title ); ?></th>
        <th class="td yaymail-order-details-download-title--expires" colspan="1" scope="col" style="<?php echo esc_attr( $table_td_style ); ?> font-size: <?php echo esc_attr( $table_heading_font_size ); ?>px;"><?php yaymail_kses_post_e( $expires_title ); ?></th>
        <th class="td yaymail-order-details-download-title--download" colspan="1" scope="col" style="<?php echo esc_attr( $table_td_style ); ?> font-size: <?php echo esc_attr( $table_heading_font_size ); ?>px;"><?php yaymail_kses_post_e( $download_title ); ?></th>
    </tr>
    <tr style="<?php echo esc_attr( $table_td_style ); ?>">
        <td class="td yaymail-order-details-download-content--product" colspan="1" scope="col" style="<?php echo esc_attr( $table_td_style ); ?> font-size: <?php echo esc_attr( $table_content_font_size ); ?>px;">
        <?php
        if ( $show_product_image ) :
            $image_url      = wc_placeholder_img_src();
            $image_width    = isset( $settings['product_image_width'] ) ? $settings['product_image_width'] : '30';
            $image_height   = isset( $settings['product_image_height'] ) ? $settings['product_image_height'] : '30';
            $image_position = isset( $settings['product_image_position'] ) ? $settings['product_image_position'] : 'top';

            $image_style =
                [
                    'margin-bottom' => '5px',
                    'margin-right'  => '5px',
                ];
            if ( $image_position === 'left' && ! $is_placeholder ) {
                if ( 'ltr' === $direction ) {
                    $image_style['float'] = 'left';
                } else {
                    $image_style['float']        = 'right';
                    $image_style['margin-right'] = '0';
                    $image_style['margin-left']  = '5px';
                }
            }
            $image_style = TemplateHelpers::get_style( $image_style );
            $image       = $is_placeholder ? "<img width='{{product_image_width}}px' height='{{product_image_height}}px' src='{$image_url}' alt='product image' style='{$image_style}'/>" : "<img width='{$image_width}px' height='{$image_height}px' src='{$image_url}' alt='product image' style='{$image_style}'/>";

            ?>
            <div class="yaymail-product-download-image">
                <a href="" style="<?php echo esc_attr( $table_link_style ); ?>">
                    <?php
                    if ( $is_placeholder || ( $image_position === 'top' || $image_position === 'left' ) ) {
                        echo wp_kses_post( "<span class='yaymail-product_image_position__top'>" );
                        require YAYMAIL_PLUGIN_PATH . 'templates/shortcodes/order-details/order-items/image-content.php';
                        echo ( '</span>' );
                    }
                    ?>
                    <span><?php esc_html_e( 'Downloadable Product', 'yaymail' ); ?></span>
                    <?php
                    if ( $is_placeholder || ( $image_position === 'bottom' ) ) {
                        echo wp_kses_post( "<span class='yaymail-product_image_position__bottom'>" );
                        require YAYMAIL_PLUGIN_PATH . 'templates/shortcodes/order-details/order-items/image-content.php';
                        echo ( '</span>' );
                    }
                    ?>
                </a>
            </div>
            <?php else : ?>
            <a href="" style="<?php echo esc_attr( $table_link_style ); ?>">
                <?php esc_html_e( 'Downloadable Product', 'yaymail' ); ?>
            </a>
            <?php endif; ?>
        </td>
        <td class="td yaymail-order-details-download-content--expires" colspan="1" scope="col" style="<?php echo esc_attr( $table_td_style ); ?> font-size: <?php echo esc_attr( $table_content_font_size ); ?>px;">
            <time datetime="2021-02-13" title="1613174400"> <?php echo wp_kses_post( wc_format_datetime( new WC_DateTime() ) ); ?></time>
        </td>
        <td class="td yaymail-order-details-download-content--download" colspan="1" scope="col" style="<?php echo esc_attr( $table_td_style ); ?> font-size: <?php echo esc_attr( $table_content_font_size ); ?>px;">
            <a href="" style="<?php echo esc_attr( $table_link_style ); ?>" ><?php esc_html_e( 'Download.doc', 'yaymail' ); ?></a>
        </td>
    </tr>
</tbody>

