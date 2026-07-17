<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Constants\AttributesData;
use YayMail\Utils\SingletonTrait;

/**
 * Column Elements
 */
class ColumnLayout extends BaseElement {

    use SingletonTrait;

    protected static $type = 'column_layout';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $amount = 1, $attributes = [] ) {
        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => self::get_name( $amount ),
            'icon'      => self::get_icon( $amount ),
            'group'     => 'general',
            'available' => true,
            'position'  => 150,
            'children'  => isset( $attributes['children'] ) ? $attributes['children'] : self::get_nested_columns( $amount ),
            'data'      => [
                'amount_of_columns'                  => [
                    'value_path'    => 'amount_of_columns',
                    'default_value' => $amount,
                ],
                'column_width'                       => [
                    'value_path'    => 'column_width',
                    'component'     => 1 == $amount ? '' : 'ColumnWidth',
                    'title'         => __( 'Column width (%)', 'yaymail' ),
                    'default_value' => [],
                // Trick to bypass checking value_path
                ],
                'column_spacing'                     => [
                    'value_path'    => 'column_spacing',
                    'component'     => 'Dimension',
                    'title'         => __( 'Column spacing', 'yaymail' ),
                    'default_value' => isset( $attributes['column_spacing'] ) ? $attributes['column_spacing'] : '0',
                    'min'           => 0,
                    'max'           => 100,
                    'type'          => 'style',
                ],
                'responsive_on_mobile'               => [
                    'value_path'    => 'responsive_on_mobile',
                    'component'     => 'Switcher',
                    'title'         => __( 'Responsive on mobile', 'yaymail' ),
                    'default_value' => isset( $attributes['responsive_on_mobile'] ) ? (bool) $attributes['responsive_on_mobile'] : false,
                    'type'          => 'content',
                ],
                'vertical_align'                     => [
                    'value_path'    => 'vertical_align',
                    'component'     => 'Selector',
                    'title'         => __( 'Vertical align', 'yaymail' ),
                    'default_value' => isset( $attributes['vertical_align'] ) ? $attributes['vertical_align'] : 'top',
                    'options'       => [
                        [
                            'label' => __( 'Top', 'yaymail' ),
                            'value' => 'top',
                        ],
                        [
                            'label' => __( 'Middle', 'yaymail' ),
                            'value' => 'middle',
                        ],
                        [
                            'label' => __( 'Bottom', 'yaymail' ),
                            'value' => 'bottom',
                        ],
                    ],
                    'type'          => 'style',
                ],
                'container_setting_breaker'          => [
                    'component' => 'LineBreaker',
                ],
                'container_setting_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container layout settings', 'yaymail' ),
                    'description' => __( 'Handle container layout settings', 'yaymail' ),
                ],
                'padding'                            => [
                    'value_path'    => 'padding',
                    'component'     => 'Spacing',
                    'title'         => __( 'Padding', 'yaymail' ),
                    'default_value' => isset( $attributes['padding'] ) ? $attributes['padding'] : [
                        'top'    => '15',
                        'right'  => '0',
                        'bottom' => '15',
                        'left'   => '0',
                    ],
                    'type'          => 'style',
                ],
                'border'                             => [
                    'value_path'    => 'border',
                    'component'     => 'Border',
                    'title'         => __( 'Border', 'yaymail' ),
                    'default_value' => isset( $attributes['border'] ) ? $attributes['border'] : AttributesData::BORDER_DEFAULT,
                    'type'          => 'style',
                ],
                'border_radius'                      => [
                    'value_path'    => 'border_radius',
                    'component'     => 'BorderRadius',
                    'title'         => __( 'Border radius', 'yaymail' ),
                    'default_value' => [
                        'top_left'     => isset( $attributes['border_radius']['top_left'] ) ? $attributes['border_radius']['top_left'] : '0',
                        'top_right'    => isset( $attributes['border_radius']['top_right'] ) ? $attributes['border_radius']['top_right'] : '0',
                        'bottom_left'  => isset( $attributes['border_radius']['bottom_left'] ) ? $attributes['border_radius']['bottom_left'] : '0',
                        'bottom_right' => isset( $attributes['border_radius']['bottom_right'] ) ? $attributes['border_radius']['bottom_right'] : '0',
                    ],
                    'type'          => 'style',
                ],
                'background_color'                   => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'background_image'                   => [
                    'value_path'    => 'background_image',
                    'component'     => 'BackgroundImage',
                    'title'         => __( 'Background image', 'yaymail' ),
                    'default_value' => isset( $attributes['background_image'] ) ? $attributes['background_image'] : [
                        'url'        => '',
                        'position'   => 'default',
                        'x_position' => 0,
                        'y_position' => 0,
                        'repeat'     => 'default',
                        'size'       => 'default',
                    ],
                    'type'          => 'style',
                ],
                'inner_setting_breaker'              => [
                    'component' => 'LineBreaker',
                ],
                'inner_setting_group_definition'     => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Inner layout settings', 'yaymail' ),
                    'description' => __( 'Handle inner layout settings', 'yaymail' ),
                ],
                'inner_border_radius'                => [
                    'value_path'    => 'inner_border_radius',
                    'component'     => 'BorderRadius',
                    'title'         => __( 'Inner border radius', 'yaymail' ),
                    'default_value' => [
                        'top_left'     => isset( $attributes['inner_border_radius']['top_left'] ) ? $attributes['inner_border_radius']['top_left'] : '0',
                        'top_right'    => isset( $attributes['inner_border_radius']['top_right'] ) ? $attributes['inner_border_radius']['top_right'] : '0',
                        'bottom_left'  => isset( $attributes['inner_border_radius']['bottom_left'] ) ? $attributes['inner_border_radius']['bottom_left'] : '0',
                        'bottom_right' => isset( $attributes['inner_border_radius']['bottom_right'] ) ? $attributes['inner_border_radius']['bottom_right'] : '0',
                    ],
                    'type'          => 'style',
                ],
                'inner_background_color'             => [
                    'value_path'    => 'inner_background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Inner background color', 'yaymail' ),
                    'default_value' => isset( $attributes['inner_background_color'] ) ? $attributes['inner_background_color'] : '#ffffff00',
                    'type'          => 'style',
                ],
                'column_borders'                     => [
                    'component' => 'ColumnBorders',
                    'title'     => __( 'Column borders', 'yaymail' ),
                ],
            ],
        ];
    }

    private static function get_nested_columns( $amount ) {
        if ( ! isset( $amount ) || $amount <= 0 ) {
            return [];
        }
        $width = 100 / $amount;

        $nested_columms = [];
        for ( $i = 0; $i < $amount; $i++ ) {
            array_push(
                $nested_columms,
                Column::get_data( $width )
            );
        }

        return $nested_columms;
    }

    private static function get_name( $amount ) {
        $column_names = [
            1 => __( 'One Column', 'yaymail' ),
            2 => __( 'Two Columns', 'yaymail' ),
            3 => __( 'Three Columns', 'yaymail' ),
            4 => __( 'Four Columns', 'yaymail' ),
        ];

        return isset( $column_names[ $amount ] ) ? $column_names[ $amount ] : 'Column';
    }

    private static function get_icon( $amount ) {
        $icons = [
            1 => '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.75,1.25v17.5H1.25V1.25h17.5M19,1H1v18h18V1h0Z"/>
  <rect x="8.4" y="1.94" width="3.2" height="16.12"/>
</svg>',
            2 => '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.75,1.25v17.5H1.25V1.25h17.5M19,1H1v18h18V1h0Z"/>
  <rect x="2.12" y="1.94" width="7.2" height="16.12"/>
  <rect x="10.66" y="1.94" width="7.2" height="16.12"/>
</svg>',
            3 => '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.75,1.25v17.5H1.25V1.25h17.5M19,1H1v18h18V1h0Z"/>
  <rect x="2.07" y="1.94" width="4.36" height="16.12"/>
  <rect x="7.79" y="1.94" width="4.36" height="16.12"/>
  <rect x="13.51" y="1.94" width="4.36" height="16.12"/>
</svg>',
            4 => '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.75,1.25v17.5H1.25V1.25h17.5M19,1H1v18h18V1h0Z"/>
  <rect x="2.07" y="1.94" width="3.2" height="16.12"/>
  <rect x="6.26" y="1.94" width="3.2" height="16.12"/>
  <rect x="10.46" y="1.94" width="3.2" height="16.12"/>
  <rect x="14.66" y="1.94" width="3.2" height="16.12"/>
</svg>',
        ];

        return isset( $icons[ $amount ] ) ? $icons[ $amount ] : $icons[1];
    }
}
