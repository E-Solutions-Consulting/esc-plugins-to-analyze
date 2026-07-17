<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Utils\TemplateHelpers;
use YayMail\Utils\Helpers;

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

$billing_content_format = [
    'bold'      => isset( $data['billing_content_text_format']['bold'] ) ? filter_var( $data['billing_content_text_format']['bold'], FILTER_VALIDATE_BOOLEAN ) : false,
    'italic'    => isset( $data['billing_content_text_format']['italic'] ) ? filter_var( $data['billing_content_text_format']['italic'], FILTER_VALIDATE_BOOLEAN ) : true,
    'underline' => isset( $data['billing_content_text_format']['underline'] ) ? filter_var( $data['billing_content_text_format']['underline'], FILTER_VALIDATE_BOOLEAN ) : false,
];

$shipping_content_alignment = isset( $data['shipping_content_alignment'] ) ? $data['shipping_content_alignment'] : 'left';
$billing_content_alignment  = isset( $data['billing_content_alignment'] ) ? $data['billing_content_alignment']  : 'left';

$billing_address_html  = wp_kses_post( do_shortcode( '[yaymail_billing_address]' ) );
$shipping_address_html = wp_kses_post( do_shortcode( '[yaymail_shipping_address]' ) );
$width                 = ! empty( $billing_address_html ) & ! empty( $shipping_address_html ) ? '50%' : '100%';

if ( empty( $billing_address_html ) && empty( $shipping_address_html ) ) :
    return '';
endif;

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$table_style = TemplateHelpers::get_style(
    [
        'width'           => '100%',
        'text-align'      => yaymail_get_text_align(),
        'border-collapse' => 'separate',
        'border-spacing'  => '5px',
    ]
);

$column_style = TemplateHelpers::get_style(
    [
        'color'       => isset( $data['text_color'] ) ? $data['text_color'] : 'inherit',
        'padding'     => '12px',
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'border'      => 'solid 1px ' . $data['border_color'],
    ]
);

$title_style = TemplateHelpers::get_style(
    [
        'text-align'    => yaymail_get_text_align(),
        'color'         => isset( $data['title_color'] ) ? $data['title_color'] : 'inherit',
        'margin-top'    => '0',
        'font-family'   => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'margin-bottom' => '7px',
    ]
);

$is_layout_type_modern   = isset( $data['layout_type'] ) && 'modern' === $data['layout_type'];
$is_responsive_on_mobile = Helpers::is_true( $data['responsive_on_mobile'] ?? false );

ob_start();
?>
<style>
    /* Modern layout */
    <?php if ( $is_layout_type_modern ) { ?>
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-billing-address-wrap,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-shipping-address-wrap {
        border: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    <?php } ?>

    /* Responsive layout: stack columns on smaller screens */
    <?php if ( $is_responsive_on_mobile ) { ?>
    @media only screen and (max-width: 400px) {
        .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-billing-address-column,
        .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-shipping-address-column {
            display: block;
            width: 100% !important;
            margin-bottom: 10px;
        }
    }
    <?php } ?>

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-shipping-address-wrap address {
        font-weight: <?php echo esc_attr( $shipping_content_format['bold'] ?? false ? 'bold' : 'normal' ); ?> !important;
        font-style: <?php echo esc_attr( $shipping_content_format['italic'] ?? false ? 'italic' : 'normal' ); ?> !important;
        text-decoration: <?php echo esc_attr( $shipping_content_format['underline'] ?? false ? 'underline' : 'none' ); ?> !important;
        font-size: <?php echo esc_attr( TemplateHelpers::get_dimension_value( $data['shipping_content_font_size'] ?? 14 ) ); ?> !important;
        text-align: <?php echo esc_attr( $shipping_content_alignment ); ?> !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-billing-address-wrap address {
        font-weight: <?php echo esc_attr( $billing_content_format['bold'] ?? false ? 'bold' : 'normal' ); ?> !important;
        font-style: <?php echo esc_attr( $billing_content_format['italic'] ?? false ? 'italic' : 'normal' ); ?> !important;
        text-decoration: <?php echo esc_attr( $billing_content_format['underline'] ?? false ? 'underline' : 'none' ); ?> !important;
        font-size: <?php echo esc_attr( TemplateHelpers::get_dimension_value( $data['billing_content_font_size'] ?? 14 ) ); ?> !important;
        text-align: <?php echo esc_attr( $billing_content_alignment ); ?> !important;
    }
</style>
<table class="yaymail-table-billing-shipping-address" cellpadding="0" cellspacing="0" border="0" style="<?php echo esc_attr( $table_style ); ?>"<?php echo $is_responsive_on_mobile ? ' data-responsive-type="true"' : ''; ?>>
    <tbody>
        <tr>
            <?php if ( ! empty( $billing_address_html ) ) : ?>
            <td class="yaymail-billing-address-column" style="width: <?php echo esc_attr( $width ); ?>; vertical-align: top;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-spacing: 0;">
                    <tbody>
                    <tr>
                        <td>
                            <div class="yaymail-billing-title" style="<?php echo esc_attr( $title_style ); ?>"><?php echo wp_kses_post( do_shortcode( $data['billing_title'] ) ); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tbody>
                                    <tr>
                                        <td class="yaymail-billing-address-wrap" style="<?php echo esc_attr( $column_style ); ?>">
                                            <div>
                                                <?php echo wp_kses_post( do_shortcode( '[yaymail_billing_address]' ) ); ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </td>
            <?php endif; ?>
            <?php if ( ! empty( $shipping_address_html ) ) : ?>
            <td class="yaymail-shipping-address-column" style="width: <?php echo esc_attr( $width ); ?>; vertical-align: top;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-spacing: 0;">
                    <tbody>
                    <tr>
                        <td>
                            <div class="yaymail-shipping-title" style="<?php echo esc_attr( $title_style ); ?>"><?php echo wp_kses_post( do_shortcode( $data['shipping_title'] ) ); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tbody>
                                    <tr>
                                        <td class="yaymail-shipping-address-wrap" style="<?php echo esc_attr( $column_style ); ?>">
                                            <div>
                                                <?php echo wp_kses_post( do_shortcode( '[yaymail_shipping_address]' ) ); ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </td>
            <?php endif; ?>
        </tr>
    </tbody>
</table>
<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );

