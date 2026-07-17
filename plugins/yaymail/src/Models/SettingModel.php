<?php

namespace YayMail\Models;

use YayMail\Utils\SingletonTrait;

/**
 * Setting Model
 *
 * @method static SettingModel get_instance()
 */
class SettingModel {
    use SingletonTrait;

    const OPTION_NAME = 'yaymail_settings';

    public const META_KEYS = [
        'direction'                    => 'direction',
        'container_width'              => 'container_width',
        'payment_display_mode'         => 'payment_display_mode',
        'show_product_image'           => 'show_product_image',
        'product_image_position'       => 'product_image_position',
        'product_image_height'         => 'product_image_height',
        'product_image_width'          => 'product_image_width',
        'show_product_sku'             => 'show_product_sku',
        'show_product_description'     => 'show_product_description',
        'show_product_hyper_links'     => 'show_product_hyper_links',
        'show_product_regular_price'   => 'show_product_regular_price',
        'show_product_item_cost'       => 'show_product_item_cost',
        'enable_custom_css'            => 'enable_custom_css',
        'custom_css'                   => 'custom_css',
        'global_header_footer_enabled' => 'global_header_footer_enabled',
    ];

    // TODO: change variable name to be more meaning in db ( when initialize )
    const DEFAULT = [
        self::META_KEYS['direction']                    => 'ltr',
        self::META_KEYS['container_width']              => 605,
        self::META_KEYS['payment_display_mode']         => 'yes',
        self::META_KEYS['show_product_image']           => false,
        self::META_KEYS['product_image_position']       => 'top',
        self::META_KEYS['product_image_height']         => 30,
        self::META_KEYS['product_image_width']          => 30,
        self::META_KEYS['show_product_sku']             => true,
        self::META_KEYS['show_product_description']     => false,
        self::META_KEYS['show_product_hyper_links']     => false,
        self::META_KEYS['show_product_regular_price']   => false,
        self::META_KEYS['show_product_item_cost']       => false,
        self::META_KEYS['enable_custom_css']            => false,
        self::META_KEYS['custom_css']                   => '',
        self::META_KEYS['global_header_footer_enabled'] => false,
    ];

    public static function find_by_name( $name ) {
        $settings = self::find_all();
        if ( isset( $settings[ $name ] ) && ! empty( $settings[ $name ] ) ) {
            return $settings[ $name ];
        }
        return null;
    }

    public static function find_all() {
        $default_settings = self::DEFAULT;
        $settings         = get_option( self::OPTION_NAME, [] );
        if ( ! is_array( $settings ) ) {
            return $default_settings;
        }
        return wp_parse_args( $settings, $default_settings );
    }

    public static function update( $settings ) {
        $settings_option = get_option( self::OPTION_NAME );
        if ( ! empty( $settings ) && is_array( $settings ) ) {
            return update_option( self::OPTION_NAME, wp_parse_args( $settings, $settings_option ) );
        }
        return false;
    }

    public static function delete() {
        delete_option( self::OPTION_NAME );
    }
}
