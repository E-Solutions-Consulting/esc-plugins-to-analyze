<?php

namespace YayMail\TemplateLibrary\Templates\Completed;

use YayMail\Abstracts\BaseTemplate;
use YayMail\Elements\BillingShippingAddress;
use YayMail\Elements\Divider;
use YayMail\Elements\Footer;
use YayMail\Elements\Heading;
use YayMail\Elements\OrderDetails;
use YayMail\Elements\Text;
use YayMail\Utils\SingletonTrait;

/**
 * Modern template for Completed Order email.
 */
class Modern extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'completed_order_modern';
        $this->email_type  = 'customer_completed_order';
        $this->name        = 'Modern';
        $this->description = 'Clean layout with plenty of whitespace';
        $this->elements    = [
            Text::get_object_data(
                [
                    'padding'          => [
                        'top'    => 15,
                        'right'  => '50',
                        'bottom' => 0,
                        'left'   => '50',
                    ],
                    'background_color' => '#fff',
                    'border'           => [
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
                    'font_family'      => 'Helvetica,Roboto,Arial,sans-serif',
                    'text_color'       => '#720eec',
                    'rich_text'        => '<p><span style="font-size: 16px">[yaymail_site_name]</span></p>',
                ]
            ),
            Heading::get_object_data(
                [
                    'padding'          => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color' => '#fff',
                    'border'           => [
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
                    'font_family'      => 'Helvetica,Roboto,Arial,sans-serif',
                    'text_color'       => '#3c3c3c',
                    'rich_text'        => '<h1 style="font-size: 32px;letter-spacing: -1px;line-height: 38.4px;margin: 0px">Good things are heading your way!</h1>',
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
                        'top'    => 0,
                        'right'  => '50',
                        'bottom' => 0,
                        'left'   => '50',
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
                    'font_family'                => 'Helvetica,Roboto,Arial,sans-serif',
                    'text_color'                 => '#636363',
                    'rich_text'                  => '<p><span style="font-size: 16px">Hi </span><span style="font-size: 16px">[yaymail_billing_first_name],</span></p><br/><p style="font-size: 16px;margin: 0px 0px 16px">We have finished processing your order.</p><p style="font-size: 16px;margin: 0px 0px 16px">Here\'s a reminder of what you\'ve ordered:</p>',
                ]
            ),
            OrderDetails::get_object_data(
                [
                    'rich_text'               => '[yaymail_order_details]',
                    'payment_instructions'    => '[yaymail_payment_instructions]',
                    'padding'                 => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'        => '#fff',
                    'border'                  => [
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
                    'layout_type'             => 'modern',
                    'title'                   => '<p><strong><span style="font-size: 20px">Order summary</span></strong></p><p><span style="font-size: 14px">Order #[yaymail_order_number is_plain="true"] ([yaymail_order_date])</span></p>',
                    'title_color'             => '#3c3c3c',
                    'text_color'              => '#636363',
                    'border_color'            => '#e5e5e5',
                    'font_family'             => 'Helvetica,Roboto,Arial,sans-serif',
                    'table_content_font_size' => 16,
                    'table_heading_font_size' => 16,
                    'show_table_header'       => 1,
                    'product_title'           => 'Product',
                    'cost_title'              => 'Cost',
                    'quantity_title'          => 'Quantity',
                    'price_title'             => 'Price',
                    'cart_subtotal_title'     => 'Subtotal:',
                    'payment_method_title'    => 'Payment method:',
                    'order_total_title'       => 'Total:',
                    'order_note_title'        => 'Note:',
                    'shipping_title'          => 'Shipping: [yaymail_shipping_method]',
                    'discount_title'          => 'Discount:',
                    'custom_footer_rows'      => [],
                ]
            ),
            BillingShippingAddress::get_object_data(
                [
                    'container_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                    => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'           => '#fff',
                    'content_breaker'            => [
                        'component' => 'LineBreaker',
                    ],
                    'content_group_definition'   => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Content settings',
                        'description' => 'Handle content settings',
                    ],
                    'layout_type'                => 'modern',
                    'title_color'                => '#3c3c3c',
                    'text_color'                 => '#636363',
                    'border_color'               => '#e5e5e5',
                    'font_family'                => 'Helvetica,Roboto,Arial,sans-serif',
                    'billing_title'              => '<p><span style="font-size: 16px; font-weight: 600;">Billing address</span></p>',
                    'shipping_title'             => '<p><span style="font-size: 16px; font-weight: 600;">Shipping address</span></p>',
                    'shipping_address_content'   => '[yaymail_shipping_address]',
                    'billing_address_content'    => '[yaymail_billing_address]',
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
                        'top'    => '0',
                        'right'  => '50',
                        'bottom' => 15,
                        'left'   => '50',
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
                    'font_family'                => 'Helvetica,Roboto,Arial,sans-serif',
                    'text_color'                 => '#636363',
                    'rich_text'                  => '<p style="text-align: center"><span style="font-size: 16px">Thanks again! If you need any help with your order, please contact us at [yaymail_store_email]</span></p>',
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
                    'height'           => 1,
                    'background_color' => '#ffffff',
                    'divider_color'    => '#00000033',
                    'divider_type'     => 'solid',
                ]
            ),
            Footer::get_object_data(
                [
                    'container_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                    => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'           => '#ffffff',
                    'content_breaker'            => [
                        'component' => 'LineBreaker',
                    ],
                    'content_group_definition'   => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Content settings',
                        'description' => 'Handle content settings',
                    ],
                    'text_color'                 => '#8a8a8a',
                    'font_family'                => 'Helvetica,Roboto,Arial,sans-serif',
                    'rich_text'                  => '<p style="font-size: 14px;margin: 0px 0px 16px;text-align: center">[yaymail_site_name]&nbsp;- Built with <a style="font-weight: normal;text-decoration: underline" href="https://woocommerce.com" target="_blank" rel="noopener">WooCommerce</a></p>',
                ]
            ),
        ];
    }
}
