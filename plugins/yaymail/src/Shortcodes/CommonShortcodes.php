<?php

namespace YayMail\Shortcodes;

use YayMail\Abstracts\BaseShortcode;
use YayMail\Utils\Helpers;
use YayMail\Utils\TemplateHelpers;
use YayMail\Utils\SingletonTrait;

/**
 * @method: static CommonShortcodes get_instance()
 */
class CommonShortcodes extends BaseShortcode {

    use SingletonTrait;

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public function get_shortcodes() {
        $shortcodes = [
            [
                'name'        => 'yaymail_site_name',
                'description' => __( 'Site Name', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_site_name' ],
            ],
            [
                'name'        => 'yaymail_site_link',
                'description' => __( 'Site Link', 'yaymail' ),
                'attributes'  => [
                    'text_link' => __( 'Home URL', 'yaymail' ),
                ],
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_site_link' ],
            ],
            [
                'name'        => 'yaymail_site_url',
                'description' => __( 'Site URL (String)', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_site_url' ],
            ],
            [
                'name'        => 'yaymail_domain',
                'description' => __( 'Domain', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_domain' ],
            ],
            [
                'name'        => 'yaymail_user_account_link',
                'description' => __( 'User Account Link', 'yaymail' ),
                'attributes'  => [
                    'text_link' => __( 'My Account', 'yaymail' ),
                ],
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_user_account_link' ],
            ],
            [
                'name'        => 'yaymail_user_account_url',
                'description' => __( 'User Account URL (String)', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_user_account_url' ],
            ],
            [
                'name'        => 'yaymail_user_email',
                'description' => __( 'User Email', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_user_email' ],
            ],
            [
                'name'        => 'yaymail_user_id',
                'description' => __( 'User ID', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_user_id' ],
            ],
            [
                'name'        => 'yaymail_customer_username',
                'description' => __( 'Username', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_customer_username' ],
            ],
            [
                'name'        => 'yaymail_customer_name',
                'description' => __( 'Name', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_customer_name' ],
            ],
            [
                'name'        => 'yaymail_customer_first_name',
                'description' => __( 'First Name', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_customer_first_name' ],
            ],
            [
                'name'        => 'yaymail_customer_last_name',
                'description' => __( 'Last Name', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_customer_last_name' ],
            ],
            [
                'name'        => 'yaymail_get_heading',
                'description' => __( 'Email heading', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_get_heading' ],
            ],
            [
                'name'        => 'yaymail_additional_content',
                'description' => __( 'Additional Content', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_additional_content' ],
            ],
            [
                'name'        => 'yaymail_store_email',
                'description' => __( 'Store Email', 'yaymail' ),
                'group'       => 'general',
                'callback'    => [ $this, 'yaymail_store_email' ],
            ],
        ];

        return apply_filters( 'yaymail_common_shortcodes', $shortcodes );
    }

    public function yaymail_site_name() {
        return esc_html( get_bloginfo( 'name' ) );
    }

    public function yaymail_site_link( $data, $shortcode_atts = [] ) {
        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Home URL', 'yaymail' ), $is_placeholder );

        return '<a href="' . esc_url( get_home_url() ) . '"> ' . $text_link . ' </a>';
    }

    public function yaymail_site_url() {
        return esc_url( get_home_url() );
    }

    public function yaymail_domain() {
        if ( ! empty( wp_parse_url( get_site_url() )['host'] ) ) {
            return wp_parse_url( get_site_url() )['host'];
        } return '';
    }

    public function yaymail_user_account_link( $data, $shortcode_atts = [] ) {
        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'My Account', 'yaymail' ), $is_placeholder );

        return '<a href="' . esc_url( function_exists( 'WC' ) && defined( 'YAYMAIL_VERSION' ) ? wc_get_page_permalink( 'myaccount' ) : '' ) . '"> ' . $text_link . ' </a>';
    }

    public function yaymail_user_account_url() {
        return esc_url( function_exists( 'WC' ) && defined( 'YAYMAIL_VERSION' ) ? wc_get_page_permalink( 'myaccount' ) : '' );
    }

    public function yaymail_user_email( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'johndoe@gmail.com';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                return $render_data['email']->user_email;
            }
            $user = wp_get_current_user();
            return ! empty( $user ) ? $user->data->user_email : '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) && ! empty( $user->user_email ) ) {
                return $user->user_email;
            } else {
                return $order->get_billing_email();
            }
        }
    }

    public function yaymail_user_id( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return '0';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->object ) ) {
                if ( $render_data['email']->object instanceof \WP_User ) {
                    return $render_data['email']->object->ID;
                }
            }
            $user = wp_get_current_user();
            return ! empty( $user ) ? $user->ID : '0';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) && ! empty( $user->ID ) ) {
                return $user->ID;
            } else {
                $user = get_user_by( 'email', $order->get_billing_email() );
                if ( ! empty( $user ) && ! empty( $user->ID ) ) {
                    return $user->ID;
                }
                return '';
            }
        }
    }

    public function yaymail_customer_username( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'johndoe';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_login ) ) {
                return $render_data['email']->user_login;
            }
            $user = wp_get_current_user();
            return ! empty( $user ) ? $user->user_login : '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) && ! empty( $user->user_login ) ) {
                return $user->user_login;
            } else {
                $user = get_user_by( 'email', $order->get_billing_email() );
                if ( ! empty( $user ) && ! empty( $user->user_login ) ) {
                    return $user->user_login;
                }
                return '';
            }
        }
    }

    public function yaymail_customer_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'John Doe';
        }
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                $user = get_user_by( 'email', $render_data['email']->user_email );
            } else {
                $user = wp_get_current_user();
            }
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
                return ' ' !== $name ? $name : $user->user_nicename;
            }
            return '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
                if ( ' ' === $name ) {
                    $name = $user->user_nicename;
                }
            }
            if ( empty( $name ) ) {
                $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            }
            return ! empty( $name ) ? $name : '';
        }//end if
    }

    public function yaymail_customer_first_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'John';
        }
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                $user = get_user_by( 'email', $render_data['email']->user_email );
            } else {
                $user = wp_get_current_user();
            }
            if ( ! empty( $user ) ) {
                return get_user_meta( $user->ID, 'first_name', true );
            }
            return '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'first_name', true );
            }
            if ( empty( $name ) ) {
                $name = $order->get_billing_first_name();
            }
            return $name;
        }
    }

    public function yaymail_customer_last_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'Doe';
        }
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                $user = get_user_by( 'email', $render_data['email']->user_email );
            } else {
                $user = wp_get_current_user();
            }
            if ( ! empty( $user ) ) {
                return get_user_meta( $user->ID, 'last_name', true );
            }
            return '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'last_name', true );
            }
            if ( empty( $name ) ) {
                $name = $order->get_billing_last_name();
            }
            return $name;
        }
    }

    public function yaymail_get_heading( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( isset( $render_data['email_heading'] ) ) {
            return $render_data['email_heading'];
        } else {
            $order = Helpers::get_order_from_shortcode_data( $render_data );
            if ( ! empty( $order ) && isset( $render_data['email'] ) && 'customer_refunded_order' === $render_data['email']->id ) {
                return 'Order Refunded: ' . ! empty( $order ) ? $order->get_id() : '1';
            }
        }
        return __( 'Email heading', 'yaymail' );
    }

    public function yaymail_additional_content( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( isset( $render_data['additional_content'] ) ) {
            return wpautop( wptexturize( $render_data['additional_content'] ) );
        }
        return __( 'Additional content', 'yaymail' );
    }

    public function yaymail_store_email( $data ) {
        $store_email = sanitize_email( get_option( 'woocommerce_email_from_address' ) ?? '' );

        return $store_email;
    }
}
