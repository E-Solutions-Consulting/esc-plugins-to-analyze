<?php

namespace YayMail\TemplateLibrary\Templates\Completed;

use YayMail\Abstracts\BaseTemplate;
use YayMail\Elements\BillingAddress;
use YayMail\Elements\Button;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\Logo;
use YayMail\Elements\OrderDetails;
use YayMail\Elements\ShippingAddress;
use YayMail\Elements\SocialIcon;
use YayMail\Elements\Text;
use YayMail\Utils\SingletonTrait;

/**
 * Completed Order v2 template for Completed Order email.
 */
class Completed2 extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id                = 'completed_order_v2';
        $this->email_type        = 'customer_completed_order';
        $this->name              = 'Completed Order v2';
        $this->description       = 'Clean layout with plenty of whitespace';
        $this->template_settings = [
            'text_link_color' => '#1a1a1a',
        ];

        $this->elements = [
            ColumnLayout::get_object_data(
                3,
                [
                    'column_width'                       => [],
                    'column_spacing'                     => '0',
                    'vertical_align'                     => 'top',
                    'container_setting_breaker'          => [
                        'component' => 'LineBreaker',
                    ],
                    'container_setting_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container layout settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                            => [
                        'top'    => '0',
                        'right'  => '0',
                        'bottom' => '0',
                        'left'   => '0',
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#fafafa',
                    'background_image'                   => [
                        'url'        => '',
                        'position'   => 'default',
                        'x_position' => 0,
                        'y_position' => 0,
                        'repeat'     => 'default',
                        'size'       => 'default',
                    ],
                    'inner_setting_breaker'              => [
                        'component' => 'LineBreaker',
                    ],
                    'inner_setting_group_definition'     => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Inner layout settings',
                        'description' => 'Handle inner layout settings',
                    ],
                    'inner_border_radius'                => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'inner_background_color'             => '#ffffff00',
                    'column_borders'                     => [
                        'component' => 'ColumnBorders',
                        'title'     => 'Column borders',
                    ],
                    'children'                           => [
                        Column::get_object_data(
                            33.333333333333,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => '15',
                                                'right'  => 0,
                                                'bottom' => '15',
                                                'left'   => '50',
                                            ],
                                            'background_color' => '#ffffff00',
                                            'border'      => [
                                                'side'   => 'none',
                                                'width'  => '1',
                                                'style'  => 'solid',
                                                'color'  => '#e5e5e5',
                                                'custom' => [
                                                    'top'  => '1',
                                                    'right' => '1',
                                                    'bottom' => '1',
                                                    'left' => '1',
                                                ],
                                            ],
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p style="text-align: right"><span style="font-size: 16px">My Account</span></p>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            33.333333333333,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => '15',
                                                'right'  => 0,
                                                'bottom' => '15',
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'border'      => [
                                                'side'   => 'none',
                                                'width'  => '1',
                                                'style'  => 'solid',
                                                'color'  => '#e5e5e5',
                                                'custom' => [
                                                    'top'  => '1',
                                                    'right' => '1',
                                                    'bottom' => '1',
                                                    'left' => '1',
                                                ],
                                            ],
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p style="text-align: center"><span style="font-size: 16px">Order Tracking</span></p>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            33.333333333333,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => '15',
                                                'right'  => '50',
                                                'bottom' => '15',
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'border'      => [
                                                'side'   => 'none',
                                                'width'  => '1',
                                                'style'  => 'solid',
                                                'color'  => '#e5e5e5',
                                                'custom' => [
                                                    'top'  => '1',
                                                    'right' => '1',
                                                    'bottom' => '1',
                                                    'left' => '1',
                                                ],
                                            ],
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p style="text-align: left"><span style="font-size: 16px">Contact</span></p>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            Logo::get_object_data(
                [
                    'container_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                    => [
                        'top'    => '50',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'           => '#ffffff',
                    'logo_breaker'               => [
                        'component' => 'LineBreaker',
                    ],
                    'logo_group_definition'      => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Logo settings',
                        'description' => 'Handle logo settings',
                    ],
                    'src'                        => YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png',
                    'align'                      => 'center',
                    'width'                      => '131',
                    'url'                        => '#',
                    'alt'                        => '',
                ]
            ),
            Text::get_object_data(
                [
                    'container_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                    => [
                        'top'    => 15,
                        'right'  => 50,
                        'bottom' => 15,
                        'left'   => 50,
                    ],
                    'background_color'           => '#fff',
                    'border'                     => [
                        'side'   => 'none',
                        'width'  => '1',
                        'style'  => 'solid',
                        'color'  => '#e5e5e5',
                        'custom' => [
                            'top'    => '1',
                            'right'  => '1',
                            'bottom' => '1',
                            'left'   => '1',
                        ],
                    ],
                    'content_breaker'            => [
                        'component' => 'LineBreaker',
                    ],
                    'content_group_definition'   => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Content settings',
                        'description' => 'Handle content settings',
                    ],
                    'font_family'                => '"Outfit", "DM Sans", sans-serif',
                    'text_color'                 => '#333439',
                    'rich_text'                  => '<p><span style="font-size: 18px">Hi <strong>[yaymail_billing_first_name] [yaymail_billing_last_name],</strong></span></p><br /><p><span style="font-size: 18px">Great news! Your order #[yaymail_order_id is_plain="true"] has been successfully delivered. If you\'ve received your order, please take a moment to confirm delivery in your account.</span></p><br /><p><span style="font-size: 18px">Thank you for shopping with us!</span></p>',
                ]
            ),
            Button::get_object_data(
                [
                    'container_group_definition'      => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                         => [
                        'top'    => 10,
                        'right'  => '50',
                        'bottom' => 10,
                        'left'   => '50',
                    ],
                    'background_color'                => '#fff',
                    'button_setting_breaker'          => [
                        'component' => 'LineBreaker',
                    ],
                    'button_group_definition'         => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Button settings',
                        'description' => 'Handle button settings',
                    ],
                    'button_type'                     => 'default',
                    'align'                           => 'left',
                    'width'                           => 'auto',
                    'custom_width'                    => '50',
                    'height'                          => '21',
                    'button_padding'                  => [
                        'top'    => '12',
                        'right'  => '20',
                        'bottom' => '12',
                        'left'   => '20',
                    ],
                    'button_background_color'         => '#873EFF',
                    'text_color'                      => '#ffffff',
                    'border'                          => [
                        'side'   => 'none',
                        'width'  => '1',
                        'style'  => 'solid',
                        'color'  => '#e5e5e5',
                        'custom' => [
                            'top'    => '1',
                            'right'  => '1',
                            'bottom' => '1',
                            'left'   => '1',
                        ],
                    ],
                    'border_radius'                   => [
                        'top_left'     => 0,
                        'top_right'    => 0,
                        'bottom_right' => 0,
                        'bottom_left'  => 0,
                    ],
                    'button_content_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Button content',
                        'description' => 'Handle button content',
                    ],
                    'text'                            => 'View Your Order',
                    'url'                             => '#',
                    'font_size'                       => 16,
                    'weight'                          => 'normal',
                    'font_family'                     => '"Outfit", "DM Sans", sans-serif',
                ]
            ),
            OrderDetails::get_object_data(
                [
                    'rich_text'                      => '[yaymail_order_details]',
                    'payment_instructions'           => '[yaymail_payment_instructions]',
                    'container_group_definition'     => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                        => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'               => '#fff',
                    'border'                         => [
                        'side'   => 'none',
                        'width'  => '1',
                        'style'  => 'solid',
                        'color'  => '#e5e5e5',
                        'custom' => [
                            'top'    => '1',
                            'right'  => '1',
                            'bottom' => '1',
                            'left'   => '1',
                        ],
                    ],
                    'table_setting_breaker'          => [
                        'component' => 'LineBreaker',
                    ],
                    'table_group_definition'         => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Table settings',
                        'description' => 'Handle table settings',
                    ],
                    'layout_type'                    => 'legacy',
                    'title'                          => '<p><span style="font-size: 16px"><strong>ORDER SUMMARY - FOR BUYER</strong></span></p>',
                    'title_color'                    => '#1A1A1A',
                    'text_color'                     => '#636363',
                    'border_color'                   => '#e5e5e5',
                    'font_family'                    => '"Outfit", "DM Sans", sans-serif',
                    'table_content_font_size'        => 16,
                    'table_heading_line_breaker'     => [
                        'component' => 'LineBreaker',
                    ],
                    'table_heading_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Table heading settings',
                        'description' => 'Handle table heading settings',
                    ],
                    'table_heading_font_size'        => 14,
                    'show_table_header'              => 1,
                    'product_title'                  => 'Product',
                    'cost_title'                     => 'Cost',
                    'quantity_title'                 => 'Quantity',
                    'price_title'                    => 'Price',
                    'cart_subtotal_title'            => 'Subtotal:',
                    'payment_method_title'           => 'Payment method:',
                    'order_total_title'              => 'Total:',
                    'order_note_title'               => 'Note:',
                    'shipping_title'                 => 'Shipping: [yaymail_shipping_method]',
                    'discount_title'                 => 'Discount:',
                    'custom_footer_rows_breaker'     => [
                        'component' => 'LineBreaker',
                    ],
                    'custom_footer_rows_group'       => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Custom Footer Rows',
                        'description' => 'Add custom rows to the order totals footer',
                    ],
                    'custom_footer_rows'             => [],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'column_width'                       => [],
                    'column_spacing'                     => 30,
                    'vertical_align'                     => 'top',
                    'container_setting_breaker'          => [
                        'component' => 'LineBreaker',
                    ],
                    'container_setting_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container layout settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                            => [
                        'top'    => 15,
                        'right'  => 50,
                        'bottom' => '15',
                        'left'   => 50,
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#fff',
                    'background_image'                   => [
                        'url'        => '',
                        'position'   => 'default',
                        'x_position' => 0,
                        'y_position' => 0,
                        'repeat'     => 'default',
                        'size'       => 'default',
                    ],
                    'inner_setting_breaker'              => [
                        'component' => 'LineBreaker',
                    ],
                    'inner_setting_group_definition'     => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Inner layout settings',
                        'description' => 'Handle inner layout settings',
                    ],
                    'inner_border_radius'                => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'inner_background_color'             => '#ffffff00',
                    'column_borders'                     => [
                        'component' => 'ColumnBorders',
                        'title'     => 'Column borders',
                    ],
                    'children'                           => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 0,
                                                'right'  => '50',
                                                'bottom' => '15',
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fff',
                                            'border'      => [
                                                'side'   => 'none',
                                                'width'  => '1',
                                                'style'  => 'solid',
                                                'color'  => '#e5e5e5',
                                                'custom' => [
                                                    'top'  => '1',
                                                    'right' => '1',
                                                    'bottom' => '1',
                                                    'left' => '1',
                                                ],
                                            ],
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p><strong style="font-size: 16px">SHIPPING ADDRESS</strong></p>',
                                        ]
                                    ),
                                    ShippingAddress::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'      => [
                                                'top'    => 0,
                                                'right'  => 30,
                                                'bottom' => 0,
                                                'left'   => 30,
                                            ],
                                            'background_color' => '#fafafa',
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'layout_type'  => 'modern',
                                            'title_color'  => '#1a1a1a',
                                            'text_color'   => '#636363',
                                            'border_color' => '#e5e5e5',
                                            'font_family'  => '"Outfit", "DM Sans", sans-serif',
                                            'title'        => '',
                                            'shipping_content_font_size' => 14,
                                            'shipping_content_alignment' => 'left',
                                            'shipping_content_text_format' => [
                                                'bold'   => false,
                                                'italic' => false,
                                                'underline' => false,
                                            ],
                                            'rich_text'    => '[yaymail_shipping_address]',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 0,
                                                'right'  => '50',
                                                'bottom' => '15',
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fff',
                                            'border'      => [
                                                'side'   => 'none',
                                                'width'  => '1',
                                                'style'  => 'solid',
                                                'color'  => '#e5e5e5',
                                                'custom' => [
                                                    'top'  => '1',
                                                    'right' => '1',
                                                    'bottom' => '1',
                                                    'left' => '1',
                                                ],
                                            ],
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p><strong style="font-size: 16px">BILLING ADDRESS</strong></p>',
                                        ]
                                    ),
                                    BillingAddress::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'      => [
                                                'top'    => 0,
                                                'right'  => 30,
                                                'bottom' => 0,
                                                'left'   => 30,
                                            ],
                                            'background_color' => '#fafafa',
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'layout_type'  => 'modern',
                                            'title_color'  => '#1a1a1a',
                                            'text_color'   => '#636363',
                                            'border_color' => '#e5e5e5',
                                            'font_family'  => '"Outfit", "DM Sans", sans-serif',
                                            'title'        => '',
                                            'billing_content_font_size' => '14',
                                            'billing_content_alignment' => 'left',
                                            'billing_content_text_format' => [
                                                'bold'   => false,
                                                'italic' => false,
                                                'underline' => false,
                                            ],
                                            'rich_text'    => '[yaymail_billing_address]',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'container_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                    => [
                        'top'    => 15,
                        'right'  => 50,
                        'bottom' => 15,
                        'left'   => 50,
                    ],
                    'background_color'           => '#fff',
                    'border'                     => [
                        'side'   => 'none',
                        'width'  => '1',
                        'style'  => 'solid',
                        'color'  => '#e5e5e5',
                        'custom' => [
                            'top'    => '1',
                            'right'  => '1',
                            'bottom' => '1',
                            'left'   => '1',
                        ],
                    ],
                    'content_breaker'            => [
                        'component' => 'LineBreaker',
                    ],
                    'content_group_definition'   => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Content settings',
                        'description' => 'Handle content settings',
                    ],
                    'font_family'                => '"Outfit", "DM Sans", sans-serif',
                    'text_color'                 => '#333439',
                    'rich_text'                  => '<p><span style="font-size: 18px">If there\'s anything wrong with your order, please contact our support team within 7 days and we\'ll be happy to help.</span></p><br /><p><span style="font-size: 18px">Best regards,</span></p><p><span style="font-size: 18px">[yaymail_site_name]</span></p>',
                ]
            ),
            Divider::get_object_data(
                [
                    'align'            => 'center',
                    'padding'          => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'width'            => '100',
                    'height'           => 2,
                    'background_color' => '#fff',
                    'divider_color'    => '#333',
                    'divider_type'     => 'solid',
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'column_width'                       => [],
                    'column_spacing'                     => '0',
                    'vertical_align'                     => 'top',
                    'container_setting_breaker'          => [
                        'component' => 'LineBreaker',
                    ],
                    'container_setting_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container layout settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                            => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 50,
                        'right'  => 50,
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#fff',
                    'background_image'                   => [
                        'url'        => '',
                        'position'   => 'default',
                        'x_position' => 0,
                        'y_position' => 0,
                        'repeat'     => 'default',
                        'size'       => 'default',
                    ],
                    'inner_setting_breaker'              => [
                        'component' => 'LineBreaker',
                    ],
                    'inner_setting_group_definition'     => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Inner layout settings',
                        'description' => 'Handle inner layout settings',
                    ],
                    'inner_border_radius'                => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'inner_background_color'             => '#ffffff00',
                    'column_borders'                     => [
                        'component' => 'ColumnBorders',
                        'title'     => 'Column borders',
                    ],
                    'children'                           => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 0,
                                                'right'  => 40,
                                                'bottom' => 0,
                                                'left'   => 40,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'border'      => [
                                                'side'   => 'none',
                                                'width'  => '1',
                                                'style'  => 'solid',
                                                'color'  => '#e5e5e5',
                                                'custom' => [
                                                    'top'  => '1',
                                                    'right' => '1',
                                                    'bottom' => '1',
                                                    'left' => '1',
                                                ],
                                            ],
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#333439',
                                            'rich_text'   => '<p style="text-align: center;margin: 0;font-weight: 300"><span style="font-size: 16px">For questions, contact <u>hello@example.com</u>, visit our <u>FAQs</u>, or <u>chat</u> with us during operating hours for account support</span></p>',
                                        ]
                                    ),
                                    SocialIcon::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'    => [
                                                'top'    => 20,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fff',
                                            'align'      => 'center',
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'width_icon' => 36,
                                            'spacing'    => 25,
                                            'theme'      => 'Colorful',
                                            'icon_list'  => [
                                                '0' => [
                                                    'icon' => 'facebook',
                                                    'url'  => '#',
                                                ],
                                                '1' => [
                                                    'icon' => 'instagram',
                                                    'url'  => '#',
                                                ],
                                                '2' => [
                                                    'icon' => 'tiktok',
                                                    'url'  => '#',
                                                ],
                                                '3' => [
                                                    'icon' => 'youtube',
                                                    'url'  => '#',
                                                ],
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fff',
                                            'border'      => [
                                                'side'   => 'none',
                                                'width'  => '1',
                                                'style'  => 'solid',
                                                'color'  => '#e5e5e5',
                                                'custom' => [
                                                    'top'  => '1',
                                                    'right' => '1',
                                                    'bottom' => '1',
                                                    'left' => '1',
                                                ],
                                            ],
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#77859B',
                                            'rich_text'   => '<p style="text-align: center;margin: 0;font-weight: 300"><span style="font-size: 12px">© 2025 YayCommerce.com</span></p>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
        ];
    }
}