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
        'word-break'       => 'break-word',
        'text-align'       => $data['align'],
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

ob_start();
?>
    <a href="<?php echo esc_url( do_shortcode( $data['url'] ) ); ?>" target="_blank" rel="noreferrer">
        <img alt="<?php echo esc_attr( $data['alt'] ?? '' ); ?>" src="<?php echo esc_url( $data['src'] ); ?>" style="max-width: 100%; width: <?php echo esc_attr( TemplateHelpers::get_dimension_value( $data['width'] ) ); ?>" />
    </a>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );

