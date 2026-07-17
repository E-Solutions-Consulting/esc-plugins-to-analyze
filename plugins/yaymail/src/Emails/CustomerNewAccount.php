<?php

namespace YayMail\Emails;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;

/**
 * CustomerNewAccount Class
 *
 * @method static CustomerNewAccount get_instance()
 */
class CustomerNewAccount extends BaseEmail {
    use SingletonTrait;

    public $email_types = [ YAYMAIL_NON_ORDER_EMAILS ];

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_New_Account'];
        if ( ! $email ) {
            return;
        }
        $this->id         = $email->id;
        $this->title      = $email->get_title();
        $this->root_email = $email;
        $this->recipient  = function_exists( 'yaymail_get_email_recipient_zone' ) ? yaymail_get_email_recipient_zone( $email ) : '';

        $this->render_priority = apply_filters( 'yaymail_email_render_priority', $this->render_priority, $this->id );
        add_filter( 'wc_get_template', [ $this, 'get_template_file' ], $this->render_priority ?? 10, 3 );
        add_filter( 'yaymail_trigger_to_preview_email', [ $this, 'trigger_to_preview_email' ], 10, 3 );
        $this->maybe_disable_block_email_editor();
    }

    public function trigger_to_preview_email( $is_permitted, $email, $order_id ) {
        if ( $email->id === $this->id ) {
            add_action( 'yaymail_trigger_email', [ $this, 'trigger_email' ], 10, 2 );
            $is_permitted = true;
        }
        return $is_permitted;
    }

    public function trigger_email( $email, $order_id ) {
        $email->trigger( get_current_user_id() );
    }

    public function get_default_elements() {
        $email_title = __( 'Welcome to {site_title}', 'woocommerce' );
        $email_title = str_replace( '{site_title}', '', $email_title );
        // translators: customer username.
        $email_hi = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[yaymail_customer_username]' );
        // translators: %1$s: site name, %2$s: customer username, %3$s: account url .
        $email_text        = sprintf( esc_html__( 'Thanks for creating an account on %1$s. Your username is %2$s. You can access your account area to view orders, change your password, and more at: %3$s', 'woocommerce' ), '[yaymail_site_name]', '<strong>[yaymail_customer_username]</strong>', '[yaymail_user_account_url]' );
        $email_text_1      = __( 'We look forward to seeing you soon.', 'woocommerce' );
        $password_generate = '[yaymail_set_password_link]';

        $default_elements = ElementsLoader::load_elements(
            [
                [
                    'type' => 'Logo',
                ],
                [
                    'type'       => 'Heading',
                    'attributes' => [
                        'rich_text' => $email_title . '[yaymail_site_name]',
                    ],
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<p><span>' . $email_hi . '<br><br>' . $email_text . '</span></p><p style=\"margin: 26px 0px 0px 0px;\"><span>' . $password_generate . '</span></p><p style=\"margin: 26px 0px 0px 0px;\"><span>' . $email_text_1 . '</span></p>',
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
        return yaymail_get_template( 'emails/customer-new-account.php', '', YAYMAIL_PLUGIN_PATH . 'templates/' );
    }
}
