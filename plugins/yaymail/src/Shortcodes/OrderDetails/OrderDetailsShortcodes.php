<?php

namespace YayMail\Shortcodes\OrderDetails;

use YayMail\Utils\Helpers;
use YayMail\Utils\TemplateHelpers;
use YayMail\Utils\SingletonTrait;
use YayMail\Abstracts\BaseShortcode;

/**
 * @method: static OrderDetailsShortcodes get_instance()
 */
class OrderDetailsShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'yaymail_order_id',
            'description' => __( 'Order ID', 'yaymail' ),
            'attributes'  => [
                'is_plain'   => false,
                'forced_url' => '',
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_id' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_number',
            'description' => __( 'Order Number', 'yaymail' ),
            'attributes'  => [
                'is_plain' => false,
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_number' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_date',
            'description' => __( 'Order Date', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_date' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_link',
            'description' => __( 'Order URL', 'yaymail' ),
            'attributes'  => [
                'text_link' => __( 'Order', 'yaymail' ),
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_url',
            'description' => __( 'Order URL (String)', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_url' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_view_order_link',
            'description' => __( 'View Order Link', 'yaymail' ),
            'attributes'  => [
                'text_link' => __( 'Your Order', 'yaymail' ),
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_view_order_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_view_order_url',
            'description' => __( 'View Order URL (String)', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_view_order_url' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_status',
            'description' => __( 'Order Status', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_status' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_fee',
            'description' => __( 'Order Fee', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_fee' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_refund',
            'description' => __( 'Order Refund', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_refund' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_subtotal',
            'description' => __( 'Order Subtotal', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_subtotal' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_total',
            'description' => __( 'Order Total', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_total' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_total_value',
            'description' => __( 'Order Total Value', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_total_value' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_coupon_codes',
            'description' => __( 'Order Coupon Codes', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_coupon_codes' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_product_line_item_count',
            'description' => __( 'Number of line items in the order', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_product_line_item_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_product_line_item_count_double',
            'description' => __( 'Number of line items in the order (double)', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_product_line_item_count_double' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_product_item_count',
            'description' => __( 'Total quantity of all items in the order', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_product_item_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_product_count',
            'description' => __( 'Number of base products in the order', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_product_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_product_variation_count',
            'description' => __( 'Number of product variations in the order', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_order_product_variation_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_customer_roles',
            'description' => __( 'Customer Roles', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_customer_roles' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_customer_note',
            'description' => __( 'Customer Last Note', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_customer_note' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_customer_notes',
            'description' => __( 'All Customer Note', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_customer_notes' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_customer_provided_note',
            'description' => __( 'Customer Provided Note', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'yaymail_customer_provided_note' ],
        ];
        $shortcodes[] = [
            'name'        => 'woocommerce_email_order_meta',
            'description' => __( 'Order Meta Content', 'yaymail' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'woocommerce_email_order_meta' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_details',
            'description' => __( 'Order Details', 'yaymail' ),
            'group'       => 'order_details',
            'attributes'  => [
                'product_title'          => 'Product',
                'quantity_title'         => 'Quantity',
                'price_title'            => 'Price',
                'total_title'            => 'Total',
                'cart_subtotal_title'    => 'Subtotal',
                'shipping_title'         => 'Shipping',
                'payment_method_title'   => 'Payment method',
                'order_total_title'      => 'Total',
                'show_product_item_cost' => 'false',
            ],
            'callback'    => [ $this, 'yaymail_order_details' ],
        ];
        $shortcodes[] = [
            'name'        => 'yaymail_order_details_download_product',
            'description' => __( 'Order Details Download Product', 'yaymail' ),
            'group'       => '',
            'attributes'  => [
                'product_title'          => 'Product',
                'quantity_title'         => 'Quantity',
                'price_title'            => 'Price',
                'total_title'            => 'Total',
                'cart_subtotal_title'    => 'Subtotal',
                'shipping_title'         => 'Shipping',
                'payment_method_title'   => 'Payment method',
                'order_total_title'      => 'Total',
                'show_product_item_cost' => 'false',
            ],
            'callback'    => [ $this, 'yaymail_order_details_download_product' ],
        ];

        return $shortcodes;
    }

    /**
     * Render order details shortcode
     *
     * @param array $args
     * $render_data
     * $element
     * $settings
     * $is_placeholder
     */
    public static function yaymail_order_details( $args ) {

        $render_data = isset( $args['render_data'] ) ? $args['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = yaymail_get_content( 'templates/shortcodes/order-details/sample.php', $args );
            return $html;
        }

        if ( empty( $render_data['order'] ) ) {
            /**
             * Not having order/order_id
             */
            return '';
        }

        $html = yaymail_get_content( 'templates/shortcodes/order-details/main.php', $args );
        return $html;
    }

    public static function yaymail_order_details_download_product( $args ) {

        $render_data = isset( $args['render_data'] ) ? $args['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = yaymail_get_content( 'templates/shortcodes/order-details-download-product/sample.php', $args );
            return $html;
        }

        if ( empty( $render_data['order'] ) ) {
            /**
             * Not having order/order_id
             */
            return '';
        }

        $html = yaymail_get_content( 'templates/shortcodes/order-details-download-product/main.php', $args );
        return $html;
    }

    public function yaymail_order_id( $data, $shortcode_atts ) {

        $render_data           = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $is_placeholder        = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;
        $is_customized_preview = isset( $render_data['is_customized_preview'] ) ? $render_data['is_customized_preview'] : false;
        $is_plain              = isset( $shortcode_atts['is_plain'] ) ? Helpers::is_true( $shortcode_atts['is_plain'] ) : false;

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */

            if ( ! $is_plain ) {
                return '<a href="#">1</a>';
            }

            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;

        $template = ! empty( $data['template'] ) ? $data['template'] : null;

        $text_link_color = ! empty( $template ) ? $template->get_text_link_color() : YAYMAIL_COLOR_WC_DEFAULT;

        $element_type = isset( $data['element']['type'] ) ? $data['element']['type'] : '';

        $link_style = TemplateHelpers::get_style(
            [
                'color'           => 'heading' === $element_type ? 'inherit' : $text_link_color,
                'text-decoration' => 'heading' !== $element_type ? 'underline' : 'none',
            ]
        );

        $forced_url = '';

        if ( isset( $shortcode_atts['forced_url'] ) ) {
            $forced_url = filter_var( $shortcode_atts['forced_url'], FILTER_VALIDATE_URL ) === false ? '' : $shortcode_atts['forced_url'];
        }

        $url = ! empty( $forced_url ) ? do_shortcode( $forced_url ) : $order->get_edit_order_url();

        // If not plain text and (placeholder or customized preview or sent to admin), show as link
        if ( ! $is_plain && ( $is_placeholder || $is_customized_preview || $sent_to_admin ) ) {
            return wp_kses_post( "<a style='$link_style' href='{$url}'>{$order->get_id()}</a>" );
        }

        // If is_plain is true, return just the order ID without link
        return $order->get_id();
    }

    public function yaymail_order_number( $data, $shortcode_atts = [] ) {

        $render_data           = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $is_placeholder        = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;
        $is_customized_preview = isset( $render_data['is_customized_preview'] ) ? $render_data['is_customized_preview'] : false;
        $is_plain              = isset( $shortcode_atts['is_plain'] ) ? Helpers::is_true( $shortcode_atts['is_plain'] ) : false;

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */

            if ( ! $is_plain ) {
                return '<a href="#">1</a>';
            }

            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $template = ! empty( $data['template'] ) ? $data['template'] : null;

        $text_link_color = ! empty( $template ) ? $template->get_text_link_color() : YAYMAIL_COLOR_WC_DEFAULT;

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;

        if ( ! $is_plain && ( $is_placeholder || $is_customized_preview || $sent_to_admin ) ) {
            // $sent_to_admin === true
            return wp_kses_post( "<a style='$text_link_color' href='{$order->get_edit_order_url()}'>{$order->get_order_number()}</a>" );
        }

        return $order->get_order_number();
    }

    public function yaymail_order_link( $data, $shortcode_atts = [] ) {
        $order_url = $this->yaymail_order_url( $data );

        if ( empty( $order_url ) ) {
            return '';
        }

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Order', 'yaymail' ), $is_placeholder );

        return wp_kses_post( "<a href='{$order_url}'>" . $text_link . '</a>' );
    }

    public function yaymail_order_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( get_home_url() );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;

        $order_url = $sent_to_admin ? $order->get_edit_order_url() : $order->get_view_order_url();

        if ( empty( $order_url ) ) {
            return '';
        }

        return esc_url( $order_url );
    }

    public function yaymail_view_order_link( $data, $shortcode_atts = [] ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Your Order', 'yaymail' ), $is_placeholder );

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '<a href="' . esc_url( get_home_url() ) . '">' . $text_link . '</a>';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $view_order_url = $order->get_view_order_url();

        if ( empty( $view_order_url ) ) {
            return '';
        }

        return wp_kses_post( "<a href='{$view_order_url}'>" . $text_link . '</a>' );
    }

    public function yaymail_view_order_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( get_home_url() );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $view_order_url = $order->get_view_order_url();

        if ( empty( $view_order_url ) ) {
            return '';
        }

        return esc_url( $view_order_url );
    }

    public function yaymail_order_date( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return date_i18n( wc_date_format() );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_created_date = $order->get_date_created();

        if ( empty( $order_created_date ) ) {
            return '';
        }

        return $order_created_date->date_i18n( wc_date_format() );
    }

    public function yaymail_order_status( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'sample status', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $status = $order->get_status();

        if ( empty( $status ) ) {
            return '';
        }

        return strtolower( wc_get_order_status_name( $status ) );
    }

    public function yaymail_order_fee( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return 0;
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $fee = 0;

        $order_totals = $order->get_order_item_totals();

        if ( ! empty( $order_totals ) ) {
            foreach ( $order_totals as $index => $value ) {
                if ( strpos( $index, 'fee' ) !== false ) {
                    $fees = $order->get_fees();
                    foreach ( $fees as $fee_val ) {
                        if ( method_exists( $fee_val, 'get_amount' ) ) {
                            $fee += (float) $fee_val->get_amount();
                        }
                    }
                }
            }
        }

        return $fee;
    }

    public function yaymail_order_refund( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( 0 );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $refund = 0;

        $order_totals = $order->get_order_item_totals();

        if ( ! empty( $order_totals ) ) {
            foreach ( $order_totals as $index => $value ) {
                if ( strpos( $index, 'refund' ) !== false ) {
                    $refund = $order->get_total_refunded();
                }
            }
        }

        return wc_price( $refund, [ 'currency' => $order->get_currency() ] );
    }

    public function yaymail_order_subtotal( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( '18.00' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_totals = $order->get_order_item_totals();

        if ( empty( $order_totals ) ) {
            return '';
        }

        if ( ! empty( $order_totals['cart_subtotal'] ) && isset( $order_totals['cart_subtotal']['value'] ) ) {
            return $order_totals['cart_subtotal']['value'];
        } else {
            return '';
        }
    }

    public function yaymail_order_total( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( '18.00' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] );
    }

    public function yaymail_order_total_value( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '18.00';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_total();
    }

    public function yaymail_order_coupon_codes( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return 'sample_code';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_coupon_codes = '';

        if ( method_exists( $order, 'get_coupon_codes' ) && ! empty( $order->get_coupon_codes() ) ) {
            $coupon_codes = $order->get_coupon_codes();
            foreach ( $coupon_codes as $coupon_code ) {
                $order_coupon_codes .= wp_kses_post( $coupon_code );
            }
        }
        return $order_coupon_codes;
    }

    public function yaymail_order_product_line_item_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return count( $order->get_items() );
    }

    public function yaymail_order_product_line_item_count_double( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '2';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return count( $order->get_items() ) * 2;
    }

    public function yaymail_order_product_item_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_item_count();
    }

    public function yaymail_order_product_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $products_in_order = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product->is_type( 'variation' ) ) {
                $products_in_order[] = $product->get_parent_id();
            } else {
                $products_in_order[] = $product->get_id();
            }
        }

        return count( array_unique( $products_in_order ) );
    }

    public function yaymail_order_product_variation_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $products_in_order = [];
        foreach ( $order->get_items() as $item ) {
            $product             = $item->get_product();
            $products_in_order[] = $product->get_id();
        }

        return count( array_unique( $products_in_order ) );
    }

    public function yaymail_customer_roles( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $current_user = wp_get_current_user();
            if ( ! empty( $current_user ) && isset( $current_user->roles ) && ! empty( $current_user->roles ) ) {
                return implode( ', ', $current_user->roles );
            }
            return '';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $user = $order->get_user();
        if ( ! empty( $user ) && isset( $user->roles ) && ! empty( $user->roles ) ) {
            return implode( ', ', $user->roles );
        }
        return '';
    }

    public function yaymail_customer_note( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'customer note', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_notes = $order->get_customer_order_notes();
        if ( ! empty( $order_notes ) && count( $order_notes ) > 0 ) {
            return wp_kses_post( wpautop( wptexturize( make_clickable( $order_notes[0]->comment_content ) ) ) );
        }
        return '';
    }

    public function yaymail_customer_notes( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'customer notes', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_notes = $order->get_customer_order_notes();
        $list_notes  = '';
        if ( ! empty( $order_notes ) && count( $order_notes ) > 0 ) {
            foreach ( $order_notes as $note ) {
                $list_notes .= wp_kses_post( wpautop( wptexturize( make_clickable( $note->comment_content ) ) ) );
            }
        }
        return $list_notes;
    }

    public function yaymail_customer_provided_note( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'customer provided notes', 'yaymail' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $customer_note = $order->get_customer_note();
        if ( ! empty( $customer_note ) ) {
            return $customer_note;
        }
        return '';
    }

    public function woocommerce_email_order_meta( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '[woocommerce_email_order_meta]';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        $email = $render_data['email'] ?? [];

        if ( empty( $order ) || empty( $email ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;
        $plain_text    = isset( $render_data['plain_text'] ) ? $render_data['plain_text'] : false;

        ob_start();

        do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

        $return = ob_get_clean();

        return $return;
    }
}
