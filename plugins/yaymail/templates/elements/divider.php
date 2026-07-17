<?php
defined( 'ABSPATH' ) || exit;

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

$element = $args['element'];
$data    = $element['data'];

$wrapper_style = TemplateHelpers::get_style(
    [
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

// Handle table alignment
$table_margin = '0 auto';
if ( isset( $data['align'] ) ) {
    switch ( $data['align'] ) {
        case 'center':
            $table_margin = '0 auto';
            break;
        case 'right':
            $table_margin = '0 0 0 auto';
            break;
        case 'left':
        default:
            $table_margin = '0';
            break;
    }
}

$width_value = TemplateHelpers::get_dimension_value( $data['width'], '%' );

$table_style = TemplateHelpers::get_style(
    [
        'border-collapse' => 'collapse',
        'width'           => $width_value,
        'min-width'       => $width_value,
        'margin'          => $table_margin,
    ]
);

$cell_style = TemplateHelpers::get_style(
    [
        'width'            => TemplateHelpers::get_dimension_value( $data['width'], '%' ),
        'border-top-width' => TemplateHelpers::get_dimension_value( $data['height'] ),
        'border-top-color' => $data['divider_color'],
        'border-top-style' => $data['divider_type'],
        'padding'          => '0',
        'margin'           => '0',
        'line-height'      => '0',
    ]
);

ob_start();
?>
<table class="yaymail-customizer-element-divider" cellpadding="0" cellspacing="0" role="presentation" style="<?php echo esc_attr( $table_style ); ?>">
    <tbody>
        <tr>
            <td style="<?php echo esc_attr( $cell_style ); ?>">
                &nbsp;
            </td>
        </tr>
    </tbody>
</table>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
