<?php

namespace YayMail\Constants;

use YayMail\Utils\SingletonTrait;

/**
 * ConstantsHandler
 */
class ConstantsHandler {
    use SingletonTrait;

    protected function __construct() {
        YayMailStyles::get_instance();
        EmailTypes::get_instance();
        Sources::get_instance();
    }
}
