<?php

namespace YayMail\Emails;

use YayMail\Utils\SingletonTrait;
use YayMail\YayMailEmails;

/**
 * EmailsLoader Class
 *
 * @method static EmailsLoader get_instance()
 */
class EmailsLoader {

    use SingletonTrait;

    private function __construct() {
        $this->init_hooks();
        $this->load_emails();
    }

    private function init_hooks() {
        add_action( 'yaymail_before_email_content', [ $this, 'before_email_content' ], 10, 1 );
        add_action( 'yaymail_after_email_content', [ $this, 'after_email_content' ], 10, 1 );

        /**
         * Email references hooks
         */
        add_filter( 'safe_style_css', [ $this, 'filter_safe_style_css' ], 10, 1 );
        add_filter( 'woocommerce_email_styles', [ $this, 'inject_custom_css' ] );
    }

    private function load_emails() {

        $yaymail_emails = YayMailEmails::get_instance();

        if ( yaymail_is_wc_installed() ) {
            $yaymail_emails->register( \YayMail\Emails\NewOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CancelledOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerCancelledOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\FailedOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerFailedOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerOnHoldOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerProcessingOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerCompletedOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerRefundedOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerInvoice::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerNote::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerResetPassword::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerNewAccount::get_instance() );
            /**
             * POS emails, WC 9.9.3
             *
             * @since 4.0.6
             */

            $yaymail_emails->register( \YayMail\Emails\CustomerPOSCompletedOrder::get_instance() );
            $yaymail_emails->register( \YayMail\Emails\CustomerPOSRefundedOrder::get_instance() );
        }//end if

        $yaymail_emails->register( \YayMail\Emails\GlobalHeaderFooter::get_instance() );

        do_action( 'yaymail_register_emails', $yaymail_emails );
    }

    public function before_email_content( $template ) {
        include YAYMAIL_PLUGIN_PATH . 'templates/emails/before-email-content.php';
    }

    public function after_email_content( $template ) {
        include YAYMAIL_PLUGIN_PATH . 'templates/emails/after-email-content.php';
    }

    public function filter_safe_style_css( $default_array ) {
        $additional_allowed_css_attributes = [ 'display', 'background-repeat', 'word-wrap' ];
        return array_merge( $default_array, $additional_allowed_css_attributes );
    }

    public function inject_custom_css( $css = '' ) {
        $css             .= '.yaymail-element table { border-spacing: 0; }';
        $yaymail_settings = yaymail_settings();
        if ( ! boolval( $yaymail_settings['enable_custom_css'] ?? false ) ) {
            return $css;
        }
        $custom_css = isset( $yaymail_settings['custom_css'] ) ? $yaymail_settings['custom_css'] : '';
        $css       .= $custom_css;
        $css        = apply_filters( 'yaymail_email_styles', $css );
        return $css;
    }
}
