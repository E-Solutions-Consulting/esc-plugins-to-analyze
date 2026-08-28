<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Subscription_State_Email {

    private $logger;
    private $source = 'ah-state-reactivation';

    public function __construct() {
        $this->logger = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
    }

    /**
     * Send "state reactivated" email.
     *
     * @param WC_Subscription $subscription
     * @param string         $state Two-letter code, e.g. 'FL'
     * @return bool
     */
    public function send_reactivated_email( WC_Subscription $subscription, $state ) {

        $to = $subscription->get_billing_email();
        if ( empty( $to ) || ! is_email( $to ) ) {
            $this->log( 'warning', 'Invalid billing email; cannot send reactivation email.', [
                'subscription_id' => $subscription->get_id(),
                'email' => $to,
            ] );
            return false;
        }

        $subject = __( 'You can now restore your subscription 💛', 'bh' );

        $myaccount_url = wc_get_page_permalink( 'myaccount' );

        $heading = __( 'You can now restore your subscription 💛', 'bh' );

        $body = wc_get_template_html(
            'emails/subscription-state-reactivated.php',
            [
                'subscription'  => $subscription,
                'state'         => strtoupper( $state ),
                'heading'       => $heading,
                'myaccount_url' => $myaccount_url,
            ],
            '', // default template path
            trailingslashit( __DIR__ ) . 'templates/' // module template base
        );

        if ( empty( $body ) ) {
            $this->log( 'error', 'Email body template returned empty.', [
                'subscription_id' => $subscription->get_id(),
                'state' => $state,
            ] );
            return false;
        }

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        $sent = wc_mail( $to, $subject, $body, $headers );

        $this->log( $sent ? 'info' : 'error', 'Sent reactivation email attempt.', [
            'subscription_id' => $subscription->get_id(),
            'state' => $state,
            'to' => $to,
            'sent' => $sent ? 1 : 0,
        ] );

        return (bool) $sent;
    }

    private function log( $level, $message, array $context = [] ) {
        if ( $this->logger ) {
            $this->logger->log( $level, $message, array_merge( [ 'source' => $this->source ], $context ) );
        }
    }
}
