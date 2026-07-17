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
 * Completed Order v4 template for Completed Order email.
 */
class Completed4 extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id                = 'completed_order_v4';
        $this->email_type        = 'customer_completed_order';
        $this->name              = 'Completed Order v4';
        $this->description       = 'Clean layout with plenty of whitespace';
        $this->template_settings = [
            'text_link_color' => '#1a1a1a',
        ];

        $this->elements = [
            Logo::get_object_data(
                [
                    'container_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                    => [
                        'top'    => 20,
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'           => '#e6f7ee',
                    'logo_breaker'               => [
                        'component' => 'LineBreaker',
                    ],
                    'logo_group_definition'      => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Logo settings',
                        'description' => 'Handle logo settings',
                    ],
                    'src'                        => 'https://images.wpbrandy.com/uploads/woo-black-logo.png',
                    'align'                      => 'center',
                    'width'                      => 132,
                    'url'                        => '#',
                    'alt'                        => '',
                ]
            ),
            Divider::get_object_data(
                [
                    'align'            => 'center',
                    'padding'          => [
                        'top'    => 5,
                        'right'  => 40,
                        'bottom' => 5,
                        'left'   => 40,
                    ],
                    'width'            => '100',
                    'height'           => 2,
                    'background_color' => '#e6f7ee',
                    'divider_color'    => '#333',
                    'divider_type'     => 'solid',
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'column_width'                       => [],
                    'column_spacing'                     => '0',
                    'vertical_align'                     => 'middle',
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
                        'right'  => 40,
                        'bottom' => '15',
                        'left'   => 40,
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#e6f7ee',
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
                                                'top'    => 10,
                                                'right'  => '50',
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
                                            'font_family' => '"Kodchasan", system-ui, sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<div style="border: 1px solid #ccc;border-radius: 7px;padding: 5px;background-color: #ffffff;width: fit-content"><span style="margin-right: 5px">Order ID: </span>#[yaymail_order_id is_plain="true"]</div>',
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
                                                'top'    => 4,
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
                                            'font_family' => '"Kodchasan", system-ui, sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p><span style="font-size: 40px;line-height: 1.2"><strong>Purchase was successful</strong></span></p>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            50,
                            [
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
                                                'right'  => 0,
                                                'bottom' => '15',
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/completed-4-img-1.png',
                                            'align'   => 'right',
                                            'width'   => 201,
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
                        'bottom' => '10',
                        'left'   => '50',
                    ],
                    'background_color'              => '#fefce8',
                    'display_style'                 => 'filled_bar',
                    'current_step_index'            => 2,
                    'connector_height'              => 2,
                    'connector_active_color'        => '#efcc5f33',
                    'connector_inactive_color'      => '#faf3e0',
                    'icon_size'                     => '18',
                    'filled_bar_icon_border_radius' => 50,
                    'label_font_size'               => 12,
                    'font_family'                   => '"Kodchasan", system-ui, sans-serif',
                    'label_active_color'            => '#1f2937',
                    'label_inactive_color'          => '#6b7280',
                    'steps'                         => [
                        '0' => [
                            'title'             => 'Ordered',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#efcc5f',
                            'icon_border_color' => '#1a1a1a',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
                        '1' => [
                            'title'             => 'Processing',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#efcc5f',
                            'icon_border_color' => '#1a1a1a',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
                        '2' => [
                            'title'             => 'Completed',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#efcc5f',
                            'icon_border_color' => '#1a1a1a',
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
                    'background_color'           => '#fefce8',
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
                    'font_family'                => '"Kodchasan", system-ui, sans-serif',
                    'text_color'                 => '#1a1a1a',
                    'rich_text'                  => '<p><span style="font-size: 18px">Hi <strong>[yaymail_billing_first_name] [yaymail_billing_last_name],</strong></span></p><br /><p><span style="font-size: 18px">Great news! Your order #[yaymail_order_id is_plain="true"] has been successfully delivered. If you\'ve received your order, please take a moment to confirm delivery in your account.</span></p>',
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
                        'bottom' => 40,
                        'left'   => '50',
                    ],
                    'background_color'                => '#fefce8',
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
                    'button_background_color'         => '#efcc5f',
                    'text_color'                      => '#1a1a1a',
                    'border'                          => [
                        'side'   => 'all',
                        'width'  => 2,
                        'style'  => 'solid',
                        'color'  => '#1a1a1a',
                        'custom' => [
                            'top'    => '1',
                            'right'  => '1',
                            'bottom' => '1',
                            'left'   => '1',
                        ],
                    ],
                    'border_radius'                   => [
                        'top_left'     => 10,
                        'top_right'    => 10,
                        'bottom_right' => 10,
                        'bottom_left'  => 10,
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
                    'font_family'                     => '"Kodchasan", system-ui, sans-serif',
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
                        'bottom' => 25,
                        'left'   => '50',
                    ],
                    'background_color'               => '#fbe8f0',
                    'border'                         => [
                        'side'   => 'none',
                        'width'  => '1',
                        'style'  => 'solid',
                        'color'  => '#190b0b',
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
                    'text_color'                     => '#1a1a1a',
                    'border_color'                   => '#1a1a1a',
                    'font_family'                    => '"Kodchasan", system-ui, sans-serif',
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
                        'top'    => 0,
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
                    'background_color'                   => '#fbe8f0',
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
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fbe8f000',
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
                                            'font_family' => '"Kodchasan", system-ui, sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p><strong style="font-size: 16px">SHIPPING ADDRESS</strong></p>',
                                        ]
                                    ),
                                    ShippingAddress::get_object_data(
                                        [
                                            'padding'      => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fafafa1a',
                                            'layout_type'  => 'legacy',
                                            'title_color'  => '#1a1a1a',
                                            'text_color'   => '#1a1a1a',
                                            'border_color' => '#1a1a1a',
                                            'font_family'  => '"Kodchasan", system-ui, sans-serif',
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
                                                'bottom' => 10,
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
                                            'font_family' => '"Kodchasan", system-ui, sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<p><strong style="font-size: 16px">BILLING ADDRESS</strong></p>',
                                        ]
                                    ),
                                    BillingAddress::get_object_data(
                                        [
                                            'padding'      => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fafafa00',
                                            'layout_type'  => 'legacy',
                                            'title_color'  => '#1a1a1a',
                                            'text_color'   => '#1a1a1a',
                                            'border_color' => '#1a1a1a',
                                            'font_family'  => '"Kodchasan", system-ui, sans-serif',
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
                        'bottom' => 20,
                        'left'   => 50,
                    ],
                    'background_color'           => '#fbe8f0',
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
                    'font_family'                => '"Kodchasan", system-ui, sans-serif',
                    'text_color'                 => '#1a1a1a',
                    'rich_text'                  => '<p><span style="font-size: 18px;font-weight: 400">If there\'s anything wrong with your order, please contact our support team within 7 days and we\'ll be happy to help.</span></p><br /><p><span style="font-size: 18px;font-weight: 400">Best regards,</span></p><p><span style="font-size: 18px;font-weight: 400">[yaymail_site_name]</span></p>',
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
                        'top'    => 40,
                        'bottom' => 40,
                        'left'   => 50,
                        'right'  => 50,
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#ebf1fd',
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
                                            'font_family' => '"Kodchasan", system-ui, sans-serif',
                                            'text_color'  => '#1a1a1a',
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
                                            'font_family' => '"Kodchasan", system-ui, sans-serif',
                                            'text_color'  => '#1a1a1a',
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