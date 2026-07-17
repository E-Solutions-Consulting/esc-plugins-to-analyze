<?php

defined( 'ABSPATH' ) || exit;

use YayMail\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'text-align'       => $data['align'] ?? 'center',
        'background-color' => $data['background_color'] ?? '#fff',
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$table_style = TemplateHelpers::get_style(
    [
        'display'         => 'inline-table',
        'border-collapse' => 'collapse',
    ]
);

$active_stars   = min( $data['active_stars'] ?? 5, $data['total_stars'] ?? 5 );
$total_stars    = $data['total_stars'] ?? 5;
$size           = $data['size'] ?? 40;
$spacing        = $data['spacing'] ?? 10;
$active_color   = $data['active_stars_color'] ?? '#FFD700';
$inactive_color = $data['inactive_stars_color'] ?? '#E0E0E0';

ob_start();
?>
<table class="yaymail-element-rating-stars" cellpadding="0" cellspacing="0" role="presentation" style="<?php echo esc_attr( $table_style ); ?>">
    <tbody>
        <tr>
            <td>
            <?php
            for ( $i = 0; $i < $total_stars; $i++ ) :
                $is_filled   = $i < floor( $active_stars );
                $color       = $is_filled ? $active_color : $inactive_color;
                $cell_styles = [
                    'color'         => $color,
                    'font-size'     => $size . 'px',
                    'line-height'   => 1,
                    'padding'       => '0',
                    'padding-left'  => TemplateHelpers::get_dimension_value( ( $spacing - 4 ) / 2, 'px' ),
                    'padding-right' => TemplateHelpers::get_dimension_value( ( $spacing - 4 ) / 2, 'px' ),
                    'display'       => 'inline-block',
                ];
                ?>
                <span style="<?php echo esc_attr( TemplateHelpers::get_style( $cell_styles ) ); ?>">&#9733;</span>
            <?php endfor; ?>
            </td>
        </tr>
    </tbody>
</table>
<?php

$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
