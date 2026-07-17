<?php

namespace YayMail\Shortcodes\OrderDetails;

use YayMail\Utils\TemplateHelpers;
use YayMail\Utils\Helpers;

/**
 * @method: static OrderDetailsRenderer get_instance()
 */
class OrderDetailsRenderer {

    public $item_totals = [];

    public $order = null;

    public $order_note = '';

    public $element_data = null;

    public $is_placeholder = false;

    public $titles = [];

    public $show_product_item_cost = false;

    public $colspan_value = '2';

    public function __construct( $order, $element_data, $is_placeholder ) {
        $yaymail_settings             = yaymail_settings();
        $this->show_product_item_cost = isset( $yaymail_settings['show_product_item_cost'] ) ? boolval( $yaymail_settings['show_product_item_cost'] ) : false;
        $this->colspan_value          = $is_placeholder ? '{{show_product_item_cost}}' : ( $this->show_product_item_cost ? '3' : '2' );
        $this->element_data           = $element_data;
        $this->is_placeholder         = $is_placeholder;
        $this->initialize_titles();

        if ( ! Helpers::is_woocommerce_order( $order ) ) {
            $this->initialize_sample_data();
        } else {
            $this->initialize_order_data( $order );
        }
    }

    public function initialize_titles() {
        $this->titles = [
            'product'        => isset( $this->element_data['product_title'] ) ? $this->element_data['product_title'] : TemplateHelpers::get_content_as_placeholder( 'product_title', esc_html__( 'Product', 'woocommerce' ), $this->is_placeholder ),
            'cost'           => isset( $this->element_data['cost_title'] ) ? $this->element_data['cost_title'] : TemplateHelpers::get_content_as_placeholder( 'cost_title', esc_html__( 'Cost', 'woocommerce' ), $this->is_placeholder ),
            'quantity'       => isset( $this->element_data['quantity_title'] ) ? $this->element_data['quantity_title'] : TemplateHelpers::get_content_as_placeholder( 'quantity_title', esc_html__( 'Quantity', 'woocommerce' ), $this->is_placeholder ),
            'price'          => isset( $this->element_data['price_title'] ) ? $this->element_data['price_title'] : TemplateHelpers::get_content_as_placeholder( 'price_title', esc_html__( 'Price', 'woocommerce' ), $this->is_placeholder ),
            'cart_subtotal'  => isset( $this->element_data['cart_subtotal_title'] ) ? $this->element_data['cart_subtotal_title'] : TemplateHelpers::get_content_as_placeholder( 'cart_subtotal_title', esc_html__( 'Subtotal:', 'woocommerce' ), $this->is_placeholder ),
            'shipping'       => isset( $this->element_data['shipping_title'] ) ? $this->element_data['shipping_title'] : TemplateHelpers::get_content_as_placeholder( 'shipping_title', esc_html__( 'Shipping:', 'woocommerce' ), $this->is_placeholder ),
            'discount'       => isset( $this->element_data['discount_title'] ) ? $this->element_data['discount_title'] : TemplateHelpers::get_content_as_placeholder( 'discount_title', esc_html__( 'Discount:', 'woocommerce' ), $this->is_placeholder ),
            'payment_method' => isset( $this->element_data['payment_method_title'] ) ? $this->element_data['payment_method_title'] : TemplateHelpers::get_content_as_placeholder( 'payment_method_title', esc_html__( 'Payment method:', 'woocommerce' ), $this->is_placeholder ),
            'order_total'    => isset( $this->element_data['order_total_title'] ) ? $this->element_data['order_total_title'] : TemplateHelpers::get_content_as_placeholder( 'order_total_title', esc_html__( 'Total:', 'woocommerce' ), $this->is_placeholder ),
            'order_note'     => isset( $this->element_data['order_note_title'] ) ? $this->element_data['order_note_title'] : TemplateHelpers::get_content_as_placeholder( 'order_note_title', esc_html__( 'Note:', 'woocommerce' ), $this->is_placeholder ),
        ];
    }

