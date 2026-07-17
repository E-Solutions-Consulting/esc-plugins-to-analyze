<?php

namespace YayMail\Shortcodes;

use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;
use YayMail\Abstracts\BaseShortcode;

/**
 * @method: static BillingShortcodes get_instance()
 */
class BillingShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_address',
            'description' => __( 'Billing Address', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_address' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_address_1',
            'description' => __( 'Billing Address 1', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_address_1' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_address_2',
            'description' => __( 'Billing Address 2', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_address_2' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_first_name',
            'description' => __( 'Billing First Name', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_first_name' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_last_name',
            'description' => __( 'Billing Last Name', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_last_name' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_company',
            'description' => __( 'Billing Company', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_company' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_city',
            'description' => __( 'Billing City', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_city' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_country',
            'description' => __( 'Billing Country', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_country' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_state',
            'description' => __( 'Billing State', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_state' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_postcode',
            'description' => __( 'Billing Postal Code', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_postcode' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_phone',
            'description' => __( 'Billing Phone', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_phone' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_email',
            'description' => __( 'Billing Email', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_email' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_billing_shipping_address',
            'description' => __( 'Billing Shipping Address', 'yaymail' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'yaymail_billing_shipping_address' ],
        ];
        return $shortcodes;
    }

    /**
     * Render order billing shortcode
     *
     * @param $args includes
     * $render_data
     * $element
     * $settings
     * $is_placeholder
     */
    public function yaymail_billing_shipping_address( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $template = ! empty( $data['template'] ) ? $data['template'] : null;
        if ( empty( $template ) ) {
            $text_link_color = YAYMAIL_COLOR_WC_DEFAULT;
        } else {
            $text_link_color = $template->get_text_link_color();
        }

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $args = [
                'text_link_color' => $text_link_color,
            ];
            $html = yaymail_get_content( 'templates/shortcodes/billing-shipping-address/sample.php', $args );
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
            'order'           => $order,
            'text_link_color' => $text_link_color,
        ];
        $html = yaymail_get_content( 'templates/shortcodes/billing-shipping-address/main.php', $args );
        return $html;
    }

    public function yaymail_billing_address( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = yaymail_get_content( 'templates/shortcodes/billing-address/sample.php' );
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
        $html = yaymail_get_content( 'templates/shortcodes/billing-address/main.php', $args );
        return $html;
    }

    public function yaymail_billing_address_1( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '7400 Edwards Rd', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_address_1() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_address_1();
    }

    public function yaymail_billing_address_2( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '7400 Edwards Rd', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_address_2() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_address_2();
    }

    public function yaymail_billing_first_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'John', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_first_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_first_name();
    }

    public function yaymail_billing_last_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Doe', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_last_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_last_name();
    }

    public function yaymail_billing_company( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'YayCommerce', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_company() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_company();
    }

    public function yaymail_billing_city( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Edwards Rd', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_city() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_city();
    }

    public function yaymail_billing_country( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'United States', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_country() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $order_billing_country_code = $order->get_billing_country();
        $wc_countries               = \WC()->countries;
        return $wc_countries->countries[ $order_billing_country_code ];
    }

    public function yaymail_billing_state( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Random', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_state() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_state();
    }

    public function yaymail_billing_postcode( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '48744', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_postcode() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_postcode();
    }

    public function yaymail_billing_phone( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '(910) 529-1147', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_phone() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_phone();
    }

    public function yaymail_billing_email( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $template = ! empty( $data['template'] ) ? $data['template'] : null;
        if ( empty( $template ) ) {
            $text_link_color = YAYMAIL_COLOR_WC_DEFAULT;
        } else {
            $text_link_color = $template->get_text_link_color();
        }

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '<a href="#">' . __( 'johndoe@gmail.com', 'yaymail' ) . '</a>';

        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_email() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return '<a href="mailto:' . esc_url( $order->get_billing_email() ) . '">' . $order->get_billing_email() . '</a>';
    }
}
