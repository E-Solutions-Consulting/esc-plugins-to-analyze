<?php
/**
 * Renewal payment failed email
 *
 * Emails the customer when the payment of a renewal order fails, asking them
 * to update their payment method and call Member Services.
 *
 * It is a WooCommerce email (AH_Email_Renewal_Payment_Failed): subject,
 * heading, body, sender and delivery method are editable under
 * WooCommerce > Settings > Emails, where it can also be disabled.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Renewal_Failed_Email' ) ) {

class AH_Renewal_Failed_Email {

    const EMAIL_CLASS = 'AH_Email_Renewal_Payment_Failed';

    /** @var int[] Renewal orders already handled in this request. */
    private $handled = [];

    private $log_context = [ 'source' => 'ah-renewal-failed-email' ];

    public function __construct() {
        add_filter( 'woocommerce_email_classes', [ $this, 'register_emails' ] );
        add_action( 'woocommerce_subscription_renewal_payment_failed', [ $this, 'on_payment_failed' ], 10, 2 );
    }

    public function register_emails( $emails ) {
        // The base class (template, logo, delivery) is shared with the renewal review emails.
        require_once dirname( __DIR__ ) . '/review-emails/class-ah-email-renewal-review.php';
        require_once __DIR__ . '/class-ah-email-renewal-payment-failed.php';

        $class = self::EMAIL_CLASS;

        // A missing class here would be a fatal error for every WooCommerce email.
        if ( class_exists( $class ) ) {
            $emails[ $class ] = new $class();
        }

        return $emails;
    }

    /**
     * WooCommerce Subscriptions can fire the hook more than once for the same
     * order in a request: the customer is emailed once.
     *
     * @param WC_Subscription $subscription
     * @param WC_Order        $renewal_order
     */
    public function on_payment_failed( $subscription, $renewal_order ) {
        if ( ! $subscription instanceof WC_Subscription || ! $renewal_order instanceof WC_Order ) {
            return;
        }

        $order_id = $renewal_order->get_id();

        if ( in_array( $order_id, $this->handled, true ) || ! wcs_order_contains_renewal( $renewal_order ) ) {
            return;
        }

        $this->handled[] = $order_id;

        $emails = WC()->mailer()->get_emails();

        if ( empty( $emails[ self::EMAIL_CLASS ] ) ) {
            wc_get_logger()->error( sprintf( '[on_payment_failed] email class %s is not registered. order_id=%d', self::EMAIL_CLASS, $order_id ), $this->log_context );
            return;
        }

        $emails[ self::EMAIL_CLASS ]->trigger( $subscription->get_id(), $order_id );
    }
}

add_action( 'woocommerce_loaded', function () {
    new AH_Renewal_Failed_Email();
} );

}