    private function initialize_sample_data() {
        $this->item_totals = [
            'cart_subtotal'  => [
                'label' => $this->titles['cart_subtotal'],
                'value' => wc_price( 18 ),
            ],
            'shipping'       => [
                'label' => $this->titles['shipping'],
                'value' => __( 'Free shipping', 'yaymail' ),
            ],
            'order_total'    => [
                'label' => $this->titles['order_total'],
                'value' => wc_price( 18 ),
            ],
            'payment_method' => [
                'label' => $this->titles['payment_method'],
                'value' => __( 'Direct bank transfer', 'yaymail' ),
            ],
        ];
        $this->order_note  = 'YayMail';
    }

    private function initialize_order_data( $order ) {
        // Avoid appending the "via {shipping method}" suffix in the Shipping total.
        // WooCommerce adds this suffix via the `woocommerce_order_shipping_to_display_shipped_via` filter.
        add_filter( 'woocommerce_order_shipping_to_display_shipped_via', '__return_false', 10, 2 );
        $this->item_totals = $order->get_order_item_totals();
        remove_filter( 'woocommerce_order_shipping_to_display_shipped_via', '__return_false', 10 );
        $this->order_note = $order->get_customer_note();
        $this->order      = $order;
    }

    /**
     * Whether order details use the modern (borderless grid) layout.
     */
    private function is_layout_modern() {
        return isset( $this->element_data['layout_type'] ) && 'modern' === $this->element_data['layout_type'];
    }

    public function get_styles() {
        $base_styles = [
            'padding'     => '12px',
            'text-align'  => yaymail_get_text_align(),
            'font-family' => TemplateHelpers::get_font_family_value(
                $this->element_data['font_family'] ?? 'inherit'
            ),
            'color'       => $this->element_data['text_color'] ?? 'inherit',
        ];

        if ( $this->is_layout_modern() ) {
            return TemplateHelpers::get_style( $base_styles );
        }

        $border_styles = [
            'border-width' => '1px',
            'border-style' => 'solid',
            'border-color' => $this->element_data['border_color'] ?? 'inherit',
        ];

        return TemplateHelpers::get_style( array_merge( $base_styles, $border_styles ) );
    }

    public function get_styles_product_image() {
        $direction = yaymail_get_email_direction();

        if ( 'ltr' === $direction ) {
            $margin_right = '5px';
            $margin_left  = '0';
        } else {
            $margin_right = '0';
            $margin_left  = '5px';
        }

        return TemplateHelpers::get_style(
            [
                'margin-bottom' => '5px',
                'margin-right'  => $margin_right,
                'margin-left'   => $margin_left,
            ]
        );
    }

    public function get_structure_table() {
        return apply_filters(
            'yaymail_order_details_structure_table',
            [
                'items'  => [
                    'product'  => [
                        'label'    => $this->titles['product'],
                        'col_span' => apply_filters( 'yaymail_order_item_product_title_colspan', 1, $this->element_data ),
                        'style'    => [
                            'word-wrap' => 'break-word',
                            'width'     => $this->show_product_item_cost ? '40%' : '45%',
                        ],
                    ],
                    'cost'     => [
                        'label'    => $this->titles['cost'],
                        'col_span' => apply_filters( 'yaymail_order_item_cost_colspan', 1, $this->element_data ),
                        'style'    => [
                            'min-width' => '65px',
                        ],
                    ],
                    'quantity' => [
                        'label'    => $this->titles['quantity'],
                        'col_span' => apply_filters( 'yaymail_order_item_quantity_colspan', 1, $this->element_data ),
                        'style'    => [
                            'min-width' => '85px',
                        ],
                    ],
                    'price'    => [
                        'label'    => $this->titles['price'],
                        'col_span' => apply_filters( 'yaymail_order_item_price_colspan', 1, $this->element_data ),
                        'style'    => [
                            'word-wrap' => 'break-word',
                            'min-width' => '100px',
                            'width'     => $this->show_product_item_cost ? '28%' : '38%',
                        ],

                    ],
                ],
                'footer' => [
                    'label_col_span' => $this->colspan_value,
                    'value_col_span' => 1,
                    'hidden_rows'    => [],
                ],
            ]
        );
    }

