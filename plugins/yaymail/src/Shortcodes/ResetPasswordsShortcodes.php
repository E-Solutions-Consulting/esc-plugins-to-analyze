<?php

namespace YayMail\Shortcodes;

use YayMail\Abstracts\BaseShortcode;
use YayMail\Utils\SingletonTrait;

/**
 * @method: static ResetPasswordsShortcodes get_instance()
 */
class ResetPasswordsShortcodes extends BaseShortcode {
    use SingletonTrait;

    public $available_email_ids = [
        'customer_reset_password',
    ];

    public function get_shortcodes() {
        $shortcodes[] = [
            'name'        => 'yaymail_password_reset_link',
            'description' => __( 'Click here to reset your password', 'woocommerce' ),
            'group'       => 'reset_passwords',
            'callback'    => [ $this, 'yaymail_password_reset_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_password_reset_url',
            'description' => __( 'Password Reset URL (String)', 'yaymail' ),
            'group'       => 'reset_passwords',
            'callback'    => [ $this, 'yaymail_password_reset_url' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_wp_password_reset_url',
            'description' => __( 'Password Reset URL by WP', 'yaymail' ),
            'group'       => 'reset_passwords',
            'callback'    => [ $this, 'yaymail_wp_password_reset_url' ],
        ];
        return $shortcodes;
    }

    public function yaymail_password_reset_link( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $link_text = esc_html__( 'Click here to reset your password', 'woocommerce' );

        if ( ! empty( $render_data['is_sample'] ) || ( empty( $render_data['reset_key'] ) && empty( $render_data['email'] ) ) ) {
            /**
             * Is sample order
             */

            $link_reset = get_home_url() . '/my-account/lost-password';

            return wp_kses_post( "<a href='$link_reset'> $link_text </a>" );
        }

        $user = new \WP_User( intval( $render_data['email']->user_id ) );

        $link_reset = add_query_arg(
            [
                'key' => $render_data['reset_key'],
                'id'  => $user->ID,
            ],
            wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
        );

        return wp_kses_post( "<a href='$link_reset'> $link_text </a>" );
    }

    public function yaymail_password_reset_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) || ( empty( $render_data['reset_key'] ) && empty( $render_data['email'] ) ) ) {
            /**
             * Is sample order
             */
            return esc_url( get_home_url() . '/my-account/lost-password' );
        }

        if ( empty( $render_data['reset_key'] ) && empty( $render_data['email'] ) ) {
            return '';
        }

        $user = new \WP_User( intval( $render_data['email']->user_id ) );

        $link_reset = add_query_arg(
            [
                'key' => $render_data['reset_key'],
                'id'  => $user->ID,
            ],
            wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
        );

        return esc_url( $link_reset );
    }

    public function yaymail_wp_password_reset_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( get_home_url() . '/my-account/lost-password' );
        }

        if ( isset( $render_data['email']->user_login ) && isset( $render_data['email']->user_data ) && isset( $render_data['email']->key ) ) {
            $locale     = get_user_locale( $render_data['email']->user_data );
            $key        = $render_data['email']->key;
            $user_login = $render_data['email']->user_login;
            return network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . '&wp_lang=' . $locale;
        }
        return '';
    }
}
