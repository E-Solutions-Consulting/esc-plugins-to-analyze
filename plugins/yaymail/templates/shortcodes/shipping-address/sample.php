<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$address        = '<span>John Doe <br>YayCommerce <br>755 E North Grove Rd <br>Mayville, Michigan</span>';
$shipping_phone = '(910) 529-1147';
?>
<address>
    <?php echo wp_kses_post( $address ); ?>
    <br/>
    <a href='tel:<?php echo esc_attr( $shipping_phone ); ?>' style="font-family: inherit">
        <?php echo esc_html( $shipping_phone ); ?>
    </a>
    <br>
</address>
