<?php do_action('woocommerce_email_header', $email_heading, $email); ?>

<p><?php printf(__('Hi %s,', 'woocommerce'), $subscription->get_billing_first_name()); ?></p>

<?php
$next_payment   =   $subscription->get_date('next_payment');
$formatted_date =   is_a($next_payment, 'WC_DateTime') ? 
                    $next_payment->date_i18n('F j, Y') : 
                    date_i18n('F j, Y', strtotime($next_payment));
$interval = $subscription->get_billing_interval();
$formatted_order_total = wc_price($subscription->get_total());

$custom_text = '';
$custom_text_2 = '';
if ( $interval == 1 ) {
    $custom_text = '25 days';
    $custom_text_2 = 'Monthly';
} elseif ( $interval == 3 ) {
    $custom_text = '10 weeks';
    $custom_text_2 = '3 Month';
}

?>
<p><?php printf(
    __('This is a quick reminder that your subscription (<strong>#%1$s</strong>) is scheduled to automatically renew on <strong>%2$s</strong> for <strong>%3$s</strong>. Your plan renews every <strong>%4$s</strong>, which ensures enough time for your provider’s review, order processing, and shipment—so you receive your next refill on time (pending provider approval).', 'woocommerce'),
    $subscription->get_order_number(),
    $formatted_date,
    $formatted_order_total,
    $custom_text
); ?></p>

<p><?php printf(
    __('As part of the renewal process, you\'ll be prompted to complete a <strong>Follow-Up Intake Form</strong>. 
    This allows your healthcare provider to review your information and determine if another %1$s plan is appropriate.', 'woocommerce'),
    $custom_text_2
); ?></p>

<?php do_action('woocommerce_subscriptions_email_order_details', $subscription, $sent_to_admin, $plain_text, $email); ?>

<p>If you’d like to make any changes before your renewal—such as updating your details, adjusting your plan, or pausing your subscription—you can:</p>
<ul>
    <li><a href="https://shop.brellohealth.com/my-account">Log into your account</a> to review your current information and make updates as needed.</li>
    <li>Contact our support team at <a href="mailto:info@brellohealth.com">info@brellohealth.com</a> and we’ll be happy to assist.</li>
</ul>

<p>Thank you for choosing Brello for your care. 💜</p>
<br>
<p>Warmly,<br>
<strong>The Brello Health Team</strong></p>

<?php do_action('woocommerce_email_footer', $email); ?>
