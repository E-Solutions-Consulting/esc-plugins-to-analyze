<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * [bh_delivery_time]
 *
 * Prints the delivery-time text configured once under Brello > Settings, so
 * pages and Elementor templates don't repeat it by hand.
 */
class BH_Shortcode_Delivery_Time {

    const KEY          = 'delivery_time_text';
    const DEFAULT_TEXT = '2-3 business days';

    public static function init() {
        if ( class_exists( 'BH_Settings' ) ) {
            BH_Settings::register_section(
                'delivery',
                'Delivery',
                'Shipping and delivery copy shared across pages and templates.'
            );

            BH_Settings::register_field( self::KEY, [
                'section'      => 'delivery',
                'label'        => 'Delivery time text',
                'default'      => self::DEFAULT_TEXT,
                'description'  => 'Printed by the <code>[bh_delivery_time]</code> shortcode.',
                'allowed_html' => [
                    'strong' => [],
                    'b'      => [],
                    'em'     => [],
                    'i'      => [],
                    'br'     => [],
                ],
            ] );
        }

        add_shortcode( 'bh_delivery_time', [ __CLASS__, 'render' ] );
    }

    public static function render() {
        return class_exists( 'BH_Settings' )
            ? BH_Settings::get_html( self::KEY )
            : self::DEFAULT_TEXT;
    }
}

BH_Shortcode_Delivery_Time::init();
