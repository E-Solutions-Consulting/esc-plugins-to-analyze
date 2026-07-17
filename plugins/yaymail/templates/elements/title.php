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

$wrapper_style = TemplateHelpers::get_style(
    [
        'line-height'      => 'normal',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);


$common_font_family = TemplateHelpers::get_font_family_value( $data['font_family'] );

$title_size  = TemplateHelpers::get_font_size( $data['title_size'] );
$title_style = TemplateHelpers::get_style(
    [
        'text-align'  => $data['align'],
        'margin'      => '0',
        'color'       => $data['text_color'],
        'font-size'   => $title_size,
        'font-family' => $common_font_family,
        'font-weight' => '600',
    ]
);

$subtitle_size  = TemplateHelpers::get_font_size( $data['subtitle_size'], true );
$subtitle_style = TemplateHelpers::get_style(
    [
        'text-align'  => $data['align'],
        'font-family' => $common_font_family,
        'color'       => $data['text_color'],
        'font-size'   => $subtitle_size,
        'margin'      => '0',
    ]
);
ob_start();
?>

    <h1 class="yaymail-customizer-element-title__title" style="<?php echo esc_html( $title_style ); ?>">
        <?php yaymail_kses_post_e( do_shortcode( $data['title'] ) ); ?>
    </h1>
    <?php if ( ! empty( $data['subtitle'] ) ) : ?>
        <h4 class="yaymail-customizer-element-title__subtitle" style="<?php echo esc_html( $subtitle_style ); ?>">
            <?php yaymail_kses_post_e( do_shortcode( $data['subtitle'] ) ); ?>
        </h4>
    <?php endif; ?>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );

