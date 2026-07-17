<?php

namespace YayMail\Shortcodes;

use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;
use YayMail\Utils\Logger;
/**
 * @method: static OrderMetaShortcodes init()
 */
class OrderMetaShortcodes {

    use SingletonTrait;

    private $logger;

    protected function __construct() {
        $this->logger = new Logger();
        add_filter( 'yaymail_extra_shortcodes', [ $this, 'get_order_meta_shortcodes' ], 10, 2 );
        add_filter( 'yaymail_extra_shortcodes', [ $this, 'get_order_tax_shortcodes' ], 10, 2 );
    }

    public function get_order_tax_shortcodes( $shortcodes, $data ) {
        $order = $data['render_data']['order'] ?? null;

        if ( ! $order ) {
            return $shortcodes;
        }

        $tax_items = $order->get_items( 'tax' );

        foreach ( $tax_items as $item_id => $item_tax ) {
            $tax_rate_id   = $item_tax->get_rate_id();
            $new_shortcode = [
                'name'          => "yaymail_order_taxes_{$tax_rate_id}",
                'description'   => $item_tax->get_label(),
                'group'         => 'order_taxes',
                'callback'      => [ $this, 'order_tax_callback' ],
                'callback_args' => [
                    'item_tax' => $item_tax,
                ],
            ];
            $shortcodes[]  = $new_shortcode;
        }

        return $shortcodes;
    }

    public function order_tax_callback( $data, $shortcode_attrs = [] ) {
        $item_tax = $data['item_tax'] ?? '';

        if ( empty( $item_tax ) || ! is_object( $item_tax ) ) {
            return '';
        }

        $tax_amount_total   = $item_tax->get_tax_total();
        $tax_shipping_total = $item_tax->get_shipping_tax_total();
        $totals_taxes       = $tax_amount_total + $tax_shipping_total;
        return wc_price( $totals_taxes );
    }

    /**
     * Init order meta shortcodes
     *
     * @param array $shortcodes The shortcodes array.
     * @param array $data The data array.
     *
     * @return array The shortcodes array.
     */
    public function get_order_meta_shortcodes( $shortcodes, $data ) {
        $order = $data['render_data']['order'] ?? null;

        if ( ! $order && ! empty( $data['render_data']['order_id'] ) ) {
            $order = wc_get_order( $data['render_data']['order_id'] );
        }

        if ( empty( $order ) ) {
            return $shortcodes;
        }

        $metadata = self::get_allowed_order_meta_data( $order );

        foreach ( $metadata as $meta_item ) {
            $data = $meta_item->get_data();

            $field = Helpers::to_snake_case( $data['key'] );

            $description = Helpers::snake_case_to_capitalized_words( $field ) . ' (' . $field . ')';

            $callback = 'order_meta_callback';
            if ( $field === 'pickup_date' || $field === 'delivery_date' ) {
                $callback = 'date_meta_callback';
            }

            $new_shortcode = [
                'name'          => "yaymail_order_meta:{$field}",
                'description'   => $description,
                'group'         => 'order_meta',
                'callback'      => [ $this, $callback ],
                'callback_args' => [
                    'meta_item' => $meta_item,
                    'field'     => $field,
                ],
                'attributes'    => [
                    'is_date' => false,
                ],
            ];

            $shortcodes[] = $new_shortcode;
        }//end foreach

        return $shortcodes;
    }

    public function order_meta_callback( $data, $shortcode_attrs = [] ) {
        $field     = $data['field'] ?? '';
        $meta_item = $data['meta_item'] ?? '';

        if ( empty( $meta_item ) || ! method_exists( $meta_item, 'get_data' ) ) {
            return '';
        }

        $meta_data = $meta_item->get_data();
        $value     = $meta_data['value'];

        if ( ! empty( $shortcode_attrs['is_date'] ) ) {
            $date = is_numeric( $value )
                ? \DateTime::createFromFormat( 'U', $value )
                : \DateTime::createFromFormat( 'Ymd', $value );

            if ( $date ) {
                return date_i18n( wc_date_format(), $date->getTimestamp() );
            }

            $this->logger->log( "Order meta shortcode: field {$field} with value {$value} is not a valid date" );
            return nl2br( $value );
        }

        if ( is_array( $value ) || is_object( $value ) ) {
            if ( is_object( $value ) ) {
                $value = (array) $value;
            }
            $str = isset( $value['extra'] ) && ( is_string( $value['extra'] ) || is_numeric( $value['extra'] ) )
            ? $value['extra']
                : $this->yaymail_flatten_to_string( $value );
            return str_replace( '|', '<br />', $str );
        }

        return $value;
    }

    public function date_meta_callback( $data ) {
        $meta_item = $data['meta_item'] ?? '';

        if ( empty( $meta_item ) || ! method_exists( $meta_item, 'get_data' ) ) {
            return '';
        }

        $meta_data = $meta_item->get_data();
        $value     = $meta_data['value'];

        if ( ! is_string( $value ) ) {
            return $value;
        }

        if ( strtotime( $value ) ) {
            return date_i18n( wc_date_format(), strtotime( $value ) );
        }

        return $value;
    }

