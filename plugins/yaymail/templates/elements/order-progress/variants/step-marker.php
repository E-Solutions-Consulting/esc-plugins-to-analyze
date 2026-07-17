<?php
/**
 * Step marker variant for Order Progress.
 *
 * Variables expected from parent template:
 * - $steps, $current_step_index, $align, $table_style
 * - $connector_height, $connector_active_color, $connector_inactive_color
 * - $icon_size, $label_font_size, $label_font_family
 * - $legacy_label_active_color, $legacy_label_inactive_color
 */

defined( 'ABSPATH' ) || exit;

use YayMail\Utils\OrderProgressVariantHelpers;
use YayMail\Utils\TemplateHelpers;


if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$yaymail_op_max_steps = 5;
$display_steps        = array_slice( $steps, 0, $yaymail_op_max_steps );
$step_count           = count( $display_steps );

if ( $step_count < 1 ) {
    return;
}

$active_index = min( max( 0, (int) $current_step_index ), max( 0, $step_count - 1 ) );

$bubble_pad        = 6;
$bubble_size       = $icon_size + ( $bubble_pad * 2 );
$bubble_cell_size  = max( 42, $bubble_size );
$bubble_row_height = max( $bubble_size, $bubble_cell_size );
$small_dot_px      = 14;
$ring_outer_px     = 24;
$inner_dot_px      = 14;
// First/last column inset: align marker center with teardrop bubble center (mirror getStepMarkerEdgeTrackPaddingPx).
$edge_track_padding_px = max( 0, (int) round( $bubble_cell_size / 2 - $ring_outer_px / 2 ) );
// Connector bar: at least 1px (parent already clamps connector_height to 1–10).
$bar_height    = max( 1, (int) $connector_height );
$dot_ring_size = 3;

$current_step_for_accent = isset( $display_steps[ $active_index ] ) ? $display_steps[ $active_index ] : [];
$current_step_accent     = OrderProgressVariantHelpers::resolve_step_marker_accent_color(
    is_array( $current_step_for_accent ) ? $current_step_for_accent : [],
    $connector_active_color
);
$yaymail_ring_border     = OrderProgressVariantHelpers::blend_hex_with_white( $current_step_accent, 0.55 );

$yaymail_step_marker_presets = OrderProgressVariantHelpers::get_step_marker_presets();

$preset_key               = min( 5, max( 1, $step_count ) );
$column_width_percents    = $yaymail_step_marker_presets[ $preset_key ]['widths'];
$yaymail_step_edge_aligns = $yaymail_step_marker_presets[ $preset_key ]['aligns'];

$table_style = TemplateHelpers::get_style(
    [
        'width'            => '100%',
        'table-layout'     => 'auto',
        'border-collapse'  => 'collapse',
        'border-spacing'   => '0',
        'mso-table-lspace' => '0pt',
        'mso-table-rspace' => '0pt',
    ]
);

