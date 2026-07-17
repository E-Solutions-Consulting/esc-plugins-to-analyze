<?php
defined( 'ABSPATH' ) || exit;
use YayMail\Utils\TemplateHelpers;
use YayMail\Constants\AttributesData;
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

$default_button_padding = [
    'top'    => '12',
    'right'  => '20',
    'bottom' => '12',
    'left'   => '20',
];

$border       = isset( $data['border'] ) ? $data['border'] : AttributesData::BORDER_DEFAULT;
$border_style = TemplateHelpers::get_border_css_value( $border );

$wrapper_style = TemplateHelpers::get_style(
    [
        'width'            => '100%',
        'text-align'       => 'center',
        'background-color' => $data['background_color'],
    ]
);

$width = isset($data['width']) ? $data['width'] : 'auto';
$custom_width = isset($data['custom_width']) ? $data['custom_width'] : '50';

if ($width === 'custom') {
    $parsed_width = $custom_width . '%';
} elseif (in_array($width, ['auto', '25%', '50%', '75%', '100%'])) {
    $parsed_width = $width;
} else {
    $parsed_width = $width . '%';
}

$margin_value        = isset( $data['margin'] ) && 'center' === $data['margin'] ? '0 auto' : 'auto';
$float_value         = in_array( $data['align'], [ 'left', 'right' ], true ) ? $data['align'] : 'unset';
$button_holder_style = TemplateHelpers::get_style(
    [
        'width'          => $parsed_width,
        'min-width'      => $parsed_width === 'auto' ? 'auto' : $parsed_width,
        'margin'         => $margin_value,
        'padding'        => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
        'float'          => $float_value,
        'border-spacing' => '0',
    // Make sure this will work when inject css not working
    ]
);


$border_radius = $data['border_radius'];

$container_style = TemplateHelpers::get_style(
    [
        'display'          => 'inline-block',
        'width'            => '100%',
        'box-sizing'       => 'border-box',
        'border-radius'    => TemplateHelpers::get_border_radius_value( $border_radius, 'px' ),
        'background-color' => $data['button_background_color'],
        'word-break'       => 'break-word',
    ]
) . $border_style;

$link_style = TemplateHelpers::get_style(
    [
        'text-decoration' => 'none',
    ]
);

$text_style = TemplateHelpers::get_style(
    [
        'display'     => 'block',
        'font-family' => TemplateHelpers::get_font_family_value( $data['font_family'] ),
        'line-height' => "{$data['height']}px",
        'color'       => $data['text_color'],
        'font-size'   => "{$data['font_size']}px",
        'font-weight' => $data['weight'],
        'padding'     => TemplateHelpers::get_spacing_value( isset( $data['button_padding'] ) ? $data['button_padding'] : $default_button_padding ),
        'text-align'  => 'center',
    ]
);

ob_start();
?>

    <table class="yaymail-element-button" style="<?php echo esc_attr( $button_holder_style ); ?>">
        <tbody>
            <tr>
                <td style="padding: 0;">
                    <div style="<?php echo esc_attr( $container_style ); ?>">
                        <a
                            href="<?php echo esc_url( do_shortcode( $data['url'] ) ); ?>"
                            style="<?php echo esc_attr( $link_style ); ?>"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <span style="<?php echo esc_attr( $text_style ); ?>"><?php yaymail_kses_post_e( do_shortcode( $data['text'] ) ); ?></span>
                        </a>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
