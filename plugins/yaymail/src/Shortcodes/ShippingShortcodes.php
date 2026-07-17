<?php

namespace YayMail\Shortcodes;

use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;
use YayMail\Abstracts\BaseShortcode;

/**
 * @method: static ShippingShortcodes get_instance()
 */
class ShippingShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_address',
            'description' => __( 'Shipping Address', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_address' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_address_1',
            'description' => __( 'Shipping Address 1', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_address_1' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_address_2',
            'description' => __( 'Shipping Address 2', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_address_2' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_first_name',
            'description' => __( 'Shipping First Name', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_first_name' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_last_name',
            'description' => __( 'Shipping Last Name', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_last_name' ],
        ];

        $shortcodes[] = [
            'name'        => 'yaymail_shipping_company',
            'description' => __( 'Shipping Company', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_company' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_city',
            'description' => __( 'Shipping City', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_city' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_country',
            'description' => __( 'Shipping Country', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_country' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_state',
            'description' => __( 'Shipping State', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_state' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_postcode',
            'description' => __( 'Shipping Postal Code', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_postcode' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_phone',
            'description' => __( 'Shipping Phone', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_phone' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_method',
            'description' => __( 'Shipping Method', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_method' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_shipping_total',
            'description' => __( 'Shipping Total', 'yaymail' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'yaymail_shipping_total' ],
        ];
        return $shortcodes;
    }

    /**
     * Render order shipping shortcode
     *
     * @param $args includes
     * $render_data
     * $element
     * $settings
     * $is_placeholder
     */
    public function yaymail_shipping_address( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = yaymail_get_content( 'templates/shortcodes/shipping-address/sample.php' );
            return $html;
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order/order_id
             */
            return '';
        }

        $args = [
            'order' => $order,
        ];
        $html = yaymail_get_content( 'templates/shortcodes/shipping-address/main.php', $args );
        return $html;
    }

    public function yaymail_shipping_address_1( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '755 E North Grove Rd', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_address_1() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_address_1();
    }

    public function yaymail_shipping_address_2( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '755 E North Grove Rd', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_address_2() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_address_2();
    }

    public function yaymail_shipping_first_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'John', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_first_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_first_name();
    }

    public function yaymail_shipping_last_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Doe', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_last_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_last_name();
    }

    public function yaymail_shipping_company( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'YayCommerce', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_company() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_company();
    }

    public function yaymail_shipping_city( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Mayville, Michigan', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_city() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_city();
    }

    public function yaymail_shipping_country( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'United States', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_country() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $order_shipping_country_code = $order->get_shipping_country();
        $wc_countries                = \WC()->countries;
        return $wc_countries->countries[ $order_shipping_country_code ];
    }

    public function yaymail_shipping_state( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Random', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_state() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_state();
    }

    public function yaymail_shipping_postcode( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '48744', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_postcode() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_postcode();
    }

    public function yaymail_shipping_phone( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '(910) 529-1147', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( ! empty( $order ) && method_exists( $order, 'get_shipping_phone' ) && ! empty( $order->get_shipping_phone() ) ) {
            return $order->get_shipping_phone();
        }

        // Not having order_id or empty shipping phone
        return '';
    }

    public function yaymail_shipping_method( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Free shipping', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_method() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_method();
    }

    public function yaymail_shipping_total( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( 0 );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->calculate_shipping() ) ) {
            /**
             * Not having order_id
             */
            return wc_price( 0 );
        }

        if ( isset( $order->get_data()['shipping_total'] ) && ! empty( $order->get_data()['shipping_total'] ) ) {
            return wc_price( $order->get_data()['shipping_total'] + $order->get_data()['shipping_tax'], [ 'currency' => $order->get_currency() ] );
        } else {
            return wc_price( 0, [ 'currency' => $order->get_currency() ] );
        }
    }
}
