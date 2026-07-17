<?php

namespace YayMail\Integrations\DHL;

use YayMail\Utils\SingletonTrait;

/**
 * DHL
 * * @method static DHLIntegration get_instance()
 */
class DHLIntegration {
    use SingletonTrait;

    protected function __construct() {
        if ( ! class_exists( 'PR_DHL_WC' ) ) {
            return;
        }

        add_action(
            'yaymail_register_shortcodes',
            function() {
                DHLShortcodes::get_instance();
            }
        );
    }
}
