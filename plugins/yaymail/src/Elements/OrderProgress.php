<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * OrderProgress element.
 */
class OrderProgress extends BaseElement {

    use SingletonTrait;

    protected static $type = 'order_progress';

    public $available_email_ids = [ YAYMAIL_WITH_ORDER_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M3,2.5h14v1.75H3Z"/><path fill-rule="evenodd" d="M3.5,4.25h13v6.5h-13ZM4.25,5h11.5v5h-11.5Z"/><path d="M9.5,2.5h1v8.25h-1Z"/><path d="M4,15.5h12v.6H4Z"/><path d="M2.5,15.8a1.8,1.8 0 1,0 3.6,0a1.8,1.8 0 1,0 -3.6,0Z"/><path fill-rule="evenodd" d="M8.2,15.8a1.8,1.8 0 1,0 3.6,0a1.8,1.8 0 1,0 -3.6,0ZM9.15,15.8a.85,.85 0 1,1 1.7,0a.85,.85 0 1,1 -1.7,0Z"/><path fill-rule="evenodd" d="M13.7,15.8a1.8,1.8 0 1,0 3.6,0a1.8,1.8 0 1,0 -3.6,0ZM14.65,15.8a.85,.85 0 1,1 1.7,0a.85,.85 0 1,1 -1.7,0Z"/></svg>';

        $default_icon = esc_url( YAYMAIL_PLUGIN_URL . 'assets/images/check.png' );

        $inactive_step_fields = [
            'image_url'         => $default_icon,
            'image_bg_color'    => '#E2E6EE',
            'icon_border_color' => '#E2E6EE',
            'icon_border_style' => 'solid',
            'icon_border_width' => 2,
        ];

        $active_step_fields = [
            'image_url'         => $default_icon,
            'image_bg_color'    => '#873eff',
            'icon_border_color' => '#873eff',
            'icon_border_style' => 'solid',
            'icon_border_width' => 2,
        ];

        $default_steps = [
            array_merge(
                [
                    'title' => __( 'Ordered', 'yaymail' ),
                ],
                $active_step_fields
            ),
            array_merge(
                [
                    'title' => __( 'Processing', 'yaymail' ),
                ],
                $inactive_step_fields
            ),
            array_merge(
                [
                    'title' => __( 'Completed', 'yaymail' ),
                ],
                $inactive_step_fields
            ),
        ];

        return [
            'id'          => uniqid(),
            'type'        => self::$type,
            'name'        => __( 'Order Progress', 'yaymail' ),
            'icon'        => self::$icon,
            'group'       => 'woocommerce',
            'available'   => true,
            'position'    => 152,
            'status_info' => [
                'text' => __( 'New', 'yaymail' ),
            ],
            'data'        => [
                'container_group_definition'    => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'yaymail' ),
                    'description' => __( 'Handle container layout settings', 'yaymail' ),
                ],
                'padding'                       => [
                    'value_path'    => 'padding',
                    'component'     => 'Spacing',
                    'title'         => __( 'Padding', 'yaymail' ),
                    'default_value' => isset( $attributes['padding'] ) ? $attributes['padding'] : [
                        'top'    => '10',
                        'right'  => '50',
                        'bottom' => '10',
                        'left'   => '50',
                    ],
                    'type'          => 'style',
                ],
                'background_color'              => ElementsHelper::get_color(
                    $attributes,
                    [
                        'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#ffffff',
                    ]
                ),
                'quick_presets'                 => [
                    'component' => 'OrderProgressQuickPresets',
                    'title'     => __( 'Quick presets', 'yaymail' ),
                    'type'      => 'style',
                ],
                'display_style'                 => [
                    'value_path'    => 'display_style',
                    'component'     => 'Selector',
                    'title'         => __( 'Display style', 'yaymail' ),
                    'default_value' => isset( $attributes['display_style'] ) ? $attributes['display_style'] : 'step_marker',
                    'options'       => [
                        [
                            'value' => 'step_marker',
                            'label' => __( 'Step marker', 'yaymail' ),
                        ],
                        [
                            'value' => 'filled_bar',
                            'label' => __( 'Filled bar', 'yaymail' ),
                        ],
                    ],
                    'type'          => 'style',
                ],
                'content_breaker'               => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'      => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content settings', 'yaymail' ),
                    'description' => __( 'Handle content settings', 'yaymail' ),
                ],
                'current_step_index'            => [
                    'value_path'    => 'current_step_index',
                    'component'     => 'OrderProgressCurrentStep',
                    'title'         => __( 'Active step', 'yaymail' ),
                    'description'   => __( 'Choose the active step.', 'yaymail' ),
                    'default_value' => isset( $attributes['current_step_index'] ) ? $attributes['current_step_index'] : 0,
                    'type'          => 'content',
                ],
                'connector_height'              => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'connector_height',
                        'title'         => __( 'Connector height', 'yaymail' ),
                        'default_value' => isset( $attributes['connector_height'] ) ? $attributes['connector_height'] : '4',
                        'min'           => 1,
                        'max'           => 10,
                        'unit'          => 'px',
                        'type'          => 'style',
                    ]
                ),
                'connector_active_color'        => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'connector_active_color',
                        'title'         => __( 'Connector color (active)', 'yaymail' ),
                        'default_value' => isset( $attributes['connector_active_color'] ) ? $attributes['connector_active_color'] : '#873eff',
                    ]
                ),
                'connector_inactive_color'      => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'connector_inactive_color',
                        'title'         => __( 'Connector color (inactive)', 'yaymail' ),
                        'default_value' => isset( $attributes['connector_inactive_color'] ) ? $attributes['connector_inactive_color'] : '#E2E6EE',
                    ]
                ),
                'label_active_color'            => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'label_active_color',
                        'title'         => __( 'Label color (active)', 'yaymail' ),
                        'default_value' => isset( $attributes['label_active_color'] ) ? $attributes['label_active_color'] : '#111827',
                    ]
                ),
                'label_inactive_color'          => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'label_inactive_color',
                        'title'         => __( 'Label color (inactive)', 'yaymail' ),
                        'default_value' => isset( $attributes['label_inactive_color'] ) ? $attributes['label_inactive_color'] : '#9CA3AF',
                    ]
                ),
                'icon_size'                     => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'icon_size',
                        'title'         => __( 'Icon size', 'yaymail' ),
                        'default_value' => isset( $attributes['icon_size'] ) ? $attributes['icon_size'] : '18',
                        'min'           => 10,
                        'max'           => 80,
                        'unit'          => 'px',
                        'type'          => 'style',
                    ]
                ),
                'filled_bar_icon_border_radius' => [
                    'value_path'    => 'filled_bar_icon_border_radius',
                    'component'     => 'OrderProgressFilledBarBorderRadius',
                    'title'         => __( 'Icon border radius', 'yaymail' ),
                    'default_value' => isset( $attributes['filled_bar_icon_border_radius'] ) ? $attributes['filled_bar_icon_border_radius'] : '50',
                    'min'           => 0,
                    'max'           => 50,
                    'unit'          => 'px',
                    'type'          => 'style',
                ],
                'label_font_size'               => ElementsHelper::get_dimension(
                    $attributes,
                    [
                        'value_path'    => 'label_font_size',
                        'title'         => __( 'Label font size', 'yaymail' ),
                        'default_value' => isset( $attributes['label_font_size'] ) ? $attributes['label_font_size'] : '14',
                        'min'           => 8,
                        'max'           => 24,
                        'unit'          => 'px',
                        'type'          => 'style',
                    ]
                ),
                'font_family'                   => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Label font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : 'Georgia, "Times New Roman", Times, serif',
                    'type'          => 'style',
                ],
                'appearance_breaker'            => [
                    'component' => 'LineBreaker',
                ],
                'appearance_group_definition'   => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Appearance', 'yaymail' ),
                    'description' => __( 'Handle per-step icon and background colors.', 'yaymail' ),
                ],
                'steps'                         => [
                    'value_path'    => 'steps',
                    'component'     => 'OrderProgressSteps',
                    'title'         => __( 'Steps', 'yaymail' ),
                    'default_value' => isset( $attributes['steps'] ) && is_array( $attributes['steps'] ) ? $attributes['steps'] : $default_steps,
                    'type'          => 'content',
                ],
            ],
        ];
    }
}