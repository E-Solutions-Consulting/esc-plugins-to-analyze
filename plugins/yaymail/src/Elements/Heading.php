<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Constants\AttributesData;
use YayMail\Utils\SingletonTrait;
use YayMail\Utils\TemplateHelpers;
/**
 * EmailHeading Elements
 */
class Heading extends BaseElement {

    use SingletonTrait;

    protected static $type = 'heading';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {

        $style = TemplateHelpers::get_style(
            [
                'font-size'   => '30px',
                'font-weight' => '300',
                'line-height' => 'normal',
                'margin'      => '0px',
                'color'       => 'inherit',
            ]
        );

        if ( isset( $attributes['rich_text'] ) ) {
            $content = '<h1 style="' . esc_attr( $style ) . '">' . $attributes['rich_text'] . '</h1>';
        } else {
            $content = __( 'Email Heading', 'yaymail' );
            $content = '<h1 style="' . esc_attr( $style ) . '">' . $content . '</h1>';
        }

        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.82,17.17H1.18c-.1,0-.18.06-.18.13v1.25c0,.07.08.13.18.13h17.64c.1,0,.18-.06.18-.13v-1.25c0-.07-.08-.13-.18-.13ZM4.19,15.04h1.91c.09,0,.18-.06.21-.15l1.21-3.73h4.93l1.2,3.73c.03.09.11.15.21.15h2s.05,0,.07-.01c.03,0,.05-.02.07-.04.02-.02.04-.04.05-.07.01-.03.02-.05.02-.08,0-.03,0-.06-.01-.08L11.39,1.15s-.04-.08-.08-.11c-.04-.03-.08-.04-.13-.04h-2.3c-.09,0-.18.06-.21.15L3.98,14.75s-.01.05-.01.07c0,.12.1.22.22.22ZM9.95,3.43h.09l1.89,5.94h-3.88l1.91-5.94Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Email Heading', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 20,
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
                        'top'    => '40',
                        'right'  => '50',
                        'bottom' => '40',
                        'left'   => '50',
                    ],
                    'type'          => 'style',
                ],
                'background_color'           => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : YAYMAIL_COLOR_WC_DEFAULT,
                    'type'          => 'style',
                ],
                'border'                     => [
                    'value_path'    => 'border',
                    'component'     => 'Border',
                    'title'         => __( 'Border', 'yaymail' ),
                    'default_value' => isset( $attributes['border'] ) ? $attributes['border'] : AttributesData::BORDER_DEFAULT,
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
                'font_family'                => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'text_color'                 => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'yaymail' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : '#ffffff',
                    'type'          => 'style',
                ],
                'rich_text'                  => [
                    'value_path'    => 'rich_text',
                    'component'     => 'RichTextEditor',
                    'title'         => __( 'Content', 'yaymail' ),
                    'default_value' => $content,
                    'type'          => 'content',
                ],
            ],
        ];
    }
}
