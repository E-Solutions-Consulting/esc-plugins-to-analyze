<?php
defined( 'ABSPATH' ) || exit;
use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element         = $args['element'];
$data            = $element['data'];
$settings        = $args['settings'];
$container_width = isset( $settings['container_width'] ) ? $settings['container_width'] : 605;


$data_column_default = is_array( $data['text_list']['column_1'] ) && ! empty( $data['text_list']['column_1'] ) ? $data['text_list']['column_1'] : [
    'rich_text'   => '<p><span style="font-size: 18px;"><strong>This is a title</strong></span></p><p>&nbsp;</p><p><span> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy.</span></p><p>&nbsp;</p><p><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p>',
    'font_family' => YAYMAIL_DEFAULT_FAMILY,
    'padding'     => [
        'top'    => '10',
        'right'  => '10',
        'bottom' => '10',
        'left'   => '50',
    ],
    'show_button' => true,
    'button'      => [
        'type'             => 'default',
        'text'             => 'Click me',
        'align'            => 'left',
        'height'           => '21',
        'width'            => '50',
        'weight'           => 'normal',
        'font_family'      => YAYMAIL_DEFAULT_FAMILY,
        'font_size'        => '13',
        'url'              => '#',
        'background_color' => YAYMAIL_COLOR_WC_DEFAULT,
        'text_color'       => '#fff',
        'padding'          => [
            'top'    => '0',
            'right'  => '0',
            'bottom' => '0',
            'left'   => '50',
        ],
        'border_radius'    => [
            'top'    => '5',
            'right'  => '5',
            'bottom' => '5',
            'left'   => '5',
        ],
    ],
];

$data_column_1 = is_array( $data['text_list']['column_1'] ) && ! empty( $data['text_list']['column_1'] ) ? $data['text_list']['column_1'] : $data_column_default;
if ( is_array( $data['text_list']['column_1'] ) && ! empty( $data['text_list']['column_1'] ) ) {
    $_data_column_1 = $data['text_list']['column_1'];
    foreach ( $_data_column_1 as $key => $value ) {
        if ( isset( $value['value'] ) ) {
            $data_column_1[ $key ] = $value['value'];
        }
    }
}

$data_column_2 = is_array( $data['text_list']['column_2'] ) && ! empty( $data['text_list']['column_2'] ) ? $data['text_list']['column_2'] : $data_column_default;
if ( is_array( $data['text_list']['column_2'] ) && ! empty( $data['text_list']['column_2'] ) ) {
    $_data_column_2 = $data['text_list']['column_2'];
    foreach ( $_data_column_2 as $key => $value ) {
        if ( isset( $value['value'] ) ) {
            $data_column_2[ $key ] = $value['value'];
        }
    }
}

$data_column_3 = is_array( $data['text_list']['column_3'] ) && ! empty( $data['text_list']['column_3'] ) ? $data['text_list']['column_3'] : $data_column_default;
if ( is_array( $data['text_list']['column_3'] ) && ! empty( $data['text_list']['column_3'] ) ) {
    $_data_column_3 = $data['text_list']['column_3'];
    foreach ( $_data_column_3 as $key => $value ) {
        if ( isset( $value['value'] ) ) {
            $data_column_3[ $key ] = $value['value'];
        }
    }
}

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
    ]
);

$column_width = $container_width / $data['number_column'];

$column_style = TemplateHelpers::get_style(
    [
        'color'      => $data['text_color'],
        'width'      => TemplateHelpers::get_dimension_value( $column_width, 'px' ),
        'box-sizing' => 'border-box',
        'display'    => 'table-cell',
        'word-break' => 'break-word',
    ]
);

$column_text_style = TemplateHelpers::get_style(
    [
        'width' => TemplateHelpers::get_dimension_value( 100 / $data['number_column'] ),
    ]
);

$column_1_text_style = TemplateHelpers::get_style(
    [
        'text-align'  => 'left',
        'padding'     => TemplateHelpers::get_spacing_value( isset( $data_column_1['padding'] ) ? $data_column_1['padding'] : [] ),
        'font-family' => TemplateHelpers::get_font_family_value( $data_column_1['font_family'] ),
    ]
);

