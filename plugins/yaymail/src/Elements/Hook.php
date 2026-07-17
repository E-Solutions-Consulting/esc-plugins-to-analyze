<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Hook Elements
 */
class Hook extends BaseElement {

    use SingletonTrait;

    protected static $type = 'hook';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M13.97,1c-1.16,0-2.11.95-2.11,2.11,0,.92.59,1.7,1.41,1.99v8.52c0,2.19-1.78,3.97-3.97,3.97s-3.97-1.78-3.97-3.97h2.46l-3.87-3.87v3.87c0,2.97,2.41,5.38,5.38,5.38s5.38-2.41,5.38-5.38V5.1c.82-.29,1.41-1.07,1.41-1.99,0-1.16-.95-2.11-2.11-2.11ZM13.97,3.81c-.39,0-.7-.32-.7-.7s.32-.7.7-.7.7.32.7.7-.32.7-.7.7Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Hook', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'woocommerce',
            'available' => true,
            'position'  => 210,
            'data'      => [
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
                'text_color'       => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'yaymail' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : YAYMAIL_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'hook_shortcode'   => [
                    'value_path'    => 'hook_shortcode',
                    'component'     => 'HookSelector',
                    'title'         => __( 'Hook shortcode', 'yaymail' ),
                    'default_value' => isset( $attributes['rich_text'] ) ? $attributes['hook_shortcode'] : '[yaymail_custom_hook hook="woocommerce_email_before_order_table"]',
                    'type'          => 'content',
                ],
                'font_family'      => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
            ],
        ];
    }
}
