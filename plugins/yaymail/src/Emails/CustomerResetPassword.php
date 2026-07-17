<?php

namespace YayMail\Emails;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;

/**
 * CustomerResetPassword Class
 *
 * @method static CustomerResetPassword get_instance()
 */
class CustomerResetPassword extends BaseEmail {
    use SingletonTrait;

    public $email_types = [ YAYMAIL_NON_ORDER_EMAILS ];

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_Reset_Password'];
        if ( ! $email ) {
            return;
        }

        $this->id         = $email->id;
        $this->title      = $email->get_title();
        $this->root_email = $email;
        $this->recipient  = function_exists( 'yaymail_get_email_recipient_zone' ) ? yaymail_get_email_recipient_zone( $email ) : '';

        $this->render_priority = apply_filters( 'yaymail_email_render_priority', $this->render_priority, $this->id );
        add_filter( 'wc_get_template', [ $this, 'get_template_file' ], $this->render_priority ?? 10, 3 );
        $this->maybe_disable_block_email_editor();
    }

    public function get_default_elements() {
        $email_title = __( 'Password Reset Request', 'woocommerce' );
        // translators: customer username.
        $email_hi = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[yaymail_customer_username]' );
        // translators: site name.
        $email_text      = sprintf( esc_html__( 'Someone has requested a new password for the following account on %s:,', 'woocommerce' ), '[yaymail_site_name]' );
        $email_text_1    = esc_html__( 'If you didn\'t make this request, just ignore this email. If you\'d like to proceed:', 'woocommerce' );
        $text_username   = __( 'Username', 'woocommerce' );
        $additional_text = __( 'Thanks for reading.', 'woocommerce' );

        $default_elements = ElementsLoader::load_elements(
            [
                [
                    'type' => 'Logo',
                ],
                [
                    'type'       => 'Heading',
                    'attributes' => [
                        'rich_text' => $email_title,
                    ],
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<p style=\"margin: 0 0 16px;\"><span>' . $email_hi . '</span></p><p style=\"margin: 0 0 16px;\"><span>' . $email_text . '</span></p><p style=\"margin: 0 0 16px;\"><span>' . $text_username . ': [yaymail_customer_username]</span></p><p style=\"margin: 0 0 16px;\"><span>' . $email_text_1 . '</span></p><p style=\"margin: 0 0 16px;\"><span>[yaymail_password_reset_link]</span></p><p style=\"margin: 0 0 16px;\"><span>' . $additional_text . '</span></p>',
                    ],
                ],
                [
                    'type' => 'Footer',
                ],
            ]
        );

        return $default_elements;
    }

    public function get_template_path() {
        return yaymail_get_template( 'emails/customer-reset-password.php', '', YAYMAIL_PLUGIN_PATH . 'templates/' );
    }
}