    public function render() {
        if ( $this->is_layout_modern() ) {
            $table_style = TemplateHelpers::get_style(
                [
                    'width'            => '100%',
                    'border'           => '0',
                    'border-collapse'  => 'collapse',
                    'mso-table-lspace' => '0pt',
                    'mso-table-rspace' => '0pt',
                    'padding'          => '0',
                    'color'            => isset( $this->element_data['text_color'] ) ? $this->element_data['text_color'] : 'inherit',
                    'font-family'      => TemplateHelpers::get_font_family_value( isset( $this->element_data['font_family'] ) ? $this->element_data['font_family'] : 'inherit' ),
                ]
            );
            $border_attr = 0;
        } else {
            $table_style = $this->get_styles() . 'padding: 0;border-collapse: separate;';
            $border_attr = 1;
        }
        ?>
            <table class="yaymail-order-details-table"<?php echo $this->is_layout_modern() ? ' data-layout-type-modern="true"' : ''; ?> cellspacing="0" cellpadding="6" width="100%" style="<?php echo esc_attr( $table_style ); ?>" border="<?php echo esc_attr( (string) $border_attr ); ?>">
            <?php
            $this->render_heading();
            $this->render_order_items();
            $this->render_footer();
            ?>
            </table>
            <?php
    }

    public function render_heading() {
        $styles          = $this->get_styles();
        $structure_table = $this->get_structure_table();
        $structure_items = isset( $structure_table['items'] ) ? $structure_table['items'] : [];
        if ( isset( $structure_items['cost'] ) && ! $this->show_product_item_cost && ! $this->is_placeholder ) {
            unset( $structure_items['cost'] );
        }
        ?>
            <thead class="yaymail_element_head_order_details yaymail_element_head_order_item">
                <tr>
                    <?php
                    foreach ( $structure_items as $key => $structure_item ) :
                        if ( isset( $structure_item['width'] ) ) {
                            $width = 'width: ' . $structure_item['width'] . ';';
                        } else {
                            $width = '';
                        }
                        $item_style = isset( $structure_item['style'] ) ? $structure_item['style'] : [];
                        if ( ! empty( $item_style ) ) {
                            $item_style_string = TemplateHelpers::get_style( $item_style );
                        } else {
                            $item_style_string = '';
                        }
                        $column_style = $styles . $width . $item_style_string;
                        echo '<th class="td yaymail_item_' . esc_attr( $key ) . '_title" colspan="' . esc_attr( $structure_item['col_span'] ) . '" scope="col" style="' . esc_attr( $column_style ) . ';"><span>' . esc_html( $structure_item['label'] ) . '</span></th>';
                    endforeach;
                    ?>
                </tr>
            </thead>
            <?php
    }

    public function render_order_items() {
        $structure_table = $this->get_structure_table();
        $structure_items = isset( $structure_table['items'] ) ? $structure_table['items'] : [];
        if ( isset( $structure_items['cost'] ) && ! $this->show_product_item_cost && ! $this->is_placeholder ) {
            unset( $structure_items['cost'] );
        }
        ?>
        <tbody class="yaymail_element_body_order_details yaymail_element_body_order_item">
        <?php
        if ( null === $this->order || ! ( $this->order instanceof \WC_Order ) || ( 12345 === $this->order->get_id() && ! wc_get_order( 12345 ) ) ) {
            $this->render_sample_items( $structure_items );
        } else {
            $this->render_real_items( $structure_items );
        }
        ?>
        </tbody>
        <?php
    }

