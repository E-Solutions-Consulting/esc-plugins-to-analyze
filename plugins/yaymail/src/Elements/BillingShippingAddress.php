<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Billing Address Elements
 */
class BillingShippingAddress extends BaseElement {

    use SingletonTrait;

    protected static $type = 'billing_shipping_address';

    public $available_email_ids = [ YAYMAIL_WITH_ORDER_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $is_email_improvements_enabled = get_option( 'woocommerce_feature_email_improvements_enabled', 'no' ) === 'yes';
        $layout_type                   = $is_email_improvements_enabled ? 'modern' : 'legacy';

        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <defs>
    <style>
      .cls-1 {
        fill: none;
      }
    </style>
  </defs>
  <g>
    <polygon class="cls-1" points="4.61 8.21 4.14 9.53 15.87 9.53 14.36 5.33 10.04 5.33 10.04 8.21 4.61 8.21"/>
    <path class="cls-1" d="M3.73,10.67l-.31.87v4.79h13.18v-4.79l-.31-.87H3.73ZM5.59,13.4c-.15.15-.36.24-.57.24s-.42-.09-.57-.24-.24-.36-.24-.57.08-.42.24-.57c.15-.15.36-.24.57-.24s.42.09.57.24c.15.15.24.36.24.57s-.08.42-.24.57ZM12.81,15.16c0,.09-.07.16-.16.16h-5.3c-.09,0-.16-.07-.16-.16v-1.7c0-.09.07-.16.16-.16h.8c.09,0,.16.07.16.16v.73h3.37v-.73c0-.09.07-.16.16-.16h.8c.09,0,.16.07.16.16v1.7ZM15.55,13.4c-.15.15-.36.24-.57.24s-.42-.09-.57-.24-.24-.36-.24-.57.08-.42.24-.57c.15-.15.36-.24.57-.24s.42.09.57.24c.15.15.24.36.24.57s-.08.42-.24.57Z"/>
    <path d="M18.99,9.56s0-.08-.01-.12l-.48-.84s-.06-.06-.1-.07c-.04-.01-.08,0-.12.02l-1.02.6-1.57-4.37c-.08-.26-.25-.49-.47-.65-.22-.16-.48-.25-.76-.25h-4.43v1.45h4.32l1.51,4.21H4.14l.48-1.32h-1.54l-.34.93-1.02-.6s-.08-.03-.12-.02c-.04.01-.08.04-.1.07l-.48.84c-.04.08-.02.17.06.22l1.21.71-.29.81c-.02.06-.04.13-.04.2v7.04c0,.32.24.57.53.57h1.36c.25,0,.46-.19.51-.45l.15-.76h10.96l.15.76c.05.26.27.45.51.45h1.36c.29,0,.53-.26.53-.57v-7.04c0-.07-.01-.14-.04-.2l-.29-.81,1.21-.71s.06-.06.07-.1ZM16.59,16.33H3.41v-4.79l.31-.87h12.55l.31.87v4.79Z"/>
    <path d="M5.02,12.02c-.21,0-.42.09-.57.24-.15.15-.24.36-.24.57s.08.42.24.57.36.24.57.24.42-.09.57-.24.24-.36.24-.57-.08-.42-.24-.57c-.15-.15-.36-.24-.57-.24Z"/>
    <path d="M12.65,13.3h-.8c-.09,0-.16.07-.16.16v.73h-3.37v-.73c0-.09-.07-.16-.16-.16h-.8c-.09,0-.16.07-.16.16v1.7c0,.09.07.16.16.16h5.3c.09,0,.16-.07.16-.16v-1.7c0-.09-.07-.16-.16-.16Z"/>
    <path d="M14.98,12.02c-.21,0-.42.09-.57.24-.15.15-.24.36-.24.57s.08.42.24.57.36.24.57.24.42-.09.57-.24.24-.36.24-.57-.08-.42-.24-.57c-.15-.15-.36-.24-.57-.24Z"/>
  </g>
  <g>
    <path d="M8.71,7.99H2.17c-.55,0-1-.45-1-1.01V2.01c0-.56.45-1.01,1-1.01h6.53c.55,0,1,.45,1,1.01v4.98c0,.56-.45,1.01-1,1.01ZM8.71,6.98v.5-.5h0ZM2.17,2.01v4.98h6.53V2.01H2.17Z"/>
    <path d="M4.14,3.63c.17,0,.31.14.31.32s-.14.32-.31.32-.31-.14-.31-.32.14-.32.31-.32M4.14,2.88c-.59,0-1.06.48-1.06,1.07s.48,1.07,1.06,1.07,1.06-.48,1.06-1.07-.48-1.07-1.06-1.07h0Z"/>
    <path d="M5.77,5.95h-.75c0-.49-.4-.89-.88-.89s-.88.4-.88.89h-.75c0-.91.73-1.65,1.63-1.65s1.63.74,1.63,1.65Z"/>
    <rect x="6.32" y="3.48" width="1.23" height="1.01"/>
    <rect x="6.32" y="4.9" width="1.93" height="1.01"/>
  </g>
</svg>';

        $result = [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Billing Shipping Address', 'yaymail' ),
            'icon'      => self::$icon,
            'group'     => 'woocommerce',
            'available' => true,
            'position'  => 180,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'yaymail' ),
                    'description' => __( 'Handle container layout settings', 'yaymail' ),
                ],
                'padding'                    => ElementsHelper::get_spacing( $attributes ),
                'background_color'           => ElementsHelper::get_color(
                    $attributes,
                    [
                        'default_value' => '#fff',
                    ]
                ),
                'content_breaker'            => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'   => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content settings', 'yaymail' ),
                    'description' => __( 'Handle content settings', 'yaymail' ),
                ],
                'layout_type'                => [
                    'value_path'    => 'layout_type',
                    'component'     => 'Selector',
                    'title'         => __( 'Layout type', 'yaymail' ),
                    'default_value' => isset( $attributes['layout_type'] ) ? $attributes['layout_type'] : $layout_type,
                    'options'       => [
                        [
                            'label' => __( 'Legacy', 'yaymail' ),
                            'value' => 'legacy',
                        ],
                        [
                            'label' => __( 'Modern', 'yaymail' ),
                            'value' => 'modern',
                        ],
                    ],
                    'type'          => 'content',
                ],
                'responsive_on_mobile'       => [
                    'value_path'    => 'responsive_on_mobile',
                    'component'     => 'Switcher',
                    'title'         => __( 'Responsive on mobile', 'yaymail' ),
                    'default_value' => isset( $attributes['responsive_on_mobile'] ) ? (bool) $attributes['responsive_on_mobile'] : false,
                    'type'          => 'content',
                ],
                'title_color'                => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'title_color',
                        'title'         => __( 'Title color', 'yaymail' ),
                        'default_value' => YAYMAIL_COLOR_WC_DEFAULT,
                    ]
                ),
                'text_color'                 => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'text_color',
                        'title'         => __( 'Text color', 'yaymail' ),
                        'default_value' => YAYMAIL_COLOR_TEXT_DEFAULT,
                    ]
                ),
                'border_color'               => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'border_color',
                        'title'         => __( 'Table border color', 'yaymail' ),
                        'default_value' => YAYMAIL_COLOR_BORDER_DEFAULT,
                        'conditions' => [
                            'condition_1' => [
                                'attribute'  => 'layout_type',
                                'comparison' => '!=',
                                'value'      => 'modern',
                            ],
                        ]
                    ]
                ),
                'font_family'                => ElementsHelper::get_font_family_selector( $attributes ),
                'billing_title'              => ElementsHelper::get_rich_text(
                    $attributes,
                    [
                        'value_path'    => 'billing_title',
                        'title'         => __( 'Billing title', 'yaymail' ),
                        'default_value' => '<span style="font-size: 20px;font-weight:600;">' . __( 'Billing Address', 'woocommerce' ) . '</span>',
                        'editor_id'     => 'bsa_billing_title',
                        'text_color_path' => 'title_color',
                        'background_color_path' => 'background_color',
                    ]
                ),
                'billing_content_font_size' => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'billing_content_font_size',
                        'title'         => __( 'Billing content font size', 'yaymail' ),
                        'default_value' => '14',
                        'min'           => 8,
                        'max'           => 25,
                        'unit'          => 'px',
                        'type'          => 'style',
                    ]
                ),
                'billing_content_alignment' => ElementsHelper::get_align(
                    $attributes,
                    [
                        'value_path'    => 'billing_content_alignment',
                        'title'         => __( 'Billing content alignment', 'yaymail' ),
                        'default_value' => 'left',
                    ]
                ),
                'billing_content_text_format'  => ElementsHelper::get_text_format_toggles(
                    $attributes,
                    [
                        'value_path'    => 'billing_content_text_format',
                        'title'         => __( 'Billing address text formatting', 'yaymail' ),
                    ]
                ),
                'shipping_title'             => ElementsHelper::get_rich_text(
                    $attributes,
                    [
                        'value_path'    => 'shipping_title',
                        'title'         => __( 'Shipping title', 'yaymail' ),
                        'default_value' => '<span style="font-size: 20px;font-weight:600;">' . __( 'Shipping Address', 'woocommerce' ) . '</span>',
                        'editor_id'     => 'bsa_shipping_title',
                        'text_color_path' => 'title_color',
                        'background_color_path' => 'background_color',
                    ]
                ),
                'shipping_content_font_size' => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'shipping_content_font_size',
                        'title'         => __( 'Shipping content font size', 'yaymail' ),
                        'default_value' => '14',
                        'min'           => 8,
                        'max'           => 25,
                        'unit'          => 'px',
                        'type'          => 'style',
                    ]
                ),
                'shipping_content_alignment' => ElementsHelper::get_align(
                    $attributes,
                    [
                        'value_path'    => 'shipping_content_alignment',
                        'title'         => __( 'Shipping content alignment', 'yaymail' ),
                        'default_value' => 'left',
                    ]
                ),
                'shipping_content_text_format' => ElementsHelper::get_text_format_toggles(
                    $attributes,
                    [
                        'value_path'    => 'shipping_content_text_format',
                        'title'         => __( 'Shipping address text formatting', 'yaymail' ),
                    ]
                ),
                'shipping_address_content'   => [
                    'value_path'    => 'shipping_address_content',
                    'component'     => '',
                    'title'         => __( 'Shipping Content', 'yaymail' ),
                    'default_value' => '[yaymail_shipping_address]',
                    'type'          => 'content',
                ],
                'billing_address_content'    => [
                    'value_path'    => 'billing_address_content',
                    'component'     => '',
                    'title'         => __( 'Billing Content', 'yaymail' ),
                    'default_value' => '[yaymail_billing_address]',
                    'type'          => 'content',
                ],

            ],
        ];
        return $result;
    }
}
