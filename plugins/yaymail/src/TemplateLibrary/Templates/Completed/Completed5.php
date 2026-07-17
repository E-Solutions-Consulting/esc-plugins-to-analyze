<?php

namespace YayMail\TemplateLibrary\Templates\Completed;

use YayMail\Abstracts\BaseTemplate;
use YayMail\Elements\BillingAddress;
use YayMail\Elements\Button;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\Heading;
use YayMail\Elements\Image;
use YayMail\Elements\Logo;
use YayMail\Elements\OrderDetails;
use YayMail\Elements\OrderProgress;
use YayMail\Elements\ShippingAddress;
use YayMail\Elements\SocialIcon;
use YayMail\Elements\Text;
use YayMail\Utils\SingletonTrait;

/**
 * Completed Order v5 template for Completed Order email.
 */
class Completed5 extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id                = 'completed_order_v5';
        $this->email_type        = 'customer_completed_order';
        $this->name              = 'Completed Order v5';
        $this->description       = 'Clean layout with plenty of whitespace';
        $this->template_settings = [
            'text_link_color' => '#c33531',
        ];

        $this->elements = [
            ColumnLayout::get_object_data(
                4,
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
                        'top'    => '15',
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
                    'background_color'                   => '#faf9f5',
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
                            29,
                            [
                                'children' => [
                                    Logo::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding' => [
                                                'top'    => 10,
                                                'right'  => 10,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'image_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'image_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Image settings',
                                                'description' => 'Handle image settings',
                                            ],
                                            'src'     => 'https://images.wpbrandy.com/uploads/woo-black-logo.png',
                                            'align'   => 'left',
                                            'width'   => 116,
                                            'url'     => '#',
                                            'alt'     => '',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            25.59,
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<div style="text-align: right"><span style="font-size: 16px">My Account</span></div>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            34.16,
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<div style="text-align: center"><span style="font-size: 16px;font-weight: 500">Order Tracking</span></div>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            11.25,
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
                                                'right'  => 2,
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<div style="text-align: right"><span style="font-size: 16px;font-weight: 500">Contact</span></div>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'column_width'                       => [],
                    'column_spacing'                     => 0,
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
                        'top'    => 2,
                        'right'  => 0,
                        'bottom' => 2,
                        'left'   => 0,
                    ],
                    'border_radius'                      => [
                        'top_left'     => 0,
                        'top_right'    => 0,
                        'bottom_left'  => 0,
                        'bottom_right' => 0,
                    ],
                    'background_color'                   => '#faf9f5',
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
                        'top_left'     => 0,
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
                                'border'   => [
                                    'side'   => 'custom',
                                    'width'  => 1,
                                    'style'  => 'solid',
                                    'color'  => '#031d29',
                                    'custom' => [
                                        'top'    => 1,
                                        'right'  => 1,
                                        'bottom' => 1,
                                        'left'   => 0,
                                    ],
                                ],
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 31,
                                                'right'  => 50,
                                                'bottom' => 10,
                                                'left'   => 50,
                                            ],
                                            'background_color' => '#faf9f500',
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<p><span style="font-size: 14px">Order ID: #[yaymail_order_id is_plain="true"]</span></p>',
                                        ]
                                    ),
                                    Heading::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 0,
                                                'right'  => 50,
                                                'bottom' => 31,
                                                'left'   => 50,
                                            ],
                                            'background_color' => '#faf9f500',
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<p><span style="font-size: 30px"><strong>Purchase was successful</strong></span></p>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            50,
                            [
                                'border'   => [
                                    'side'   => 'custom',
                                    'width'  => 1,
                                    'style'  => 'solid',
                                    'color'  => '#031d29',
                                    'custom' => [
                                        'top'    => 1,
                                        'right'  => 0,
                                        'bottom' => 1,
                                        'left'   => 0,
                                    ],
                                ],
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding' => [
                                                'top'    => '15',
                                                'right'  => '50',
                                                'bottom' => '15',
                                                'left'   => '50',
                                            ],
                                            'background_color' => '#faf9f500',
                                            'image_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'image_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Image settings',
                                                'description' => 'Handle image settings',
                                            ],
                                            'src'     => 'https://images.wpbrandy.com/uploads/completed-5-img-1.png',
                                            'align'   => 'center',
                                            'width'   => 159,
                                            'url'     => '#',
                                            'alt'     => '',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            OrderProgress::get_object_data(
                [
                    'padding'                       => [
                        'top'    => 40,
                        'right'  => '50',
                        'bottom' => 15,
                        'left'   => '50',
                    ],
                    'background_color'              => '#faf9f5',
                    'display_style'                 => 'filled_bar',
                    'current_step_index'            => 2,
                    'connector_height'              => 2,
                    'connector_active_color'        => '#c335311a',
                    'connector_inactive_color'      => '#fce7f3',
                    'icon_size'                     => '18',
                    'filled_bar_icon_border_radius' => 14,
                    'label_font_size'               => 12,
                    'font_family'                   => '"Comic Sans MS", cursive, sans-serif',
                    'label_active_color'            => '#374151',
                    'label_inactive_color'          => '#9ca3af',
                    'steps'                         => [
                        '0' => [
                            'title'             => 'Ordered',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#c33531',
                            'icon_border_color' => '#c33531',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
                        '1' => [
                            'title'             => 'Processing',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#c33531',
                            'icon_border_color' => '#c33531',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
                        '2' => [
                            'title'             => 'Completed',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#c33531',
                            'icon_border_color' => '#c33531',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
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
                    'background_color'           => '#faf9f5',
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
                    'font_family'                => '"Comic Sans MS", cursive, sans-serif',
                    'text_color'                 => '#031d29',
                    'rich_text'                  => '<p><span style="font-size: 18px">Hi <strong>[yaymail_billing_first_name] [yaymail_billing_last_name],</strong></span></p><br /><p><span style="font-size: 18px">Great news! Your order #[yaymail_order_id is_plain="true"] has been successfully delivered. If you\'ve received your order, please take a moment to confirm delivery in your account.</span></p>',
                ]
            ),
            Divider::get_object_data(
                [
                    'align'            => 'center',
                    'padding'          => [
                        'top'    => '15',
                        'right'  => 0,
                        'bottom' => '15',
                        'left'   => 0,
                    ],
                    'width'            => '100',
                    'height'           => 2,
                    'background_color' => '#faf9f5',
                    'divider_color'    => '#333',
                    'divider_type'     => 'solid',
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
                    'background_color'               => '#faf9f5',
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
                    'layout_type'                    => 'modern',
                    'title'                          => '<p><span style="font-size: 20px"><strong>ORDER SUMMARY</strong></span></p>',
                    'title_color'                    => '#1A1A1A',
                    'text_color'                     => '#031d29',
                    'border_color'                   => '#e5e5e5',
                    'font_family'                    => '"Comic Sans MS", cursive, sans-serif',
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
            Button::get_object_data(
                [
                    'container_group_definition'      => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                         => [
                        'top'    => 0,
                        'right'  => '50',
                        'bottom' => 30,
                        'left'   => '50',
                    ],
                    'background_color'                => '#faf9f5',
                    'button_setting_breaker'          => [
                        'component' => 'LineBreaker',
                    ],
                    'button_group_definition'         => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Button settings',
                        'description' => 'Handle button settings',
                    ],
                    'button_type'                     => 'default',
                    'align'                           => 'center',
                    'width'                           => '100%',
                    'custom_width'                    => '50',
                    'height'                          => '21',
                    'button_padding'                  => [
                        'top'    => '12',
                        'right'  => '20',
                        'bottom' => '12',
                        'left'   => '20',
                    ],
                    'button_background_color'         => '#c33531',
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
                        'top_left'     => 14,
                        'top_right'    => 14,
                        'bottom_right' => 14,
                        'bottom_left'  => 14,
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
                    'font_family'                     => '"Comic Sans MS", cursive, sans-serif',
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'column_width'                       => [],
                    'column_spacing'                     => 2,
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
                        'top'    => 2,
                        'right'  => 0,
                        'bottom' => 2,
                        'left'   => 0,
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#faf9f5',
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
                                'border'   => [
                                    'side'   => 'custom',
                                    'width'  => 1,
                                    'style'  => 'solid',
                                    'color'  => '#031d29',
                                    'custom' => [
                                        'top'    => 1,
                                        'right'  => 1,
                                        'bottom' => 1,
                                        'left'   => 0,
                                    ],
                                ],
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 25,
                                                'right'  => '50',
                                                'bottom' => 0,
                                                'left'   => 50,
                                            ],
                                            'background_color' => '#faf9f500',
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<p><span style="font-size: 20px"><strong>SHIPPING ADDRESS</strong></span></p>',
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
                                                'bottom' => 5,
                                                'left'   => 50,
                                            ],
                                            'background_color' => '#faf9f500',
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
                                            'text_color'   => '#031d29',
                                            'border_color' => '#ffffff00',
                                            'font_family'  => '"Comic Sans MS", cursive, sans-serif',
                                            'title'        => '',
                                            'shipping_content_font_size' => '14',
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
                                'border'   => [
                                    'side'   => 'custom',
                                    'width'  => 1,
                                    'style'  => 'solid',
                                    'color'  => '#031d29',
                                    'custom' => [
                                        'top'    => 1,
                                        'right'  => 0,
                                        'bottom' => 1,
                                        'left'   => 0,
                                    ],
                                ],
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'container_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'     => [
                                                'top'    => 25,
                                                'right'  => '50',
                                                'bottom' => 0,
                                                'left'   => 50,
                                            ],
                                            'background_color' => '#faf9f500',
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<p><span style="font-size: 20px"><strong>BILLING ADDRESS</strong></span></p>',
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
                                                'bottom' => 5,
                                                'left'   => 50,
                                            ],
                                            'background_color' => '#faf9f500',
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
                                            'text_color'   => '#031d29',
                                            'border_color' => '#e5e5e5',
                                            'font_family'  => '"Comic Sans MS", cursive, sans-serif',
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
                        'top'    => 25,
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
                    'background_color'                   => '#faf9f5',
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
                                                'right'  => 0,
                                                'bottom' => 0,
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
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
                                                'top'    => 15,
                                                'right'  => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'align'      => 'center',
                                            'content_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'content_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Content settings',
                                                'description' => 'Handle content settings',
                                            ],
                                            'width_icon' => 30,
                                            'spacing'    => 10,
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
                                                    'icon' => 'twitter',
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
                                            'font_family' => '"Comic Sans MS", cursive, sans-serif',
                                            'text_color'  => '#031d29',
                                            'rich_text'   => '<p style="text-align: center;margin: 0;font-weight: 300"><span style="font-size: 12px">© 2026 YayCommerce.com</span></p>',
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