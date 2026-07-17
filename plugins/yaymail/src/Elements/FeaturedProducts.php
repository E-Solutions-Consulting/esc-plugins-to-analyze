<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Featured Products Elements
 */
class FeaturedProducts extends BaseElement {

    use SingletonTrait;

    protected static $type = 'featured_products';

    public $available_email_ids = [];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <g>
    <path d="M7.7,2.7v9.75H2.5V2.7h5.2M8.7,1.2H1.5c-.28,0-.5.22-.5.5v11.75c0,.28.22.5.5.5h7.2c.28,0,.5-.22.5-.5V1.7c0-.28-.22-.5-.5-.5h0Z"/>
    <g>
      <path d="M8,16.49H2.21c-.41,0-.75-.34-.75-.75s.34-.75.75-.75h5.79c.41,0,.75.34.75.75s-.34.75-.75.75Z"/>
      <path d="M7.19,18.95H3.01c-.41,0-.75-.34-.75-.75s.34-.75.75-.75h4.19c.41,0,.75.34.75.75s-.34.75-.75.75Z"/>
    </g>
  </g>
  <g>
    <path d="M17.5,2.7v9.75h-5.2V2.7h5.2M18.5,1.2h-7.2c-.28,0-.5.22-.5.5v11.75c0,.28.22.5.5.5h7.2c.28,0,.5-.22.5-.5V1.7c0-.28-.22-.5-.5-.5h0Z"/>
    <path d="M17.79,16.49h-5.79c-.41,0-.75-.34-.75-.75s.34-.75.75-.75h5.79c.41,0,.75.34.75.75s-.34.75-.75.75Z"/>
    <path d="M16.99,18.95h-4.19c-.41,0-.75-.34-.75-.75s.34-.75.75-.75h4.19c.41,0,.75.34.75.75s-.34.75-.75.75Z"/>
  </g>
</svg>';

        $buy_button_conditions = [
            [
                'comparison' => 'contain',
                'value'      => [ 'buy_button' ],
                'attribute'  => 'showing_items',
            ],
        ];

        $pro_preview = '';

