<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$shipping_content_format = [
    'bold'      => isset( $data['shipping_content_text_format']['bold'] ) ? filter_var( $data['shipping_content_text_format']['bold'], FILTER_VALIDATE_BOOLEAN ) : false,
    'italic'    => isset( $data['shipping_content_text_format']['italic'] ) ? filter_var( $data['shipping_content_text_format']['italic'], FILTER_VALIDATE_BOOLEAN ) : true,
    'underline' => isset( $data['shipping_content_text_format']['underline'] ) ? filter_var( $data['shipping_content_text_format']['underline'], FILTER_VALIDATE_BOOLEAN ) : false,
];

$shipping_content_alignment = isset( $data['shipping_content_alignment'] ) ? $data['shipping_content_alignment'] : 'left';

$shipping_address_html = wp_kses_post( do_shortcode( isset( $data['rich_text'] ) ? $data['rich_text'] : '[yaymail_shipping_address]' ) );

if ( empty( $shipping_address_html ) ) :
    return '';
endif;

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$shipping_border_style = TemplateHelpers::get_style(
    [
        'border' => 'solid 1px ' . $data['border_color'],
    ]
);

$shipping_wrapper_style = TemplateHelpers::get_style(
    [
        'color'       => isset( $data['text_color'] ) ? $data['text_color'] : 'inherit',
        'padding'     => '12px',
        'text-align'  => yaymail_get_text_align(),
        'font-size'   => TemplateHelpers::get_dimension_value( $data['shipping_content_font_size'] ?? 14 ),
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'border'      => 'solid 1px ' . $data['border_color'],
        'text-align'  => $shipping_content_alignment,
    ]
);

$title_style = TemplateHelpers::get_style(
    [
        'text-align'  => yaymail_get_text_align(),
        'color'       => isset( $data['title_color'] ) ? $data['title_color'] : 'inherit',
        'margin'      => '0 0 7px 0',
        'font-size'   => '20px',
        'font-weight' => '600',
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
    ]
);

$is_layout_type_modern = isset( $data['layout_type'] ) && 'modern' === $data['layout_type'];

ob_start();
?>
<style>
    /* Modern layout */
    <?php if ( $is_layout_type_modern ) { ?>
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-shipping-address-wrap {
        border: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
        <?php
    }//end if
    ?>
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-shipping-address-wrap address {
        font-weight: <?php echo esc_attr( $shipping_content_format['bold'] ?? false ? 'bold' : 'normal' ); ?> !important;
        font-style: <?php echo esc_attr( $shipping_content_format['italic'] ?? false ? 'italic' : 'normal' ); ?> !important;
        text-decoration: <?php echo esc_attr( $shipping_content_format['underline'] ?? false ? 'underline' : 'none' ); ?> !important;
    }
</style>
<div class="yaymail-shipping-title" style="<?php echo esc_attr( $title_style ); ?>" > <?php echo wp_kses_post( do_shortcode( $data['title'] ) ); ?> </div>
<div class="yaymail-shipping-address-wrap" style="<?php echo esc_attr( $shipping_wrapper_style ); ?>">
    <?php echo wp_kses_post( do_shortcode( isset( $data['rich_text'] ) ? $data['rich_text'] : '[yaymail_shipping_address]' ) ); ?>
</div>


           
<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );

