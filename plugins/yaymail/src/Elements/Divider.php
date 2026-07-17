<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Divider Elements
 */
class Divider extends BaseElement {

    use SingletonTrait;

    protected static $type = 'divider';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.82,9.25H1.18c-.1,0-.18.06-.18.13v1.25c0,.07.08.13.18.13h17.64c.1,0,.18-.06.18-.13v-1.25c0-.07-.08-.13-.18-.13Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Divider', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'general',
            'available' => true,
            'position'  => 140,
            'data'      => [
                'align'            => [
                    'value_path'    => 'align',
                    'component'     => 'Align',
                    'title'         => __( 'Align', 'yaymail' ),
                    'default_value' => isset( $attributes['align'] ) ? $attributes['align'] : 'center',
                    'type'          => 'style',
                ],
                'padding'          => [
                    'value_path'    => 'padding',
                    'component'     => 'Spacing',
                    'title'         => __( 'Padding', 'yaymail' ),
                    'default_value' => isset( $attributes['padding'] ) ? $attributes['padding'] : [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'type'          => 'style',
                ],
                'width'            => [
                    'value_path'    => 'width',
                    'component'     => 'Dimension',
                    'title'         => __( 'Width', 'yaymail' ),
                    'default_value' => isset( $attributes['width'] ) ? $attributes['width'] : '100',
                    'min'           => 0,
                    'max'           => 100,
                    'unit'          => '%',
                    'type'          => 'style',
                ],
                'height'           => [
                    'value_path'    => 'height',
                    'component'     => 'Dimension',
                    'title'         => __( 'Height', 'yaymail' ),
                    'default_value' => isset( $attributes['height'] ) ? $attributes['height'] : '6',
                    'min'           => 1,
                    'max'           => 30,
                    'type'          => 'style',
                ],
                'background_color' => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'divider_color'    => [
                    'value_path'    => 'divider_color',
                    'component'     => 'Color',
                    'title'         => __( 'Line color', 'yaymail' ),
                    'default_value' => isset( $attributes['divider_color'] ) ? $attributes['divider_color'] : '#333',
                    'type'          => 'style',
                ],
                'divider_type'     => [
                    'value_path'    => 'divider_type',
                    'component'     => 'DividerTypeSelector',
                    'title'         => __( 'Line type', 'yaymail' ),
                    'default_value' => isset( $attributes['divider_type'] ) ? $attributes['divider_type'] : 'solid',
                    'options'       => [
                        [
                            'label' => __( 'Solid', 'yaymail' ),
                            'value' => 'solid',
                        ],
                        [
                            'label' => __( 'Double', 'yaymail' ),
                            'value' => 'double',
                        ],
                        [
                            'label' => __( 'Dotted', 'yaymail' ),
                            'value' => 'dotted',
                        ],
                        [
                            'label' => __( 'Dashed', 'yaymail' ),
                            'value' => 'dashed',
                        ],
                    ],
                    'type'          => 'style',
                ],
            ],
        ];
    }
}
