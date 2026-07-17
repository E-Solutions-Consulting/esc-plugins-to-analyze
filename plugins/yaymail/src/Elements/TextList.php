<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * TextList Elements
 */
class TextList extends BaseElement {

    use SingletonTrait;

    protected static $type = 'text_list';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <defs>
    <style>
      .cls-1 {
        fill: none;
      }
    </style>
  </defs>
  <rect class="cls-1" x="1" y="10.75" width="18" height="6.74"/>
  <rect class="cls-1" x="1" y="2.5" width="18" height="6.74"/>
  <rect x="1" y="9.25" width="18" height="1.5"/>
  <rect x="1" y="17.5" width="18" height="1.5"/>
  <rect x="1" y="1" width="18" height="1.5"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Text List', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 130,
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
                'text_color'                 => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'yaymail' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : YAYMAIL_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'number_column'              => [
                    'value_path'    => 'number_column',
                    'component'     => 'NumberColumn',
                    'title'         => __( 'Number of columns:', 'yaymail' ),
                    'default_value' => isset( $attributes['number_column'] ) ? $attributes['number_column'] : 2,
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
                'text_list'                  => [
                    'component'  => 'TextList',
                    'value_path' => 'text_list',
                    'column_1'   => [
                        'padding'                 => [
                            'value' => isset( $attributes['column_1']['padding'] ) ? $attributes['column_1']['padding'] : [
                                'top'    => '10',
                                'right'  => '10',
                                'bottom' => '10',
                                'left'   => '50',
                            ],
                            'type'  => 'style',
                        ],

                        'font_family'             => [
                            'value' => isset( $attributes['column_1']['font_family'] ) ? $attributes['column_1']['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                            'type'  => 'style',
                        ],

                        'rich_text'               => [
                            'value' => isset( $attributes['column_1']['rich_text'] ) ? $attributes['column_1']['rich_text'] : '<p><span style="font-size: 18px;"><strong>This is a title</strong></span></p><p><span> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy.</span></p><p><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p>',
                            'type'  => 'content',
                        ],

                        'show_button'             => [
                            'value' => isset( $attributes['column_1']['show_button'] ) ? $attributes['column_1']['show_button'] : true,
                            'type'  => 'content',
                        ],

                        'button_type'             => [
                            'value' => isset( $attributes['column_1']['button_type'] ) ? $attributes['column_1']['button_type'] : 'default',
                            'type'  => 'style',
                        ],

                        'button_align'            => [
                            'value' => isset( $attributes['column_1']['button_align'] ) ? $attributes['column_1']['button_align'] : 'center',
                            'type'  => 'style',
                        ],

                        'button_padding'          => [
                            'value' => isset( $attributes['column_1']['button_padding'] ) ? $attributes['column_1']['button_padding'] : [
                                'top'    => '0',
                                'right'  => '0',
                                'bottom' => '0',
                                'left'   => '50',
                            ],
                            'type'  => 'style',
                        ],

                        'button_border_radius'    => [
                            'value' => isset( $attributes['column_1']['button_border_radius'] ) ? $attributes['column_1']['button_border_radius'] : [
                                'top_left'     => '5',
                                'top_right'    => '5',
                                'bottom_right' => '5',
                                'bottom_left'  => '5',
                            ],
                            'type'  => 'style',
                        ],

                        'button_text'             => [
                            'value' => isset( $attributes['column_1']['button_text'] ) ? $attributes['column_1']['button_text'] : __( 'Click me', 'yaymail' ),
                            'type'  => 'content',
                        ],

                        'button_url'              => [
                            'value' => isset( $attributes['column_1']['button_url'] ) ? $attributes['column_1']['button_url'] : '#',
                            'type'  => 'content',
                        ],

                        'button_background_color' => [
                            'value' => isset( $attributes['column_1']['button_background_color'] ) ? $attributes['column_1']['button_background_color'] : YAYMAIL_COLOR_WC_DEFAULT,
                            'type'  => 'style',
                        ],

                        'button_text_color'       => [
                            'value' => isset( $attributes['column_1']['button_text_color'] ) ? $attributes['column_1']['button_text_color'] : '#ffffff',
                            'type'  => 'style',
                        ],

                        'button_font_size'        => [
                            'value' => isset( $attributes['column_1']['button_font_size'] ) ? $attributes['column_1']['button_font_size'] : '13',
                            'type'  => 'style',
                        ],

                        'button_height'           => [
                            'value' => isset( $attributes['column_1']['button_height'] ) ? $attributes['column_1']['button_height'] : '21',
                            'type'  => 'style',
                        ],

                        'button_width'            => [
                            'value' => isset( $attributes['column_1']['button_width'] ) ? $attributes['column_1']['button_width'] : '50',
                            'type'  => 'style',
                        ],

                        'button_weight'           => [
                            'value' => isset( $attributes['column_1']['button_weight'] ) ? $attributes['column_1']['button_weight'] : 'normal',
                            'type'  => 'style',
                        ],

                        'button_font_family'      => [
                            'value' => isset( $attributes['column_1']['button_font_family'] ) ? $attributes['column_1']['button_font_family'] : YAYMAIL_DEFAULT_FAMILY,
                            'type'  => 'style',
                        ],
                    ],
                    'column_2'   => [
                        'padding'                 => [
                            'value' => isset( $attributes['column_2']['padding'] ) ? $attributes['column_2']['padding'] : [
                                'top'    => '10',
                                'right'  => '50',
                                'bottom' => '10',
                                'left'   => '10',
                            ],
                            'type'  => 'style',
                        ],

                        'font_family'             => [
                            'value' => isset( $attributes['column_2']['font_family'] ) ? $attributes['column_2']['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                            'type'  => 'style',
                        ],

                        'rich_text'               => [
                            'value' => isset( $attributes['column_2']['rich_text'] ) ? $attributes['column_2']['rich_text'] : '<p><span style="font-size: 18px;"><strong>This is a title</strong></span></p><p><span> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy.</span></p><p><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p>',
                            'type'  => 'content',
                        ],

                        'show_button'             => [
                            'value' => isset( $attributes['column_2']['show_button'] ) ? $attributes['column_2']['show_button'] : true,
                            'type'  => 'content',
                        ],

                        'button_type'             => [
                            'value' => isset( $attributes['column_2']['button_type'] ) ? $attributes['column_2']['button_type'] : 'default',
                            'type'  => 'style',
                        ],

                        'button_align'            => [
                            'value' => isset( $attributes['column_2']['button_align'] ) ? $attributes['column_2']['button_align'] : 'center',
                            'type'  => 'style',
                        ],

                        'button_padding'          => [
                            'value' => isset( $attributes['column_2']['button_padding'] ) ? $attributes['column_2']['button_padding'] : [
                                'top'    => '0',
                                'right'  => '0',
                                'bottom' => '0',
                                'left'   => '10',
                            ],
                            'type'  => 'style',
                        ],

                        'button_border_radius'    => [
                            'value' => isset( $attributes['column_2']['button_border_radius'] ) ? $attributes['column_2']['button_border_radius'] : [
                                'top_left'     => '5',
                                'top_right'    => '5',
                                'bottom_right' => '5',
                                'bottom_left'  => '5',
                            ],
                            'type'  => 'style',
                        ],

                        'button_text'             => [
                            'value' => isset( $attributes['column_2']['button_text'] ) ? $attributes['column_2']['button_text'] : __( 'Click me', 'yaymail' ),
                            'type'  => 'content',
                        ],

                        'button_url'              => [
                            'value' => isset( $attributes['column_2']['button_url'] ) ? $attributes['column_2']['button_url'] : '#',
                            'type'  => 'content',
                        ],

                        'button_background_color' => [
                            'value' => isset( $attributes['column_2']['button_background_color'] ) ? $attributes['column_2']['button_background_color'] : YAYMAIL_COLOR_WC_DEFAULT,
                            'type'  => 'style',
                        ],

                        'button_text_color'       => [
                            'value' => isset( $attributes['column_2']['button_text_color'] ) ? $attributes['column_2']['button_text_color'] : '#ffffff',
                            'type'  => 'style',
                        ],

                        'button_font_size'        => [
                            'value' => isset( $attributes['column_2']['button_font_size'] ) ? $attributes['column_2']['button_font_size'] : '13',
                            'type'  => 'style',
                        ],

                        'button_height'           => [
                            'value' => isset( $attributes['column_2']['button_height'] ) ? $attributes['column_2']['button_height'] : '21',
                            'type'  => 'style',
                        ],

                        'button_width'            => [
                            'value' => isset( $attributes['column_2']['button_width'] ) ? $attributes['column_2']['button_width'] : '35',
                            'type'  => 'style',
                        ],

                        'button_weight'           => [
                            'value' => isset( $attributes['column_2']['button_weight'] ) ? $attributes['column_2']['button_weight'] : 'normal',
                            'type'  => 'style',
                        ],

                        'button_font_family'      => [
                            'value' => isset( $attributes['column_2']['button_font_family'] ) ? $attributes['column_2']['button_font_family'] : YAYMAIL_DEFAULT_FAMILY,
                            'type'  => 'style',
                        ],
                    ],
                    'column_3'   => [
                        'padding'                 => [
                            'value' => isset( $attributes['column_3']['padding'] ) ? $attributes['column_3']['padding'] : [
                                'top'    => '10',
                                'right'  => '50',
                                'bottom' => '10',
                                'left'   => '10',
                            ],
                            'type'  => 'style',
                        ],

                        'font_family'             => [
                            'value' => isset( $attributes['column_3']['font_family'] ) ? $attributes['column_3']['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                            'type'  => 'style',
                        ],

                        'rich_text'               => [
                            'value' => isset( $attributes['column_3']['rich_text'] ) ? $attributes['column_3']['rich_text'] : '<p><span style="font-size: 18px;"><strong>This is a title</strong></span></p><p><span> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy.</span></p><p><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p>',
                            'type'  => 'content',
                        ],

                        'show_button'             => [
                            'value' => isset( $attributes['column_3']['show_button'] ) ? $attributes['column_3']['show_button'] : true,
                            'type'  => 'content',
                        ],

                        'button_type'             => [
                            'value' => isset( $attributes['column_3']['button_type'] ) ? $attributes['column_3']['button_type'] : 'default',
                            'type'  => 'style',
                        ],

                        'button_align'            => [
                            'value' => isset( $attributes['column_3']['button_align'] ) ? $attributes['column_3']['button_align'] : 'center',
                            'type'  => 'style',
                        ],

                        'button_padding'          => [
                            'value' => isset( $attributes['column_3']['button_padding'] ) ? $attributes['column_3']['button_padding'] : [
                                'top'    => '0',
                                'right'  => '40',
                                'bottom' => '0',
                                'left'   => '10',
                            ],
                            'type'  => 'style',
                        ],

                        'button_border_radius'    => [
                            'value' => isset( $attributes['column_3']['button_border_radius'] ) ? $attributes['column_3']['button_border_radius'] : [
                                'top_left'     => '5',
                                'top_right'    => '5',
                                'bottom_right' => '5',
                                'bottom_left'  => '5',
                            ],
                            'type'  => 'style',
                        ],

                        'button_text'             => [
                            'value' => isset( $attributes['column_3']['button_text'] ) ? $attributes['column_3']['button_text'] : __( 'Click me', 'yaymail' ),
                            'type'  => 'content',
                        ],

                        'button_url'              => [
                            'value' => isset( $attributes['column_3']['button_url'] ) ? $attributes['column_3']['button_url'] : '#',
                            'type'  => 'content',
                        ],

                        'button_background_color' => [
                            'value' => isset( $attributes['column_3']['button_background_color'] ) ? $attributes['column_3']['button_background_color'] : YAYMAIL_COLOR_WC_DEFAULT,
                            'type'  => 'style',
                        ],

                        'button_text_color'       => [
                            'value' => isset( $attributes['column_3']['button_text_color'] ) ? $attributes['column_3']['button_text_color'] : '#ffffff',
                            'type'  => 'style',
                        ],

                        'button_font_size'        => [
                            'value' => isset( $attributes['column_3']['button_font_size'] ) ? $attributes['column_3']['button_font_size'] : '13',
                            'type'  => 'style',
                        ],

                        'button_height'           => [
                            'value' => isset( $attributes['column_3']['button_height'] ) ? $attributes['column_3']['button_height'] : '21',
                            'type'  => 'style',
                        ],

                        'button_width'            => [
                            'value' => isset( $attributes['column_3']['button_width'] ) ? $attributes['column_3']['button_width'] : '35',
                            'type'  => 'style',
                        ],

                        'button_weight'           => [
                            'value' => isset( $attributes['column_3']['button_weight'] ) ? $attributes['column_3']['button_weight'] : 'normal',
                            'type'  => 'style',
                        ],

                        'button_font_family'      => [
                            'value' => isset( $attributes['column_3']['button_font_family'] ) ? $attributes['column_3']['button_font_family'] : YAYMAIL_DEFAULT_FAMILY,
                            'type'  => 'style',
                        ],
                    ],
                ],
            ],
        ];
    }
}
