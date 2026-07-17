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
 * Completed Order v6 template for Completed Order email.
 */
class Completed6 extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'completed_order_v6';
        $this->email_type  = 'customer_completed_order';
        $this->name        = 'Completed Order v6';
        $this->description = 'Clean layout with plenty of whitespace';
        $this->elements    = [
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
                        'top'    => '15',
                        'right'  => '0',
                        'bottom' => '15',
                        'left'   => '0',
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#ffffff00',
                    'background_image'                   => [
                        'url'        => 'https://images.wpbrandy.com/uploads/completed-6-img-2.png',
                        'position'   => 'default',
                        'x_position' => 0,
                        'y_position' => 0,
                        'repeat'     => 'no-repeat',
                        'size'       => 'cover',
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
                                    ColumnLayout::get_object_data(
                                        4,
                                        [
                                            'column_width' => [],
                                            'column_spacing' => '0',
                                            'vertical_align' => 'top',
                                            'container_setting_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'container_setting_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container layout settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'      => [
                                                'top'    => '15',
                                                'right'  => 50,
                                                'bottom' => '15',
                                                'left'   => 50,
                                            ],
                                            'border_radius' => [
                                                'top_left' => '0',
                                                'top_right' => '0',
                                                'bottom_left' => '0',
                                                'bottom_right' => '0',
                                            ],
                                            'background_color' => '#ffffff00',
                                            'background_image' => [
                                                'url'      => '',
                                                'position' => 'default',
                                                'x_position' => 0,
                                                'y_position' => 0,
                                                'repeat'   => 'default',
                                                'size'     => 'default',
                                            ],
                                            'inner_setting_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'inner_setting_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Inner layout settings',
                                                'description' => 'Handle inner layout settings',
                                            ],
                                            'inner_border_radius' => [
                                                'top_left' => '0',
                                                'top_right' => '0',
                                                'bottom_left' => '0',
                                                'bottom_right' => '0',
                                            ],
                                            'inner_background_color' => '#ffffff00',
                                            'column_borders' => [
                                                'component' => 'ColumnBorders',
                                                'title' => 'Column borders',
                                            ],
                                            'children'     => [
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
                                                                        'top' => 10,
                                                                        'right' => 10,
                                                                        'bottom' => 10,
                                                                        'left' => 0,
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
                                                                    'src' => YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png',
                                                                    'align' => 'left',
                                                                    'width' => 116,
                                                                    'url' => '#',
                                                                    'alt' => '',
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
                                                                    'padding' => [
                                                                        'top' => '15',
                                                                        'right' => 0,
                                                                        'bottom' => '15',
                                                                        'left' => 0,
                                                                    ],
                                                                    'background_color' => '#ffffff00',
                                                                    'border' => [
                                                                        'side' => 'none',
                                                                        'width' => '1',
                                                                        'style' => 'solid',
                                                                        'color' => '#e5e5e5',
                                                                        'custom' => [
                                                                            'top' => '1',
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
                                                                    'font_family' => '"Fraunces", serif',
                                                                    'text_color' => '#000000',
                                                                    'rich_text' => '<div style="text-align: right"><span style="font-size: 16px;font-weight: 600">My Account</span></div>',
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
                                                                    'padding' => [
                                                                        'top' => '15',
                                                                        'right' => 0,
                                                                        'bottom' => '15',
                                                                        'left' => 0,
                                                                    ],
                                                                    'background_color' => '#ffffff00',
                                                                    'border' => [
                                                                        'side' => 'none',
                                                                        'width' => '1',
                                                                        'style' => 'solid',
                                                                        'color' => '#e5e5e5',
                                                                        'custom' => [
                                                                            'top' => '1',
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
                                                                    'font_family' => '"Fraunces", serif',
                                                                    'text_color' => '#1a1a1a',
                                                                    'rich_text' => '<div style="text-align: center"><span style="font-size: 16px;font-weight: 600">Order Tracking</span></div>',
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
                                                                    'padding' => [
                                                                        'top' => '15',
                                                                        'right' => 2,
                                                                        'bottom' => '15',
                                                                        'left' => 0,
                                                                    ],
                                                                    'background_color' => '#ffffff00',
                                                                    'border' => [
                                                                        'side' => 'none',
                                                                        'width' => '1',
                                                                        'style' => 'solid',
                                                                        'color' => '#e5e5e5',
                                                                        'custom' => [
                                                                            'top' => '1',
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
                                                                    'font_family' => '"Fraunces", serif',
                                                                    'text_color' => '#1a1a1a',
                                                                    'rich_text' => '<div style="text-align: right"><span style="font-size: 16px;font-weight: 600">Contact</span></div>',
                                                                ]
                                                            ),
                                                        ],
                                                    ]
                                                ),
                                            ],
                                        ]
                                    ),
                                    Divider::get_object_data(
                                        [
                                            'align'        => 'center',
                                            'padding'      => [
                                                'top'    => 0,
                                                'right'  => '50',
                                                'bottom' => 5,
                                                'left'   => '50',
                                            ],
                                            'width'        => '100',
                                            'height'       => 1,
                                            'background_color' => '#ffffff00',
                                            'divider_color' => '#ffffff80',
                                            'divider_type' => 'solid',
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
                                                'top'    => '15',
                                                'right'  => 250,
                                                'bottom' => '15',
                                                'left'   => 250,
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
                                            'font_family' => '"Fraunces", serif',
                                            'text_color'  => '#0d0d25',
                                            'rich_text'   => '<p style="border: 1px solid #cccccc;border-radius: 7px;padding: 5px;background-color: #ffffff;font-weight: 600;text-align: center"><span style="font-weight: 500"><span style="margin-right: 5px">Order ID: </span>#[yaymail_order_id is_plain="true"]</span></p>',
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
                                                'bottom' => 0,
                                                'left'   => 50,
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
                                            'font_family' => '"Fraunces", serif',
                                            'text_color'  => '#0d0d25',
                                            'rich_text'   => '<div style="text-align: center"><span style="font-size: 60px;font-weight: bold;line-height: 1.2">Purchase was <br />successful</span></div>',
                                        ]
                                    ),
                                    OrderProgress::get_object_data(
                                        [
                                            'padding'     => [
                                                'top'    => '10',
                                                'right'  => '50',
                                                'bottom' => '10',
                                                'left'   => '50',
                                            ],
                                            'background_color' => '#ffffff00',
                                            'display_style' => 'step_marker',
                                            'current_step_index' => 2,
                                            'connector_height' => 8,
                                            'connector_active_color' => '#873eff',
                                            'connector_inactive_color' => '#E2E6EE',
                                            'label_active_color' => '#636363',
                                            'label_inactive_color' => '#71717a',
                                            'icon_size'   => '18',
                                            'label_font_size' => 12,
                                            'font_family' => 'Georgia, "Times New Roman", Times, serif',
                                            'steps'       => [
                                                '0' => [
                                                    'title' => 'Ordered',
                                                    'image_url' => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',

                                                    'image_bg_color' => '#873eff',
                                                    'icon_border_color' => '#636363',

                                                    'icon_border_style' => 'solid',

                                                    'icon_border_width' => 2,
                                                ],
                                                '1' => [
                                                    'title' => 'Processing',
                                                    'image_url' => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',

                                                    'image_bg_color' => '#873eff',
                                                    'icon_border_color' => '#c4b5fd',

                                                    'icon_border_style' => 'solid',

                                                    'icon_border_width' => 2,
                                                ],
                                                '2' => [
                                                    'title' => 'Completed',
                                                    'image_url' => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',

                                                    'image_bg_color' => '#873eff',
                                                    'icon_border_color' => '#c4b5fd',

                                                    'icon_border_style' => 'solid',

                                                    'icon_border_width' => 2,
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
                                                'top'    => '15',
                                                'right'  => '50',
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
                                            'font_family' => '"Fraunces", serif',
                                            'text_color'  => '#0d0d25',
                                            'rich_text'   => '<p><span style="font-size: 18px">Hi <strong>[yaymail_billing_first_name] [yaymail_billing_last_name],</strong></span></p><br /><p><span style="font-size: 18px">Great news! Your order is about to ship. It should be delivered to you within 7 working days.</span></p>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
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
                    'background_color'               => '#fbfdff',
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
                    'title'                          => '<div style="text-align: center"><span style="font-size: 20px;font-weight: 600">Order Summary</span></div>',
                    'title_color'                    => '#0d0d25',
                    'text_color'                     => '#0d0d25',
                    'border_color'                   => '#e5e5e5',
                    'font_family'                    => '"Fraunces", serif',
                    'table_content_font_size'        => 16,
                    'table_heading_line_breaker'     => [
                        'component' => 'LineBreaker',
                    ],
                    'table_heading_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Table heading settings',
                        'description' => 'Handle table heading settings',
                    ],
                    'table_heading_font_size'        => 16,
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
                        'top'    => 10,
                        'right'  => '50',
                        'bottom' => 10,
                        'left'   => 50,
                    ],
                    'background_color'                => '#ffffff',
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
                    'button_background_color'         => '#873eff',
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
                    'font_family'                     => '"Fraunces", serif',
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'column_width'                       => [],
                    'column_spacing'                     => 1,
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
                        'top'    => 30,
                        'right'  => 50,
                        'bottom' => 20,
                        'left'   => 50,
                    ],
                    'border_radius'                      => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'                   => '#ffffff',
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
                                                'bottom' => 0,
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
                                            'font_family' => '"Fraunces", serif',
                                            'text_color'  => '#0d0d25',
                                            'rich_text'   => '<p style="text-align: right"><span style="font-size: 24px"><strong>Shipping Address</strong></span></p>',
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
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fafafa1a',
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
                                            'text_color'   => '#0d0d25',
                                            'border_color' => '#0d0d25',
                                            'font_family'  => '"Fraunces", serif',
                                            'title'        => '',
                                            'shipping_content_font_size' => 18,
                                            'shipping_content_alignment' => 'center',
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
                                            'font_family' => '"Fraunces", serif',
                                            'text_color'  => '#0d0d25',
                                            'rich_text'   => '<p style="text-align: right"><span style="font-size: 24px"><strong>Billing Address</strong></span></p>',
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
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#fafafa00',
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
                                            'text_color'   => '#0d0d25',
                                            'border_color' => '#1a1a1a',
                                            'font_family'  => '"Fraunces", serif',
                                            'title'        => '',
                                            'billing_content_font_size' => 18,
                                            'billing_content_alignment' => 'center',
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
                        'top'    => 30,
                        'bottom' => 0,
                        'left'   => 50,
                        'right'  => 50,
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
                                            'font_family' => '"Outfit", "DM Sans", sans-serif',
                                            'text_color'  => '#0d0d25',
                                            'rich_text'   => '<p style="margin: 0px;font-weight: 300;text-align: left"><span style="font-size: 16px">For questions, contact <u>hello@example.com</u>, visit our <u>FAQs</u>, or <u>chat</u> with us during operating hours for account support</span></p>',
                                        ]
                                    ),
                                    Divider::get_object_data(
                                        [
                                            'align'        => 'center',
                                            'padding'      => [
                                                'top'    => 30,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                            'width'        => '100',
                                            'height'       => 1,
                                            'background_color' => '#ffffff00',
                                            'divider_color' => '#ceccd3',
                                            'divider_type' => 'solid',
                                        ]
                                    ),
                                    ColumnLayout::get_object_data(
                                        2,
                                        [
                                            'column_width' => [],
                                            'column_spacing' => '0',
                                            'vertical_align' => 'top',
                                            'container_setting_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'container_setting_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Container layout settings',
                                                'description' => 'Handle container layout settings',
                                            ],
                                            'padding'      => [
                                                'top'    => 0,
                                                'right'  => '0',
                                                'bottom' => 0,
                                                'left'   => '0',
                                            ],
                                            'border_radius' => [
                                                'top_left' => '0',
                                                'top_right' => '0',
                                                'bottom_left' => '0',
                                                'bottom_right' => '0',
                                            ],
                                            'background_color' => '#ffffff00',
                                            'background_image' => [
                                                'url'      => '',
                                                'position' => 'default',
                                                'x_position' => 0,
                                                'y_position' => 0,
                                                'repeat'   => 'default',
                                                'size'     => 'default',
                                            ],
                                            'inner_setting_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'inner_setting_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Inner layout settings',
                                                'description' => 'Handle inner layout settings',
                                            ],
                                            'inner_border_radius' => [
                                                'top_left' => '0',
                                                'top_right' => '0',
                                                'bottom_left' => '0',
                                                'bottom_right' => '0',
                                            ],
                                            'inner_background_color' => '#ffffff00',
                                            'column_borders' => [
                                                'component' => 'ColumnBorders',
                                                'title' => 'Column borders',
                                            ],
                                            'children'     => [
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
                                                                    'padding' => [
                                                                        'top' => 15,
                                                                        'right' => 0,
                                                                        'bottom' => 20,
                                                                        'left' => 0,
                                                                    ],
                                                                    'background_color' => '#ffffff00',
                                                                    'border' => [
                                                                        'side' => 'none',
                                                                        'width' => '1',
                                                                        'style' => 'solid',
                                                                        'color' => '#e5e5e5',
                                                                        'custom' => [
                                                                            'top' => '1',
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
                                                                    'text_color' => '#0d0d25',
                                                                    'rich_text' => '<p style="margin: 0px;font-weight: 300;text-align: left"><span style="font-size: 12px">© 2025 YayCommerce.com</span></p>',
                                                                ]
                                                            ),
                                                        ],
                                                    ]
                                                ),
                                                Column::get_object_data(
                                                    50,
                                                    [
                                                        'children' => [
                                                            SocialIcon::get_object_data(
                                                                [
                                                                    'container_group_definition' => [
                                                                        'component' => 'GroupDefinition',
                                                                        'title' => 'Container settings',
                                                                        'description' => 'Handle container layout settings',
                                                                    ],
                                                                    'padding' => [
                                                                        'top' => 0,
                                                                        'right' => 0,
                                                                        'bottom' => 20,
                                                                        'left' => 0,
                                                                    ],
                                                                    'background_color' => '#ffffff00',
                                                                    'align' => 'center',
                                                                    'content_breaker' => [
                                                                        'component' => 'LineBreaker',
                                                                    ],
                                                                    'content_group_definition' => [
                                                                        'component' => 'GroupDefinition',
                                                                        'title' => 'Content settings',
                                                                        'description' => 'Handle content settings',
                                                                    ],
                                                                    'width_icon' => 36,
                                                                    'spacing' => 25,
                                                                    'theme' => 'SolidDark',
                                                                    'icon_list' => [
                                                                        '0' => [
                                                                            'icon' => 'facebook',
                                                                            'url' => '#',
                                                                        ],
                                                                        '1' => [
                                                                            'icon' => 'instagram',
                                                                            'url' => '#',
                                                                        ],
                                                                        '2' => [
                                                                            'icon' => 'tiktok',
                                                                            'url' => '#',
                                                                        ],
                                                                        '3' => [
                                                                            'icon' => 'twitter',
                                                                            'url' => '#',
                                                                        ],
                                                                    ],
                                                                ]
                                                            ),
                                                        ],
                                                    ]
                                                ),
                                            ],
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