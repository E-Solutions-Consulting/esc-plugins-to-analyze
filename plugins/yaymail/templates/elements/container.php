<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Elements\ElementsLoader;
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

$element   = $args['element'];
$data      = $element['data'];
$direction = isset( $data['direction'] ) ? $data['direction'] : 'vertical';

if ( 'horizontal' === $direction ) {
    $args['is_horizontal'] = true;
}

$wrapper_style = TemplateHelpers::get_style(
    [
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
        'overflow'         => 'hidden',
    ]
);

if ( isset( $data['background_image'], $data['background_image']['url'] ) ) {
    $background_image = $data['background_image'];
    // Calculate background-position
    $background_position = str_replace( '_', ' ', $background_image['position'] );
    if ( ! isset( $background_image['position'] ) || 'default' === $background_image['position'] ) {
        $background_position = 'unset';
    } elseif ( 'custom' === $background_image['position'] ) {
        $background_position = "{$background_image['x_position']}% {$background_image['y_position']}%";
    }

    // Calculate background-repeat
    if ( ! isset( $background_image['repeat'] ) || 'default' === $background_image['repeat'] ) {
        $background_repeat = 'unset';
    } else {
        $background_repeat = $background_image['repeat'];
    }

    // Calculate background-size
    $background_size = $background_image['size'];

    if ( 'default' === $background_size ) {
        $background_size = 'unset';
    } elseif ( 'custom' === $background_size ) {
        $background_size = "{$background_image['custom_size']}%";
    }

    $wrapper_style .= TemplateHelpers::get_style(
        [
            'background-image'    => "url({$background_image['url']})",
            'background-position' => $background_position,
            'background-repeat'   => $background_repeat,
            'background-size'     => $background_size,
        ]
    );
}//end if

$inner_style = TemplateHelpers::get_style(
    [
        'width'    => '100%',
        'overflow' => 'hidden',
    ]
);


ob_start();
?>

<div class="yaymail-inner-customizer-element-container" style="<?php echo esc_attr( $inner_style ); ?>">  
    <table style="width: 100%; background-color: inherit;" cellpadding="0" cellspacing="0" width="100%">
        <tbody>
            <?php
                ElementsLoader::render_elements(
                    $element['children'],
                    $args
                );
                ?>
        </tbody>
    </table>
</div>  


<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
