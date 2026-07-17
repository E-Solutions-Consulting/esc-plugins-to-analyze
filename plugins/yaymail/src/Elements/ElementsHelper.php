<?php

namespace YayMail\Elements;

use YayMail\Constants\AttributesData;

/**
 * Class ElementsHelper
 *
 * Helper class for managing elements in YayMail.
 */
class ElementsHelper {
    /**
     * Private constructor to prevent instantiation of the class.
     */
    private function __construct() {}

    /**
     * Get alignment element data.
     *
     * @param array $attributes     The attributes array containing the alignment data.
     * @param array $config  Config for the Align component. Default:
     *              [
     *                  'value_path' => "align",
     *                  'title' => "Align",
     *                  'default_value' => "center"
     *              ]
     *
     * @return array An array containing alignment property data.
     */
    public static function get_align( $attributes, $config = [] ) {
        $default_config = [
            'value_path'    => 'align',
            'title'         => __( 'Align', 'yaymail' ),
            'default_value' => 'center',
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'Align';

        return $result;
    }

    /**
     * Get Spacing element data. Default is 'padding'
     *
     * @param array      $attributes The attributes array containing the spacing data.
     * @param array|null $config (Optional) Configuration for the Spacing component.
     *                          Default:
     *                                  [
     *                                      'value_path'    => "padding",
     *                                      'title'         => "Padding",
     *                                      'default_value' => [
     *                                          'top'    => '15',
     *                                          'right'  => '50',
     *                                          'bottom' => '15',
     *                                          'left'   => '50',
     *                                      ]
     *                                  ]
     *
     * @return array An array containing spacing property data.
     */
    public static function get_spacing( $attributes, $config = [] ) {

        if ( ! is_array( $attributes ) || empty( $attributes ) ) {
            $attributes = [];
        }

        $default_config = [
            'value_path'    => 'padding',
            'title'         => __( 'Padding', 'yaymail' ),
            'default_value' => [
                'top'    => '15',
                'right'  => '50',
                'bottom' => '15',
                'left'   => '50',
            ],
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'Spacing';

        return $result;
    }

    /**
     * Get URL element data.
     *
     * @param array $attributes    The attributes array containing the URL data.
     * @param array $config  Configuration for Media component.
     *              [
     *                  'value_path' => "src",
     *                  'title' => "Image URL",
     *                  'default_value' => YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png'
     *              ]
     *
     * @return array An array containing image URL property data.
     */
    public static function get_media( $attributes, $config = [] ) {
        $default_src = esc_url( YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png' );

        $default_config = [
            'value_path'    => 'src',
            'title'         => __( 'Image URL', 'yaymail' ),
            'default_value' => $default_src,
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'Media';

        return $result;
    }

    /**
     * Get dimension data. Default is 'width'
     *
     * @param array $attributes    The attributes array containing the Dimension data.
     * @param array $config  Configuration for component Dimension.
     *              [
     *                  'value_path' => "width",
     *                  'title' => "Width",
     *                  'default_value' => "172"
     *              ]
     *
     * @return array An array containing Dimension property data.
     */
    public static function get_dimension( $attributes, $config = [] ) {
        $default_config = [
            'value_path'    => 'width',
            'title'         => __( 'Width', 'yaymail' ),
            'default_value' => '172',
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'Dimension';

        return $result;
    }

    /**
     * Get color data. Default is Background color
     *
     * @param array $attributes    The attributes array containing the color data.
     * @param array $config Configuration for Component Color.
     *              [
     *                  'value_path' => "background_color",
     *                  'title' => "Background Color",
     *                  'default_value' => "#f9f9f9",
     *              ]
     *
     * @return array An array containing color property data.
     */
    public static function get_color( $attributes, $config = [] ) {
        $default_config = [
            'value_path'    => 'background_color',
            'title'         => __( 'Background color', 'yaymail' ),
            'default_value' => '#f9f9f9',
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'Color';

        return $result;
    }

    /**
     * Get TextInput data. Default is URL
     *
     * @param array $attributes    The attributes array containing the TextInput data.
     * @param array $config  Configuration for component TextInput.
     *              [
     *                  'value_path' => "url",
     *                  'title' => "URL",
     *                  'default_value' => "#"
     *              ]
     *
     * @return array An array containing TextInput property data.
     */
    public static function get_text_input( $attributes, $config = null ) {
        $default_config = [
            'value_path'    => 'url',
            'title'         => __( 'URL', 'yaymail' ),
            'default_value' => '#',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'TextInput';

        return $result;
    }

    /**
     * Retrieves configuration for a font family selector component.
     *
     * @param array      $attributes The attributes for the font family selector component.
     * @param array|null $config (Optional) Configuration for the font family selector component.
     *                            If not provided, default configuration values are used.
     *                            Default: null
     *                            - 'value_path' (string): The path to the value in the attributes array.
     *                                                     Default: 'font_family'
     *                            - 'title' (string): The title of the font family selector component.
     *                                               Default: 'Font family'
     *                            - 'default_value' (string): The default value for the font family selector component.
     *                                                        Default: YAYMAIL_DEFAULT_FAMILY
     * @return array An array containing configuration options for the font family selector component.
     *               - 'value_path' (string): The path to the value in the attributes array.
     *               - 'component' (string): The type of component, which is 'FontFamilySelector'.
     *               - 'title' (string): The title of the font family selector component.
     *               - 'default_value' (string): The default value for the font family selector component.
     */
    public static function get_font_family_selector( $attributes, $config = null ) {

            $default_config = [
                'value_path'    => 'font_family',
                'title'         => __( 'Font family', 'yaymail' ),
                'default_value' => YAYMAIL_DEFAULT_FAMILY,
                'type'          => 'style',
            ];

            $result              = self::get_component_data( $attributes, $config, $default_config );
            $result['component'] = 'FontFamilySelector';

            return $result;
    }

    /**
     * Retrieves data for a rich text component.
     *
     * @param array      $attributes The attributes for the rich text component.
     * @param array|null $config (Optional) The configuration options for the rich text component.
     *                           The structure of $config should match $default_config:
     *                           [
     *                               'value_path'    => 'rich_text',  // The path to the value in the attributes.
     *                               'title'         => 'Content',     // The title of the rich text component.
     *                               'default_value' => '',            // The default value for the rich text component.
     *                           ]
     *
     * @return array An array containing data for the rich text component, including value path, title,
     *               default value, and component type.
     */
    public static function get_rich_text( $attributes, $config = null ) {
            $default_config = [
                'value_path'    => 'rich_text',
                'title'         => __( 'Content', 'yaymail' ),
                'default_value' => '',
                'text_color_path' => 'text_color',
                'background_color_path' => 'background_color',
            ];

            $result              = self::get_component_data( $attributes, $config, $default_config );
            $result['component'] = 'RichTextEditor';

            return $result;
    }


    /**
     * Retrieves configuration for a Button type selector component.
     *
     * @param array      $attributes The attributes for the Button type selector component.
     * @param array|null $config (Optional)
     *                            - 'value_path' (string): The path to the value in the attributes array.
     *                                                     Default: 'button_type'
     *                            - 'title' (string): The title of the Button type selector component.
     *                                               Default: 'Type'
     *                            - 'default_value' (string): The default value for the Button type selector component.
     *                                                        Default: 'default'
     * @return array An array containing configuration options for the font family selector component.
     *               - 'value_path' (string): The path to the value in the attributes array.
     *               - 'component' (string): The type of component, which is 'ButtonTypeSelector'.
     *               - 'title' (string): The title of the Button type selector component.
     *               - 'default_value' (string): The default value for the Button type selector component.
     */
    public static function get_button_type_selector( $attributes, $config = null ) {

        $default_config = [
            'value_path'    => 'button_type',
            'title'         => __( 'Type', 'yaymail' ),
            'default_value' => 'default',
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'ButtonTypeSelector';

        return $result;
    }

    /**
     * Retrieves configuration for a Font weight selector component.
     *
     * @param array      $attributes The attributes for the Font weight selector component.
     * @param array|null $config (Optional)
     *                            - 'value_path' (string): The path to the value in the attributes array.
     *                                                     Default: 'weight'
     *                            - 'title' (string): The title of the Font weight selector component.
     *                                               Default: 'Weight'
     *                            - 'default_value' (string): The default value for the Font weight selector component.
     *                                                        Default: 'normal'
     * @return array An array containing configuration options for the Font weight selector component.
     *               - 'value_path' (string): The path to the value in the attributes array.
     *               - 'component' (string): The type of component, which is 'FontWeightSelector'.
     *               - 'title' (string): The title of the  component.
     *               - 'default_value' (string): The default value for the component.
     */
    public static function get_font_weight_selector( $attributes, $config = null ) {

        $default_config = [
            'value_path'    => 'weight',
            'title'         => __( 'Weight', 'yaymail' ),
            'default_value' => 'normal',
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'FontWeightSelector';

        return $result;
    }


    private static function get_component_data( $attributes, $config, $default_config ) {
        $value_path = $config['value_path'] ?? $default_config['value_path'] ?? '';
        $result     = wp_parse_args(
            [
                'value_path'    => $value_path,
                'title'         => $config['title'] ?? $default_config['title'] ?? '',
                'default_value' => self::get_default_value( $attributes, $value_path, $config['default_value'] ?? $default_config['default_value'] ?? '' ),
                'type'          => $config['type'] ?? $default_config['type'] ?? 'content',
            ],
            $config ?? [],
        );

        return $result;
    }

    private static function get_default_value( $attributes, $value_path, $fallback_value ) {
        return isset( $attributes[ $value_path ] ) ? $attributes[ $value_path ] : $fallback_value;
    }

    public static function filter_available_elements( $elements, $email_id = '' ): array {

        $email = yaymail_get_email( $email_id );
        // Optimize this to call get_email only once

        if ( empty( $email ) ) {
            return $elements;
        }

        $available_elements = [];

        foreach ( $elements as $element ) {
            $element_instance = ElementsLoader::get_instance()->get_element_instance_by_type( $element['type'] );
            if ( ! empty( $element_instance ) && $element_instance->is_available_in_email( $email ) ) {
                $available_elements[] = $element;
            }
        }
        return $available_elements;
    }

    /**
     * Get Spacing element data. Default is 'padding'
     *
     * @param array      $attributes The attributes array containing the spacing data.
     * @param array|null $config (Optional) Configuration for the Spacing component.
     *                          Default:
     *                                  [
     *                                      'value_path'    => "padding",
     *                                      'title'         => "Padding",
     *                                      'default_value' => [
     *                                          'top'    => '15',
     *                                          'right'  => '50',
     *                                          'bottom' => '15',
     *                                          'left'   => '50',
     *                                      ]
     *                                  ]
     *
     * @since 4.2.0
     * @return array An array containing spacing property data.
     */
    public static function get_border_radius( $attributes, $config = [] ) {

        if ( ! is_array( $attributes ) || empty( $attributes ) ) {
            $attributes = [];
        }

        $default_config = [
            'value_path'    => 'border_radius',
            'title'         => __( 'Border radius', 'yaymail' ),
            'default_value' => [
                'top'    => '0',
                'right'  => '0',
                'bottom' => '0',
                'left'   => '0',
            ],
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'BorderRadius';

        return $result;
    }

    public static function get_button_width_selector( $attributes, $config = [] ) {
        $default_config = [
            'value_path'    => 'width',
            'title'         => __( 'Width', 'yaymail' ),
            'default_value' => 'auto',
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'ButtonWidthSelector';

        return $result;
    }

    public static function get_text_format_toggles( $attributes, $config = [] ) {
        $default_config = [
            'value_path'    => 'text_format_toggles',
            'title'         => __( 'Text format toggles', 'yaymail' ),
            'default_value' => [
                'bold'      => false,
                'italic'    => true,
                'underline' => false,
            ],
            'type'          => 'style',
        ];

        $result              = self::get_component_data( $attributes, $config, $default_config );
        $result['component'] = 'TextFormatToggles';

        return $result;
    }
}
