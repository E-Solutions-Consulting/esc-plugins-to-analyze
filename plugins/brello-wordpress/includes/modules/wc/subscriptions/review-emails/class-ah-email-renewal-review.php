<?php
/**
 * Renewal review customer emails.
 *
 * Three WooCommerce emails (editable under WooCommerce > Settings > Emails)
 * sent through Resend while a subscription sits in RENEWAL_PENDING_REVIEW:
 * the initial notice, a mid-grace reminder and a final reminder.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'WC_Email' ) && ! class_exists( 'AH_Email_Renewal_Review_Base' ) ) {

abstract class AH_Email_Renewal_Review_Base extends WC_Email {

    const RESEND_ENDPOINT       = 'https://api.resend.com/emails';
    const DEFAULT_SUPPORT_EMAIL = 'info@brellohealth.com';
    const DEFAULT_SUPPORT_PHONE = '1-888-BRELLO-1';
    const DEFAULT_FROM          = 'Brello Health <info@brellohealth.com>';
    const DEFAULT_DISCLAIMER   = 'Any information provided is for informational purposes only and should not be construed as medical advice; it is not a substitute for professional medical consultation, diagnosis, or treatment. All patients must consult with a healthcare provider prior to the prescription or dispensing of any medication, which will be done only pursuant to a valid prescription. Compounded drug products are not FDA-approved, and the FDA does not evaluate their safety, effectiveness, or quality. It is not a generic version of, nor equivalent to, any FDA-approved product. Patients are encouraged to discuss the risks, benefits, and appropriateness of any medications, including compounded products, with their healthcare provider before use.';

    /** @var WC_Order|null The previous renewal order that never completed. */
    public $stuck_order;

    protected $log_context = [ 'source' => 'ah-renewal-review-emails' ];

    abstract protected function get_default_body();

    public function __construct() {
        $this->customer_email = true;
        $this->template_base  = trailingslashit( __DIR__ ) . 'templates/';
        $this->template_html  = 'renewal-review-notice.php';

        $this->placeholders = $this->get_default_placeholders();

        parent::__construct();

        add_filter( 'woocommerce_prepare_email_for_preview', [ $this, 'prepare_for_preview' ] );
    }

    /**
     * These emails are always HTML. The "Email type" setting is removed from
     * them, and WooCommerce treats an email without a type as plain text: its
     * preview and its test email under WooCommerce > Settings > Emails would
     * be empty.
     *
     * @return string
     */
    public function get_email_type() {
        return 'html';
    }

    /**
     * The preview and the test email have no real subscription: fill the
     * placeholders with sample values.
     *
     * @param WC_Email $email The email being previewed.
     * @return WC_Email
     */
    public function prepare_for_preview( $email ) {
        if ( $email === $this ) {
            $this->placeholders = array_merge( $this->placeholders, $this->get_preview_placeholders() );
        }

        return $email;
    }

    /**
     * Sample placeholder values for the preview.
     *
     * @return array
     */
    protected function get_preview_placeholders() {
        $support_phone = $this->get_option( 'support_phone', self::DEFAULT_SUPPORT_PHONE );

        return [
            '{customer_first_name}' => 'John',
            '{subscription_number}' => '12345',
            '{order_number}'        => '12346',
            '{order_status}'        => __( 'Pending Medical Intake', 'bh-features' ),
            '{days_remaining}'      => '7',
            '{cancellation_date}'   => wp_date( 'F j, Y', time() + ( 7 * DAY_IN_SECONDS ) ),
            '{support_email}'       => $this->get_option( 'support_email', self::DEFAULT_SUPPORT_EMAIL ),
            '{support_phone}'       => $support_phone,
            '{support_phone_tel}'   => self::phone_to_tel( $support_phone ),
        ];
    }

    /**
     * Placeholders offered in the subject, heading and body, with their
     * values before trigger() fills them.
     *
     * @return array
     */
    protected function get_default_placeholders() {
        return [
            '{customer_first_name}' => '',
            '{subscription_number}' => '',
            '{order_number}'        => '',
            '{order_status}'        => '',
            '{days_remaining}'      => '',
            '{cancellation_date}'   => '',
            '{support_email}'       => '',
            '{support_phone}'       => self::DEFAULT_SUPPORT_PHONE,
            '{support_phone_tel}'   => self::phone_to_tel( self::DEFAULT_SUPPORT_PHONE ),
        ];
    }

    /**
     * Dialable form of a phone number for tel: links, including vanity
     * numbers: "1-888-BRELLO-1" becomes "+18882735561".
     *
     * @param string $phone
     * @return string
     */
    public static function phone_to_tel( $phone ) {
        $digits = strtr(
            strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', (string) $phone ) ),
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            '22233344455566677778889999'
        );

        if ( 10 === strlen( $digits ) ) {
            $digits = '1' . $digits;
        }

        return '+' . $digits;
    }

    /**
     * Customer-facing wording for the stuck order's status. Internal status
     * names ("Send to Telegra", "Error - Review", "Require Prerequisites"...)
     * must never reach the patient.
     *
     * @param WC_Order $order
     * @return string
     */
    public static function customer_status_label( WC_Order $order ) {
        $labels = [
            SEND_TO_TELEGRA   => __( 'Pending Medical Intake', 'bh-features' ),
            NOT_SENT_TO_TELEGRA => __( 'Under Review', 'bh-features' ),
            'prerequisites'   => __( 'Pending Requirements', 'bh-features' ),
            'waiting_room'    => __( 'Waiting Room', 'bh-features' ),
            'provider_review' => __( 'Provider Review', 'bh-features' ),
            'collect_payment' => __( 'Pending Payment', 'bh-features' ),
            'error_review'    => __( 'Under Review', 'bh-features' ),
            'admin_review'    => __( 'Under Review', 'bh-features' ),
            'on-hold'         => __( 'On Hold', 'bh-features' ),
            'processing'      => __( 'Processing', 'bh-features' ),
        ];

        return $labels[ $order->get_status() ] ?? __( 'In Progress', 'bh-features' );
    }

    public function init_form_fields() {
        parent::init_form_fields();

        // Body and delivery are handled by this class, not by WooCommerce's mailer.
        unset( $this->form_fields['email_type'], $this->form_fields['additional_content'] );

        // Prefill the defaults (WooCommerce only shows them as a grey placeholder).
        $this->form_fields['subject']['default'] = $this->get_default_subject();
        $this->form_fields['heading']['default'] = $this->get_default_heading();

        $this->form_fields = array_merge( $this->form_fields, [
            'body'          => [
                'title'       => __( 'Email body', 'bh-features' ),
                'type'        => 'textarea',
                'css'         => 'width:100%;height:280px;',
                'description' => sprintf(
                    /* translators: %s: list of placeholders */
                    __( 'HTML is allowed. Available placeholders: %s', 'bh-features' ),
                    '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>'
                ),
                'default'     => $this->get_default_body(),
            ],
            'from_email'    => [
                'title'       => __( 'From', 'bh-features' ),
                'type'        => 'text',
                'description' => __( 'Sender used with Resend. The domain must be verified in Resend.', 'bh-features' ),
                'default'     => self::DEFAULT_FROM,
            ],
            'support_email' => [
                'title'       => __( 'Support email', 'bh-features' ),
                'type'        => 'text',
                'description' => __( 'Shown in the {support_email} placeholder and used as reply-to.', 'bh-features' ),
                'default'     => self::DEFAULT_SUPPORT_EMAIL,
            ],
            'support_phone' => [
                'title'       => __( 'Support phone', 'bh-features' ),
                'type'        => 'text',
                'description' => __( 'Shown in the {support_phone} placeholder. Vanity numbers are fine — {support_phone_tel} converts them for tel: links.', 'bh-features' ),
                'default'     => self::DEFAULT_SUPPORT_PHONE,
            ],
            'transport'     => [
                'title'       => __( 'Delivery method', 'bh-features' ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Resend requires the RESEND_APIKEY constant in wp-config.php. WordPress uses wp_mail() and the site mail configuration.', 'bh-features' ),
                'default'     => 'resend',
                'options'     => [
                    'resend'  => __( 'Resend', 'bh-features' ),
                    'wp_mail' => __( 'WordPress (wp_mail)', 'bh-features' ),
                ],
            ],
        ] );
    }

    /**
     * @param int $subscription_id
     * @param int $stuck_order_id
     * @param int $deadline Timestamp when the grace period ends.
     * @return bool Whether Resend accepted the message.
     */
    public function trigger( $subscription_id, $stuck_order_id, $deadline ) {
        if ( ! $this->is_enabled() ) {
            $this->log( 'info', sprintf( '[%s] disabled in WooCommerce > Emails — not sent. subscription_id=%d', $this->id, $subscription_id ) );
            return false;
        }

        $subscription = wcs_get_subscription( $subscription_id );
        $stuck_order  = wc_get_order( $stuck_order_id );

        if ( ! $subscription || ! $stuck_order ) {
            $this->log( 'error', sprintf( '[%s] subscription or order not found. subscription_id=%d order_id=%d', $this->id, $subscription_id, $stuck_order_id ) );
            return false;
        }

        $recipient = $subscription->get_billing_email();

        if ( ! is_email( $recipient ) ) {
            $this->log( 'error', sprintf( '[%s] invalid billing email on the subscription — not sent. subscription_id=%d', $this->id, $subscription_id ) );
            return false;
        }

        $this->object      = $subscription;
        $this->stuck_order = $stuck_order;
        $this->recipient   = $recipient;

        $cancellation_ts = (int) $deadline;
        $support_phone   = $this->get_option( 'support_phone', self::DEFAULT_SUPPORT_PHONE );

        $this->placeholders = array_merge( $this->placeholders, [
            '{customer_first_name}' => $subscription->get_billing_first_name(),
            '{subscription_number}' => $subscription->get_order_number(),
            '{order_number}'        => $stuck_order->get_order_number(),
            '{order_status}'        => self::customer_status_label( $stuck_order ),
            '{days_remaining}'      => (string) max( 1, (int) ceil( ( $cancellation_ts - time() ) / DAY_IN_SECONDS ) ),
            '{cancellation_date}'   => wp_date( 'F j, Y', $cancellation_ts ),
            '{support_email}'       => $this->get_option( 'support_email', self::DEFAULT_SUPPORT_EMAIL ),
            '{support_phone}'       => $support_phone,
            '{support_phone_tel}'   => self::phone_to_tel( $support_phone ),
        ] );

        $this->subject = $this->format_string( $this->get_option( 'subject', $this->get_default_subject() ) );
        $this->heading = $this->format_string( $this->get_option( 'heading', $this->get_default_heading() ) );

        return $this->deliver( $recipient, $this->get_subject(), $this->style_inline( $this->get_content_html() ) );
    }

    /**
     * The body setting with its placeholders replaced. It is HTML.
     *
     * @return string
     */
    protected function get_body() {
        return $this->format_string( $this->get_option( 'body', $this->get_default_body() ) );
    }

    public function get_content_html() {
        $body = $this->get_body();

        // The site logo (Appearance > Customize) goes in WooCommerce's header
        // image slot, above the heading — for these emails only, the global
        // WooCommerce "Header image" setting is left untouched.
        $logo     = self::logo_url();
        $use_logo = static function () use ( $logo ) {
            return $logo;
        };

        if ( $logo ) {
            add_filter( 'pre_option_woocommerce_email_header_image', $use_logo );
        }

        try {
            $html = wc_get_template_html(
                $this->template_html,
                [
                    'email_heading' => $this->get_heading(),
                    'body_html'     => wpautop( wp_kses_post( $body ) ),
                    'email'         => $this,
                    'sent_to_admin' => false,
                    'plain_text'    => false,
                ],
                '',
                $this->template_base
            );
        } finally {
            remove_filter( 'pre_option_woocommerce_email_header_image', $use_logo );
        }

        if ( $logo ) {
            // Inline attributes, not a <style> block: several email clients
            // (Outlook among them) ignore head styles. width="160" is what
            // Outlook honors; max-width keeps it fluid on narrow screens. The
            // width is in pixels because WooCommerce's inliner rewrites the
            // width attribute from the style.
            $html = preg_replace(
                '/<img(?=[^>]*src="' . preg_quote( esc_url( $logo ), '/' ) . '")/',
                '<img width="160" style="display: block; margin: 0 auto 16px; width: 160px; max-width: 100%; height: auto; border: 0;"',
                $html,
                1
            );
        }

        return $html;
    }

    /**
     * URL of the logo set in Appearance > Customize (custom_logo), or '' if none.
     *
     * @return string
     */
    public static function logo_url() {
        $logo_id = (int) get_theme_mod( 'custom_logo' );

        return $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
    }

    protected function deliver( $recipient, $subject, $html ) {
        if ( 'wp_mail' === $this->get_option( 'transport', 'resend' ) ) {
            return $this->send_via_wp_mail( $recipient, $subject, $html );
        }

        return $this->send_via_resend( $recipient, $subject, $html );
    }

    private function send_via_wp_mail( $recipient, $subject, $html ) {
        $sent = wp_mail(
            $recipient,
            $subject,
            $html,
            [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->get_option( 'from_email', self::DEFAULT_FROM ),
                'Reply-To: ' . $this->get_option( 'support_email', self::DEFAULT_SUPPORT_EMAIL ),
            ]
        );

        $this->log(
            $sent ? 'info' : 'error',
            sprintf( '[%s] wp_mail %s. subscription_id=%d', $this->id, $sent ? 'sent' : 'failed', $this->object->get_id() )
        );

        return (bool) $sent;
    }

    private function send_via_resend( $recipient, $subject, $html ) {
        if ( ! defined( 'RESEND_APIKEY' ) || ! RESEND_APIKEY ) {
            $this->log( 'error', sprintf( '[%s] RESEND_APIKEY is not defined — not sent. subscription_id=%d', $this->id, $this->object->get_id() ) );
            return false;
        }

        $support = $this->get_option( 'support_email', self::DEFAULT_SUPPORT_EMAIL );

        $response = wp_remote_post( self::RESEND_ENDPOINT, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . RESEND_APIKEY,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'from'     => $this->get_option( 'from_email', self::DEFAULT_FROM ),
                'to'       => [ $recipient ],
                'reply_to' => $support,
                'subject'  => $subject,
                'html'     => $html,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log( 'error', sprintf( '[%s] Resend request failed: %s', $this->id, $response->get_error_message() ) );
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code < 200 || $code >= 300 ) {
            $this->log( 'error', sprintf( '[%s] Resend rejected the message (HTTP %d): %s', $this->id, $code, wp_remote_retrieve_body( $response ) ) );
            return false;
        }

        $this->log( 'info', sprintf( '[%s] sent. subscription_id=%d', $this->id, $this->object->get_id() ) );

        return true;
    }

    protected function log( $level, $message ) {
        wc_get_logger()->log( $level, $message, $this->log_context );
    }
}