    public function render_sample_items( $structure_items ) {
        $yaymail_settings = yaymail_settings();
        $direction        = yaymail_get_email_direction();
        $image_position   = isset( $yaymail_settings['product_image_position'] ) ? $yaymail_settings['product_image_position'] : 'top';

        $style_image_position_left = TemplateHelpers::get_style(
            [
                'float' => 'left' === $image_position && 'rtl' === $direction ? 'right' : 'left',
            ]
        );

        $style          = $this->get_styles();
        $is_placeholder = $this->is_placeholder;

        $show_image   = isset( $yaymail_settings['show_product_image'] ) ? boolval( $yaymail_settings['show_product_image'] ) : false;
        $image_height = isset( $yaymail_settings['product_image_height'] ) ? $yaymail_settings['product_image_height'] : '30';
        $image_width  = isset( $yaymail_settings['product_image_width'] ) ? $yaymail_settings['product_image_width'] : '30';

        $show_image         = isset( $yaymail_settings['show_product_image'] ) ? boolval( $yaymail_settings['show_product_image'] ) : false;
        $show_sku           = isset( $yaymail_settings['show_product_sku'] ) ? boolval( $yaymail_settings['show_product_sku'] ) : false;
        $show_des           = isset( $yaymail_settings['show_product_description'] ) ? boolval( $yaymail_settings['show_product_description'] ) : false;
        $show_regular_price = isset( $yaymail_settings['show_product_regular_price'] ) ? boolval( $yaymail_settings['show_product_regular_price'] ) : false;
        $show_hyper_links   = isset( $yaymail_settings['show_product_hyper_links'] ) ? boolval( $yaymail_settings['show_product_hyper_links'] ) : false;
        $image_style        = 'left' === $image_position ? $this->get_styles_product_image() . $style_image_position_left : $this->get_styles_product_image();

        $image_url             = wc_placeholder_img_src();
        $image                 = $is_placeholder ? "<img style='margin-right:0;' width='{{product_image_width}}px' height='{{product_image_height}}px' src='{$image_url}' alt='product image'/>" : "<img style='margin-right: 0; width: {$image_width}px; height: {$image_height}px;' src='{$image_url}' alt='product image'/>";
        $sku                   = __( 'sku', 'yaymail' );
        $short_description     = __( 'Product short description', 'yaymail' );
        $product_name          = __( 'Happy YayCommerce', 'yaymail' );
        $product_permalink     = '#';
        $product_hyper_link    = "<a href='{$product_permalink}' target='_blank'>{$product_name}</a>";
        $product_cost          = 9;
        $product_quantity      = 2;
        $product_regular_price = 10;

        $is_layout_type_modern = isset( $this->element_data['layout_type'] ) && 'modern' === $this->element_data['layout_type'];
        ?>
        <tr class="order_item yaymail-order-item-last">
            <?php foreach ( $structure_items as $key => $structure_item ) : ?>
                <?php
                if ( isset( $structure_item['width'] ) ) {
                    $width = 'width: ' . $structure_item['width'] . ';';
                } else {
                    $width = '';
                }
                $item_style = isset( $structure_item['style'] ) ? $structure_item['style'] : [];
                if ( ! empty( $item_style ) ) {
                    $item_style_string = TemplateHelpers::get_style( $item_style );
                } else {
                    $item_style_string = '';
                }
                $column_style = $style . $width . $item_style_string;
                ?>
                <td colspan="<?php echo esc_attr( $structure_item['col_span'] ); ?>" class="td yaymail_item_<?php echo esc_attr( $key ); ?>_content" scope="row" style="<?php echo esc_attr( $column_style ); ?>">
                    <?php
                    switch ( $key ) :
                        case 'product':
                            // Show title/image etc.
                            if ( ( $show_image && 'bottom' !== $image_position ) || $is_placeholder ) {
                                echo wp_kses_post( "<div class='yaymail-product_image_position__top' style='{$image_style}'>" );
                                require YAYMAIL_PLUGIN_PATH . 'templates/shortcodes/order-details/order-items/image-content.php';
                                echo ( '</div>' );
                            }
                            ?>

                            <!-- Product details -->
                            <div class='yaymail-product-details'>
                            <?php

                            // Product name.
                            require YAYMAIL_PLUGIN_PATH . 'templates/shortcodes/order-details/order-items/product-name-content.php';

                            // SKU.
                            if ( ( $show_sku && ! empty( $sku ) ) || ( $is_placeholder && ! empty( $sku ) ) ) {
                                require YAYMAIL_PLUGIN_PATH . 'templates/shortcodes/order-details/order-items/sku-content.php';
                            }

                            // Product Description.
                            if ( ( $show_des && ! empty( $short_description ) ) || ( $is_placeholder && ! empty( $short_description ) ) ) {
                                require YAYMAIL_PLUGIN_PATH . 'templates/shortcodes/order-details/order-items/product-short-description-content.php';
                            }

                            // Show title/image etc in bottom.
                            if ( ( $show_image && 'bottom' === $image_position ) || $is_placeholder ) {
                                echo wp_kses_post( "<div class='yaymail-product_image_position__bottom' style='{$image_style}'>" );
                                require YAYMAIL_PLUGIN_PATH . 'templates/shortcodes/order-details/order-items/image-content.php';
                                echo ( '</div>' );
                            }
                            ?>
                            </div>
                            <!-- End Product details -->
                            <?php
                            break;
                        case 'cost':
                            echo wp_kses_post( wc_price( $product_cost ) );
                            break;
                        case 'quantity':
                            ?>
                            <?php if ( $is_layout_type_modern || $is_placeholder ) : ?>
                                <span class="yaymail-quantity-type-modern">x</span>
                            <?php endif; ?>
                            <?php
                            echo wp_kses_post( $product_quantity );
                            break;
                        case 'price':
                            // Show product regular price.
                            if ( ( $show_regular_price ) ) {
                                ?>
                                <del class="yaymail-product-regular-price" style="padding-right:5px"> <?php echo wp_kses_post( wc_price( $product_regular_price * $product_quantity ) ); ?> </del>
                                <?php
                            }
                            echo wp_kses_post( wc_price( $product_cost * $product_quantity ) );
                            break;
                        default:
                            yaymail_kses_post_e( do_action( 'yaymail_order_details_item_' . $key . '_content', null, $this->order, $this->element_data, true ) );
                            break;
                    endswitch;
                    ?>
                </td>
            <?php endforeach; ?>
        </tr>
            <?php
    }

