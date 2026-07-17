<?php

defined( 'ABSPATH' ) || exit;

use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element     = $args['element'];
$data        = $element['data'];
$render_data = isset( $args['render_data'] ) && is_array( $args['render_data'] ) ? $args['render_data'] : [];

$steps = isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : [];
if ( empty( $steps ) ) {
    return;
}

$current_step_index = isset( $data['current_step_index'] ) ? (int) $data['current_step_index'] : 0;

/**
 * Filter to resolve the active step index based on WooCommerce order status.
 *
 * Return null to keep the manual value from the element settings.
 *
 * @param int|null  $resolved_step_index Resolved index.
 * @param WC_Order|null $wc_order WooCommerce order instance (if available).
 * @param array     $element Element data.
 */
$wc_order            = isset( $render_data['order'] ) ? $render_data['order'] : null;
$resolved_step_index = apply_filters( 'yaymail_order_progress_step_index', null, $wc_order, $element );
if ( null !== $resolved_step_index ) {
    $current_step_index = (int) $resolved_step_index;
}

$current_step_index = max( 0, min( $current_step_index, count( $steps ) - 1 ) );

$align = 'center';

$display_style = isset( $data['display_style'] ) ? (string) $data['display_style'] : 'step_marker';

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'text-align'       => $align,
        'background-color' => $data['background_color'] ?? '#ffffff',
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$table_style = TemplateHelpers::get_style(
    [
        'width'            => '100%',
        'table-layout'     => 'auto',
        'border-collapse'  => 'collapse',
        'border-spacing'   => 0,
        'mso-table-lspace' => '0pt',
        'mso-table-rspace' => '0pt',
    ]
);

$connector_height = (int) ( $data['connector_height'] ?? 2 );
$connector_height = max( 1, min( $connector_height, 10 ) );

$connector_active_color   = TemplateHelpers::replace_color_paths(
    (string) ( $data['connector_active_color'] ?? '#873eff' )
);
$connector_inactive_color = TemplateHelpers::replace_color_paths(
    (string) ( $data['connector_inactive_color'] ?? '#E2E6EE' )
);

$icon_size = (int) ( $data['icon_size'] ?? 18 );
$icon_size = max( 10, min( $icon_size, 80 ) );

$label_font_size = (int) ( $data['label_font_size'] ?? 14 );
$label_font_size = max( 8, min( $label_font_size, 24 ) );

$label_font_family = TemplateHelpers::get_font_family_value(
    ! empty( $data['font_family'] ) ? $data['font_family'] : YAYMAIL_DEFAULT_FAMILY
);

// Backward compatible fallbacks (older versions used global label colors + per-step label_color override).
// Use opinionated defaults close to design: dark text for active, grey for inactive.
$legacy_label_active_color   = $data['label_active_color'] ?? '#111827';
$legacy_label_inactive_color = $data['label_inactive_color'] ?? '#9CA3AF';

ob_start();
?>
<?php
switch ( $display_style ) {
    case 'filled_bar':
        include __DIR__ . '/order-progress/variants/filled-bar.php';
        break;
    case 'step_marker':
    default:
        include __DIR__ . '/order-progress/variants/step-marker.php';
        break;
}
?>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
