<?php

namespace YayMail\Emails;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;

/**
 * CustomerNote Class
 *
 * @method static CustomerNote get_instance()
 */
class CustomerNote extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_Note'];
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
        $email_title = __( 'A note has been added to your order', 'woocommerce' );
        // translators: customer name.
        $email_hi        = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[yaymail_billing_first_name]' );
        $email_text      = esc_html__( 'The following note has been added to your order:', 'woocommerce' );
        $email_text_1    = esc_html__( 'As a reminder, here are your order details:', 'woocommerce' );
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
                        'rich_text' => '<p><span>' . $email_hi . '</span></p><p><span>' . $email_text . '</span></p><p style=\"margin: 0px; padding-left: 40px;\"><span> [yaymail_customer_note]</span></p><p><span>' . $email_text_1 . '</span></p>',
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
                        'rich_text' => '<p><span>' . $additional_text . '</span></p>',
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
        return yaymail_get_template( 'emails/customer-note.php', '', YAYMAIL_PLUGIN_PATH . 'templates/' );
    }
}
