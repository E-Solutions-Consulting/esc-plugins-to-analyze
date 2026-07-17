<?php

namespace YayMail\Emails;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;

/**
 * CustomerInvoice Class
 *
 * @method static CustomerInvoice get_instance()
 */
class CustomerInvoice extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_Invoice'];
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
        $email_title = __( 'Details for order: #{order_number}', 'woocommerce' );
        $email_title = str_replace( '#{order_number}', '', $email_title );
        // translators: customer name.
        $email_hi = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[yaymail_billing_first_name]' );
        // translators: order date.
        $email_text      = sprintf( esc_html__( 'Here are the details of your order placed on  %s,', 'woocommerce' ), '[yaymail_order_date]' );
        $additional_text = __( 'Thanks for using {site_url}!', 'woocommerce' );
        $additional_text = str_replace( '{site_url}!', '', $additional_text );

        $default_elements = ElementsLoader::load_elements(
            [
                [
                    'type' => 'Logo',
                ],
                [
                    'type'       => 'Heading',
                    'attributes' => [
                        'rich_text' => $email_title . '#[yaymail_order_number]',
                    ],
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<p><span>' . $email_hi . '<br /><br /></span></p><p><span>' . $email_text . '</span></p>',
                    ],
                ],
                [
                    'type' => 'OrderDetails',
                ],
                [
                    'type' => 'BillingShippingAddress',
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<p><span>' . $additional_text . '[yaymail_domain]!</span></p>',
                        'padding'   => [
                            'top'    => '0',
                            'right'  => '50',
                            'bottom' => '38',
                            'left'   => '50',
                        ],
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
        return yaymail_get_template( 'emails/customer-invoice.php', '', YAYMAIL_PLUGIN_PATH . 'templates/' );
    }
}