    public function render_real_items( $structure_items ) {
        $yaymail_settings = yaymail_settings();
        $direction        = yaymail_get_email_direction();
        $image_position   = isset( $yaymail_settings['product_image_position'] ) ? $yaymail_settings['product_image_position'] : 'top';

        $style_image_position_left = TemplateHelpers::get_style(
            [
                'float' => 'left' === $image_position && 'rtl' === $direction ? 'right' : 'left',
            ]
        );

        $args_data = [
            'order'                => $this->order,
            'text_style'           => $this->get_styles(),
            'styles_product_image' => 'left' === $image_position ? $this->get_styles_product_image() . $style_image_position_left : $this->get_styles_product_image(),
            'is_placeholder'       => $this->is_placeholder,
            'structure_items'      => $structure_items,
        ];

        // Just has data when send mail
        if ( ! empty( $this->element_data ) ) {
            $args_data['element'] = $this->element_data;
        }
        $path_data    = apply_filters( 'yaymail_order_details_items', 'templates/shortcodes/order-details/order-items/main.php' );
        $html         = yaymail_get_content( $path_data, $args_data );
        $allowed_html = TemplateHelpers::wp_kses_allowed_html();
        echo wp_kses( $html, $allowed_html );
    }

    public function render_footer() {
        $structure_table  = $this->get_structure_table();
        $structure_footer = isset( $structure_table['footer'] ) ? $structure_table['footer'] : [];

        if ( empty( $structure_footer ) ) {
            return;
        }
        // TODO: change class name
        ?>
        <tfoot class="yaymail_element_foot_order_details yaymail_element_foot_order_item">
        <?php
        $this->render_item_totals( $structure_footer );

        if ( ! empty( $this->order ) && $this->order->get_customer_note() ) {
            $this->render_customer_note( $structure_footer );
        }
        ?>
        </tfoot>
        <?php
    }

