<?php

namespace YayMail;

use YayMail\Abstracts\BaseEmail;
use YayMail\Utils\SingletonTrait;

/**
 * Emails Class
 *
 * @method static YayMailEmails get_instance()
 */
class YayMailEmails {

    use SingletonTrait;

    private $emails = [];

    public function register( BaseEmail $email_instance ) {
        if ( ! ( $email_instance instanceof BaseEmail ) ) {
            return;
        }
        if ( ! $email_instance->is_existed() ) {
            return;
        }
        $this->emails[] = $email_instance;
    }

    public function get_emails() {
        return $this->emails;
    }
}