$column_1_button_holder_style = TemplateHelpers::get_style(
    [
        'width'   => TemplateHelpers::get_dimension_value( $data_column_1['button_width'], '%' ),
        'margin'  => 'auto',
        'padding' => TemplateHelpers::get_spacing_value( isset( $data_column_1['button_padding'] ) ? $data_column_1['button_padding'] : [] ),
        'float'   => in_array( $data_column_1['button_align'], [ 'left', 'right' ], true ) ? $data_column_1['button_align'] : 'unset',
    ]
);

$column_1_button_link_style = TemplateHelpers::get_style(
    [
        'text-decoration'  => 'none',
        'padding'          => '12px 20px',
        'display'          => 'block',
        'box-sizing'       => 'border-box',
        'border-radius'    => TemplateHelpers::get_border_radius_value( isset( $data_column_1['button_border_radius'] ) ? $data_column_1['button_border_radius'] : [] ),
        'font-size'        => "{$data_column_1['button_font_size']}px",
        'font-weight'      => $data_column_1['button_weight'],
        'background-color' => $data_column_1['button_background_color'],
        'word-break'       => 'break-word',
    ]
);

$column_1_button_text_style = TemplateHelpers::get_style(
    [
        'font-family' => TemplateHelpers::get_font_family_value( $data_column_1['button_font_family'] ),
        'line-height' => "{$data_column_1['button_height']}px",
        'color'       => $data_column_1['button_text_color'],
        'text-align'  => 'center',
        'display'     => 'block',
    ]
);

$column_2_text_style = TemplateHelpers::get_style(
    [
        'text-align'  => 'left',
        'padding'     => TemplateHelpers::get_spacing_value( isset( $data_column_2['padding'] ) ? $data_column_2['padding'] : [] ),
        'font-family' => TemplateHelpers::get_font_family_value( $data_column_2['font_family'] ),
    ]
);

$column_2_button_holder_style = TemplateHelpers::get_style(
    [
        'width'   => TemplateHelpers::get_dimension_value( $data_column_2['button_width'], '%' ),
        'margin'  => 'auto',
        'padding' => TemplateHelpers::get_spacing_value( isset( $data_column_2['button_padding'] ) ? $data_column_2['button_padding'] : [] ),
        'float'   => in_array( $data_column_2['button_align'], [ 'left', 'right' ], true ) ? $data_column_2['button_align'] : 'unset',
    ]
);

$column_2_button_link_style = TemplateHelpers::get_style(
    [
        'text-decoration'  => 'none',
        'padding'          => '12px 20px',
        'display'          => 'block',
        'box-sizing'       => 'border-box',
        'border-radius'    => TemplateHelpers::get_border_radius_value( isset( $data_column_2['button_border_radius'] ) ? $data_column_2['button_border_radius'] : [] ),
        'font-size'        => "{$data_column_2['button_font_size']}px",
        'font-weight'      => $data_column_2['button_weight'],
        'background-color' => $data_column_2['button_background_color'],
        'word-break'       => 'break-word',
    ]
);

$column_2_button_text_style = TemplateHelpers::get_style(
    [
        'font-family' => TemplateHelpers::get_font_family_value( $data_column_2['button_font_family'] ),
        'line-height' => "{$data_column_2['button_height']}px",
        'color'       => $data_column_2['button_text_color'],
        'text-align'  => 'center',
        'display'     => 'block',
    ]
);

$column_3_text_style = TemplateHelpers::get_style(
    [
        'text-align'  => 'left',
        'padding'     => TemplateHelpers::get_spacing_value( isset( $data_column_3['padding'] ) ? $data_column_3['padding'] : [] ),
        'font-family' => TemplateHelpers::get_font_family_value( $data_column_3['font_family'] ),
    ]
);

$column_3_button_holder_style = TemplateHelpers::get_style(
    [
        'width'   => TemplateHelpers::get_dimension_value( $data_column_3['button_width'], '%' ),
        'margin'  => 'auto',
        'padding' => TemplateHelpers::get_spacing_value( isset( $data_column_3['button_padding'] ) ? $data_column_3['button_padding'] : [] ),
        'float'   => in_array( $data_column_3['button_align'], [ 'left', 'right' ], true ) ? $data_column_3['button_align'] : 'unset',
    ]
);

