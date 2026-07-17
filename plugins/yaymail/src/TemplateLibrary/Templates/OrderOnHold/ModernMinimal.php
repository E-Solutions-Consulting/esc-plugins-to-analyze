<?php

namespace YayMail\TemplateLibrary\Templates\OrderOnHold;

use YayMail\Abstracts\BaseTemplate;
use YayMail\Elements\BillingShippingAddress;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Heading;
use YayMail\Elements\Image;
use YayMail\Elements\Logo;
use YayMail\Elements\OrderDetails;
use YayMail\Elements\OrderProgress;
use YayMail\Elements\SocialIcon;
use YayMail\Elements\Text;
use YayMail\Utils\SingletonTrait;

/**
 * Modern Minimal template for Order On Hold email.
 */
class ModernMinimal extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'order_on_hold_modern_minimal';
        $this->email_type  = 'customer_on_hold_order';
        $this->name        = 'Modern Minimal';
        $this->description = 'Clean layout with plenty of whitespace';
        $this->elements    = [
            ColumnLayout::get_object_data(
                4,
                [
                    'column_width'           => [],
                    'column_spacing'         => '0',
                    'vertical_align'         => 'top',
                    'padding'                => [
                        'top'    => '15',
                        'right'  => 50,
                        'bottom' => '15',
                        'left'   => 50,
                    ],
                    'border_radius'          => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'background_color'       => '#fff',
                    'background_image'       => [
                        'url'        => '',
                        'position'   => 'default',
                        'x_position' => 0,
                        'y_position' => 0,
                        'repeat'     => 'default',
                        'size'       => 'default',
                    ],
                    'inner_border_radius'    => [
                        'top_left'     => '0',
                        'top_right'    => '0',
                        'bottom_left'  => '0',
                        'bottom_right' => '0',
                    ],
                    'inner_background_color' => '#ffffff00',
                    'children'               => [
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
                                            'background_color' => '#fff',
                                            'image_breaker' => [
                                                'component' => 'LineBreaker',
                                            ],
                                            'image_group_definition' => [
                                                'component' => 'GroupDefinition',
                                                'title' => 'Image settings',
                                                'description' => 'Handle image settings',
                                            ],
                                            'src'     => YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png',
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
                                            'font_family' => 'Helvetica,Roboto,Arial,sans-serif',
                                            'text_color'  => '#000000',
                                            'rich_text'   => '<div style="text-align: right"><span style="font-size: 18px">My Account</span></div>',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            34.160,
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
                                            'font_family' => 'Helvetica,Roboto,Arial,sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<div style="text-align: center"><span style="font-size: 18px;font-weight: 500">Order Tracking</span></div>',
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
                                            'font_family' => 'Helvetica,Roboto,Arial,sans-serif',
                                            'text_color'  => '#1a1a1a',
                                            'rich_text'   => '<div style="text-align: right"><span style="font-size: 18px;font-weight: 500">Contact</span></div>',
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
                    'background_color'       => '#FFFBEB',
                    'inner_background_color' => '#ffffff00',
                    'vertical_align'         => 'middle',
                    'children'               => [
                        Column::get_object_data(
                            70,
                            [
                                'children' => [
                                    Heading::get_object_data(
                                        [
                                            'rich_text'  => '<h1 style="font-size: 30px; font-weight: 600;">Order #[yaymail_order_id is_plain="true"]<br />You\'re just one step away</h1>',
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            30,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'align'   => 'left',
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-on-hold-banner-icon.png',
                                            'width'   => 120,
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 10,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-on-hold-banner-bg.png',
                        'position'   => 'custom',
                        'x_position' => 70,
                        'y_position' => 0,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'padding'                => [
                        'top'    => 30,
                        'right'  => 0,
                        'bottom' => 30,
                        'left'   => 0,
                    ],
                ]
            ),
            OrderProgress::get_object_data(
                [
                    'padding'                       => [
                        'top'    => 40,
                        'right'  => '50',
                        'bottom' => 25,
                        'left'   => '50',
                    ],
                    'background_color'              => '#ffffff',
                    'display_style'                 => 'filled_bar',
                    'current_step_index'            => 0,
                    'connector_height'              => 2,
                    'connector_active_color'        => '#873eff',
                    'connector_inactive_color'      => '#e2e6ee',
                    'icon_size'                     => '18',
                    'filled_bar_icon_border_radius' => 50,
                    'label_font_size'               => 13,
                    'font_family'                   => '"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif',
                    'label_active_color'            => '#111827',
                    'label_inactive_color'          => '#9ca3af',
                    'steps'                         => [
                        '0' => [
                            'title'             => 'Ordered',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#873eff',
                            'icon_border_color' => '#873eff',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
                        '1' => [
                            'title'             => 'Processing',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#E2E6EE',
                            'icon_border_color' => '#E2E6EE',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
                        '2' => [
                            'title'             => 'Completed',
                            'image_url'         => YAYMAIL_PLUGIN_URL . '/assets/images/check.png',
                            'image_bg_color'    => '#E2E6EE',
                            'icon_border_color' => '#E2E6EE',
                            'icon_border_style' => 'solid',
                            'icon_border_width' => 2,
                        ],
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p>Hi <b>[yaymail_billing_first_name] [yaymail_billing_last_name],</b></p><p>Thank you for your order! It\'s on-hold until we confirm that payment has been received. In the meantime, here\'s a reminder of what you ordered.</p>',
                    'text_color' => '#333439',
                    'padding'    => [
                        'top'    => 15,
                        'right'  => 50,
                        'bottom' => 15,
                        'left'   => 50,
                    ],
                ]
            ),
            OrderDetails::get_object_data(
                [
                    'title'       => '<p><span style="font-size: 20px;"><strong>Order Summary</strong></span></p>',
                    'title_color' => '#1A1A1A',
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="font-size: 16px;">[yaymail_order_link text_link="View Order Detail"]</p>',
                    'text_color' => '#333439',
                    'padding'    => [
                        'top'    => 0,
                        'right'  => 50,
                        'bottom' => 0,
                        'left'   => 50,
                    ],
                ]
            ),
            BillingShippingAddress::get_object_data(
                [
                    'title_color' => '#1A1A1A',
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'padding'  => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children' => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 300;"><span style="font-size: 16px;">For questions, contact <u>hello@example.com</u>, visit our <u>FAQs</u>, or <u>chat</u> with us during operating hours for account support</span></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    SocialIcon::get_object_data(
                                        [
                                            'align'      => 'center',
                                            'spacing'    => 24,
                                            'width_icon' => 24,
                                            'style'      => 'Colorful',
                                            'icon_list'  => [
                                                [
                                                    'icon' => 'facebook',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'instagram',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'tiktok',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'youtube',
                                                    'url'  => '#',
                                                ],

                                            ],
                                            'padding'    => [
                                                'top'    => 20,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 300;"><span style="font-size: 12px;">© 2025 YayCommerce.com</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#77859B',
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
