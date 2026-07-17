<?php

namespace YayMail\Integrations\DHL;

use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;
use YayMail\Abstracts\BaseShortcode;

/**
 * @method: static DHLShortcodes get_instance()
 */
class DHLShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'yaymail_dhl_tracking_number',
            'description' => __( 'DHL Tracking Number', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_dhl_tracking_number' ],
        ];
        return $shortcodes;
    }

    public function yaymail_dhl_tracking_number( $data ) {

        $render_data           = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $is_placeholder        = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;
        $is_customized_preview = isset( $render_data['is_customized_preview'] ) ? $render_data['is_customized_preview'] : false;

        if ( ! empty( $render_data['is_sample'] ) ) {
            return '#123456';
        }

        if ( $is_placeholder || $is_customized_preview ) {
            return '#123456';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( $order ) {
            $label = get_post_meta( $order->id, '_pr_shipment_dhl_label_tracking', true );
            if ( empty( $label['tracking_number'] ) ) {
                return '';
            }

            if ( ! class_exists( 'PR_DHL_WC_Order_Paket' ) ) {
                return '';
            }

            $shipping_dhl_settings     = null;
            $pr_dhl_wc_order_paket     = new \PR_DHL_WC_Order_Paket();
            $arr_pr_dhl_wc_order_paket = (array) $pr_dhl_wc_order_paket;

            if ( ! empty( $arr_pr_dhl_wc_order_paket ) ) {
                foreach ( $arr_pr_dhl_wc_order_paket as $key => $value ) {
                    if ( strpos( $key, 'shipping_dhl_settings' ) !== false ) {
                        $shipping_dhl_settings = $arr_pr_dhl_wc_order_paket[ $key ];
                    }
                }
            }

            $dhl_tracking_url_language = isset( $shipping_dhl_settings['dhl_tracking_url_language'] ) ? $shipping_dhl_settings['dhl_tracking_url_language'] : null;
            $tracking_url              = null;

            if ( 'en' == $dhl_tracking_url_language && defined( 'PR_DHL_PAKET_TRACKING_URL_EN' ) ) {
                $tracking_url = PR_DHL_PAKET_TRACKING_URL_EN;
            }

            $tracking_url    = defined( 'PR_DHL_PAKET_TRACKING_URL' ) ? PR_DHL_PAKET_TRACKING_URL : '';
            $tracking_number = is_array( $label['tracking_number'] ) ? $label['tracking_number'][0] : $label['tracking_number'];
            /* translators: %s: Order tracking info. */
            $tracking_text = sprintf( __( 'DHL Tracking Number: <a class="yaymai_dhl_tracking_url" href="%1$s%2$s" target="_blank">%3$s</a>', 'dhl-for-woocommerce' ), $tracking_url, $tracking_number, $tracking_number );

            return $tracking_text;
        } else {
            return '';
        }//end if
    }
}
