<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
use YayMail\Constants\AttributesData;
/**
 * Button Elements
 */
class Button extends BaseElement {

    use SingletonTrait;

    protected static $type = 'button';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" version="1.1" viewBox="0 0 20 20">
  <path d="M10,1C5,1,1,5,1,10s4,9,9,9,9-4,9-9S15,1,10,1ZM10,17.5c-4.1,0-7.5-3.4-7.5-7.5s3.4-7.5,7.5-7.5,7.5,3.4,7.5,7.5-3.4,7.5-7.5,7.5Z"/>
  <path d="M12.8,9.2h-2.1v-2.1c0-.4-.3-.8-.8-.8s-.8.3-.8.8v2.1h-2.1c-.4,0-.8.3-.8.8s.3.8.8.8h2.1v2.1c0,.4.3.8.8.8s.8-.3.8-.8v-2.1h2.1c.4,0,.8-.3.8-.8s-.3-.8-.8-.8Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Button', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 60,
            'data'      => [
                'container_group_definition'      => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'yaymail' ),
                    'description' => __( 'Handle container layout settings', 'yaymail' ),
                ],
                'padding'                         => ElementsHelper::get_spacing(
                    $attributes,
                    [
                        'title' => __( 'Padding', 'yaymail' ),
                    ]
                ),
                'background_color'                => ElementsHelper::get_color(
                    $attributes,
                    [
                        'default_value' => '#fff',
                    ]
                ),
                'button_setting_breaker'          => [
                    'component' => 'LineBreaker',
                ],
                'button_group_definition'         => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Button settings', 'yaymail' ),
                    'description' => __( 'Handle button settings', 'yaymail' ),
                ],
                'button_type'                     => ElementsHelper::get_button_type_selector( $attributes ),
                'align'                           => ElementsHelper::get_align(
                    $attributes,
                    [
                        'title' => __( 'Button position', 'yaymail' ),
                    ]
                ),
                'width'                           => ElementsHelper::get_button_width_selector( $attributes ),
                'custom_width'                    => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'custom_width',
                        'title'         => __( 'Custom width', 'yaymail' ),
                        'default_value' => '50',
                        'min'           => 0,
                        'max'           => 100,
                        'unit'          => '%',
                        'conditions'    => [
                            'condition_1' => [
                                'attribute'  => 'width',
                                'comparison' => '==',
                                'value'      => 'custom',
                            ],
                        ],
                    ]
                ),
                'height'                          => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'height',
                        'title'         => __( 'Height', 'yaymail' ),
                        'default_value' => '21',
                        'min'           => 0,
                        'max'           => 100,
                    ]
                ),
                'button_padding'                  => [
                    'value_path'    => 'button_padding',
                    'component'     => 'Spacing',
                    'title'         => __( 'Padding', 'yaymail' ),
                    'default_value' => [
                        'top'    => '12',
                        'right'  => '20',
                        'bottom' => '12',
                        'left'   => '20',
                    ],
                    'type'          => 'style',
                ],
                'button_background_color'         => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'button_background_color',
                        'title'         => __( 'Background color', 'yaymail' ),
                        'default_value' => YAYMAIL_COLOR_WC_DEFAULT,
                    ]
                ),
                'text_color'                      => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'text_color',
                        'title'         => __( 'Text color', 'yaymail' ),
                        'default_value' => '#ffffff',
                    ]
                ),
                'border'                          => [
                    'value_path'    => 'border',
                    'component'     => 'Border',
                    'title'         => __( 'Border', 'yaymail' ),
                    'default_value' => isset( $attributes['border'] ) ? $attributes['border'] : AttributesData::BORDER_DEFAULT,
                    'type'          => 'style',
                ],
                'border_radius'                   => ElementsHelper::get_border_radius(
                    $attributes,
                    [
                        'value_path'    => 'border_radius',
                        'title'         => __( 'Border radius', 'yaymail' ),
                        'default_value' => [
                            'top_left'     => '5',
                            'top_right'    => '5',
                            'bottom_right' => '5',
                            'bottom_left'  => '5',
                        ],
                    ]
                ),
                'button_content_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Button content', 'yaymail' ),
                    'description' => __( 'Handle button content', 'yaymail' ),
                ],
                'text'                            => ElementsHelper::get_text_input(
                    $attributes,
                    [
                        'value_path'    => 'text',
                        'title'         => __( 'Button text', 'yaymail' ),
                        'default_value' => __( 'Click me', 'yaymail' ),
                    ]
                ),
                'url'                             => ElementsHelper::get_text_input( $attributes ),
                'font_size'                       => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'font_size',
                        'title'         => __( 'Font size', 'yaymail' ),
                        'default_value' => '13',
                        'min'           => 10,
                        'max'           => 40,
                    ]
                ),
                'weight'                          => ElementsHelper::get_font_weight_selector( $attributes ),
                'font_family'                     => ElementsHelper::get_font_family_selector( $attributes ),
            ],
        ];
    }
}