    protected function yaymail_flatten_to_string( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            $result = [];
            foreach ( (array) $value as $v ) {
                $result[] = $this->yaymail_flatten_to_string( $v );
            }
            return implode( ', ', $result );
        }
        return strval( $value );
    }

    /**
     * Get allowed order meta data
     * Allow 3rd party to filter the which order meta data to be used in the email
     *
     * @since 4.1.0
     */
    public static function get_allowed_order_meta_data( $order ) {

        if ( is_string( $order ) || is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order && ! ( $order instanceof \WC_Order ) ) {
            return [];
        }

        $order_meta_data = $order->get_meta_data();

        return $order_meta_data;

        // Maybe we can use this in the future
        // $order_id = $order->get_id();

        // // ACF integration
        // if ( function_exists( 'acf_is_field_key' ) ) {
        // $order_meta_data = array_filter(
        // $order_meta_data,
        // function( $meta_item ) use ( $order_id ) {
        // $data       = $meta_item->get_data();
        // $search_key = strpos( $data['key'], '_' ) === 0 ? $data['key'] : '_' . $data['key'];

        // $acf_ref = get_post_meta( $order_id, $search_key, true );

        // if ( ! $acf_ref && strpos( $acf_ref, 'field_' ) !== 0 ) {
        // return true;
        // }

        // return ! acf_is_field_key( $acf_ref );
        // }
        // );
        // }

        // // Checkout fields by ThemeHigh
        // if ( class_exists( 'THWCFD_Utils' ) ) {

        // $thwcfd_data = [
        // \THWCFD_Utils::get_fields( 'billing' ),
        // \THWCFD_Utils::get_fields( 'shipping' ),
        // \THWCFD_Utils::get_fields( 'additional' ),
        // ];

        // $custom_fields_sets = [];

        // foreach ( $thwcfd_data as $set ) {
        // $custom_fields = [];
        // foreach ( array_keys( $set ) as $field_name ) {
        // $custom_fields[] = '_' . $field_name;
        // $custom_fields[] = $field_name;
        // }
        // $custom_fields_sets[] = $custom_fields;
        // }

        // foreach ( $custom_fields_sets as $custom_fields ) {
        // if ( empty( $custom_fields ) ) {
        // continue;
        // }
        // $order_meta_data = array_filter(
        // $order_meta_data,
        // function( $meta_item ) use ( $custom_fields ) {
        // $data = $meta_item->get_data();
        // return ! in_array( $data['key'], $custom_fields );
        // }
        // );
        // }
        // }//end if

        // // Checkout fields editor by WooCommerce
        // if ( class_exists( 'WC_Checkout_Field_Editor' ) && method_exists( 'WC_Checkout_Field_Editor', 'get_fields' ) ) {
        // $custom_fields_sets = [
        // \WC_Checkout_Field_Editor::get_fields( 'billing' ),
        // \WC_Checkout_Field_Editor::get_fields( 'shipping' ),
        // \WC_Checkout_Field_Editor::get_fields( 'additional' ),
        // ];

        // foreach ( $custom_fields_sets as $custom_fields ) {
        // if ( empty( $custom_fields ) ) {
        // continue;
        // }
        // $custom_fields   = array_filter(
        // $custom_fields,
        // function( $field ) {
        // return ! empty( $field['custom'] );
        // }
        // );
        // $order_meta_data = array_filter(
        // $order_meta_data,
        // function( $meta_item ) use ( $custom_fields ) {
        // $data = $meta_item->get_data();
        // return ! in_array( $data['key'], array_keys( $custom_fields ) );
        // }
        // );
        // }
        // }//end if

        // // Checkout fields by Acoweb
        // if ( defined( 'AWCFE_ORDER_META_KEY' ) ) {
        // $awcf_data          = $order->get_meta( AWCFE_ORDER_META_KEY, true );
        // $custom_fields_sets = [];

        // foreach ( $awcf_data as $set ) {
        // $custom_fields = [];
        // foreach ( $set as $field ) {
        // $custom_fields[] = '_' . $field['name'];
        // }
        // $custom_fields[]      = AWCFE_ORDER_META_KEY;
        // $custom_fields_sets[] = $custom_fields;
        // }

        // foreach ( $custom_fields_sets as $custom_fields ) {
        // if ( empty( $custom_fields ) ) {
        // continue;
        // }
        // $order_meta_data = array_filter(
        // $order_meta_data,
        // function( $meta_item ) use ( $custom_fields ) {
        // $data = $meta_item->get_data();
        // return ! in_array( $data['key'], $custom_fields );
        // }
        // );
        // }
        // }//end if

        // return $order_meta_data;
    }
}
