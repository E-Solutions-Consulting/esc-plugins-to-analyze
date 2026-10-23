<?php
/**
 * Renewal review notice email (HTML).
 *
 * @var string $email_heading
 * @var string $body_html Already sanitized by the email class.
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

echo wp_kses_post( $body_html );

do_action( 'woocommerce_email_footer', $email );
