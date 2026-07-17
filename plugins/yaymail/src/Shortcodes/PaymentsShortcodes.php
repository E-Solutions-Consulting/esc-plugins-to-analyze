<?php

namespace YayMail\Shortcodes;

use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;
use YayMail\Abstracts\BaseShortcode;
use YayMail\Utils\TemplateHelpers;

/**
 * @method: static PaymentsShortcodes get_instance()
 */
class PaymentsShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'yaymail_order_payment_method',
            'description' => __( 'Payment method', 'yaymail' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'yaymail_order_payment_method' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_payment_link',
            'description' => __( 'Payment Link', 'yaymail' ),
            'attributes'  => [
                'text_link' => __( 'Payment page', 'yaymail' ),
            ],
            'group'       => 'payments',
            'callback'    => [ $this, 'yaymail_order_payment_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_payment_url',
            'description' => __( 'Payment URL (String)', 'yaymail' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'yaymail_order_payment_url' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_payment_instructions',
            'description' => __( 'Payment Instructions', 'yaymail' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'yaymail_payment_instructions' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_payment_transaction_id',
            'description' => __( 'Payment Transaction ID', 'yaymail' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'yaymail_payment_transaction_id' ],
        ];
        return $shortcodes;
    }

    public function yaymail_order_payment_method( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Direct bank transfer', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_item_totals = $order->get_order_item_totals();

        return isset( $order_item_totals['payment_method']['value'] ) ? $order_item_totals['payment_method']['value'] : '';
    }

    public function yaymail_order_payment_link( $data, $shortcode_atts = [] ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Payment page', 'yaymail' ), $is_placeholder );

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '<a href="#">' . $text_link . '</a>';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . $text_link . '</a>';
    }

    public function yaymail_order_payment_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( wc_get_endpoint_url( 'order-pay', 0, wc_get_checkout_url() ) );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_checkout_payment_url();
    }

    public function yaymail_payment_instructions( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Payment Instructions', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $args = [
            'order' => $order,
        ];

        $html = yaymail_get_content( 'templates/shortcodes/payment-instruction/main.php', $args );
        return $html;
    }

    public function yaymail_payment_transaction_id( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_transaction_id() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_transaction_id();
    }
}
