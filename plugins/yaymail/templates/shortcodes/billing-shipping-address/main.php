<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayMail\Utils\TemplateHelpers;
use YayMail\Utils\Helpers;

if ( ! isset( $args['order'] ) || ! ( Helpers::is_woocommerce_order( $args['order'] ) ) ) {
    return;
}

$order_instance   = $args['order'];
$billing_address  = $order_instance->get_formatted_billing_address();
$shipping_address = $order_instance->get_formatted_shipping_address();

if ( empty( $billing_address ) && empty( $shipping_address ) ) {
    return;
} else {
    if ( ! empty( $billing_address ) && ! empty( $shipping_address ) ) {
        $width = '50%';
    } else {
        $width = '100%';
    }
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
    $text_style  = TemplateHelpers::get_style(
        [
            'font-family' => TemplateHelpers::get_font_family_value( YAYMAIL_DEFAULT_FAMILY ) . ' !important',
        ]
    );
    ?>
    <table style="<?php echo esc_attr( $table_style ); ?>">
        <tbody>
            <tr>
                <?php
                if ( ! empty( $billing_address ) ) :
                        $billing_phone = $order_instance->get_billing_phone();
                        $billing_email = $order_instance->get_billing_email();
                    ?>
                <td style="width: <?php echo esc_attr( $width ); ?>; vertical-align: top;">
                    <h2 style="padding: 0; margin: 0 0 12px 0; font-size: 20px; color: <?php echo esc_attr( YAYMAIL_COLOR_WC_DEFAULT ); ?>"><?php esc_html_e( 'Billing Address', 'woocommerce' ); ?></h2>
                    <div style="<?php echo esc_attr( $wrap_style ); ?>">
                        <div style="padding: 12px; color: <?php echo esc_attr( YAYMAIL_COLOR_TEXT_DEFAULT ); ?>;">
                            <address style="<?php echo esc_attr( $text_style ); ?>">
                                <?php echo wp_kses_post( $billing_address ); ?>
                                <?php if ( ! empty( $billing_phone ) ) : ?>
                                <br/>
                                <a href='tel:<?php echo esc_attr( $billing_phone ); ?>' style="font-family: inherit;color:<?php echo esc_attr( $args['text_link_color'] ); ?>;">
                                    <?php echo esc_html( $billing_phone ); ?>
                                </a>
                                <?php endif; ?>
                                <?php if ( ! empty( $billing_email ) ) : ?>
                                    <br/>
                                    <a href='mailto:<?php echo esc_attr( $billing_email ); ?>' style="font-family: inherit;color:<?php echo esc_attr( $args['text_link_color'] ); ?>;">
                                        <?php echo esc_html( $billing_email ); ?>
                                    </a>
                                <?php endif; ?>
                            </address>
                        </div>
                    </div>
                </td>
                <?php endif; ?>
                <?php
                if ( ! empty( $shipping_address ) ) :
                        $shipping_phone = $order_instance->get_shipping_phone();
                    ?>
                <td style="width: <?php echo esc_attr( $width ); ?>; vertical-align: top;">
                    <h2 style="padding: 12px; font-size: 20px; color: <?php echo esc_attr( YAYMAIL_COLOR_WC_DEFAULT ); ?>"><?php esc_html_e( 'Shipping Address', 'woocommerce' ); ?></h2>
                    <div style="<?php echo esc_attr( $wrap_style ); ?>">
                        <div style="padding: 12px; color: <?php echo esc_attr( YAYMAIL_COLOR_TEXT_DEFAULT ); ?>;">
                            <address style="<?php echo esc_attr( $text_style ); ?>">
                                <?php echo wp_kses_post( $shipping_address ); ?>
                                <?php if ( ! empty( $shipping_phone ) ) : ?>
                                <br/>
                                <a href='tel:<?php echo esc_attr( $shipping_phone ); ?>' style="font-family: inherit; color:<?php echo esc_attr( $args['text_link_color'] ); ?>;">
                                    <?php echo esc_html( $shipping_phone ); ?>
                                </a>
                                <?php endif; ?>
                            </address>
                        </div>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>
    <?php
}//end if
