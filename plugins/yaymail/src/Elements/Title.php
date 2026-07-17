<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * Title Elements
 */
class Title extends BaseElement {

    use SingletonTrait;

    protected static $type = 'title';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.82,17.17H1.18c-.1,0-.18.06-.18.13v1.25c0,.07.08.13.18.13h17.64c.1,0,.18-.06.18-.13v-1.25c0-.07-.08-.13-.18-.13ZM4.19,15.04h1.91c.09,0,.18-.06.21-.15l1.21-3.73h4.93l1.2,3.73c.03.09.11.15.21.15h2s.05,0,.07-.01c.03,0,.05-.02.07-.04.02-.02.04-.04.05-.07.01-.03.02-.05.02-.08,0-.03,0-.06-.01-.08L11.39,1.15s-.04-.08-.08-.11c-.04-.03-.08-.04-.13-.04h-2.3c-.09,0-.18.06-.21.15L3.98,14.75s-.01.05-.01.07c0,.12.1.22.22.22ZM9.95,3.43h.09l1.89,5.94h-3.88l1.91-5.94Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Title', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 80,
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
                'text_color'                 => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'yaymail' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : '#444444',
                    'type'          => 'style',
                ],
                'font_family'                => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'align'                      => [
                    'value_path'    => 'align',
                    'component'     => 'Align',
                    'title'         => __( 'Text align', 'yaymail' ),
                    'default_value' => isset( $attributes['align'] ) ? $attributes['align'] : 'center',
                    'type'          => 'style',
                ],
                'title_breaker'              => [
                    'component' => 'LineBreaker',
                ],
                'title_group_definition'     => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Title settings', 'yaymail' ),
                    'description' => __( 'Handle title settings', 'yaymail' ),
                ],
                'title'                      => [
                    'value_path'    => 'title',
                    'component'     => 'TextInput',
                    'title'         => __( 'Text', 'yaymail' ),
                    'default_value' => isset( $attributes['title'] ) ? $attributes['title'] : __( 'Enter your title here', 'yaymail' ),
                    'type'          => 'content',
                ],
                'title_size'                 => [
                    'value_path'    => 'title_size',
                    'component'     => 'FontSizeSelector',
                    'title'         => __( 'Font size', 'yaymail' ),
                    'default_value' => isset( $attributes['title_size'] ) ? $attributes['title_size'] : 'default',
                    'type'          => 'style',
                ],
                'subtitle_breaker'           => [
                    'component' => 'LineBreaker',
                ],
                'subtitle_group_definition'  => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Subtitle settings', 'yaymail' ),
                    'description' => __( 'Handle subtitle settings', 'yaymail' ),
                ],
                'subtitle'                   => [
                    'value_path'    => 'subtitle',
                    'component'     => 'TextInput',
                    'title'         => __( 'Text', 'yaymail' ),
                    'default_value' => isset( $attributes['subtitle'] ) ? $attributes['subtitle'] : __( 'Subtitle', 'yaymail' ),
                    'type'          => 'content',
                ],
                'subtitle_size'              => [
                    'value_path'    => 'subtitle_size',
                    'component'     => 'FontSizeSelector',
                    'title'         => __( 'Font size', 'yaymail' ),
                    'default_value' => isset( $attributes['subtitle_size'] ) ? $attributes['subtitle_size'] : 'default',
                    'type'          => 'style',
                ],
            ],
        ];
    }
}
