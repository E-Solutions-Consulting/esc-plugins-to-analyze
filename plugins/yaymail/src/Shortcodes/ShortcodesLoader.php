<?php

namespace YayMail\Shortcodes;

use YayMail\Shortcodes\OrderDetails\OrderDetailsShortcodes;
use YayMail\Shortcodes\ShippingShortcodes;
use YayMail\Shortcodes\BillingShortcodes;
use YayMail\Shortcodes\PaymentsShortcodes;
use YayMail\Shortcodes\NewUsersShortcodes;
use YayMail\Shortcodes\ResetPasswordsShortcodes;
use YayMail\Shortcodes\LegacyCustomShortcodes;
use YayMail\Utils\SingletonTrait;

/**
 * @method: static ShortcodesLoader get_instance()
 */
class ShortcodesLoader {

    use SingletonTrait;

    private $shortcode_intances = [];

    protected function __construct() {

        $this->shortcode_intances = [
            CommonShortcodes::get_instance(),

            LegacyCustomShortcodes::get_instance(),

        ];

        if ( function_exists( 'WC' ) && defined( 'YAYMAIL_VERSION' ) ) {
            $this->shortcode_intances = array_merge(
                $this->shortcode_intances,
                [
                    OrderDetailsShortcodes::get_instance(),
                    HookShortcodes::get_instance(),
                    ShippingShortcodes::get_instance(),
                    BillingShortcodes::get_instance(),
                    PaymentsShortcodes::get_instance(),
                    NewUsersShortcodes::get_instance(),
                    ResetPasswordsShortcodes::get_instance(),
                    OrderMetaShortcodes::get_instance(),
                    /**
                    * @since 4.0.6
                    */
                    RefundShortcodes::get_instance(),
                ]
            );
        }

        do_action( 'yaymail_register_shortcodes', $this );

        foreach ( yaymail_get_emails() as $email ) {

            do_action( 'yaymail_' . YAYMAIL_ALL_EMAILS . '_register_shortcodes', $email );
            do_action( 'yaymail_' . $email->get_id() . '_register_shortcodes', $email );

            if ( in_array( YAYMAIL_NON_ORDER_EMAILS, $email->email_types, true ) ) {
                do_action( 'yaymail_' . YAYMAIL_NON_ORDER_EMAILS . '_register_shortcodes', $email );
                continue;
            }
            if ( in_array( YAYMAIL_WITH_ORDER_EMAILS, $email->email_types, true ) ) {
                do_action( 'yaymail_' . YAYMAIL_WITH_ORDER_EMAILS . '_register_shortcodes', $email );
                continue;
            }

            if ( in_array( YAYMAIL_GLOBAL_HEADER_FOOTER_ID, $email->email_types, true ) ) {
                do_action( 'yaymail_' . YAYMAIL_GLOBAL_HEADER_FOOTER_ID . '_register_shortcodes', $email );
                continue;
            }
        }
    }
}
