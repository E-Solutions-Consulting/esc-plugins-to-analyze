<?php

namespace YayMail\Integrations\F4ShippingPhoneAndEmailForWooCommerce;

use YayMail\Utils\SingletonTrait;

/**
 * F4 Shipping Phone and E-Mail for WooCommerce
 * Link: https://en-ca.wordpress.org/plugins/f4-woocommerce-shipping-phone-and-e-mail/
 *
 * F4ShippingPhoneAndEmailForWooCommerce
 * * @method static F4ShippingPhoneAndEmailForWooCommerce get_instance()
 */
class F4ShippingPhoneAndEmailForWooCommerce {
    use SingletonTrait;

    private function __construct() {
        if ( self::is_3rd_party_installed() ) {
            $this->initialize_hooks();
        }
    }

    public static function is_3rd_party_installed() {
        return class_exists( 'F4\WCSPE\Core\Hooks' );
    }

    private function initialize_hooks() {
        add_filter( 'yaymail_shipping_address_content', [ $this, 'add_shipping_phone_and_email_to_shipping_address' ], 10, 2 );
    }

    public function add_shipping_phone_and_email_to_shipping_address( $shipping_address, $order ) {

        $shipping_phone = $order->get_shipping_phone();
        $shipping_email = $order->get_meta( '_shipping_email' );

        if ( ! empty( $shipping_phone ) ) {
            if ( strpos( $shipping_address, '<a href="tel:' . esc_attr( $shipping_phone ) . '">' ) === false ) {
                $html_shipping_phone = '<a class="yaymail-shipping-phone" href="tel:' . esc_attr( $shipping_phone ) . '">' . esc_html( $shipping_phone ) . '</a>';
                $shipping_address    = str_replace( $shipping_phone, $html_shipping_phone, $shipping_address );
            }
        }

        if ( ! empty( $shipping_email ) ) {
            if ( strpos( $shipping_address, '<a href="mailto:' . esc_attr( $shipping_email ) . '">' ) === false ) {
                $html_shipping_email = '<a class="yaymail-shipping-email" href="mailto:' . esc_attr( $shipping_email ) . '">' . esc_html( $shipping_email ) . '</a>';
                $shipping_address    = str_replace( $shipping_email, $html_shipping_email, $shipping_address );
            }
        }

        $shipping_address = trim( $shipping_address );

        return $shipping_address;
    }
}
