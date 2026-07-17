<?php

namespace YayMail\PreviewEmail;

use YayMail\Utils\SingletonTrait;

use YayMail\PreviewEmail\Integration\WcSubscriptions;

/**
 *
 * @method static PreviewEmailsLoader get_instance()
 */
class PreviewEmailsLoader {
    use SingletonTrait;

    protected function __construct() {
        // TODO: inject hooks for addon
        if ( class_exists( 'WC_Subscriptions' ) ) {
            // WcSubscriptions::get_instance();
        }
    }
}