        return [
            'id'              => uniqid(),
            'type'            => self::$type,
            'name'            => __( 'Featured Products', 'yaymail' ),
            'icon'            => self::$icon,
            'group'           => 'block',
            'tags'            => [ 'woocommerce' ],
            'available'       => false,
            'disabled_reason' => [
                'html' => '<svg id="yaymail-featured_products" class="yaymail-element-disabled-icon" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 428 300"><defs><style> #yaymail-featured_products .st0 { fill: #fff; } #yaymail-featured_products .st1 { fill: #edeff3; } #yaymail-featured_products .st2 { fill: none; } #yaymail-featured_products .st3 { fill: #1f2937; } #yaymail-featured_products .st4 { fill: #dee2e9; } #yaymail-featured_products .st5 { fill: #c9ced6; } </style></defs><g id="Layer_1-2"><g><rect class="st0" width="428" height="300"/><g><path class="st3" d="M101.3,31.8h12.7v2.9h-9.2v5.1h8.7v2.9h-8.7v7.8h-3.4v-18.7h0Z"/><path class="st3" d="M119.1,44.2c0,2.1,1.1,3.9,3.1,3.9s2.3-.8,2.7-1.7h3.4c-.5,1.7-2,4.4-6.2,4.4s-6.4-3.5-6.4-7.1,2.2-7.5,6.6-7.5,6.2,3.5,6.2,6.8,0,.8,0,1.2h-9.4,0ZM125.1,42.1c0-1.8-.8-3.4-2.9-3.4s-2.9,1.4-3.1,3.4h6Z"/><path class="st3" d="M142.4,47.1c0,1.3.1,3,.2,3.4h-3.2c-.1-.3-.2-1-.2-1.5-.5.8-1.5,1.8-4,1.8s-4.7-2.2-4.7-4.3c0-3.1,2.5-4.6,6.6-4.6h2.1v-1c0-1.1-.4-2.2-2.4-2.2s-2.2.8-2.4,1.9h-3.2c.2-2.3,1.6-4.3,5.8-4.3,3.6,0,5.6,1.5,5.6,4.7v6.1h0ZM139.1,44h-1.8c-2.5,0-3.5.7-3.5,2.3s.7,2.1,2.2,2.1c2.7,0,3.1-1.9,3.1-4v-.4Z"/><path class="st3" d="M144.3,36.6h2.2v-3.9h3.4v3.9h2.8v2.6h-2.8v7.3c0,1.2.3,1.7,1.5,1.7s.7,0,1,0v2.4c-.7.2-1.7.3-2.4.3-2.5,0-3.4-1.3-3.4-3.7v-7.8h-2.2v-2.6h0Z"/><path class="st3" d="M166.8,46.5c0,1.3,0,2.9,0,4h-3.3c0-.4,0-1.2-.1-1.8-.8,1.4-2,2.1-3.9,2.1s-4.6-1.5-4.6-5.1v-9.1h3.4v8.4c0,1.6.5,3,2.4,3s2.8-1.1,2.8-4.1v-7.3h3.4v9.8h0Z"/><path class="st3" d="M170.4,40.4c0-1.6,0-2.8,0-3.8h3.3c0,.3,0,1.6,0,2.6.6-1.7,2.2-2.9,4.5-2.9v3.2c-2.8,0-4.5,1-4.5,4.7v6.3h-3.4v-10.1h0Z"/><path class="st3" d="M183,44.2c0,2.1,1.1,3.9,3.1,3.9s2.3-.8,2.7-1.7h3.4c-.5,1.7-2,4.4-6.2,4.4s-6.4-3.5-6.4-7.1,2.2-7.5,6.6-7.5,6.2,3.5,6.2,6.8,0,.8,0,1.2h-9.4,0ZM189,42.1c0-1.8-.8-3.4-2.9-3.4s-2.9,1.4-3.1,3.4h6Z"/><path class="st3" d="M207.7,30.7v15.9c0,1.3,0,2.6,0,3.8h-3.2c0-.4-.1-1.2-.2-1.6-.7,1.2-1.9,2-4.1,2-3.5,0-5.8-2.8-5.8-7.1s2.4-7.4,6.3-7.4,3.2.8,3.6,1.5v-7s3.4,0,3.4,0ZM197.9,43.6c0,2.9,1.2,4.5,3.2,4.5,2.8,0,3.3-2.3,3.3-4.6s-.4-4.5-3.2-4.5-3.4,1.7-3.4,4.6h0Z"/><path class="st3" d="M218.4,31.8h7.5c3.9,0,6.5,2.2,6.5,5.7s-2.9,5.8-6.6,5.8h-3.9v7.2h-3.5v-18.7h0ZM221.9,40.5h3.5c2.1,0,3.4-.9,3.4-2.9s-1.4-2.9-3.3-2.9h-3.6v5.8h0Z"/><path class="st3" d="M234.9,40.4c0-1.6,0-2.8,0-3.8h3.3c0,.3,0,1.6,0,2.6.6-1.7,2.2-2.9,4.5-2.9v3.2c-2.8,0-4.5,1-4.5,4.7v6.3h-3.4v-10.1h0Z"/><path class="st3" d="M257.5,43.5c0,4.1-2.4,7.3-6.8,7.3s-6.7-3.1-6.7-7.2,2.5-7.3,6.8-7.3,6.6,2.9,6.6,7.2ZM247.5,43.5c0,2.8,1.3,4.6,3.3,4.6s3.3-1.8,3.3-4.6-1.2-4.6-3.3-4.6-3.3,1.6-3.3,4.6Z"/><path class="st3" d="M272.8,30.7v15.9c0,1.3,0,2.6,0,3.8h-3.2c0-.4-.1-1.2-.2-1.6-.7,1.2-1.9,2-4.1,2-3.5,0-5.8-2.8-5.8-7.1s2.4-7.4,6.3-7.4,3.2.8,3.6,1.5v-7h3.4ZM263.1,43.6c0,2.9,1.2,4.5,3.2,4.5,2.8,0,3.3-2.3,3.3-4.6s-.4-4.5-3.2-4.5-3.4,1.7-3.4,4.6Z"/><path class="st3" d="M288.3,46.5c0,1.3,0,2.9,0,4h-3.3c0-.4,0-1.2-.1-1.8-.8,1.4-2,2.1-4,2.1s-4.6-1.5-4.6-5.1v-9.1h3.4v8.4c0,1.6.5,3,2.4,3s2.8-1.1,2.8-4.1v-7.3h3.4v9.8h0Z"/><path class="st3" d="M303.7,46.1c-.5,2.3-2.1,4.7-6.1,4.7s-6.4-2.9-6.4-7.2,2.4-7.3,6.6-7.3,5.8,3.2,5.9,4.8h-3.3c-.3-1.2-1-2.2-2.7-2.2s-3.1,1.7-3.1,4.6,1.1,4.6,3.1,4.6,2.3-.8,2.7-2.1h3.3Z"/><path class="st3" d="M305,36.6h2.2v-3.9h3.4v3.9h2.8v2.6h-2.8v7.3c0,1.2.3,1.7,1.5,1.7s.7,0,1,0v2.4c-.7.2-1.7.3-2.4.3-2.5,0-3.4-1.3-3.4-3.7v-7.8h-2.2v-2.6h0Z"/><path class="st3" d="M318.2,46.4c.3,1.2,1.3,2,2.9,2s2.2-.6,2.2-1.6-.6-1.5-2.8-2c-4.3-1.1-5.1-2.4-5.1-4.3s1.4-4.1,5.4-4.1,5.4,2.2,5.6,4.1h-3.2c-.1-.6-.6-1.7-2.5-1.7s-2,.7-2,1.4.5,1.2,2.8,1.8c4.5,1,5.2,2.6,5.2,4.6s-1.8,4.3-5.8,4.3-5.8-2-6.1-4.4h3.3,0Z"/></g><rect class="st4" x="76.1" y="75.1" width="275.8" height="13.6" rx="6.8" ry="6.8"/><g><path class="st1" d="M41,197.3h80.7c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8H41c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/><path class="st1" d="M52.2,215.9h58.2c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-58.2c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/></g><rect class="st1" x="31.5" y="108.1" width="99.7" height="76" rx="7" ry="7"/><g><rect class="st4" x="43.4" y="240.1" width="75.9" height="28.1" rx="4" ry="4"/><path class="st2" d="M85,258.8c.9,0,1.7.8,1.7,1.7s-.8,1.7-1.7,1.7-1.7-.8-1.7-1.7.8-1.7,1.7-1.7ZM78.1,258.8c.9,0,1.7.8,1.7,1.7s-.8,1.7-1.7,1.7-1.7-.8-1.7-1.7.8-1.7,1.7-1.7ZM89.5,250c-.3-.5-.9-.8-1.4-.8h-10.9v-2.2c0-.5-.4-.9-.9-.9h-2.6c-.5,0-.9.4-.9.9s.4.9.9.9h1.7v3.5h0v4.4c0,1,.8,1.7,1.7,1.7h9c.7,0,1.3-.4,1.6-1.1l1.9-4.8c.2-.5.2-1.1-.2-1.6h0Z"/></g><g><path class="st1" d="M173.4,197.3h80.7c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-80.7c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/><path class="st1" d="M184.6,215.9h58.2c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-58.2c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/></g><rect class="st1" x="163.9" y="108.1" width="99.7" height="76" rx="7" ry="7"/><g><rect class="st4" x="175.8" y="240.1" width="75.9" height="28.1" rx="4" ry="4"/><path class="st2" d="M217.4,258.8c.9,0,1.7.8,1.7,1.7s-.8,1.7-1.7,1.7-1.7-.8-1.7-1.7.8-1.7,1.7-1.7ZM210.5,258.8c.9,0,1.7.8,1.7,1.7s-.8,1.7-1.7,1.7-1.7-.8-1.7-1.7.8-1.7,1.7-1.7ZM221.9,250c-.3-.5-.9-.8-1.4-.8h-10.9v-2.2c0-.5-.4-.9-.9-.9h-2.6c-.5,0-.9.4-.9.9s.4.9.9.9h1.7v3.5h0v4.4c0,1,.8,1.7,1.7,1.7h9c.7,0,1.4-.4,1.6-1.1l1.9-4.8c.2-.5.1-1.1-.2-1.6h0Z"/></g><g><path class="st1" d="M306.3,197.3h80.7c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-80.7c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/><path class="st1" d="M317.6,215.9h58.2c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-58.2c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/></g><rect class="st1" x="296.8" y="108.1" width="99.7" height="76" rx="7" ry="7"/><g><rect class="st4" x="308.7" y="240.1" width="75.9" height="28.1" rx="4" ry="4"/><path class="st2" d="M350.4,258.8c.9,0,1.7.8,1.7,1.7s-.8,1.7-1.7,1.7-1.7-.8-1.7-1.7.8-1.7,1.7-1.7ZM343.4,258.8c.9,0,1.7.8,1.7,1.7s-.8,1.7-1.7,1.7-1.7-.8-1.7-1.7.8-1.7,1.7-1.7ZM354.9,250c-.3-.5-.9-.8-1.4-.8h-10.9v-2.2c0-.5-.4-.9-.9-.9h-2.6c-.5,0-.9.4-.9.9s.4.9.9.9h1.7v3.5h0v4.4c0,1,.8,1.7,1.7,1.7h9c.7,0,1.4-.4,1.6-1.1l1.9-4.8c.2-.5.1-1.1-.2-1.6h0Z"/></g><path class="st5" d="M73.2,139.5c-2.8,0-5.1-2.4-5.1-5.2,0-2.8,2.4-5.1,5.2-5.1,2.8,0,5.2,2.4,5.1,5.2,0,2.8-2.4,5.1-5.2,5.1ZM104.2,162.9c-7.6,0-15.2,0-22.9,0h-23.6c-.7,0-1.5,0-1.8-.7-.4-.7,0-1.4.4-2,3-4.2,6-8.4,9.1-12.5,1.3-1.8,2.9-1.7,4.3,0,.8,1.1,1.6,2.2,2.4,3.3,1.4,1.8,3.2,1.8,4.6,0,.7-.9,1.3-1.7,1.9-2.6,2.5-3.5,5-7,7.6-10.4.8-1.2,1.9-1.5,3.1-1,.6.3,1,.8,1.4,1.4,5.1,7.1,10.3,14.2,15.4,21.3.5.7,1.3,1.5.8,2.5-.5,1.1-1.6.8-2.5.8h0Z"/><path class="st5" d="M205.6,139.5c-2.8,0-5.1-2.4-5.1-5.2,0-2.8,2.4-5.1,5.1-5.1,2.8,0,5.2,2.4,5.1,5.2,0,2.8-2.4,5.1-5.2,5.1ZM236.7,162.9c-7.6,0-15.2,0-22.9,0h-23.6c-.7,0-1.5,0-1.8-.7-.4-.7,0-1.4.4-2,3-4.2,6-8.4,9.1-12.5,1.3-1.8,2.9-1.7,4.3,0,.8,1.1,1.6,2.2,2.4,3.3,1.4,1.8,3.2,1.8,4.6,0,.7-.9,1.3-1.7,1.9-2.6,2.5-3.5,5-7,7.6-10.4.9-1.2,1.9-1.5,3.1-1,.6.3,1,.8,1.4,1.4,5.1,7.1,10.3,14.2,15.4,21.3.5.7,1.3,1.5.8,2.5-.5,1.1-1.6.8-2.5.8h0Z"/><path class="st5" d="M338.5,139.5c-2.8,0-5.1-2.4-5.1-5.2,0-2.8,2.4-5.1,5.1-5.1,2.8,0,5.2,2.4,5.1,5.2,0,2.8-2.4,5.1-5.2,5.1h0ZM369.6,162.9c-7.6,0-15.2,0-22.9,0h-23.6c-.7,0-1.5,0-1.8-.7-.4-.7,0-1.4.4-2,3-4.2,6-8.4,9.1-12.5,1.3-1.8,2.9-1.7,4.3,0,.8,1.1,1.6,2.2,2.4,3.3,1.4,1.8,3.2,1.8,4.6,0,.7-.9,1.3-1.7,1.9-2.6,2.5-3.5,5-7,7.6-10.4.9-1.2,1.9-1.5,3-1,.6.3,1,.8,1.4,1.4,5.1,7.1,10.3,14.2,15.4,21.3.5.7,1.3,1.5.8,2.5-.5,1.1-1.6.8-2.5.8h0Z"/></g></g></svg>'
                . '<span>'
                . __( 'This element is available in YayMail Pro', 'yaymail' )
                . '</span>',
            ],
            'position'        => 220,
            'data'            => [
                'padding'                     => [
                    'value_path'    => 'padding',
                    'component'     => 'Spacing',
                    'title'         => __( 'Padding', 'yaymail' ),
                    'default_value' => isset( $attributes['padding'] ) ? $attributes['padding'] : [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'type'          => 'style',
                ],
                'background_color'            => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'text_color'                  => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'yaymail' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : YAYMAIL_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'font_family'                 => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'content_breaker'             => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'    => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content', 'yaymail' ),
                    'description' => __( 'Handle block items settings', 'yaymail' ),
                ],
                'showing_items'               => [
                    'value_path'    => 'showing_items',
                    'component'     => 'CheckboxGroup',
                    'title'         => __( 'Showing items', 'yaymail' ),
                    'default_value' => isset( $attributes['showing_items'] ) ? $attributes['showing_items'] : [ 'top_content', 'product_image', 'product_name', 'product_price', 'product_original_price', 'buy_button' ],
                    'type'          => 'content',
                    'options'       => [
                        [
                            'label' => __( 'Top content', 'yaymail' ),
                            'value' => 'top_content',
                        ],
                        [
                            'label' => __( 'Product image', 'yaymail' ),
                            'value' => 'product_image',
                        ],
                        [
                            'label' => __( 'Product name', 'yaymail' ),
                            'value' => 'product_name',
                        ],
                        [
                            'label' => __( 'Product price', 'yaymail' ),
                            'value' => 'product_price',
                        ],
                        [
                            'label' => __( 'Product original price', 'yaymail' ),
                            'value' => 'product_original_price',
                        ],
                        [
                            'label' => __( 'Buy button', 'yaymail' ),
                            'value' => 'buy_button',
                        ],
                    ],
                ],
                'top_content'                 => [
                    'value_path'    => 'top_content',
                    'component'     => 'RichTextEditor',
                    'title'         => __( 'Top content', 'yaymail' ),
                    'default_value' => isset( $attributes['top_content'] ) ? $attributes['top_content'] : '<p style="text-align: center;"><span style="font-size: 18px;"><strong>FEATURED PRODUCTS</strong></span></p>
                    <p style="font-size: 14px; text-align: center;">&nbsp;</p>
                    <p style="text-align: center;">Lorem Ipsum is simply dummy text of the printing and typesetting industry.</p>',
                    'type'          => 'content',
                    'conditions'    => [
                        [
                            'comparison' => 'contain',
                            'value'      => [ 'top_content' ],
                            'attribute'  => 'showing_items',
                        ],
                    ]
                ],
                'sale_price_color'            => [
                    'value_path'    => 'sale_price_color',
                    'component'     => 'Color',
                    'title'         => __( 'Product price color', 'yaymail' ),
                    'default_value' => isset( $attributes['sale_price_color'] ) ? $attributes['sale_price_color'] : '#ec4770',
                    'type'          => 'style',
                    'conditions'    => [
                        [
                            'comparison' => 'contain',
                            'value'      => [ 'product_price' ],
                            'attribute'  => 'showing_items',
                        ],
                    ],
                ],
                'regular_price_color'         => [
                    'value_path'    => 'regular_price_color',
                    'component'     => 'Color',
                    'title'         => __( 'Product original price color', 'yaymail' ),
                    'default_value' => isset( $attributes['regular_price_color'] ) ? $attributes['regular_price_color'] : '#808080',
                    'type'          => 'style',
                    'conditions'    => [
                        [
                            'comparison' => 'contain',
                            'value'      => [ 'product_original_price' ],
                            'attribute'  => 'showing_items',
                        ],
                    ],
                ],
                'button_breaker'              => [
                    'component'  => 'LineBreaker',
                    'conditions' => $buy_button_conditions,
                ],
                'button_group_definition'     => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Button', 'yaymail' ),
                    'description' => __( 'Handle buy button settings', 'yaymail' ),
                    'conditions'  => $buy_button_conditions,
                ],
                'buy_button_label'            => [
                    'value_path'    => 'buy_button_label',
                    'component'     => 'TextInput',
                    'title'         => __( 'Buy button text', 'yaymail' ),
                    'default_value' => isset( $attributes['buy_button_label'] ) ? $attributes['buy_button_label'] : __( 'BUY NOW', 'yaymail' ),
                    'type'          => 'content',
                    'conditions'    => $buy_button_conditions,
                ],
                'buy_button_background_color' => [
                    'value_path'    => 'buy_button_background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Buy button background color', 'yaymail' ),
                    'default_value' => isset( $attributes['buy_button_background_color'] ) ? $attributes['buy_button_background_color'] : '#ec4770',
                    'type'          => 'style',
                    'conditions'    => $buy_button_conditions,
                ],
                'buy_button_text_color'       => [
                    'value_path'    => 'buy_button_text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Buy button text color', 'yaymail' ),
                    'default_value' => isset( $attributes['buy_button_text_color'] ) ? $attributes['buy_button_text_color'] : '#ffffff',
                    'type'          => 'style',
                    'conditions'    => $buy_button_conditions,
                ],
                'products_breaker'            => [
                    'component' => 'LineBreaker',
                ],
                'products_group_definition'   => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Products', 'yaymail' ),
                    'description' => __( 'Handle products settings', 'yaymail' ),
                ],
                'products_per_row'            => [
                    'value_path'    => 'products_per_row',
                    'component'     => 'NumberInput',
                    'title'         => __( 'Products per row', 'yaymail' ),
                    'default_value' => isset( $attributes['products_per_row'] ) ? $attributes['products_per_row'] : '3',
                    'min'           => 1,
                    'max'           => 3,
                    'type'          => 'content',
                ],
                'product_type'                => [
                    'value_path'    => 'product_type',
                    'component'     => 'Selector',
                    'title'         => __( 'Product type', 'yaymail' ),
                    'default_value' => isset( $attributes['product_type'] ) ? $attributes['product_type'] : 'newest',
                    'type'          => 'content',
                    'options'       => [
                        [
                            'label' => __( 'Newest', 'yaymail' ),
                            'value' => 'newest',
                        ],
                        [
                            'label' => __( 'On sale', 'yaymail' ),
                            'value' => 'on_sale',
                        ],
                        [
                            'label' => __( 'Featured', 'yaymail' ),
                            'value' => 'featured',
                        ],
                        [
                            'label' => __( 'Category selections', 'yaymail' ),
                            'value' => 'category_selections',
                        ],
                        [
                            'label' => __( 'Tag selections', 'yaymail' ),
                            'value' => 'tag_selections',
                        ],
                        [
                            'label' => __( 'Product selections', 'yaymail' ),
                            'value' => 'product_selections',
                        ],
                    ],
                ],
                'categories'                  => [
                    'value_path'    => 'categories',
                    'component'     => 'EntitiesSelector',
                    'title'         => __( 'Product categories', 'yaymail' ),
                    'default_value' => isset( $attributes['categories'] ) ? $attributes['categories'] : [],
                    'type'          => 'content',
                    'entity_type'   => 'categories',
                    'conditions'    => [
                        [
                            'value'     => 'category_selections',
                            'attribute' => 'product_type',
                        ],
                    ],
                ],
                'tags'                        => [
                    'value_path'    => 'tags',
                    'component'     => 'EntitiesSelector',
                    'title'         => __( 'Product tags', 'yaymail' ),
                    'default_value' => isset( $attributes['tags'] ) ? $attributes['tags'] : [],
                    'type'          => 'content',
                    'entity_type'   => 'tags',
                    'conditions'    => [
                        [
                            'value'     => 'tag_selections',
                            'attribute' => 'product_type',
                        ],
                    ],
                ],
                'products'                    => [
                    'value_path'    => 'products',
                    'component'     => 'EntitiesSelector',
                    'title'         => __( 'Products', 'yaymail' ),
                    'default_value' => isset( $attributes['products'] ) ? $attributes['products'] : [],
                    'type'          => 'content',
                    'entity_type'   => 'products',
                    'conditions'    => [
                        [
                            'value'     => 'product_selections',
                            'attribute' => 'product_type',
                        ],
                    ],
                ],
                'sorted_by'                   => [
                    'value_path'    => 'sorted_by',
                    'component'     => 'Selector',
                    'title'         => __( 'Sorted by', 'yaymail' ),
                    'default_value' => isset( $attributes['sorted_by'] ) ? $attributes['sorted_by'] : 'none',
                    'options'       => [
                        [
                            'label' => __( 'None', 'yaymail' ),
                            'value' => 'none',
                        ],
                        [
                            'label' => __( 'Name A-Z', 'yaymail' ),
                            'value' => 'name_a_z',
                        ],
                        [
                            'label' => __( 'Name Z-A', 'yaymail' ),
                            'value' => 'name_z_a',
                        ],
                        [
                            'label' => __( 'Ascending Price', 'yaymail' ),
                            'value' => 'price_ascending',
                        ],
                        [
                            'label' => __( 'Descending Price', 'yaymail' ),
                            'value' => 'price_descending',
                        ],
                        [
                            'label' => __( 'Random', 'yaymail' ),
                            'value' => 'random',
                        ],
                    ],
                    'type'          => 'content',
                ],
                'number_of_products'          => [
                    'value_path'    => 'number_of_products',
                    'component'     => 'NumberInput',
                    'title'         => __( 'Number of featured products', 'yaymail' ),
                    'default_value' => isset( $attributes['number_of_products'] ) ? $attributes['number_of_products'] : '5',
                    'type'          => 'content',
                    'min'           => 1,
                    'max'           => 20,
                    'is_debounce'   => true,
                    'conditions'    => [
                        [
                            'comparison' => '!=',
                            'value'      => 'product_selections',
                            'attribute'  => 'product_type',
                        ],
                    ],
                ],
            ],
        ];
    }
}