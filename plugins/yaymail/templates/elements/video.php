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
        'text-align'       => 'center',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$thumbnail_cell_style = TemplateHelpers::get_style(
    [
        'background-image'    => ! empty( $data['src'] ) ? "url({$data['src']})" : '',
        'background-size'     => 'cover',
        'background-position' => 'center',
        'width'               => "{$data['width']}px",
        'height'              => "{$data['height']}px",
        'text-align'          => 'center',
        'vertical-align'      => 'middle',
        'display'             => 'table-cell',
    ]
);

// Play button style for email
$btn_play_style = TemplateHelpers::get_style(
    [
        'width'   => '56px',
        'height'  => '56px',
        'border'  => 'none',
        'outline' => 'none',
        'display' => 'block',
        'margin'  => 'auto',
    ]
);

$td_style = TemplateHelpers::get_style(
    [
        'text-align' => 'center',
        'padding'    => '0',
        'height'     => "{$data['height']}px",
        'display'    => 'inline-block',
    ]
);

ob_start();
?>

<table class="yaymail-customizer-element-video" role="presentation" border="0"  align="center" cellpadding="0" cellspacing="0" style="display: table;border-collapse: collapse;width: 100%;text-align: center;">
    <tbody>
        <tr>
            <td style="<?php echo esc_attr( $td_style ); ?>">
                <a class="yaymail-customizer-element-video__anchor" href="<?php echo esc_url( isset( $data['url'] ) ? do_shortcode( $data['url'] ) : '#' ); ?>" style="text-decoration: none; display: inline-block;">
                    <div class="yaymail-customizer-element-video__thumbnail" style="<?php echo esc_attr( $thumbnail_cell_style ); ?>">
                        <img valign="middle" class="yaymail-customizer-element-video__btn-play" src="<?php echo esc_url( YAYMAIL_PLUGIN_URL . '/assets/images/play.png' ); ?>" alt="Play video" style="<?php echo esc_attr( $btn_play_style ); ?>" />
                    </div>
                </a>
            </td>
        </tr>
    </tbody>
</table>


<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