    public function render_item_totals( $structure_footer ) {
        $custom_rows = isset( $this->element_data['custom_footer_rows'] )
            ? $this->element_data['custom_footer_rows']
            : [];

        // Group custom rows by zone
        $rows_by_zone = [
            'before_all'     => [],
            'after_subtotal' => [],
            'before_total'   => [],
            'after_total'    => [],
        ];

        foreach ( $custom_rows as $row ) {
            // Check if row is enabled - handle both boolean and string values
            $is_enabled = isset( $row['enabled'] ) ? boolval( $row['enabled'] ) : false;

            if ( $is_enabled && isset( $row['zone'] ) ) {
                $zone = $row['zone'];
                if ( isset( $rows_by_zone[ $zone ] ) ) {
                    $rows_by_zone[ $zone ][] = $row;
                }
            }
        }

        // Sort each zone by order
        foreach ( $rows_by_zone as &$zone_rows ) {
            usort(
                $zone_rows,
                function( $a, $b ) {
                    $order_a = isset( $a['order'] ) ? intval( $a['order'] ) : 0;
                    $order_b = isset( $b['order'] ) ? intval( $b['order'] ) : 0;
                    return $order_a - $order_b;
                }
            );
        }

        $index = 0;

        // ZONE 1: Before all
        foreach ( $rows_by_zone['before_all'] as $row ) {
            $this->render_custom_row( $row, $index++, $structure_footer );
        }

        // Process standard rows
        foreach ( $this->item_totals as $key => $total ) {
            if ( in_array( $key, $structure_footer['hidden_rows'] ?? [], true ) ) {
                continue;
            }

            // ZONE 2: After subtotal
            if ( 'cart_subtotal' === $key ) {
                $this->render_standard_row( $key, $total, $index++, $structure_footer );
                foreach ( $rows_by_zone['after_subtotal'] as $row ) {
                    $this->render_custom_row( $row, $index++, $structure_footer );
                }
                continue;
            }

            // ZONE 3: Before total
            if ( 'order_total' === $key ) {
                foreach ( $rows_by_zone['before_total'] as $row ) {
                    $this->render_custom_row( $row, $index++, $structure_footer );
                }
                $this->render_standard_row( $key, $total, $index++, $structure_footer );
                continue;
            }

            // Other rows - render normally
            $this->render_standard_row( $key, $total, $index++, $structure_footer );
        }//end foreach

        // ZONE 4: After all (after total)
        foreach ( $rows_by_zone['after_total'] as $row ) {
            $this->render_custom_row( $row, $index++, $structure_footer );
        }
    }

