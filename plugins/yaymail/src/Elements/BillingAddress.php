<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Billing Address Elements
 */
class BillingAddress extends BaseElement {

    use SingletonTrait;

    protected static $type = 'billing_address';

    public $available_email_ids = [ YAYMAIL_WITH_ORDER_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $is_email_improvements_enabled = get_option( 'woocommerce_feature_email_improvements_enabled', 'no' ) === 'yes';
        $layout_type                   = $is_email_improvements_enabled ? 'modern' : 'legacy';

        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M17.5,2.5v15H2.5V2.5h15M18.5,1H1.5c-.28,0-.5.22-.5.5v17c0,.28.22.5.5.5h17c.28,0,.5-.22.5-.5V1.5c0-.28-.22-.5-.5-.5h0Z"/>
  <path d="M6.79,7.33c.57,0,1.04.47,1.04,1.04s-.47,1.04-1.04,1.04-1.04-.47-1.04-1.04.47-1.04,1.04-1.04M6.79,5.83c-1.4,0-2.54,1.14-2.54,2.54s1.14,2.54,2.54,2.54,2.54-1.14,2.54-2.54-1.14-2.54-2.54-2.54h0Z"/>
  <path d="M10.75,13.46h-1.5c0-1.25-1.01-2.26-2.26-2.26s-2.26,1.01-2.26,2.26h-1.5c0-2.07,1.69-3.76,3.76-3.76s3.76,1.69,3.76,3.76Z"/>
  <rect x="12.24" y="7.5" width="2.94" height="1.5"/>
  <rect x="12.24" y="10.87" width="4.62" height="1.5"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Billing Address', 'woocommerce' ),
            'icon'      => self::$icon,
            'group'     => 'woocommerce',
            'available' => true,
            'position'  => 170,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'yaymail' ),
                    'description' => __( 'Handle container layout settings', 'yaymail' ),
                ],
                'padding'                    => ElementsHelper::get_spacing( $attributes ),
                'background_color'           => ElementsHelper::get_color( $attributes, [ 'default_value' => '#fff' ] ),
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
                'title'                      => ElementsHelper::get_rich_text(
                    $attributes,
                    [
                        'value_path'    => 'title',
                        'title'         => __( 'Billing title', 'yaymail' ),
                        'default_value' => '<span style="font-size: 20px;font-weight:600;">' . __( 'Billing Address', 'woocommerce' ) . '</span>',
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
                'billing_content_text_format' => ElementsHelper::get_text_format_toggles(
                    $attributes,
                    [
                        'value_path'    => 'billing_content_text_format',
                        'title'         => __( 'Billing address text formatting', 'yaymail' ),
                    ]
                ),
                'rich_text'                  => [
                    'value_path'    => 'rich_text',
                    'component'     => '',
                    'title'         => __( 'Content', 'yaymail' ),
                    'default_value' => '[yaymail_billing_address]',
                    'type'          => 'content',
                ],
            ],
        ];
    }
}
