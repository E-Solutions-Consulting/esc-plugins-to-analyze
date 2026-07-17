<?php

defined( 'ABSPATH' ) || exit;

use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

if ( empty( $data['icon_list'] ) ) {
    return;
}

$direction_rtl = yaymail_get_email_direction();

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$theme = [
    'Colorful'   => 'colorful',
    'LineDark'   => 'line-dark',
    'LineLight'  => 'line-light',
    'SolidDark'  => 'solid-dark',
    'SolidLight' => 'solid-light',
];

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

$item_style = TemplateHelpers::get_style(
    [
        'display'        => 'inline-block',
        'vertical-align' => 'top',
        'text-align'     => 'center',
        'margin'         => '5px 0',
        'padding-left'   => TemplateHelpers::get_dimension_value( max( ( $data['spacing'] ?? 0 ) - 5, 0 ) / 2 ),
        'padding-right'  => TemplateHelpers::get_dimension_value( max( ( $data['spacing'] ?? 0 ) - 5, 0 ) / 2 ),
        'padding-top'    => 0,
        'padding-bottom' => 0,
    ]
);

$a_style = TemplateHelpers::get_style(
    [
        'display'         => 'inline-block',
        'border'          => 'none',
        'text-decoration' => 'none',
    ]
);

ob_start();
?>
<table class="yaymail-customizer-element-social" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: collapse; margin: <?php echo esc_attr( $table_margin ); ?>; width: 100%;">
    <tr>
        <td style="text-align: <?php echo esc_attr( $data['align'] ?? 'center' ); ?>;">
        <?php foreach ( $data['icon_list'] as $key => $el ) : ?>
            <?php
            $img_url     = YAYMAIL_PLUGIN_URL . 'assets/images/social-icons/' . $el['icon'] . '/' . $theme[ $data['theme'] ] . '.png';
            $first_index = ( 'rtl' === $direction_rtl ) ? count( $data['icon_list'] ) - 1 : 0;
            $spacing     = ( $first_index === $key ) ? '0px' : $data['spacing'] . 'px';
            $text_align  = isset( $data['align'] ) ? $data['align'] : 'center';
            ?>
            <span class="yaymail-social-icon-item" style="<?php echo esc_attr( $item_style ); ?>">
                <a href="<?php echo esc_attr( do_shortcode( $el['url'] ) ); ?>" target="_blank" style="<?php echo esc_attr( $a_style ); ?>">
                    <img border="0" tabindex="0" src="<?php echo esc_attr( $img_url ); ?>" height="<?php echo esc_attr( $data['width_icon'] ); ?>" width="<?php echo esc_attr( $data['width_icon'] ); ?>" style="display: block; border: 0; margin: 0; padding: 0; outline: none;" />
                </a>
            </span>
        <?php endforeach; ?>
        </td>
    </tr>
</table>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