class AH_Email_Renewal_Review_Started extends AH_Email_Renewal_Review_Base {

    public function __construct() {
        $this->id          = 'ah_renewal_review_started';
        $this->title       = __( 'Renewal review - notice', 'bh-features' );
        $this->description = __( 'Sent to the customer when their subscription is moved to review because the last order did not complete.', 'bh-features' );

        parent::__construct();
    }

    public function get_default_subject() {
        return __( 'Action needed: Review your Brello Health subscription #{subscription_number}', 'bh-features' );
    }

    public function get_default_heading() {
        return __( 'Your subscription needs attention', 'bh-features' );
    }

    protected function get_default_body() {
        return __(
            "Hi {customer_first_name},\n\n" .
            "Your most recent Brello Health order (#{order_number}) hasn't been completed yet, so we were unable to complete your renewal.\n\n" .
            "<strong>Your subscription #{subscription_number} is scheduled to be cancelled in {days_remaining} days, on {cancellation_date}, if the issue is not resolved.</strong>\n\n" .
            "Please call Brello Health Member Services at <a href=\"tel:{support_phone_tel}\">📞 {support_phone}</a> so we can review your account, explain what needs to be completed, and help you keep your subscription active.\n\n" .
            "<strong>Member Services Hours:</strong> Monday–Friday, 8:00 AM–7:00 PM CST\n\n" .
            "Thank you for choosing Brello. 💜\n\n" .
            "Warmly,\n<strong>The Brello Health Team</strong>\n\n" .
            "—\n\n" .
            self::DEFAULT_DISCLAIMER,
            'bh-features'
        );
    }
}

