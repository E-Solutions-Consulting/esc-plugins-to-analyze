<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * HTML Elements
 */
class HTML extends BaseElement {

    use SingletonTrait;

    protected static $type = 'html';

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
  <path d="M17.5,2.5v15H2.5V2.5h15M18,1H2c-.55,0-1,.45-1,1v16c0,.55.45,1,1,1h16c.55,0,1-.45,1-1V2c0-.55-.45-1-1-1h0Z"/>
  <g>
    <g>
      <polygon class="cls-1" points="6.42 9.97 4.49 8.03 4.49 11.9 6.42 9.97"/>
      <path d="M8.01,9.44l-3.38-3.38s-.1-.07-.15-.1v2.07l1.94,1.94-1.94,1.94v2.05c.07-.04.14-.08.21-.14l3.32-3.32c.14-.14.22-.33.22-.53s-.08-.39-.22-.53Z"/>
    </g>
    <path d="M15.44,12.54h-7.12s-.04.01-.06.01v1.47s.04.01.06.01h7.12s.05-.01.08-.02v-1.47s-.05-.02-.08-.02Z"/>
  </g>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'HTML', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 140,
            'data'      => [
                'rich_text' => [
                    'value_path'    => 'rich_text',
                    'component'     => 'TextInput',
                    'title'         => __( 'HTML code', 'yaymail' ),
                    'default_value' => isset( $attributes['rich_text'] ) ? $attributes['rich_text'] : '<div>Welcome to[yaymail_site_name]</div>',
                    'placeholder'   => __( 'Import HTML code', 'yaymail' ),
                    'multiple'      => true,
                    'rows'          => 10,
                    'type'          => 'content',
                ],
            ],
        ];
    }
}
