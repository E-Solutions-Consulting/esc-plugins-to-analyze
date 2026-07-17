<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Utils\Helpers;
use YayMail\Utils\TemplateHelpers;

/**
 * $args includes
 * $element
 * $render_data
 * $is_nested
 */
if ( empty( $args['element'] ) ) {
    return;
}

$yaymail_settings     = yaymail_settings();
$payment_display_mode = isset( $yaymail_settings['payment_display_mode'] ) ? $yaymail_settings['payment_display_mode'] : false;

$element                 = $args['element'];
$data                    = $element['data'];
$template_name           = isset( $args['template']->get_data()['name'] ) ? $args['template']->get_data()['name'] : '';
$border_color            = isset( $element['data']['border_color'] ) ? $element['data']['border_color'] : 'inherit';
$table_heading_font_size = isset( $data['table_heading_font_size'] ) ? $data['table_heading_font_size'] : 14;
$table_content_font_size = isset( $data['table_content_font_size'] ) ? $data['table_content_font_size'] : 14;

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$table_title_style = TemplateHelpers::get_style(
    [
        'text-align'    => yaymail_get_text_align(),
        'color'         => isset( $data['title_color'] ) ? $data['title_color'] : 'inherit',
        'margin-top'    => '0',
        'font-family'   => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'margin-bottom' => '7px',
    ]
);

$payment_instructions_style = TemplateHelpers::get_style(
    [
        'text-align'    => yaymail_get_text_align(),
        'font-size'     => '14px',
        'color'         => isset( $data['text_color'] ) ? $data['text_color'] : 'inherit',
        'font-family'   => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'margin-bottom' => '10px',
    ]
);

$is_layout_type_modern = isset( $data['layout_type'] ) && 'modern' === $data['layout_type'];
$show_table_header     = ! isset( $data['show_table_header'] ) || Helpers::is_true( $data['show_table_header'] );

ob_start();
?>
<style>
    /* Modern layout */
    <?php if ( $is_layout_type_modern ) { ?>
        <?php
        $modern_line_color = ( '' === (string) $border_color || 'inherit' === strtolower( (string) $border_color ) ) ? '#dddddd' : $border_color;
        ?>


    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table {
        border: 0 !important;
    }
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table th,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table td {
        border: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> th.yaymail_item_price_title,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> td.yaymail_item_price_content,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail_element_foot_order_details tr td {
        text-align: right !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail_item_quantity_title,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail_item_quantity_content,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail_item_cost_title,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail_item_cost_content {
        text-align: center !important;
    }
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-quantity-type-modern {
        display: inline-block !important;
    }


    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table tbody.yaymail_element_body_order_details .yaymail-order-item-last td{
        border-bottom: 1px solid <?php echo esc_attr( $modern_line_color ); ?> !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table .yaymail-order-detail-row-payment_method td,
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table .yaymail-order-detail-row-payment_method th {
        border-bottom: 1px solid <?php echo esc_attr( $modern_line_color ); ?> !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table th {
        font-size: <?php echo esc_attr( $table_heading_font_size ); ?>px !important;
    }

    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail-order-details-table td {
        font-size: <?php echo esc_attr( $table_content_font_size ); ?>px !important;
    }
    
        <?php
    }//end if
    ?>

    /* Hide table header */
    <?php if ( ! $show_table_header ) { ?>
    .yaymail-element-<?php echo esc_attr( $element['id'] ); ?> .yaymail_element_head_order_details {
        display: none !important;
    }
    <?php } ?>
</style>

<div class="yaymail-order-details-title" style="<?php echo esc_attr( $table_title_style ); ?>" > <?php echo wp_kses_post( do_shortcode( $data['title'] ) ); ?> </div>
<?php
$element_content = ob_get_contents();
ob_end_clean();
$element_content .= yaymail_kses_post( do_shortcode( isset( $data['rich_text'] ) ? $data['rich_text'] : '' ) );
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
