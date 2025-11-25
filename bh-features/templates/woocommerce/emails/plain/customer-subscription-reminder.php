<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

echo $email_heading . "\n\n";
?>

<?php echo sprintf(__('Hi %s,', 'woocommerce'), $subscription->get_billing_first_name()) . "\n\n"; ?>

<?php echo sprintf(
    __('Este es un recordatorio de que tu suscripción #%1$s se renovará el %2$s por %3$s.', 'woocommerce'),
    $subscription->get_order_number(),
    $subscription->get_date_to_display('next_payment'),
    $subscription->get_formatted_order_total()
) . "\n\n"; ?>

<?php echo __('Thank you for choosing Brello for your care.', 'woocommerce') . "\n\n"; ?>
<?php echo __('Warmly,', 'woocommerce') . "\n"; ?>
<?php echo __('The Brello Health Team.', 'woocommerce') . "\n\n"; ?>