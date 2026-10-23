<?php
/**
 * Renewal payment failed customer email.
 *
 * WooCommerce email (editable under WooCommerce > Settings > Emails) sent when
 * the payment of a renewal order fails. Template, logo and delivery come from
 * AH_Email_Renewal_Review_Base.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'AH_Email_Renewal_Review_Base' ) && ! class_exists( 'AH_Email_Renewal_Payment_Failed' ) ) {

class AH_Email_Renewal_Payment_Failed extends AH_Email_Renewal_Review_Base {

    protected $log_context = [ 'source' => 'ah-renewal-failed-email' ];

    public function __construct() {
        $this->id          = 'ah_renewal_payment_failed';
        $this->title       = __( 'Renewal payment failed', 'bh-features' );
        $this->description = __( 'Sent to the customer when the payment of a renewal order fails, asking them to update their payment method and call Member Services.', 'bh-features' );

        parent::__construct();
    }

    protected function get_default_placeholders() {
        return [
            '{customer_first_name}' => '',
            '{subscription_number}' => '',
            '{order_number}'        => '',
            '{account_url}'         => '',
            '{support_email}'       => '',
            '{support_phone}'       => self::DEFAULT_SUPPORT_PHONE,
            '{support_phone_tel}'   => self::phone_to_tel( self::DEFAULT_SUPPORT_PHONE ),
        ];
    }

    protected function get_preview_placeholders() {
        $placeholders = array_intersect_key( parent::get_preview_placeholders(), $this->get_default_placeholders() );

        $placeholders['{account_url}'] = esc_url( wc_get_page_permalink( 'myaccount' ) );

        return $placeholders;
    }

    public function init_form_fields() {
        parent::init_form_fields();

        // This email has always gone out through the site mailer.
        $this->form_fields['transport']['default'] = 'wp_mail';
    }

    public function get_default_subject() {
        return __( 'Action Required: Update Your Payment Method – Renewal Failed', 'bh-features' );
    }

    public function get_default_heading() {
        return __( 'Action Required: Update Your Payment Method', 'bh-features' );
    }

    protected function get_default_body() {
        return __(
            "Hi {customer_first_name},\n\n" .
            "We were unable to process the payment for your recent renewal order.\n\n" .
            "To help us continue processing your subscription, please update your payment method and then contact our Member Services Team to confirm your subscription information.\n\n" .
            "<a href=\"{account_url}\">[Log in to your account]</a>\n\n" .
            "<strong>How to update your payment method</strong>\n\n" .
            "<ol>\n" .
            "<li>Go to <strong>Subscriptions</strong> in the left-hand menu.</li>\n" .
            "<li>Find your subscription and click <strong>View</strong>.</li>\n" .
            "<li>If your subscription shows an <strong>On Hold</strong> status, click <strong>Reactivate</strong>. This will display the available actions for updating your payment information.</li>\n" .
            "<li>Under <strong>Actions</strong>, select <strong>Change Payment Method</strong>.</li>\n" .
            "<li>Enter your new payment details and click <strong>Change Payment Method</strong> to save your updated payment method.</li>\n" .
            "</ol>\n\n" .
            "⚠️ <strong>Important:</strong> Updating your payment method does not automatically reactivate your subscription. You must speak with a Member Services representative to confirm your subscription information and complete the next steps.\n\n" .
            "Once your payment information has been updated, please call our Member Services Team at <a href=\"tel:{support_phone_tel}\"><strong>{support_phone}</strong></a>.\n\n" .
            "<strong>Member Services Hours:</strong> 8:00 AM – 7:00 PM CST\n<strong>Typical wait time:</strong> Less than 1 minute\n\n" .
            "Thank you for your patience and we look forward to serving you!\n\n" .
            "Best,\nThe Brello Health Team\n\n" .
            "—\n\n" .
            '<span style="font-size: 12px;">' . self::DEFAULT_DISCLAIMER . '</span>',
            'bh-features'
        );
    }

    /**
     * The subject is plain text and the template escapes the heading, so the
     * placeholder holds the name as typed. Only the body, which is HTML, takes
     * it escaped.
     *
     * @return string
     */
    protected function get_body() {
        $first_name = $this->placeholders['{customer_first_name}'];

        $this->placeholders['{customer_first_name}'] = esc_html( $first_name );
        $body = parent::get_body();
        $this->placeholders['{customer_first_name}'] = $first_name;

        return $body;
    }

    /**
     * @param int $subscription_id
     * @param int $renewal_order_id The renewal order whose payment failed.
     * @param int $deadline         Unused, kept for compatibility with the base class.
     * @return bool Whether the message was handed to the mailer.
     */
    public function trigger( $subscription_id, $renewal_order_id, $deadline = 0 ) {
        if ( ! $this->is_enabled() ) {
            $this->log( 'info', sprintf( '[%s] disabled in WooCommerce > Emails — not sent. order_id=%d', $this->id, $renewal_order_id ) );
            return false;
        }

        $subscription  = wcs_get_subscription( $subscription_id );
        $renewal_order = wc_get_order( $renewal_order_id );

        if ( ! $subscription || ! $renewal_order ) {
            $this->log( 'error', sprintf( '[%s] subscription or order not found. subscription_id=%d order_id=%d', $this->id, $subscription_id, $renewal_order_id ) );
            return false;
        }

        $recipient = $renewal_order->get_billing_email();

        if ( ! is_email( $recipient ) ) {
            $this->log( 'error', sprintf( '[%s] invalid billing email on the order — not sent. order_id=%d', $this->id, $renewal_order_id ) );
            return false;
        }

        $this->object      = $subscription;
        $this->stuck_order = $renewal_order;
        $this->recipient   = $recipient;

        $first_name    = $renewal_order->get_billing_first_name();
        $support_phone = $this->get_option( 'support_phone', self::DEFAULT_SUPPORT_PHONE );

        $this->placeholders = array_merge( $this->placeholders, [
            '{customer_first_name}' => $first_name,
            '{subscription_number}' => $subscription->get_order_number(),
            '{order_number}'        => $renewal_order->get_order_number(),
            '{account_url}'         => esc_url( wc_get_page_permalink( 'myaccount' ) ),
            '{support_email}'       => $this->get_option( 'support_email', self::DEFAULT_SUPPORT_EMAIL ),
            '{support_phone}'       => $support_phone,
            '{support_phone_tel}'   => self::phone_to_tel( $support_phone ),
        ] );

        $this->subject = $this->format_string( $this->get_option( 'subject', $this->get_default_subject() ) );
        $this->heading = $this->format_string( $this->get_option( 'heading', $this->get_default_heading() ) );

        return $this->deliver( $recipient, $this->get_subject(), $this->style_inline( $this->get_content_html() ) );
    }
}

}
