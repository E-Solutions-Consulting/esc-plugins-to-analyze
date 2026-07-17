<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * Space Elements
 */
class Space extends BaseElement {

    use SingletonTrait;

    protected static $type = 'space';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <rect x=".86" y=".97" width="17.98" height="1.5"/>
  <rect x=".86" y="17.53" width="17.98" height="1.5"/>
  <g>
    <rect x="9.1" y="5.5" width="1.5" height="8.92"/>
    <path d="M11.71,6.39h-3.71l.8-1.28s0,0,0-.01l1.04-1.66,1.04,1.65s0,0,0,.02l.81,1.29Z"/>
    <polygon points="9.85 16.52 9.14 15.38 9.13 15.37 8.81 14.86 8.81 14.85 8 13.56 11.71 13.56 10.9 14.85 10.89 14.85 9.85 16.52"/>
  </g>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Space', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'general',
            'available' => true,
            'position'  => 130,
            'data'      => [
                'background_color' => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'height'           => [
                    'value_path'    => 'height',
                    'component'     => 'Dimension',
                    'title'         => __( 'Height', 'yaymail' ),
                    'default_value' => isset( $attributes['height'] ) ? $attributes['height'] : '40',
                    'min'           => 8,
                    'max'           => 200,
                    'type'          => 'style',
                ],
            ],
        ];
    }
}
