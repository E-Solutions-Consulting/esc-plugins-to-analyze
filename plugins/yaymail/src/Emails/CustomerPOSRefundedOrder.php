<?php

namespace YayMail\Emails;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;
use YayMail\YayMailTemplate;

/**
 * CustomerPOSRefundedOrder Class
 * From WC 9.9.3
 *
 * @since 4.0.6
 * @method static CustomerPOSRefundedOrder get_instance()
 */
class CustomerPOSRefundedOrder extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_POS_Refunded_Order'] ?? null;
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
        $email_title = '[yaymail_get_heading]';
        // translators: customer name.
        $email_hi = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[yaymail_billing_first_name]' );
        // translators: site name.
        $email_text      = sprintf( esc_html__( 'Your order on %s has been %s. There are more details below for your reference:', 'woocommerce' ), '[yaymail_site_name]', '[yaymail_refund_type]' );
        $additional_text = __( 'We hope to see you again soon.', 'woocommerce' );

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
                        'rich_text' => '<p><span>' . $email_hi . '<br /></span></p><p><span>' . $email_text . '</span></p>',
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

    public function get_template_file( $located, $template_name, $args ) {
        if ( ! isset( $args['email'] ) ) {
            return $located;
        }
        if ( ! $args['email'] instanceof \WC_Email || ! $args['email'] instanceof \WC_Email_Customer_POS_Refunded_Order ) {
            return $located;
        }
        $template_path = $this->get_template_path();
        if ( ! file_exists( $template_path ) ) {
            return $located;
        }

        $order = apply_filters( 'yaymail_order_for_language', isset( $args['order'] ) ? $args['order'] : null, $args );

        $language = $this->get_language( $order );

        $this->template = new YayMailTemplate( $this->id, $language );

        if ( ! $this->template->is_enabled() ) {
            return $located;
        }

        return $template_path;
    }

    public function get_template_path() {
        return yaymail_get_template( 'emails/customer-pos-refunded-order.php', '', YAYMAIL_PLUGIN_PATH . 'templates/' );
    }
}