$step_marker_table_class = 'yaymail-element-order-progress yaymail-element-order-progress--step-marker';
?>
<table class="<?php echo esc_attr( $step_marker_table_class ); ?>" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="<?php echo esc_attr( $table_style ); ?>">
    <tbody>
        <tr class="yaymail-element-order-progress--step-marker-icon-row">
            <?php foreach ( $display_steps as $index => $step ) : ?>
                <?php
                $is_step_active  = $index <= $active_index;
                $is_current_step = $index === $active_index;

                $cell_align = isset( $yaymail_step_edge_aligns[ $index ] ) ? $yaymail_step_edge_aligns[ $index ] : 'center';

                $image_url = $is_current_step && is_array( $step )
                    ? OrderProgressVariantHelpers::get_step_image_url( $step )
                    : '';

                $raw_image_bg_color = is_array( $step ) ? (string) ( $step['image_bg_color'] ?? '' ) : '';
                $image_bg_color     = $raw_image_bg_color ? $raw_image_bg_color : ( $is_step_active ? $connector_active_color : $connector_inactive_color );

                $teardrop_style = TemplateHelpers::get_style(
                    [
                        'width'             => $bubble_cell_size . 'px',
                        'height'            => $bubble_cell_size . 'px',
                        'background-color'  => $image_bg_color ? $image_bg_color : 'transparent',
                        'border-radius'     => '35%',
                        'mso-border-radius' => '35%',
                        'text-align'        => 'center',
                        'vertical-align'    => 'middle',
                        'padding'           => '0',
                    ]
                );

                $pointer_fill       = $image_bg_color ? $image_bg_color : 'transparent';
                $icon_pointer_style = TemplateHelpers::get_style(
                    [
                        'width'        => '0',
                        'height'       => '0',
                        'border-left'  => '10px solid transparent',
                        'border-right' => '10px solid transparent',
                        'border-top'   => '12px solid ' . $pointer_fill,
                        'margin-left'  => 'auto !important',
                        'margin-right' => 'auto !important',
                        'font-size'    => '0',
                        'line-height'  => '0',
                    ]
                );

                $icon_pct = isset( $column_width_percents[ $index ] ) ? (int) $column_width_percents[ $index ] : (int) floor( 100 / max( 1, $step_count ) );
                ?>
                <td width="<?php echo esc_attr( (string) $icon_pct ); ?>%" style="
                <?php
                echo esc_attr(
                    TemplateHelpers::get_style(
                        [
                            'vertical-align' => 'top',
                            'padding'        => '0',
                            'width'          => $icon_pct . '%',
                            'text-align'     => $cell_align,
                            'height'         => $bubble_row_height . 'px',
                        ]
                    )
                );
                ?>
                            ">
                    <?php if ( $is_current_step && ! empty( $image_url ) ) : ?>
                        <?php
                        $icon_table_margin = '0';
                        if ( 'center' === $cell_align ) {
                            $icon_table_margin = '0 auto';
                        } elseif ( 'right' === $cell_align ) {
                            $icon_table_margin = '0 0 0 auto';
                        }
                        ?>
                        <table width="<?php echo esc_attr( (string) $bubble_cell_size ); ?>" cellpadding="0" cellspacing="0" role="presentation" align="<?php echo esc_attr( $cell_align ); ?>" style="
                                                <?php
                                                    echo esc_attr(
                                                        TemplateHelpers::get_style(
                                                            [
                                                                'margin'          => $icon_table_margin,
                                                                'border-collapse' => 'collapse',
                                                            ]
                                                        )
                                                    );
                                                ?>
                                        ">
                            <tbody>
                                <tr>
                                    <td width="<?php echo esc_attr( (string) $bubble_cell_size ); ?>" height="<?php echo esc_attr( (string) $bubble_cell_size ); ?>" align="center" valign="middle" style="<?php echo esc_attr( $teardrop_style ); ?>">
                                        <img src="<?php echo esc_url( $image_url ); ?>" alt="" width="<?php echo esc_attr( (string) $icon_size ); ?>" height="<?php echo esc_attr( (string) $icon_size ); ?>" style="display:block;margin:0 auto;border:0;outline:none;text-decoration:none;width:<?php echo esc_attr( (string) $icon_size ); ?>px;height:<?php echo esc_attr( (string) $icon_size ); ?>px;"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="<?php echo esc_attr( (string) $bubble_cell_size ); ?>" align="center" style="
                                    <?php
                                    echo esc_attr(
                                        TemplateHelpers::get_style(
                                            [
                                                'width'   => $bubble_cell_size . 'px',
                                                'padding' => '0',
                                                'text-align' => 'center',
                                                'line-height' => '0',
                                                'font-size' => '0',
                                            ]
                                        )
                                    );
                                    ?>
                                                    ">
                                        <div style="<?php echo esc_attr( $icon_pointer_style ); ?>">&nbsp;</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <span style="
                        <?php
                        echo esc_attr(
                            TemplateHelpers::get_style(
                                [
                                    'display' => 'inline-block',
                                    'height'  => $bubble_row_height . 'px',
                                ]
                            )
                        );
                        ?>
                                        ">&nbsp;</span>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>

        <tr class="yaymail-element-order-progress--step-marker-track-row">
            <td colspan="<?php echo esc_attr( (string) $step_count ); ?>" style="<?php echo esc_attr( TemplateHelpers::get_style( [ 'padding' => '0' ] ) ); ?>">
                <table class="yaymail-element-order-progress--step-marker-track-inner" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="<?php echo esc_attr( $table_style ); ?>">
                    <tbody>
                        <tr>
                            <?php foreach ( $display_steps as $index => $step ) : ?>
                                <?php
                                $is_step_active  = $index <= $active_index;
                                $is_current_step = $index === $active_index;

                                $col_pct    = isset( $column_width_percents[ $index ] ) ? (int) $column_width_percents[ $index ] : (int) floor( 100 / max( 1, $step_count ) );
                                $dot_cell_w = $is_current_step ? $ring_outer_px : $small_dot_px;
                                $plain_fill = $is_step_active ? $connector_active_color : $connector_inactive_color;

                                $segment_colors = OrderProgressVariantHelpers::get_connector_segment_colors(
                                    $index,
                                    $active_index,
                                    $step_count,
                                    $connector_active_color,
                                    $connector_inactive_color
                                );
                                $left_color     = $segment_colors['left'];
                                $right_color    = $segment_colors['right'];

                                $cell_style = TemplateHelpers::get_style(
                                    [
                                        'width'          => $col_pct . '%',
                                        'vertical-align' => 'middle',
                                        'padding'        => '0',
                                        'padding-left'   => ( 0 === (int) $index ) ? $edge_track_padding_px . 'px' : '0',
                                        'padding-right'  => ( (int) $index === $step_count - 1 ) ? $edge_track_padding_px . 'px' : '0',
                                        'text-align'     => 'center',
                                        'line-height'    => '0',
                                        'font-size'      => '0',
                                    ]
                                );

                                $bar_left_style = TemplateHelpers::get_style(
                                    [
                                        'height'      => $bar_height . 'px',
                                        'background'  => $left_color,
                                        'width'       => '100%',
                                        'font-size'   => '0',
                                        'line-height' => '0',
                                    ]
                                );

                                $bar_right_style = TemplateHelpers::get_style(
                                    [
                                        'height'      => $bar_height . 'px',
                                        'background'  => $right_color,
                                        'width'       => '100%',
                                        'font-size'   => '0',
                                        'line-height' => '0',
                                    ]
                                );
                                ?>
                                <td width="<?php echo esc_attr( (string) $col_pct ); ?>%" align="center" valign="middle" style="<?php echo esc_attr( $cell_style ); ?>">
                                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="<?php echo esc_attr( TemplateHelpers::get_style( [ 'border-collapse' => 'collapse' ] ) ); ?>">
                                        <tbody>
                                            <tr>
                                                <?php if ( 0 !== (int) $index ) : ?>
                                                    <td valign="middle" style="
                                                    <?php
                                                    echo esc_attr(
                                                        TemplateHelpers::get_style(
                                                            [
                                                                'vertical-align' => 'middle',
                                                                'padding'        => '0',
                                                            ]
                                                        )
                                                    );
                                                    ?>
                                                                                ">
                                                        <div style="<?php echo esc_attr( $bar_left_style ); ?>">&nbsp;</div>
                                                    </td>
                                                <?php endif; ?>

                                                <td width="<?php echo esc_attr( (string) $dot_cell_w ); ?>" align="center" valign="middle" style="
                                                                        <?php
                                                                        echo esc_attr(
                                                                            TemplateHelpers::get_style(
                                                                                [
                                                                                    'width'   => $dot_cell_w . 'px',
                                                                                    'vertical-align' => 'middle',
                                                                                    'padding' => '0',
                                                                                    'text-align' => 'center',
                                                                                    'line-height' => '0',
                                                                                    'font-size' => '0',
                                                                                ]
                                                                            )
                                                                        );
                                                                        ?>
                                                            ">
                                                    <?php if ( $is_current_step ) : ?>
                                                        <?php
                                                        $inner_dot_margin_top = max(
                                                            0,
                                                            (int) floor( ( $ring_outer_px - ( 2 * $dot_ring_size ) - $inner_dot_px ) / 2 )
                                                        );
                                                        $ring_outer_div_style = TemplateHelpers::get_style(
                                                            [
                                                                'width'         => $ring_outer_px . 'px',
                                                                'height'        => $ring_outer_px . 'px',
                                                                'border-radius' => ( (int) ( $ring_outer_px ) ) . 'px',
                                                                'border'        => $dot_ring_size . 'px solid ' . $yaymail_ring_border,
                                                                'background'    => '#ffffff',
                                                                'box-sizing'    => 'border-box',
                                                                'margin'        => '0 auto',
                                                            ]
                                                        );
                                                        $inner_dot_style      = TemplateHelpers::get_style(
                                                            [
                                                                'width'         => $inner_dot_px . 'px',
                                                                'height'        => $inner_dot_px . 'px',
                                                                'border-radius' => ( (int) ( $inner_dot_px ) ) . 'px',
                                                                'background'    => $current_step_accent,
                                                                'margin'        => $inner_dot_margin_top + 3 . 'px auto 0',
                                                                'font-size'     => '0',
                                                                'line-height'   => '0',
                                                            ]
                                                        );
                                                        ?>
                                                        <table cellpadding="0" cellspacing="0" role="presentation" align="center" style="
                                                        <?php
                                                        echo esc_attr(
                                                            TemplateHelpers::get_style(
                                                                [
                                                                    'margin'          => '0 auto',
                                                                    'border-collapse' => 'collapse',
                                                                ]
                                                            )
                                                        );
                                                        ?>
                                                    ">
                                                            <tr>
                                                                <td width="<?php echo esc_attr( (string) $ring_outer_px ); ?>" height="<?php echo esc_attr( (string) $ring_outer_px ); ?>" align="center" valign="middle" style="<?php echo esc_attr( TemplateHelpers::get_style( [ 'padding' => '0' ] ) ); ?>">
                                                                    <div style="<?php echo esc_attr( $ring_outer_div_style ); ?>">
                                                                        <div style="<?php echo esc_attr( $inner_dot_style ); ?>">&nbsp;</div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    <?php else : ?>
                                                        <?php
                                                        $plain_dot_style = TemplateHelpers::get_style(
                                                            [
                                                                'width'         => $small_dot_px . 'px',
                                                                'height'        => $small_dot_px . 'px',
                                                                'border-radius' => ( (int) ( $small_dot_px / 2 ) ) . 'px',
                                                                'background'    => $plain_fill,
                                                                'margin'        => '0 3px',
                                                                'font-size'     => '0',
                                                                'line-height'   => '0',
                                                            ]
                                                        );
                                                        ?>
                                                        <div style="<?php echo esc_attr( $plain_dot_style ); ?>">&nbsp;</div>
                                                    <?php endif; ?>
                                                </td>

                                                <?php if ( (int) $index !== $step_count - 1 ) : ?>
                                                    <td valign="middle" style="
                                                    <?php
                                                    echo esc_attr(
                                                        TemplateHelpers::get_style(
                                                            [
                                                                'vertical-align' => 'middle',
                                                                'padding'        => '0',
                                                            ]
                                                        )
                                                    );
                                                    ?>
                                                                                ">
                                                        <div style="<?php echo esc_attr( $bar_right_style ); ?>">&nbsp;</div>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>

        <tr class="yaymail-element-order-progress--step-marker-labels-row">
            <td colspan="<?php echo esc_attr( (string) $step_count ); ?>" style="<?php echo esc_attr( TemplateHelpers::get_style( [ 'padding' => '10px 0 0' ] ) ); ?>">
                <table class="yaymail-element-order-progress--step-marker-labels-inner" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="<?php echo esc_attr( $table_style ); ?>">
                    <tbody>
                        <tr>
                            <?php foreach ( $display_steps as $index => $step ) : ?>
                                <?php
                                $is_step_active = $index <= $active_index;

                                $title = is_array( $step ) ? (string) ( $step['title'] ?? $step['label'] ?? '' ) : '';

                                $label_color = OrderProgressVariantHelpers::get_step_label_color(
                                    is_array( $step ) ? $step : [],
                                    $is_step_active,
                                    $legacy_label_active_color,
                                    $legacy_label_inactive_color
                                );

                                $label_align = isset( $yaymail_step_edge_aligns[ $index ] ) ? $yaymail_step_edge_aligns[ $index ] : 'center';

                                $label_col_pct = isset( $column_width_percents[ $index ] ) ? (int) $column_width_percents[ $index ] : (int) floor( 100 / max( 1, $step_count ) );

                                $label_style = TemplateHelpers::get_style(
                                    [
                                        'margin'      => '0',
                                        'padding'     => '0',
                                        'font-size'   => $label_font_size . 'px',
                                        'line-height' => '1.2',
                                        'color'       => $label_color,
                                        'text-align'  => $label_align,
                                        'font-family' => $label_font_family,
                                    ]
                                );
                                ?>
                                <td width="<?php echo esc_attr( (string) $label_col_pct ); ?>%" align="<?php echo esc_attr( $label_align ); ?>" style="
                                <?php
                                echo esc_attr(
                                    TemplateHelpers::get_style(
                                        [
                                            'vertical-align' => 'top',
                                            'padding'    => '0',
                                            'width'      => $label_col_pct . '%',
                                            'text-align' => $label_align,
                                        ]
                                    )
                                );
                                ?>
                                            ">
                                    <?php if ( '' !== $title ) : ?>
                                        <p style="<?php echo esc_attr( $label_style ); ?>"><?php echo esc_html( $title ); ?></p>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
