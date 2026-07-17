<?php

namespace YayMail\Constants;

use YayMail\Utils\SingletonTrait;

/**
 * YayMail Styles constants class
 */
class YayMailStyles {
    use SingletonTrait;

    protected function __construct() {
        $this->define_constants();
    }

    protected function define_constants() {
        if ( ! defined( 'YAYMAIL_COLOR_TEXT_DEFAULT' ) ) {
            define( 'YAYMAIL_COLOR_TEXT_DEFAULT', '#636363' );
        }

        if ( ! defined( 'YAYMAIL_COLOR_BACKGROUND_DEFAULT' ) ) {
            define( 'YAYMAIL_COLOR_BACKGROUND_DEFAULT', '#f9f9f9' );
        }

        if ( ! defined( 'YAYMAIL_COLOR_WC_DEFAULT' ) ) {
            define( 'YAYMAIL_COLOR_WC_DEFAULT', '#873EFF' );
        }

        if ( ! defined( 'YAYMAIL_COLOR_BORDER_DEFAULT' ) ) {
            define( 'YAYMAIL_COLOR_BORDER_DEFAULT', '#e5e5e5' );
        }

        if ( ! defined( 'YAYMAIL_DEFAULT_FAMILY' ) ) {
            define( 'YAYMAIL_DEFAULT_FAMILY', 'Helvetica,Roboto,Arial,sans-serif' );
        }

        if ( ! defined( 'YAYMAIL_COLOR_CONTENT_BACKGROUND_DEFAULT' ) ) {
            define( 'YAYMAIL_COLOR_CONTENT_BACKGROUND_DEFAULT', '#ffffff' );
        }

        if ( ! defined( 'YAYMAIL_COLOR_CONTENT_TEXT_DEFAULT' ) ) {
            define( 'YAYMAIL_COLOR_CONTENT_TEXT_DEFAULT', '#000000' );
        }

        if ( ! defined( 'YAYMAIL_COLOR_TITLE_DEFAULT' ) ) {
            define( 'YAYMAIL_COLOR_TITLE_DEFAULT', '#873EFF' );
        }
    }
}
