<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$address       = '<span>John Doe <br>YayCommerce <br>7400 Edwards Rd <br>Edwards Rd</span>';
$billing_phone = '(910) 529-1147';
?>
<address>
    <?php echo wp_kses_post( $address ); ?>
    <br/>
    <a href='tel:<?php echo esc_attr( $billing_phone ); ?>' style="font-family: inherit">
        <?php echo esc_html( $billing_phone ); ?>
    </a>
    <br>
</address>
