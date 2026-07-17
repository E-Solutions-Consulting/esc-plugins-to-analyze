<?php

namespace YayMail\Constants;

use YayMail\Utils\SingletonTrait;

/**
 * Email Types constants class
 */
class EmailTypes {
    use SingletonTrait;

    protected function __construct() {
        $this->define_constants();
    }

    protected function define_constants() {
        if ( ! defined( 'YAYMAIL_NON_ORDER_EMAILS' ) ) {
            define( 'YAYMAIL_NON_ORDER_EMAILS', 'NON_ORDER_EMAILS' );
        }

        if ( ! defined( 'YAYMAIL_WITH_ORDER_EMAILS' ) ) {
            define( 'YAYMAIL_WITH_ORDER_EMAILS', 'WITH_ORDER_EMAILS' );
        }

        if ( ! defined( 'YAYMAIL_ALL_EMAILS' ) ) {
            define( 'YAYMAIL_ALL_EMAILS', 'ALL_EMAILS' );
        }

        if ( ! defined( 'YAYMAIL_GLOBAL_HEADER_FOOTER_ID' ) ) {
            define( 'YAYMAIL_GLOBAL_HEADER_FOOTER_ID', 'GLOBAL_HEADER_FOOTER_ID' );
        }
    }
}
