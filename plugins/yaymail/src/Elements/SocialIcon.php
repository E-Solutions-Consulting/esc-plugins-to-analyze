<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * SocialIcon Elements
 */
class SocialIcon extends BaseElement {

    use SingletonTrait;

    protected static $type = 'social_icon';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M15.51,13.49c-.65,0-1.26.23-1.73.61l-4.76-3.44c.08-.44.08-.89,0-1.32l4.76-3.44c.47.38,1.08.61,1.73.61,1.52,0,2.76-1.24,2.76-2.76s-1.24-2.76-2.76-2.76-2.76,1.24-2.76,2.76c0,.27.04.52.11.76l-4.52,3.27c-.67-.89-1.74-1.46-2.94-1.46-2.03,0-3.67,1.64-3.67,3.67s1.64,3.67,3.67,3.67c1.2,0,2.27-.58,2.94-1.46l4.52,3.27c-.07.24-.11.5-.11.76,0,1.52,1.24,2.76,2.76,2.76s2.76-1.24,2.76-2.76-1.24-2.76-2.76-2.76ZM15.51,2.56c.66,0,1.19.53,1.19,1.19s-.53,1.19-1.19,1.19-1.19-.53-1.19-1.19.53-1.19,1.19-1.19ZM5.41,12.02c-1.11,0-2.02-.91-2.02-2.02s.91-2.02,2.02-2.02,2.02.91,2.02,2.02-.91,2.02-2.02,2.02ZM15.51,17.44c-.66,0-1.19-.54-1.19-1.19s.53-1.19,1.19-1.19,1.19.53,1.19,1.19-.53,1.19-1.19,1.19Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Social Icon', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 90,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'yaymail' ),
                    'description' => __( 'Handle container layout settings', 'yaymail' ),
                ],
                'padding'                    => [
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
                'background_color'           => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'align'                      => [
                    'value_path'    => 'align',
                    'component'     => 'Align',
                    'title'         => __( 'Alignment', 'yaymail' ),
                    'default_value' => isset( $attributes['align'] ) ? $attributes['align'] : 'center',
                    'type'          => 'style',
                ],
                'content_breaker'            => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'   => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content settings', 'yaymail' ),
                    'description' => __( 'Handle content settings', 'yaymail' ),
                ],
                'width_icon'                 => [
                    'value_path'    => 'width_icon',
                    'component'     => 'Dimension',
                    'title'         => __( 'Icon size', 'yaymail' ),
                    'default_value' => isset( $attributes['width_icon'] ) ? $attributes['width_icon'] : '24',
                    'min'           => 0,
                    'max'           => 100,
                    'type'          => 'style',
                ],
                'spacing'                    => [
                    'value_path'    => 'spacing',
                    'component'     => 'Dimension',
                    'title'         => __( 'Icon spacing', 'yaymail' ),
                    'default_value' => isset( $attributes['spacing'] ) ? $attributes['spacing'] : '5',
                    'min'           => 0,
                    'max'           => 100,
                    'type'          => 'style',
                ],
                'theme'                      => [
                    'value_path'    => 'theme',
                    'component'     => 'SocialIconThemeSelector',
                    'title'         => __( 'Styles theme', 'yaymail' ),
                    'default_value' => isset( $attributes['style'] ) ? $attributes['style'] : 'Colorful',
                    'type'          => 'style',
                ],
                'icon_list'                  => [
                    'value_path'    => 'icon_list',
                    'component'     => 'SocialList',
                    'title'         => __( 'Social', 'yaymail' ),
                    'default_value' => isset( $attributes['icon_list'] ) ? $attributes['icon_list'] : [
                        [
                            'icon' => 'facebook',
                            'url'  => '#',
                        ],
                        [
                            'icon' => 'instagram',
                            'url'  => '#',
                        ],
                        [
                            'icon' => 'linkedin',
                            'url'  => '#',
                        ],
                    ],
                    'type'          => 'content',
                ],
            ],
        ];
    }
}
