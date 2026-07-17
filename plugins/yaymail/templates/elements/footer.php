<?php
defined( 'ABSPATH' ) || exit;
use YayMail\Utils\TemplateHelpers;
use YayMail\GlobalHeaderFooter;

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
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$text_style = TemplateHelpers::get_style(
    [
        'color'       => $data['text_color'],
        'font-family' => isset( $data['font_family'] ) ? $data['font_family'] : '',
    ]
);

/**
 * Get global footer override content
 *
 * @since 4.1.0
 */
$text_content = $data['rich_text'];
if ( ! is_null( GlobalHeaderFooter::get_global_footer_override_content( $args['template'] ) ) && GlobalHeaderFooter::is_element_in_global_footer( $element, $args['template'] ) ) {
    $text_content = GlobalHeaderFooter::get_global_footer_override_content( $args['template'] );
}

ob_start();
?>

    <div style="<?php echo esc_attr( $text_style ); ?>"><?php echo wp_kses_post( do_shortcode( $text_content ) ); ?></div>

<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