class AH_Email_Renewal_Review_Reminder_Mid extends AH_Email_Renewal_Review_Base {

    public function __construct() {
        $this->id          = 'ah_renewal_review_reminder_mid';
        $this->title       = __( 'Renewal review - mid-period reminder', 'bh-features' );
        $this->description = __( 'Reminder sent halfway through the review period while the subscription is still waiting.', 'bh-features' );

        parent::__construct();
    }

    public function get_default_subject() {
        return __( 'Reminder: Action needed to keep your Brello Health subscription active', 'bh-features' );
    }

    public function get_default_heading() {
        return __( 'Your subscription still needs attention', 'bh-features' );
    }

    protected function get_default_body() {
        return __(
            "Hi {customer_first_name},\n\n" .
            "This is a friendly reminder that your most recent Brello Health order (#{order_number}) still hasn't been completed.\n\n" .
            "<strong>Your subscription #{subscription_number} is scheduled to be cancelled in {days_remaining} days, on {cancellation_date}, if the issue is not resolved.</strong>\n\n" .
            "Please call Brello Health Member Services at <a href=\"tel:{support_phone_tel}\">📞 {support_phone}</a> so we can review your account and help you complete any required next steps to keep your subscription active.\n\n" .
            "<strong>Member Services Hours:</strong> Monday–Friday, 8:00 AM–7:00 PM CST\n\n" .
            "Thank you for choosing Brello. 💜\n\n" .
            "Warmly,\n<strong>The Brello Health Team</strong>\n\n" .
            "—\n\n" .
            self::DEFAULT_DISCLAIMER,
            'bh-features'
        );
    }
}

