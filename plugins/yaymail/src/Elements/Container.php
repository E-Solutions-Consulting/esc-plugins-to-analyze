<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Container Elements
 */
class Container extends BaseElement {

    use SingletonTrait;

    protected static $type = 'container';

    public $available_email_ids = [];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M15 0.699951H3C1.5 0.699951 0.199997 1.99995 0.199997 3.49995V15.5C0.199997 17 1.4 18.2999 3 18.2999H15C16.5 18.2999 17.8 17.1 17.8 15.5V3.49995C17.8 1.99995 16.5 0.699951 15 0.699951ZM1.8 3.49995C1.8 2.79995 2.4 2.29995 3 2.29995H8.2V6.09995H1.8V3.49995ZM3 16.7C2.3 16.7 1.8 16.1 1.8 15.5V7.59995H8.3V16.7H3ZM16.2 15.5C16.2 16.2 15.6 16.7 15 16.7H9.8V2.29995H15C15.7 2.29995 16.2 2.89995 16.2 3.49995V15.5Z"/>
</svg>
';

        return [
            'id'              => uniqid(),
            'type'            => self::$type,
            'name'            => __( 'Container', 'yaymail' ),
            'icon'            => self::$icon,
            'group'           => 'general',
            'available'       => false,
            'disabled_reason' => [
                'html' => __( 'This element is available in YayMail Pro', 'yaymail' ),
            ],
            'position'        => 145,
            'children'        => isset( $attributes['children'] ) ? $attributes['children'] : [],
            'status_info'     => [
                'text' => __( 'New', 'yaymail' ),
            ],
            'data'            => [
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
                'background_color' => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'background_image' => [
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
                'direction'        => [
                    'value_path'    => 'direction',
                    'component'     => 'Selector',
                    'title'         => __( 'Direction', 'yaymail' ),
                    'default_value' => isset( $attributes['direction'] ) ? $attributes['direction'] : 'horizontal',
                    'options'       => [
                        [
                            'label' => __( 'Horizontal', 'yaymail' ),
                            'value' => 'horizontal',
                        ],
                        [
                            'label' => __( 'Vertical', 'yaymail' ),
                            'value' => 'vertical',
                        ],
                    ],
                    'type'          => 'style',
                ],
            ],
        ];
    }
}
