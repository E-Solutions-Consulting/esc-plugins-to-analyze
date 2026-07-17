<?php

namespace YayMail\Shortcodes;

use YayMail\Abstracts\BaseShortcode;
use YayMail\Utils\SingletonTrait;
use YayMail\Utils\TemplateHelpers;

/**
 * @method: static NewUsersShortcodes get_instance()
 */
class NewUsersShortcodes extends BaseShortcode {
    use SingletonTrait;

    public $available_email_ids = [
        'customer_new_account',
    ];

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'yaymail_user_new_password',
            'description' => __( 'User New Password', 'yaymail' ),
            'group'       => 'new_users',
            'callback'    => [ $this, 'yaymail_user_new_password' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_set_password_link',
            'description' => __( 'User Set New Password Link', 'yaymail' ),
            'attributes'  => [
                'text_link' => __( 'Click here to set your new password.', 'woocommerce' ),
            ],
            'group'       => 'new_users',
            'callback'    => [ $this, 'yaymail_set_password_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_set_password_url',
            'description' => __( 'User Set New Password URL (String)', 'yaymail' ),
            'group'       => 'new_users',
            'callback'    => [ $this, 'yaymail_set_password_url' ],
        ];
        return $shortcodes;
    }

    public function yaymail_user_new_password( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'G(UAM1(eIX#G', 'yaymail' );
        }

        return ! empty( $render_data['email']->user_pass ) ? $render_data['email']->user_pass : '';
    }

    public function yaymail_set_password_link( $data, $shortcode_atts = [] ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Click here to set your new password.', 'yaymail' ), $is_placeholder );

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $url = wc_customer_edit_account_url();

            return wp_kses_post( "<a href='$url'> $text_link </a>" );
        }

        if ( isset( $render_data['set_password_url'] ) && ! empty( $render_data['set_password_url'] ) ) {

            $url = $render_data['set_password_url'];

            return wp_kses_post( "<a href='$url'> $text_link </a>" );
        }

        return '';
    }

    public function yaymail_set_password_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( wc_customer_edit_account_url() );
        }

        return ! empty( $render_data['set_password_url'] ) ? $render_data['set_password_url'] : '';
    }
}