class AH_Email_Renewal_Review_Reminder_Final extends AH_Email_Renewal_Review_Base {

    public function __construct() {
        $this->id          = 'ah_renewal_review_reminder_final';
        $this->title       = __( 'Renewal review - final reminder', 'bh-features' );
        $this->description = __( 'Last reminder, sent one day before the subscription is cancelled.', 'bh-features' );

        parent::__construct();
    }

    public function get_default_subject() {
        return __( 'Final reminder: Your Brello Health subscription #{subscription_number} is at risk of cancellation', 'bh-features' );
    }

    public function get_default_heading() {
        return __( 'Final reminder: action is required', 'bh-features' );
    }

    protected function get_default_body() {
        return __(
            "Hi {customer_first_name},\n\n" .
            "This is your final reminder that your most recent Brello Health order (#{order_number}) still hasn't been completed.\n\n" .
            "<strong>Your subscription #{subscription_number} is scheduled to be cancelled on {cancellation_date}, unless the issue is resolved.</strong>\n\n" .
            "If you would like to keep your subscription active, please call Brello Health Member Services today at <a href=\"tel:{support_phone_tel}\">📞 {support_phone}</a> so we can review your account and help determine what is needed to move your order forward.\n\n" .
            "<strong>Member Services Hours:</strong> Monday–Friday, 8:00 AM–7:00 PM CST\n\n" .
            "Thank you for choosing Brello. 💜\n\n" .
            "Warmly,\n<strong>The Brello Health Team</strong>\n\n" .
            "—\n\n" .
            self::DEFAULT_DISCLAIMER,
            'bh-features'
        );
    }
}

}
