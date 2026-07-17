<?php

namespace YayMail\Shortcodes;

use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;
use YayMail\Abstracts\BaseShortcode;

/**
 * @since 4.0.6
 * @method: static RefundShortcodes get_instance()
 */
class RefundShortcodes extends BaseShortcode {
    use SingletonTrait;

    protected function __construct() {
        $this->available_email_ids = [ 'customer_refunded_order', 'customer_pos_refunded_order' ];
        parent::__construct();
    }

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'yaymail_refund_type',
            'description' => __( 'Refund Type', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_refund_type' ],
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
    public function yaymail_refund_type( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '(partially) refunded', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order
             */
            return '';
        }

        return ! empty( $render_data['partial_refund'] ) ? __( 'partially refunded', 'woocommerce' ) : __( 'refunded', 'woocommerce' );
    }
}
