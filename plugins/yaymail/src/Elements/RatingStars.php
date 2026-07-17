<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * RatingStars Elements
 */
class RatingStars extends BaseElement {

    use SingletonTrait;

    protected static $type = 'rating_stars';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg width="21" height="18" viewBox="0 0 21 18" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_1493_40017)">
<path d="M17 -0.199951H3.99995C1.89995 -0.199951 0.199951 1.50005 0.199951 3.60005V10.8C0.199951 12.9 1.89995 14.6 3.99995 14.6H5.19995V16.7001C5.19995 17.0001 5.29995 17.2 5.59995 17.3C5.69995 17.4 5.79995 17.4 5.99995 17.4C6.09995 17.4 6.29995 17.4 6.39995 17.3L11.2 14.6H17C19.1 14.6 20.7999 12.9 20.7999 10.8V3.50005C20.7999 1.40005 19.1 -0.199951 17 -0.199951ZM19.2 10.7C19.2 11.9 18.2 12.9 17 12.9H11.3C11 12.9 10.7 13 10.4 13.1L6.69995 15.2V13.7C6.69995 13.3 6.39995 12.9 5.89995 12.9H3.99995C2.79995 12.9 1.79995 11.9 1.79995 10.7V3.50005C1.79995 2.30005 2.79995 1.30005 3.99995 1.30005H17C18.2 1.30005 19.2 2.30005 19.2 3.50005V10.7Z"/>
<path d="M9.1999 6.20005L7.8999 5.90005C7.7999 5.90005 7.6999 5.80005 7.6999 5.70005L6.9999 4.70005C6.8999 4.50005 6.4999 4.50005 6.3999 4.70005L5.7999 5.70005C5.7999 5.80005 5.6999 5.80005 5.5999 5.90005L4.2999 6.20005C3.9999 6.30005 3.8999 6.50005 4.0999 6.70005L4.9999 7.60005C4.9999 7.70005 5.0999 7.80005 4.9999 7.90005L4.8999 9.10005C4.8999 9.30005 5.0999 9.50005 5.3999 9.40005L6.5999 9.00005H6.8999L8.0999 9.50005C8.2999 9.60005 8.5999 9.40005 8.5999 9.20005L8.4999 8.00005C8.4999 7.90005 8.4999 7.80005 8.5999 7.80005L9.4999 6.90005C9.5999 6.50005 9.4999 6.20005 9.1999 6.20005Z"/>
<path d="M14.4999 7.90002H11.4999C11.1999 7.90002 10.8999 8.20002 10.8999 8.50002C10.8999 8.80002 11.1999 9.10003 11.4999 9.10003H14.4999C14.7999 9.10003 15.0999 8.80002 15.0999 8.50002C15.0999 8.20002 14.7999 7.90002 14.4999 7.90002Z"/>
<path d="M16.4999 5.40002H11.4999C11.1999 5.40002 10.8999 5.70002 10.8999 6.00002C10.8999 6.30002 11.1999 6.60003 11.4999 6.60003H16.4999C16.7999 6.60003 17.0999 6.30002 17.0999 6.00002C17.0999 5.70002 16.7999 5.40002 16.4999 5.40002Z"/>
</g>
<defs>
<clipPath id="clip0_1493_40017">
<rect width="21" height="18" fill="white"/>
</clipPath>
</defs>
</svg>
';

        return [
            'id'          => uniqid(),
            'type'        => self::$type,
            'name'        => __( 'Rating Stars', 'yaymail' ),
            'icon'        => self::$icon,
            'group'       => 'basic',
            'available'   => true,
            'position'    => 151,
            'status_info' => [
                'text' => __( 'New', 'yaymail' ),
            ],
            'data'        => [
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
                        'right'  => '15',
                        'bottom' => '15',
                        'left'   => '15',
                    ],
                    'type'          => 'style',
                ],
                'background_color'           => ElementsHelper::get_color(
                    $attributes,
                    [
                        'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    ]
                ),
                'align'                      => ElementsHelper::get_align( $attributes ),
                'content_breaker'            => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'   => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content settings', 'yaymail' ),
                    'description' => __( 'Handle content settings', 'yaymail' ),
                ],
                'total_stars'                => [
                    'value_path'    => 'total_stars',
                    'component'     => 'NumberInput',
                    'title'         => __( 'Total stars', 'yaymail' ),
                    'default_value' => isset( $attributes['total_stars'] ) ? $attributes['total_stars'] : '5',
                    'min'           => 1,
                    'max'           => 10,
                    'type'          => 'style',
                ],
                'active_stars'               => [
                    'value_path'     => 'active_stars',
                    'component'      => 'NumberInput',
                    'title'          => __( 'Active stars', 'yaymail' ),
                    'default_value'  => isset( $attributes['active_stars'] ) ? $attributes['active_stars'] : '5',
                    'min'            => 0,
                    'max'            => 10,
                    'max_dependency' => 'total_stars',
                    'type'           => 'style',
                ],
                'active_stars_color'         => ElementsHelper::get_color(
                    $attributes,
                    [
                        'title'         => __( 'Active color', 'yaymail' ),
                        'description'   => __( 'The color of the active stars', 'yaymail' ),
                        'value_path'    => 'active_stars_color',
                        'default_value' => isset( $attributes['active_stars_color'] ) ? $attributes['active_stars_color'] : '#FFD700',
                    ]
                ),
                'inactive_stars_color'       => ElementsHelper::get_color(
                    $attributes,
                    [
                        'title'         => __( 'Inactive color', 'yaymail' ),
                        'description'   => __( 'The color of the inactive stars', 'yaymail' ),
                        'value_path'    => 'inactive_stars_color',
                        'default_value' => isset( $attributes['inactive_stars_color'] ) ? $attributes['inactive_stars_color'] : '#E0E0E0',
                    ]
                ),
                'size'                       => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'size',
                        'title'         => __( 'Size', 'yaymail' ),
                        'default_value' => isset( $attributes['size'] ) ? $attributes['size'] : '40',
                        'min'           => 10,
                        'max'           => 100,
                    ]
                ),
                'spacing'                    => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'spacing',
                        'title'         => __( 'Spacing', 'yaymail' ),
                        'default_value' => isset( $attributes['spacing'] ) ? $attributes['spacing'] : '10',
                        'min'           => 10,
                        'max'           => 25,
                        'type'          => 'style',
                    ]
                ),
            ],
        ];
    }
}
