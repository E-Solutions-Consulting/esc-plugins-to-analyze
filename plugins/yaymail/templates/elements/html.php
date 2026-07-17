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
        'background-color' => '#fff',
        'color'            => '#636363',
        'padding'          => '15px 50px',
        'font-family'      => 'Helvetica, Roboto, Arial, sans-serif',
        'font-size'        => '13px',
    ]
);

if ( ! empty( $data['rich_text'] ) ) {
    ob_start();
    ?>
        <div style="text-align: initial;"><?php echo wp_kses_post( do_shortcode( $data['rich_text'] ) ); ?></div>
    <?php
    $element_content = ob_get_clean();

    TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
}
?>
