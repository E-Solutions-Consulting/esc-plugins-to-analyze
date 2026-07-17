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
    ]
);

$space_style = TemplateHelpers::get_style(
    [
        'height'           => TemplateHelpers::get_dimension_value( $data['height'] ),
        'font-size'        => '0',
        'background-color' => $data['background_color'],
    ]
);

ob_start();
?>
<table class="yaymail-customizer-element-space" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: collapse; width: 100%;">
    <tbody>
        <tr>
            <td style="<?php echo esc_attr( $space_style ); ?>">
                &nbsp;
            </td>
        </tr>
    </tbody>
</table>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
