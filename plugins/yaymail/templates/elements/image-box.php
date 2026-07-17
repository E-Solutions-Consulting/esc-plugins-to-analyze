<?php
defined( 'ABSPATH' ) || exit;
use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$data_column_1 = [
    'align'      => 'center',
    'full_width' => false,
    'width'      => '242',
    'url'        => '#',
    'image'      => YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png',
    'padding'    => [
        'top'    => '10',
        'right'  => '10',
        'bottom' => '10',
        'left'   => '50',
    ],
];

if ( is_array( $data['image_box']['column_1'] ) && ! empty( $data['image_box']['column_1'] ) ) {
    $_data_column_1 = $data['image_box']['column_1'];
    foreach ( $_data_column_1 as $key => $value ) {
        if ( $key === 'full_width' ) {
            $data_column_1['full_width'] = filter_var( $value['value'], FILTER_VALIDATE_BOOLEAN );
        } else {
            $data_column_1[ $key ] = $value['value'];
        }
    }
}

$data_column_2 = [
    'rich_text'   => '<p><span style="font-size: 18px;"><strong>This is a title</strong></span></p><p><span> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy.</span></p><p><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p>',
    'font_family' => YAYMAIL_DEFAULT_FAMILY,
    'padding'     => [
        'top'    => '10',
        'right'  => '50',
        'bottom' => '10',
        'left'   => '10',
    ],
];

if ( is_array( $data['image_box']['column_2'] ) && ! empty( $data['image_box']['column_2'] ) ) {
    $_data_column_2 = $data['image_box']['column_2'];
    foreach ( $_data_column_2 as $key => $value ) {
        $data_column_2[ $key ] = $value['value'];
    }
}

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
    ]
);

$column_1_style = TemplateHelpers::get_style(
    [
        'padding' => TemplateHelpers::get_spacing_value( isset( $data_column_1['padding'] ) ? $data_column_1['padding'] : [] ),
    ]
);

$column_2_style = TemplateHelpers::get_style(
    [
        'padding' => TemplateHelpers::get_spacing_value( isset( $data_column_2['padding'] ) ? $data_column_2['padding'] : [] ),
    ]
);


$text_style = TemplateHelpers::get_style(
    [
        'color'       => $data['text_color'],
        'text-align'  => 'left',
        'font-family' => TemplateHelpers::get_font_family_value( $data_column_2['font_family'] ),
    ]
);

ob_start();
?>
    <table style="width: 100%;">
        <tbody>
            <tr>
                <td class="yaymail-table-image-box-column" align="<?php echo esc_attr( $data_column_1['align'] ); ?>" width="50%" style="<?php echo esc_attr( $column_1_style ); ?>">
                    <div>
                        <a href="<?php echo esc_html( do_shortcode( $data_column_1['url'] ) ); ?>" target="_blank" rel="noreferrer">
                            <img alt="<?php echo esc_attr( $data_column_1['alt'] ?? '' ); ?>" src="<?php echo esc_html( $data_column_1['image'] ); ?>" style="width: <?php echo esc_attr( isset( $data_column_1['full_width'] ) && $data_column_1['full_width'] ? '100%' : TemplateHelpers::get_dimension_value( $data_column_1['width'] ) ); ?>"/>
                        </a>
                    </div>
                </td>
                <td class="yaymail-table-image-box-column" width="50%" style="<?php echo esc_attr( $column_2_style ); ?>" > 
                    <div style="<?php echo esc_attr( $text_style ); ?>">
                        <?php echo wp_kses_post( do_shortcode( $data_column_2['rich_text'] ) ); ?>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
