<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$content_style = TemplateHelpers::get_style(
    [
        'background-color' => $data['background_color'],
        'color'            => $data['text_color'],
        'font-family'      => isset( $data['font_family'] ) ? $data['font_family'] : '',
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
        'text-align'       => yaymail_get_text_align(),
    ]
);


if ( ! empty( $data['hook_shortcode'] ) ) {
    ob_start();
    ?>
        <div style="<?php echo esc_attr( $content_style ); ?>"><?php yaymail_kses_post_e( do_shortcode( $data['hook_shortcode'] ) ); ?></div>
    <?php
    $element_content = ob_get_clean();

    TemplateHelpers::wrap_element_content( $element_content, $element );
}
?>
