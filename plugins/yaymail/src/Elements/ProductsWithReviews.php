<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Product reviews Elements
 */
class ProductsWithReviews extends BaseElement {

    use SingletonTrait;

    protected static $type = 'products_with_reviews';

    public $available_email_ids = [];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_1493_40039)">
<path d="M14 0.25C17.1756 0.25 19.75 2.82436 19.75 6V14C19.75 17.1756 17.1756 19.75 14 19.75H6C2.82436 19.75 0.25 17.1756 0.25 14V6C0.25 2.82436 2.82436 0.25 6 0.25H14ZM6 1.75C3.65279 1.75 1.75 3.65279 1.75 6V14C1.75 16.3472 3.65279 18.25 6 18.25H14C16.3472 18.25 18.25 16.3472 18.25 14V6C18.25 3.99375 16.8598 2.31242 14.9902 1.86621C14.9961 1.91001 15 1.95459 15 2V7C14.2711 7 13.5885 6.80354 13 6.46289C12.4115 6.80354 11.7289 7 11 7V2C11 1.91353 11.0126 1.83003 11.0332 1.75H6ZM6.05664 10.1621C6.18954 9.94607 6.51936 9.94616 6.65234 10.1621H6.64551L7.31738 11.2393C7.3667 11.3184 7.44621 11.3729 7.54102 11.3945L8.8291 11.668C9.08713 11.7222 9.18548 12.0178 9.01465 12.2051L8.1416 13.1455C8.07715 13.2139 8.04632 13.3036 8.05762 13.3936L8.18359 14.6406C8.20992 14.889 7.94407 15.0723 7.70117 14.9717L6.48926 14.4746C6.40196 14.4387 6.303 14.4386 6.21191 14.4746L5.00098 14.9717C4.75801 15.0725 4.49218 14.8891 4.51855 14.6406L4.64355 13.3936C4.65107 13.3037 4.62108 13.2139 4.56055 13.1455L3.68652 12.2051C3.51189 12.0177 3.61483 11.722 3.87305 11.668L5.16016 11.3945C5.2551 11.3765 5.3354 11.3185 5.38477 11.2393L6.05664 10.1621ZM14.0996 13.4004C14.431 13.4004 14.6992 13.6686 14.6992 14C14.6992 14.3314 14.431 14.5996 14.0996 14.5996H11.0996C10.7684 14.5994 10.5 14.3312 10.5 14C10.5 13.6688 10.7684 13.4006 11.0996 13.4004H14.0996ZM16.0996 10.9004C16.431 10.9004 16.6992 11.1686 16.6992 11.5C16.6992 11.8314 16.431 12.0996 16.0996 12.0996H11.0996C10.7684 12.0994 10.5 11.8312 10.5 11.5C10.5 11.1688 10.7684 10.9006 11.0996 10.9004H16.0996Z"/>
</g>
<defs>
<clipPath id="clip0_1493_40039">
<rect width="20" height="20" fill="white"/>
</clipPath>
</defs>
</svg>
';

        return [
            'id'              => uniqid(),
            'type'            => self::$type,
            'name'            => __( 'Products With Reviews', 'yaymail' ),
            'icon'            => self::$icon,
            'group'           => 'block',
            'tags'            => [ 'woocommerce' ],
            'available'       => false,
            'disabled_reason' => [
                'html' => '<svg id="yaymail-products_with_reviews" class="yaymail-element-disabled-icon" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 428 300"><defs><style> #yaymail-products_with_reviews .st0 { fill: #fff; } #yaymail-products_with_reviews .st1 { fill: #edeff3; } #yaymail-products_with_reviews .st2 { fill: #dee2e9; } #yaymail-products_with_reviews .st3 { fill: #c9ced6; } </style></defs><g id="Layer_1-2"><g><rect class="st0" width="428" height="300"/><g><g><path class="st1" d="M36.3,157.3h355.5c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8H36.3c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/><path class="st1" d="M36.3,175.9h225.2c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8H36.3c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/></g><g><g><g><rect class="st2" x="88.9" y="100.5" width="40.4" height="9.5" rx="4.8" ry="4.8"/><rect class="st1" x="88.9" y="119.1" width="69.7" height="9.5" rx="4.8" ry="4.8"/></g><g><path class="st3" d="M98,138.1l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4,0,.2,0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6s.4.2.7,0l2.3-1.2,2.3,1.2c0,0,.2,0,.3,0s.2,0,.4-.1c.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6s-.3-.4-.5-.4h0Z"/><path class="st3" d="M109,138.1l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.2.1.4.2.7,0l2.3-1.2,2.3,1.2c0,0,.2,0,.3,0s.3,0,.4-.1c.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6s-.3-.4-.5-.4h0Z"/><path class="st3" d="M120,138.1l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.1,0,.2.1.4.1s.2,0,.3,0l2.3-1.2,2.3,1.2c.2.1.5,0,.7,0s.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6s-.3-.4-.5-.4h0Z"/><path class="st3" d="M131,138.1l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.1,0,.2.1.4.1s.2,0,.3,0l2.3-1.2,2.3,1.2c.2.1.5,0,.6,0,.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6,0-.2-.3-.4-.5-.4h0Z"/><path class="st3" d="M142.4,138.5c0-.2-.3-.4-.5-.4l-2.6-.4-1.1-2.3c-.1-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.1,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.1,0,.2.1.4.1s.2,0,.3,0l2.3-1.2,2.3,1.2c.2.1.5,0,.6,0,.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6h0Z"/></g></g><g><circle class="st2" cx="54.7" cy="122.4" r="23.2"/><g><circle class="st0" cx="54.7" cy="117.1" r="7.2"/><path class="st0" d="M54.7,139.2c4.7,0,9-2,12.1-5.1.5-.5.6-1.4,0-1.9,0,0,0,0,0,0-2.6-3-8.6-4.9-12.4-4.8-3.4,0-7.2,1.3-9.9,3.3-.6.4-1.4,1-1.9,1.7-.4.6-.3,1.4.2,1.9,3,3.1,7.3,5.1,12,5.1h0Z"/></g></g></g></g><path class="st1" d="M115.7,53.1h74.2c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-74.2c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/><g><path class="st1" d="M36.3,258.7h225.2c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8H36.3c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/><g><g><g><path class="st2" d="M93.7,202h30.9c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-30.9c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/><path class="st1" d="M93.7,220.6h60.2c2.6,0,4.8,2.1,4.8,4.8h0c0,2.6-2.1,4.8-4.8,4.8h-60.2c-2.6,0-4.8-2.1-4.8-4.8h0c0-2.6,2.1-4.8,4.8-4.8Z"/></g><g><path class="st3" d="M98,239.5l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4,0,.2,0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6s.4.2.7,0l2.3-1.2,2.3,1.2c0,0,.2,0,.3,0s.2,0,.4-.1c.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6s-.3-.4-.5-.4h0Z"/><path class="st3" d="M109,239.5l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.2.1.4.2.7,0l2.3-1.2,2.3,1.2c0,0,.2,0,.3,0s.3,0,.4-.1c.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6s-.3-.4-.5-.4h0Z"/><path class="st3" d="M120,239.5l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.1,0,.2.1.4.1s.2,0,.3,0l2.3-1.2,2.3,1.2c.2.1.5,0,.7,0s.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6s-.3-.4-.5-.4h0Z"/><path class="st3" d="M131,239.5l-2.6-.4-1.2-2.3c0-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.2,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.1,0,.2.1.4.1s.2,0,.3,0l2.3-1.2,2.3,1.2c.2.1.5,0,.6,0,.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6,0-.2-.3-.4-.5-.4h0Z"/><path class="st1" d="M142.4,240c0-.2-.3-.4-.5-.4l-2.6-.4-1.1-2.3c-.1-.2-.3-.3-.6-.3s-.4.1-.6.3l-1.1,2.3-2.6.4c-.2,0-.4.2-.5.4s0,.5.2.6l1.9,1.8-.4,2.6c0,.2,0,.5.2.6.1,0,.2.1.4.1s.2,0,.3,0l2.3-1.2,2.3,1.2c.2.1.5,0,.6,0,.2-.1.3-.4.2-.6l-.4-2.6,1.9-1.8c.2-.2.2-.4.2-.6h0Z"/></g></g><g><circle class="st2" cx="54.7" cy="223.8" r="23.2" transform="translate(-171.4 268.4) rotate(-87.4)"/><g><circle class="st0" cx="54.7" cy="218.6" r="7.2"/><path class="st0" d="M54.7,240.6c4.7,0,9-2,12.1-5.1.5-.5.6-1.4,0-1.9,0,0,0,0,0,0-2.6-3-8.6-4.9-12.4-4.9-3.4,0-7.2,1.3-9.9,3.3-.6.4-1.4,1-1.9,1.7-.4.6-.3,1.4.2,1.9,3,3.1,7.3,5.1,12,5.1h0Z"/></g></g></g></g><g><rect class="st1" x="31.5" y="31.8" width="68.4" height="52.2" rx="7" ry="7"/><path class="st3" d="M60.1,53.3c-1.9,0-3.5-1.6-3.5-3.5s1.6-3.5,3.5-3.5c1.9,0,3.5,1.7,3.5,3.5,0,1.9-1.7,3.5-3.5,3.5h0ZM83.2,68.8c.3-.7-.2-1.2-.6-1.7-3.5-4.9-7-9.8-10.6-14.6-.3-.4-.5-.8-1-.9-.8-.3-1.5,0-2.1.7-1.7,2.4-3.5,4.8-5.2,7.2-.4.6-.8,1.2-1.3,1.8-1,1.2-2.2,1.2-3.1,0-.6-.7-1.1-1.5-1.7-2.3-.9-1.2-2-1.3-2.9,0-2.1,2.8-4.2,5.7-6.2,8.6-.3.4-.5.9-.3,1.4s.8.5,1.2.5h31.9c.6,0,1.4.2,1.7-.6h0Z"/></g></g></g></svg>'
                . '<span>'
                . __( 'This element is available in YayMail Pro', 'yaymail' )
                . '</span>',
            ],
            'position'        => 220,
            'status_info'     => [
                'text' => __( 'New', 'yaymail' ),
            ],
            'data'            => [
                'padding'                 => [
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
                'background_color'        => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'text_color'              => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'yaymail' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : YAYMAIL_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'font_family'             => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'product_type'            => [
                    'value_path'    => 'product_type',
                    'component'     => 'Selector',
                    'title'         => __( 'Type', 'yaymail' ),
                    'default_value' => isset( $attributes['product_type'] ) ? $attributes['product_type'] : 'featured',
                    'type'          => 'content',
                    'options'       => [
                        [
                            'label' => __( 'Order products', 'yaymail' ),
                            'value' => 'order_products',
                        ],
                        [
                            'label' => __( 'Featured', 'yaymail' ),
                            'value' => 'featured',
                        ],
                        [
                            'label' => __( 'Product selections', 'yaymail' ),
                            'value' => 'product_selections',
                        ],
                    ],
                ],
                'products'                => [
                    'value_path'    => 'products',
                    'component'     => 'EntitiesSelector',
                    'title'         => __( 'Select products', 'yaymail' ),
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
                'max_reviews_per_product' => [
                    'value_path'    => 'max_reviews_per_product',
                    'component'     => 'NumberInput',
                    'title'         => __( 'Maximum reviews per product', 'yaymail' ),
                    'default_value' => isset( $attributes['max_reviews_per_product'] ) ? $attributes['max_reviews_per_product'] : '2',
                    'type'          => 'content',
                    'is_debounce'   => true,
                    'debounce_time' => 400,
                ],
                'reviews_per_row'         => [
                    'value_path'    => 'reviews_per_row',
                    'component'     => 'NumberInput',
                    'title'         => __( 'Reviews per row', 'yaymail' ),
                    'default_value' => isset( $attributes['reviews_per_row'] ) ? $attributes['reviews_per_row'] : '1',
                    'min'           => 1,
                    'max'           => 3,
                    'type'          => 'content',
                ],
                'review_filter'           => [
                    'value_path'    => 'review_filter',
                    'component'     => 'Selector',
                    'title'         => __( 'Review filter by', 'yaymail' ),
                    'default_value' => isset( $attributes['review_filter'] ) ? $attributes['review_filter'] : 'review_filter_by_rating',
                    'type'          => 'content',
                    'options'       => [
                        [
                            'label' => __( 'Review filter by rating', 'yaymail' ),
                            'value' => 'review_filter_by_rating',
                        ],
                        [
                            'label' => __( 'Review filter by date', 'yaymail' ),
                            'value' => 'review_filter_by_date',
                        ],
                    ],
                ],
                'minimum_rating_to_show'  => [
                    'value_path'    => 'minimum_rating_to_show',
                    'component'     => 'NumberInput',
                    'title'         => __( 'Minimum rating', 'yaymail' ),
                    'default_value' => isset( $attributes['minimum_rating_to_show'] ) ? $attributes['minimum_rating_to_show'] : '1',
                    'type'          => 'content',
                    'min'           => 1,
                    'max'           => 5,
                    'conditions'    => [
                        [
                            'comparison' => 'contain',
                            'value'      => [ 'review_filter_by_rating' ],
                            'attribute'  => 'review_filter',
                        ],
                    ],
                ],
                'from_date_to_date'       => [
                    'value_path'    => 'from_date_to_date',
                    'component'     => 'DatePicker',
                    'title'         => __( 'Review date', 'yaymail' ),
                    'default_value' => isset( $attributes['from_date_to_date'] ) ? $attributes['from_date_to_date'] : '',
                    'type'          => 'content',
                    'calendar_type' => 'range',
                    'conditions'    => [
                        [
                            'comparison' => 'contain',
                            'value'      => [ 'review_filter_by_date' ],
                            'attribute'  => 'review_filter',
                            'operator'   => 'or',
                        ],
                        [
                            'comparison' => 'contain',
                            'value'      => '',
                            'attribute'  => 'review_filter',
                            'operator'   => 'or',
                        ],

                    ],
                ],
                'showing_items'           => [
                    'value_path'    => 'showing_items',
                    'component'     => 'CheckboxGroup',
                    'title'         => __( 'Showing items', 'yaymail' ),
                    'default_value' => isset( $attributes['showing_items'] ) ? $attributes['showing_items'] : [ 'author_avatar', 'author_name', 'review_date', 'rating_stars', 'review_message' ],
                    'type'          => 'content',
                    'options'       => [
                        [
                            'label' => __( 'Author avatar', 'yaymail' ),
                            'value' => 'author_avatar',
                        ],
                        [
                            'label' => __( 'Author name', 'yaymail' ),
                            'value' => 'author_name',
                        ],
                        [
                            'label' => __( 'Review date', 'yaymail' ),
                            'value' => 'review_date',
                        ],
                        [
                            'label' => __( 'Rating stars', 'yaymail' ),
                            'value' => 'rating_stars',
                        ],
                        [
                            'label' => __( 'Review message', 'yaymail' ),
                            'value' => 'review_message',
                        ],
                    ],
                ],
                'sorted_by'               => [
                    'value_path'    => 'sorted_by',
                    'component'     => 'Selector',
                    'title'         => __( 'Sort by', 'yaymail' ),
                    'default_value' => isset( $attributes['sorted_by'] ) ? $attributes['sorted_by'] : 'des_of_rating',
                    'options'       => [
                        [
                            'label' => __( 'Descending by rating', 'yaymail' ),
                            'value' => 'des_of_rating',
                        ],
                        [
                            'label' => __( 'Ascending by rating', 'yaymail' ),
                            'value' => 'asc_of_rating',
                        ],
                        [
                            'label' => __( 'Descending by review date', 'yaymail' ),
                            'value' => 'des_of_review_date',
                        ],
                        [
                            'label' => __( 'Ascending by review date', 'yaymail' ),
                            'value' => 'asc_of_review_date',
                        ],
                    ],
                    'type'          => 'content',
                ],

            ],
        ];
    }
}
