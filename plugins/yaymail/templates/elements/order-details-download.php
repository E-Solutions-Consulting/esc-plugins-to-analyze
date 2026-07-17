<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$title_style = TemplateHelpers::get_style(
    [
        'text-align'    => yaymail_get_text_align(),
        'color'         => isset( $data['title_color'] ) ? $data['title_color'] : 'inherit',
        'margin-top'    => '0',
        'margin-bottom' => '7px',
        'font-family'   => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
    ]
);

$is_layout_type_modern = isset( $data['layout_type'] ) && 'modern' === $data['layout_type'];

$table_style = TemplateHelpers::get_style(
    [
        'font-size'        => '14px',
        'text-align'       => yaymail_get_text_align(),
        'font-family'      => TemplateHelpers::get_font_family_value( ! empty( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'color'            => ! empty( $data['text_color'] ) ? $data['text_color'] : 'inherit',
        'border-collapse'  => $is_layout_type_modern ? 'collapse' : 'separate',
        'border'           => $is_layout_type_modern ? '0' : ( ! empty( $data['border_color'] ) ? '1px solid ' . $data['border_color'] : 'inherit' ),
        'mso-table-lspace' => $is_layout_type_modern ? '0pt' : null,
        'mso-table-rspace' => $is_layout_type_modern ? '0pt' : null,
    ]
);

$shortcoded_title   = isset( $data['title'] ) ? do_shortcode( $data['title'] ) : '';
$shortcoded_content = isset( $data['rich_text'] ) ? do_shortcode( $data['rich_text'] ) : '';

if ( empty( $shortcoded_content ) ) {
    return;
}

ob_start();
?>
<style>
    /* Modern layout */
    <?php if ( $is_layout_type_modern ) { ?>
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-download-content {
        border: 0 !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-download-content tbody,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-download-content tr,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-download-content th,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-download-content td {
        border: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-download-title--download,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-download-content--download {
        text-align: right !important;
    }
        <?php
    }//end if
    ?>
</style>

<div class="yaymail-order-details-download-title" style="<?php echo esc_attr( $title_style ); ?>" > <?php yaymail_kses_post_e( $shortcoded_title ); ?></div>
<table class="yaymail-order-details-download-content" style="<?php echo esc_attr( $table_style ); ?>" border="0" cellpadding="6" cellspacing="0" width="100%" >
    <?php yaymail_kses_post_e( $shortcoded_content ); ?>
</table>
<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
