<?php

namespace YayMail\TemplateLibrary\Templates\Processing;

use YayMail\Abstracts\BaseTemplate;
use YayMail\Elements\BillingShippingAddress;
use YayMail\Elements\Footer;
use YayMail\Elements\Heading;
use YayMail\Elements\Logo;
use YayMail\Elements\OrderDetails;
use YayMail\Elements\OrderDetailsDownload;
use YayMail\Elements\Text;
use YayMail\Utils\SingletonTrait;

/**
 * Classic template for Processing Order email.
 */
class Classic extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'processing_order_classic';
        $this->email_type  = 'customer_processing_order';
        $this->name        = 'Classic';
        $this->description = 'Clean layout with plenty of whitespace';
        $this->elements    = [
            Logo::get_object_data(
                [
                    'align'            => 'center',
                    'padding'          => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'src'              => YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png',
                    'width'            => '172',
                    'background_color' => '#f9f9f9',
                    'url'              => '#',
                    'alt'              => '',
                ]
            ),
            Heading::get_object_data(
                [
                    'global_header'    => '',
                    'padding'          => [
                        'top'    => '40',
                        'right'  => '50',
                        'bottom' => '40',
                        'left'   => '50',
                    ],
                    'background_color' => '#873EFF',
                    'text_color'       => '#ffffff',
                    'font_family'      => 'Helvetica,Roboto,Arial,sans-serif',
                    'rich_text'        => '<h1 style="font-size:30px;font-weight:300;line-height:normal;margin:0px;color:inherit;">Thank you for your order </h1>',
                ]
            ),
            Text::get_object_data(
                [
                    'padding'          => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color' => '#fff',
                    'text_color'       => '#636363',
                    'font_family'      => 'Helvetica,Roboto,Arial,sans-serif',
                    'rich_text'        => '<p><span>Hi [yaymail_billing_first_name],<br><br>Just to let you know &mdash; we&#039;ve received your order #[yaymail_order_number],  and it is now being processed:</span></p>',
                ]
            ),
            OrderDetails::get_object_data(
                [
                    'padding'              => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'     => '#fff',
                    'title_color'          => '#873EFF',
                    'text_color'           => '#636363',
                    'border_color'         => '#e5e5e5',
                    'font_family'          => 'Helvetica,Roboto,Arial,sans-serif',
                    'rich_text'            => '[yaymail_order_details]',
                    'payment_instructions' => '[yaymail_payment_instructions]',
                    'title'                => '<span style="font-size: 20px;">Order #[yaymail_order_number] <b>([yaymail_order_date])</b></span>',
                    'product_title'        => 'Product',
                    'cost_title'           => 'Cost',
                    'quantity_title'       => 'Quantity',
                    'price_title'          => 'Price',
                    'cart_subtotal_title'  => 'Subtotal:',
                    'payment_method_title' => 'Payment method:',
                    'order_total_title'    => 'Total:',
                    'order_note_title'     => 'Note:',
                    'shipping_title'       => 'Shipping:',
                    'discount_title'       => 'Discount:',
                ]
            ),
            BillingShippingAddress::get_object_data(
                [
                    'padding'                  => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color'         => '#fff',
                    'title_color'              => '#873EFF',
                    'text_color'               => '#636363',
                    'border_color'             => '#e5e5e5',
                    'font_family'              => 'Helvetica,Roboto,Arial,sans-serif',
                    'billing_title'            => '<span style="font-size: 20px;font-weight:600;">Billing Address</span>',
                    'shipping_title'           => '<span style="font-size: 20px;font-weight:600;">Shipping Address</span>',
                    'shipping_address_content' => '[yaymail_shipping_address]',
                    'billing_address_content'  => '[yaymail_billing_address]',
                ]
            ),
            Text::get_object_data(
                [
                    'padding'          => [
                        'top'    => '0',
                        'right'  => '50',
                        'bottom' => '38',
                        'left'   => '50',
                    ],
                    'background_color' => '#fff',
                    'text_color'       => '#636363',
                    'font_family'      => 'Helvetica,Roboto,Arial,sans-serif',
                    'rich_text'        => '<p><span>Thanks for using [yaymail_site_url]!</span></p>',
                ]
            ),
            Footer::get_object_data(
                [
                    'padding'          => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color' => '#f9f9f9',
                    'text_color'       => '#8a8a8a',
                    'font_family'      => 'Helvetica,Roboto,Arial,sans-serif',
                    'rich_text'        => '<p style="font-size: 14px;margin: 0px 0px 16px; text-align: center;">[yaymail_site_name]&nbsp;- Built with <a style="color: #873EFF; font-weight: normal; text-decoration: underline;" href="https://woocommerce.com" target="_blank" rel="noopener">WooCommerce</a></p>',
                ]
            ),
        ];
    }
}