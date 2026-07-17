<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * ImageList Elements
 */
class ImageList extends BaseElement {

    use SingletonTrait;

    protected static $type = 'image_list';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $src        = esc_url( YAYMAIL_PLUGIN_URL . 'assets/images/default-photo.png' );
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <g>
    <path d="M17.49,6.25v11.31H2.5V6.25h14.99M17.99,4.81H2c-.55,0-1,.43-1,.96v12.27c0,.53.45.96,1,.96h15.99c.55,0,1-.43,1-.96V5.77c0-.53-.45-.96-1-.96h0Z"/>
    <path d="M16.63,4.38H3.32c-.41,0-.75-.32-.75-.72s.34-.72.75-.72h13.31c.41,0,.75.32.75.72s-.34.72-.75.72Z"/>
    <path d="M14.87,2.44H5.25c-.41,0-.75-.32-.75-.72s.34-.72.75-.72h9.62c.41,0,.75.32.75.72s-.34.72-.75.72Z"/>
  </g>
  <path d="M12.23,15.84c-.65,0-1.3-.27-1.75-.78l-2.59-2.93c-.28-.32-.78-.36-1.11-.1l-4.36,3.46c-.32.25-.79.21-1.05-.1-.26-.31-.22-.76.1-1.01l4.36-3.46c.96-.76,2.4-.64,3.21.27l2.59,2.93c.27.3.74.36,1.07.12l5.11-3.58c.33-.23.8-.16,1.05.16.24.32.17.77-.16,1.01l-5.11,3.58c-.4.28-.88.42-1.35.42Z"/>
  <path d="M13.16,10.98c-1.06,0-1.93-.83-1.93-1.85s.87-1.85,1.93-1.85,1.93.83,1.93,1.85-.87,1.85-1.93,1.85ZM13.16,8.71c-.24,0-.43.19-.43.41s.19.41.43.41.43-.19.43-.41-.19-.41-.43-.41Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Image List', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 110,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'yaymail' ),
                    'description' => __( 'Handle container layout settings', 'yaymail' ),
                ],
                'background_color'           => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'number_column'              => [
                    'value_path'    => 'number_column',
                    'component'     => 'NumberColumn',
                    'title'         => __( 'Number of columns:', 'yaymail' ),
                    'default_value' => isset( $attributes['number_column'] ) ? $attributes['number_column'] : 3,
                    'type'          => 'content',
                ],
                'column_breaker'             => [
                    'component' => 'LineBreaker',
                ],
                'column_settings'            => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Column settings', 'yaymail' ),
                    'description' => __( 'Handle column settings', 'yaymail' ),
                ],
                'image_list'                 => [
                    'value_path'    => 'image_list',
                    'component'     => 'ImageList',
                    'default_value' => [
                        'column_1' => [
                            'align'            => [
                                'value' => isset( $attributes['column_1']['align'] ) ? $attributes['column_1']['align'] : 'center',
                                'type'  => 'style',
                            ],
                            'padding'          => [
                                'value' => isset( $attributes['column_1']['padding'] ) ? $attributes['column_1']['padding'] : [
                                    'top'    => '10',
                                    'right'  => '10',
                                    'bottom' => '10',
                                    'left'   => '50',
                                ],
                                'type'  => 'style',
                            ],
                            'image'            => [
                                'value' => isset( $attributes['column_1']['image'] ) ? $attributes['column_1']['image'] : $src,
                                'type'  => 'content',
                            ],
                            'full_width'       => [
                                'value' => isset( $attributes['column_1']['full_width'] ) ? $attributes['column_1']['full_width'] : false,
                                'type'  => 'style',
                            ],
                            'width'            => [
                                'value' => isset( $attributes['column_1']['width'] ) ? $attributes['column_1']['width'] : '100',
                                'type'  => 'style',
                            ],
                            'background_color' => [
                                'value' => isset( $attributes['column_1']['background_color'] ) ? $attributes['column_1']['background_color'] : '#fff',
                                'type'  => 'style',
                            ],
                            'url'              => [
                                'value' => isset( $attributes['column_1']['url'] ) ? $attributes['column_1']['url'] : '#',
                                'type'  => 'content',
                            ],
                            'alt'              => [
                                'value' => isset( $attributes['column_1']['alt'] ) ? $attributes['column_1']['alt'] : '',
                                'type'  => 'content',
                            ],
                        ],
                        'column_2' => [
                            'align'            => [
                                'value' => isset( $attributes['column_2']['align'] ) ? $attributes['column_2']['align'] : 'center',
                                'type'  => 'style',
                            ],
                            'padding'          => [
                                'value' => isset( $attributes['column_2']['padding'] ) ? $attributes['column_2']['padding'] : [
                                    'top'    => '10',
                                    'right'  => '10',
                                    'bottom' => '10',
                                    'left'   => '50',
                                ],
                                'type'  => 'style',
                            ],
                            'image'            => [
                                'value' => isset( $attributes['column_2']['image'] ) ? $attributes['column_2']['image'] : $src,
                                'type'  => 'content',
                            ],
                            'full_width'       => [
                                'value' => isset( $attributes['column_2']['full_width'] ) ? $attributes['column_2']['full_width'] : false,
                                'type'  => 'style',
                            ],
                            'width'            => [
                                'value' => isset( $attributes['column_2']['width'] ) ? $attributes['column_2']['width'] : '100',
                                'type'  => 'style',
                            ],
                            'background_color' => [
                                'value' => isset( $attributes['column_2']['background_color'] ) ? $attributes['column_2']['background_color'] : '#fff',
                                'type'  => 'style',
                            ],
                            'url'              => [
                                'value' => isset( $attributes['column_2']['url'] ) ? $attributes['column_2']['url'] : '#',
                                'type'  => 'content',
                            ],
                            'alt'              => [
                                'value' => isset( $attributes['column_2']['alt'] ) ? $attributes['column_2']['alt'] : '',
                                'type'  => 'content',
                            ],
                        ],
                        'column_3' => [
                            'align'            => [
                                'value' => isset( $attributes['column_3']['align'] ) ? $attributes['column_3']['align'] : 'center',
                                'type'  => 'style',
                            ],
                            'padding'          => [
                                'value' => isset( $attributes['column_3']['padding'] ) ? $attributes['column_3']['padding'] : [
                                    'top'    => '10',
                                    'right'  => '10',
                                    'bottom' => '10',
                                    'left'   => '50',
                                ],
                                'type'  => 'style',
                            ],
                            'image'            => [
                                'value' => isset( $attributes['column_3']['image'] ) ? $attributes['column_3']['image'] : $src,
                                'type'  => 'content',
                            ],
                            'full_width'       => [
                                'value' => isset( $attributes['column_3']['full_width'] ) ? $attributes['column_3']['full_width'] : false,
                                'type'  => 'style',
                            ],
                            'width'            => [
                                'value' => isset( $attributes['column_3']['width'] ) ? $attributes['column_3']['width'] : '100',
                                'type'  => 'style',
                            ],
                            'background_color' => [
                                'value' => isset( $attributes['column_3']['background_color'] ) ? $attributes['column_3']['background_color'] : '#fff',
                                'type'  => 'style',
                            ],
                            'url'              => [
                                'value' => isset( $attributes['column_3']['url'] ) ? $attributes['column_3']['url'] : '#',
                                'type'  => 'content',
                            ],
                            'alt'              => [
                                'value' => isset( $attributes['column_3']['alt'] ) ? $attributes['column_3']['alt'] : '',
                                'type'  => 'content',
                            ],
                        ],
                    ],
                ],

            ],
        ];
    }
}