$column_3_button_link_style = TemplateHelpers::get_style(
    [
        'text-decoration'  => 'none',
        'padding'          => '12px 20px',
        'display'          => 'block',
        'box-sizing'       => 'border-box',
        'border-radius'    => TemplateHelpers::get_border_radius_value( isset( $data_column_3['button_border_radius'] ) ? $data_column_3['button_border_radius'] : [] ),
        'font-size'        => "{$data_column_3['button_font_size']}px",
        'font-weight'      => $data_column_3['button_weight'],
        'background-color' => $data_column_3['button_background_color'],
        'word-break'       => 'break-word',
    ]
);

$column_3_button_text_style = TemplateHelpers::get_style(
    [
        'font-family' => TemplateHelpers::get_font_family_value( $data_column_3['button_font_family'] ),
        'line-height' => "{$data_column_3['button_height']}px",
        'color'       => $data_column_3['button_text_color'],
        'text-align'  => 'center',
        'display'     => 'block',
    ]
);

ob_start();
?>

<table  className="yaymail-table-text-list"  width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%;table-layout: fixed;">
        <tbody>
            <tr>
                <!-- Column 1 -->
                <td valign="top" style="<?php echo esc_attr( $column_style ); ?>">
                    <table style="width: 100%;table-layout: fixed;">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="yaymail-table-text-list-column" style="<?php echo esc_attr( $column_1_text_style ); ?> ">
                                        <?php echo wp_kses_post( do_shortcode( $data_column_1['rich_text'] ) ); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ( $data_column_1['show_button'] ) : ?>
                            <tr>
                                <td class="yaymail-table-text-list-column">
                                    <table style="<?php echo esc_attr( $column_1_button_holder_style ); ?>">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <a
                                                        href="<?php echo esc_url( do_shortcode( $data_column_1['button_url'] ) ); ?>"
                                                        style="<?php echo esc_attr( $column_1_button_link_style ); ?>"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <span style="<?php echo esc_attr( $column_1_button_text_style ); ?>"><?php echo esc_html( $data_column_1['button_text'] ); ?></span>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </td>
                <!-- Column 2 -->
                <?php if ( 1 < (int) $data['number_column'] ) : ?>
                <td valign="top" style="<?php echo esc_attr( $column_style ); ?>">
                    <table style="width: 100%;table-layout: fixed;">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="yaymail-table-text-list-column" style="<?php echo esc_attr( $column_2_text_style ); ?>">
                                        <?php echo wp_kses_post( do_shortcode( $data_column_2['rich_text'] ) ); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ( $data_column_2['show_button'] ) : ?>
                            <tr>
                                <td class="yaymail-table-text-list-column">
                                    <table style="<?php echo esc_attr( $column_2_button_holder_style ); ?>">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <a
                                                        href="<?php echo esc_url( do_shortcode( $data_column_2['button_url'] ) ); ?>"
                                                        style="<?php echo esc_attr( $column_2_button_link_style ); ?>"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <span style="<?php echo esc_attr( $column_2_button_text_style ); ?>"><?php echo esc_html( $data_column_2['button_text'] ); ?></span>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </td>
                <?php endif; ?>
                <!-- Column 3 -->
                <?php if ( 3 === (int) $data['number_column'] ) : ?>
                <td valign="top" style="<?php echo esc_attr( $column_style ); ?>">
                    <table style="width: 100%;table-layout: fixed;">
                        <tbody>
                            <tr>
                                <td>
                                    <div style="<?php echo esc_attr( $column_3_text_style ); ?>">
                                        <?php echo wp_kses_post( do_shortcode( $data_column_3['rich_text'] ) ); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ( $data_column_3['show_button'] ) : ?>
                            <tr>
                                <td>
                                    <table style="<?php echo esc_attr( $column_3_button_holder_style ); ?>">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <a
                                                        href="<?php echo esc_url( do_shortcode( $data_column_3['button_url'] ) ); ?>"
                                                        style="<?php echo esc_attr( $column_3_button_link_style ); ?>"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <span style="<?php echo esc_attr( $column_3_button_text_style ); ?>"><?php echo esc_html( $data_column_3['button_text'] ); ?></span>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </td>
                <?php endif; ?>
        </tbody>
</table>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
