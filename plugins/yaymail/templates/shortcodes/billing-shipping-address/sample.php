<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use YayMail\Utils\TemplateHelpers;

$billing_address  = '<span>John Doe <br>YayCommerce <br>7400 Edwards Rd <br>Edwards Rd</span>';
$shipping_address = '<span>John Doe <br>YayCommerce <br>755 E North Grove Rd <br>Mayville, Michigan</span>';

$billing_phone  = '(910) 529-1147';
$shipping_phone = '(910) 529-1147';

$table_style = TemplateHelpers::get_style(
    [
        'width'           => '100%',
        'text-align'      => 'left',
        'border-collapse' => 'separate',
        'border-spacing'  => '5px !important',
    ]
);
$wrap_style  = TemplateHelpers::get_style(
    [
        'border' => 'solid 1px ' . YAYMAIL_COLOR_BORDER_DEFAULT,
    ]
);

$text_style = TemplateHelpers::get_style(
    [
        'font-family' => TemplateHelpers::get_font_family_value( YAYMAIL_DEFAULT_FAMILY ) . ' !important',
    ]
);

?>

<table style="<?php echo esc_attr( $table_style ); ?>">
    <tbody>
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <h2 style="padding: 0; margin: 0 0 12px 0; font-size: 20px; color: <?php echo esc_attr( YAYMAIL_COLOR_WC_DEFAULT ); ?>"><?php esc_html_e( 'Billing Address', 'woocommerce' ); ?></h2>
                <div style="<?php echo esc_attr( $wrap_style ); ?>">
                    <div style="padding: 12px; color: <?php echo esc_attr( YAYMAIL_COLOR_TEXT_DEFAULT ); ?>;">
                        <address style="<?php echo esc_attr( $text_style ); ?>">
                            <?php echo wp_kses_post( $billing_address ); ?>
                            <br/>
                            <a href='tel:<?php echo esc_attr( $billing_phone ); ?>' style="font-family: inherit;color:<?php echo esc_attr( $args['text_link_color'] ); ?>;">
                                <?php echo esc_html( $billing_phone ); ?>
                            </a>
                        </address>
                    </div>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <h2 style="padding: 12px; font-size: 20px; color: <?php echo esc_attr( YAYMAIL_COLOR_WC_DEFAULT ); ?>"><?php esc_html_e( 'Shipping Address', 'woocommerce' ); ?></h2>
                <div style="<?php echo esc_attr( $wrap_style ); ?>">
                    <div style="padding: 12px; color: <?php echo esc_attr( YAYMAIL_COLOR_TEXT_DEFAULT ); ?>;">
                        <address style="<?php echo esc_attr( $text_style ); ?>">
                            <?php echo wp_kses_post( $shipping_address ); ?>
                            <br/>
                            <a href='tel:<?php echo esc_attr( $shipping_phone ); ?>' style="font-family: inherit; color:<?php echo esc_attr( $args['text_link_color'] ); ?>;">
                                <?php echo esc_html( $shipping_phone ); ?>
                            </a>
                        </address>
                    </div>
                </div>
            </td>
        </tr>
    </tbody>
</table>