    /**
     * Render a standard order total row
     *
     * @param string $key              The order total key.
     * @param array  $total            The order total data.
     * @param int    $index            The row index.
     * @param array  $structure_footer The footer structure data.
     */
    private function render_standard_row( $key, $total, $index, $structure_footer ) {
        $tr_class              = "yaymail-order-detail-row-{$key}";
        $can_apply_placeholder = $this->is_placeholder && isset( $this->titles[ $key ] );
        $label                 = TemplateHelpers::get_content_as_placeholder( "{$key}_title", esc_html( isset( $this->titles[ $key ] ) ? $this->titles[ $key ] : $total['label'] ), $can_apply_placeholder );
        $style                 = $this->get_styles();

        if ( ! $this->is_layout_modern() ) {
            $style .= TemplateHelpers::get_style(
                [
                    'border-top-width' => ( $index === 1 ) ? '4px' : '0',
                ]
            );
        }

        $heading_style = $style . 'font-size: ' . ( isset( $this->element_data['table_heading_font_size'] ) ? $this->element_data['table_heading_font_size'] : '14' ) . 'px;';
        $content_style = $style . 'font-size: ' . ( isset( $this->element_data['table_content_font_size'] ) ? $this->element_data['table_content_font_size'] : '14' ) . 'px;';
        ?>
        <tr class="<?php echo esc_attr( $tr_class ); ?>">
            <th class="td" scope="row" colspan="<?php echo esc_attr( isset( $structure_footer['label_col_span'] ) ? $structure_footer['label_col_span'] : $this->colspan_value ); ?>" style="<?php echo esc_attr( $heading_style ); ?>"><?php echo wp_kses_post( do_shortcode( $label ) ); ?></th>
            <td class="td" colspan="<?php echo esc_attr( isset( $structure_footer['value_col_span'] ) ? $structure_footer['value_col_span'] : 1 ); ?>" style="<?php echo esc_attr( $content_style ); ?>"><?php echo wp_kses_post( $total['value'] ); ?></td>
        </tr>
        <?php
    }

    /**
     * Render a custom footer row
     *
     * @param array $custom_row       The custom row data.
     * @param int   $index            The row index.
     * @param array $structure_footer The footer structure data.
     */
    private function render_custom_row( $custom_row, $index, $structure_footer ) {
        // Process shortcodes in label and value
        $label = isset( $custom_row['label'] ) ? do_shortcode( $custom_row['label'] ) : '';
        $value = isset( $custom_row['value'] ) ? do_shortcode( $custom_row['value'] ) : '';

        if ( empty( $label ) && empty( $value ) ) {
            return;
        }

        $style = $this->is_layout_modern()
            ? ''
            : $this->get_styles() . TemplateHelpers::get_style(
                [
                    'border-top-width' => ( $index === 1 ) ? '4px' : '0',
                ]
            );

        $heading_style = $style . 'font-size: ' . ( isset( $this->element_data['table_heading_font_size'] ) ? $this->element_data['table_heading_font_size'] : '14' ) . 'px;';
        $content_style = $style . 'font-size: ' . ( isset( $this->element_data['table_content_font_size'] ) ? $this->element_data['table_content_font_size'] : '14' ) . 'px;';

        $row_id = isset( $custom_row['id'] ) ? esc_attr( $custom_row['id'] ) : '';
        ?>
        <tr class="yaymail-order-detail-row-custom custom-row-<?php echo esc_attr( $row_id ); ?>">
            <th class="td" scope="row" colspan="<?php echo esc_attr( isset( $structure_footer['label_col_span'] ) ? $structure_footer['label_col_span'] : $this->colspan_value ); ?>"
                style="<?php echo esc_attr( $heading_style ); ?>">
                <?php echo wp_kses_post( $label ); ?>
            </th>
            <td class="td" colspan="<?php echo esc_attr( isset( $structure_footer['value_col_span'] ) ? $structure_footer['value_col_span'] : 1 ); ?>"
                style="<?php echo esc_attr( $content_style ); ?>">
                <?php echo wp_kses_post( $value ); ?>
            </td>
        </tr>
        <?php
    }

    public function render_customer_note() {
        if ( ! empty( $this->order_note ) ) :
            $style = $this->get_styles();
            ?>
        <tr class="yaymail-order-detail-row-order_note">
            <th class="td" scope="row" colspan="<?php echo esc_attr( $this->colspan_value ); ?>" style="<?php echo esc_attr( $style ); ?>;"><?php echo esc_html( $this->titles['order_note'] ); ?></th>
            <td class="td" style="<?php echo esc_attr( $style ); ?>;"><?php echo wp_kses_post( nl2br( wptexturize( $this->order_note ) ) ); ?></td>
        </tr>
            <?php
            endif;
    }
}
