<?php

namespace YayMail\TemplateLibrary\Templates\OrderOnHold;

use YayMail\Abstracts\BaseTemplate;
use YayMail\Elements\BillingShippingAddress;
use YayMail\Elements\Footer;
use YayMail\Elements\Heading;
use YayMail\Elements\Logo;
use YayMail\Elements\OrderDetails;
use YayMail\Elements\Text;
use YayMail\Utils\SingletonTrait;

/**
 * Classic template for Order On Hold email.
 */
class Classic extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'order_on_hold_classic';
        $this->email_type  = 'customer_on_hold_order';
        $this->name        = 'Classic';
        $this->description = 'Clean layout with plenty of whitespace';
        $this->elements    = [
            Logo::get_object_data(
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
                    'background_color'           => '#f9f9f9',
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
                    'width'                      => '172',
                    'url'                        => '#',
                    'alt'                        => '',
                ]
            ),
            Heading::get_object_data(
                [
                    'container_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Container settings',
                        'description' => 'Handle container layout settings',
                    ],
                    'padding'                    => [
                        'top'    => '40',
                        'right'  => '50',
                        'bottom' => '40',
                        'left'   => '50',
                    ],
                    'background_color'           => '#873EFF',
                    'content_breaker'            => [
                        'component' => 'LineBreaker',
                    ],
                    'content_group_definition'   => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Content settings',
                        'description' => 'Handle content settings',
                    ],
                    'font_family'                => 'Helvetica,Roboto,Arial,sans-serif',
                    'text_color'                 => '#ffffff',
                    'rich_text'                  => '<h1 style="font-size:30px;font-weight:300;line-height:normal;margin:0px;color:inherit">Thank you for your order</h1>',
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
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
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
                    'rich_text'                  => '<p><span>Hi [yaymail_billing_first_name],<br /><br /></span></p><p><span>Thanks for your order. It&#039;s on-hold until we confirm that payment has been received. In the meantime, here&#039;s a reminder of what you ordered:</span></p>',
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
                    'title'                          => '<span style="font-size: 20px">Order #[yaymail_order_number] <b>([yaymail_order_date])</b></span>',
                    'title_color'                    => '#873EFF',
                    'text_color'                     => '#636363',
                    'border_color'                   => '#e5e5e5',
                    'font_family'                    => 'Helvetica,Roboto,Arial,sans-serif',
                    'table_content_font_size'        => '14',
                    'table_heading_line_breaker'     => [
                        'component' => 'LineBreaker',
                    ],
                    'table_heading_group_definition' => [
                        'component'   => 'GroupDefinition',
                        'title'       => 'Table heading settings',
                        'description' => 'Handle table heading settings',
                    ],
                    'table_heading_font_size'        => '14',
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
                    'title_color'                => '#873EFF',
                    'text_color'                 => '#636363',
                    'border_color'               => '#e5e5e5',
                    'font_family'                => 'Helvetica,Roboto,Arial,sans-serif',
                    'billing_title'              => '<span style="font-size: 20px;font-weight:600;">Billing Address</span>',
                    'shipping_title'             => '<span style="font-size: 20px;font-weight:600;">Shipping Address</span>',
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
                        'bottom' => '38',
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
                    'rich_text'                  => '<p><span>We look forward to fulfilling your order soon.</span></p>',
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
                    'background_color'           => '#f9f9f9',
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