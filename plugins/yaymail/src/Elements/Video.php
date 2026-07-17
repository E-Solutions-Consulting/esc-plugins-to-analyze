<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * Video Elements
 */
class Video extends BaseElement {

    use SingletonTrait;

    protected static $type = 'video';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $default_src = esc_url( YAYMAIL_PLUGIN_URL . 'assets/images/default-photo.png' );
        self::$icon  = '<svg viewBox="64 64 896 896" data-icon="video-camera" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false" class=""><path d="M912 302.3L784 376V224c0-35.3-28.7-64-64-64H128c-35.3 0-64 28.7-64 64v576c0 35.3 28.7 64 64 64h592c35.3 0 64-28.7 64-64V648l128 73.7c21.3 12.3 48-3.1 48-27.6V330c0-24.6-26.7-40-48-27.7zM712 792H136V232h576v560zm176-167l-104-59.8V458.9L888 399v226zM208 360h112c4.4 0 8-3.6 8-8v-48c0-4.4-3.6-8-8-8H208c-4.4 0-8 3.6-8 8v48c0 4.4 3.6 8 8 8z"></path></svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Video', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 100,
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
                'content_breaker'            => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'   => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content settings', 'yaymail' ),
                    'description' => __( 'Handle content settings', 'yaymail' ),
                ],
                'src'                        => [
                    'value_path'    => 'src',
                    'component'     => 'Media',
                    'title'         => __( 'Thumbnail', 'yaymail' ),
                    'default_value' => isset( $attributes['src'] ) ? $attributes['src'] : $default_src,
                    'type'          => 'content',
                ],
                'width'                      => [
                    'value_path'    => 'width',
                    'component'     => 'Dimension',
                    'title'         => __( 'Width', 'yaymail' ),
                    'default_value' => isset( $attributes['width'] ) ? $attributes['width'] : '400',
                    'type'          => 'style',
                ],
                'height'                     => [
                    'value_path'    => 'height',
                    'component'     => 'Dimension',
                    'title'         => __( 'Height', 'yaymail' ),
                    'default_value' => isset( $attributes['height'] ) ? $attributes['height'] : '400',
                    'type'          => 'style',
                ],
                'url'                        => [
                    'value_path'    => 'url',
                    'component'     => 'Media',
                    'title'         => __( 'Video URL', 'yaymail' ),
                    'default_value' => isset( $attributes['url'] ) ? $attributes['url'] : '#',
                    'media_type'    => 'video',
                    'button_title'  => __( 'Change video', 'yaymail' ),
                    'show_preview'  => false,
                    'type'          => 'content',
                ],
            ],
        ];
    }
}
