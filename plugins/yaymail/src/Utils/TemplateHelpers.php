<?php

namespace YayMail\Utils;

use YayMail\Constants\AttributesData;
use YayMail\Shortcodes\ShortcodesExecutor;
use YayMail\YayMailTemplate;

defined( 'ABSPATH' ) || exit;

/**
 * TemplateHelpers Classes
 * Define all utility functions to be used inside templates
 */
class TemplateHelpers {

    /**
     * @deprecated
     */
    public static function get_attribute_data( $attribute_data ) {
        $data = [];
        foreach ( $attribute_data as $key => $attribute ) {
            if ( 'image_box' === $key || 'image_list' === $key || 'text_list' === $key ) {
                if ( isset( $attribute['column_1'] ) ) {
                    $data['column_1'] = self::get_attribute_data( $attribute['column_1'] );
                }
                if ( isset( $attribute['column_2'] ) ) {
                    $data['column_2'] = self::get_attribute_data( $attribute['column_2'] );
                }
                if ( isset( $attribute['column_3'] ) ) {
                    $data['column_3'] = self::get_attribute_data( $attribute['column_3'] );
                }
            } elseif ( 'inner_background_color' === $key ) {
                $data[ $key ] = $attribute;
            } else {
                $data[ $key ] = $attribute['default_value'];
            }
        }
        return $data;
    }

    public static function get_spacing_value( $spacing, $unit = 'px' ) {
        $unit = esc_attr( $unit );
        return sprintf(
            '%d%s %d%s %d%s %d%s',
            isset( $spacing['top'] ) ? $spacing['top'] : 0,
            $unit,
            isset( $spacing['right'] ) ? $spacing['right'] : 0,
            $unit,
            isset( $spacing['bottom'] ) ? $spacing['bottom'] : 0,
            $unit,
            isset( $spacing['left'] ) ? $spacing['left'] : 0,
            $unit
        );
    }

    public static function get_border_radius_value( $border_radius, $unit = 'px' ) {
        $unit = esc_attr( $unit );
        return sprintf(
            '%d%s %d%s %d%s %d%s',
            isset( $border_radius['top_left'] ) ? $border_radius['top_left'] : 0,
            $unit,
            isset( $border_radius['top_right'] ) ? $border_radius['top_right'] : 0,
            $unit,
            isset( $border_radius['bottom_right'] ) ? $border_radius['bottom_right'] : 0,
            $unit,
            isset( $border_radius['bottom_left'] ) ? $border_radius['bottom_left'] : 0,
            $unit
        );
    }

    /**
     * Inner border radius for column_layout children: only the four outer corners of the row
     * (first column left, last column right; single column gets all four).
     *
     * @param array $inner_border_radius Keys top_left, top_right, bottom_left, bottom_right.
     * @param int   $total_columns       amount_of_columns from parent column_layout.
     * @param int   $column_index        Zero-based column index.
     * @return array{top_left:int,top_right:int,bottom_right:int,bottom_left:int}
     */
    public static function get_inner_column_border_radius( $inner_border_radius, $total_columns, $column_index ) {
        $tl = isset( $inner_border_radius['top_left'] ) ? (int) $inner_border_radius['top_left'] : 0;
        $tr = isset( $inner_border_radius['top_right'] ) ? (int) $inner_border_radius['top_right'] : 0;
        $br = isset( $inner_border_radius['bottom_right'] ) ? (int) $inner_border_radius['bottom_right'] : 0;
        $bl = isset( $inner_border_radius['bottom_left'] ) ? (int) $inner_border_radius['bottom_left'] : 0;

        $total_columns = max( 1, (int) $total_columns );
        $column_index  = max( 0, (int) $column_index );

        if ( 1 === $total_columns ) {
            return [
                'top_left'     => $tl,
                'top_right'    => $tr,
                'bottom_right' => $br,
                'bottom_left'  => $bl,
            ];
        }

        if ( 0 === $column_index ) {
            return [
                'top_left'     => $tl,
                'top_right'    => 0,
                'bottom_right' => 0,
                'bottom_left'  => $bl,
            ];
        }

        if ( $column_index === $total_columns - 1 ) {
            return [
                'top_left'     => 0,
                'top_right'    => $tr,
                'bottom_right' => $br,
                'bottom_left'  => 0,
            ];
        }

        return [
            'top_left'     => 0,
            'top_right'    => 0,
            'bottom_right' => 0,
            'bottom_left'  => 0,
        ];
    }

    public static function get_dimension_value( $dimension, $unit = 'px' ) {
        $unit      = esc_attr( $unit );
        $dimension = floatval( $dimension );
        return "$dimension$unit";
    }

    public static function get_font_family_value( $font_family ) {
        if ( empty( $font_family ) ) {
            return 'inherit';
        }
        return str_replace( [ '\"','"' ], '', $font_family );
    }

    public static function wp_kses_allowed_html( $cus_attr_tags = [] ) {
        $allowed_html_tags           = wp_kses_allowed_html( 'post' );
        $allowed_html_tags['style']  = true;
        $allowed_html_tags['html']   = [];
        $allowed_html_tags['header'] = [];
        $allowed_html_tags['meta']   = [];
        $allowed_html_attr           = $cus_attr_tags;

        $allowed_html_attr ['data-yaymail-element-type'] = true;
        $allowed_html_attr ['charset']                   = true;
        $allowed_html_attr ['http-equiv']                = true;
        $allowed_html_attr ['content']                   = true;
        $allowed_html_attr ['name']                      = true;
        return array_map(
            function ( $item ) use ( $allowed_html_attr ) {
                return is_array( $item ) ? array_merge( $item, $allowed_html_attr ) : $item;
            },
            $allowed_html_tags
        );
    }

    public static function get_style( $css_properties = [] ) {
        return implode(
            ';',
            array_map(
                function ( $css_value, $css_name ) {
                    return "$css_name:$css_value";
                },
                $css_properties,
                array_keys( $css_properties )
            )
        ) . ';';
    }

    public static function wrap_element_content( $content_html, $element, $wrapper_style = null ) {
        $html = yaymail_get_content(
            'templates/elements/element-wrapper.php',
            [
                'content_html'  => $content_html,
                'element'       => $element,
                'wrapper_style' => $wrapper_style,
            ]
        );

        yaymail_kses_post_e( $html );
    }

    /**
     * The function returns the value based on the provided key, default value, and placeholder
     * flag.
     *
     * @param key Key parameter
     * @param default The default value is the value that will be returned if the key is empty or if the
     * is_placeholder parameter is false.
     * @param is_placeholder A boolean value indicating whether the value should be treated as a
     * placeholder or not.
     *
     * @return either the value of the  variable or the placeholder "[[]]" depending on
     * the values of the  and  variables.
     */
    public static function get_content_as_placeholder( $key, $default, $is_placeholder ) {
        return $is_placeholder && ! empty( $key ) ? "[[{$key}]]" : $default;
    }

    public static function get_booking_from_order( $order ) {
        $booking_ids = [];

        if ( null !== $order ) {
            if ( is_callable( 'WC_Booking_Data_Store::get_booking_ids_from_order_id' ) ) {
                $booking_data = new \WC_Booking_Data_Store();
                $booking_ids  = $booking_data->get_booking_ids_from_order_id( $order->get_id() );
            }

            if ( ! empty( $booking_ids ) ) {
                return new \WC_Booking( $booking_ids[0] );
            }
        }

        return null;
    }

    public static function get_font_size( $size, $is_subtitle = false ) {
        if ( 'default' === $size && $is_subtitle ) {
            return '13px';
        }
        $result  = isset( AttributesData::TITLE_SIZE_OPTIONS[ $size ] ) ? AttributesData::TITLE_SIZE_OPTIONS[ $size ] : 16;
        $result .= 'px';
        return $result;
    }

    /**
     * Remove empty shortcodes from the content
     *
     * @param string $content The content to remove empty shortcodes from
     * @return string The content with empty shortcodes removed
     * @since 4.0.2
     */
    public static function remove_empty_shortcodes( $content ) {
        $content = preg_replace( '/<p\b[^>]*>\[yaymail_[^\]]*\]<\/p>/i', '', $content );
        $content = preg_replace( '/\[yaymail_[^\]]*\]/', '', $content );
        return $content;
    }

    public static function convert_rgb_to_hex( $color ) {
        if ( is_string( $color ) && strpos( $color, 'rgb' ) === 0 ) {
            $rgb  = str_replace( 'rgb(', '', $color );
            $rgb  = str_replace( ')', '', $rgb );
            $rgb  = explode( ',', $rgb );
            $hex  = '#';
            $hex .= str_pad( dechex( $rgb[0] ), 2, '0', STR_PAD_LEFT );
            $hex .= str_pad( dechex( $rgb[1] ), 2, '0', STR_PAD_LEFT );
            $hex .= str_pad( dechex( $rgb[2] ), 2, '0', STR_PAD_LEFT );
            return $hex;
        } else {
            return $color;
        }
    }

    /**
     * Find element by id in the list of elements
     *
     * @param string $id The id of the element to find.
     * @param array  $list_elements The list of elements to search in.
     * @return array|null The element if found, null otherwise
     * @since 4.1.0
     */
    public static function find_element_by_id( $id, $list_elements ) {
        foreach ( $list_elements as $element ) {
            if ( $element['id'] === $id ) {
                return $element;
            }
            if ( $element['children'] && count( $element['children'] ) > 0 ) {
                $result = self::find_element_by_id( $id, $element['children'] );
                if ( $result ) {
                    return $result;
                }
            }
        }
        return null;
    }

    public static function find_parent_element( $id, $list_elements ) {

        foreach ( $list_elements as $element ) {
            if ( empty( $element['children'] ) ) {
                continue;
            }
            if ( in_array( $id, array_column( $element['children'], 'id' ) ) ) {
                return $element;
            }
            $sub_query = self::find_parent_element( $id, $element['children'] );
            if ( $sub_query ) {
                return $sub_query;
            }
        }

        return null;
    }

    public static function get_current_column_index( $id, $list_elements ) {
        $element = self::find_element_by_id( $id, $list_elements );
        if ( empty( $element ) ) {
            return 0;
        }
        $parent_element = self::find_parent_element( $element['id'], $list_elements );
        if ( empty( $parent_element ) ) {
            return 0;
        }
        $current_column_index = array_search( $element['id'], array_column( $parent_element['children'], 'id' ) );
        return $current_column_index;
    }

    public static function get_border_css_value( $border ) {
        if ( $border['side'] === 'none' ) {
            return '';
        }
        if ( $border['side'] === 'all' ) {
            return self::get_style(
                [
                    'border' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'top' ) {
            return self::get_style(
                [
                    'border-top' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'bottom' ) {
            return self::get_style(
                [
                    'border-bottom' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'right' ) {
            return self::get_style(
                [
                    'border-right' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'left' ) {
            return self::get_style(
                [
                    'border-left' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'custom' ) {
            return self::get_style(
                [
                    'border-top'    => self::get_border_style(
                        [
                            'width' => $border['custom']['top'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                    'border-right'  => self::get_border_style(
                        [
                            'width' => $border['custom']['right'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                    'border-bottom' => self::get_border_style(
                        [
                            'width' => $border['custom']['bottom'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                    'border-left'   => self::get_border_style(
                        [
                            'width' => $border['custom']['left'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                ]
            );
        }//end if
        return '';
    }

    public static function get_border_style( $border, $unit = 'px' ) {
        $unit = esc_attr( $unit );
        return sprintf(
            '%d%s %s %s',
            $border['width'],
            $unit,
            $border['style'],
            $border['color']
        );
    }

    public static function sanitize_elements_recursive( $elements ) {
        if ( ! is_array( $elements ) ) {
            return [];
        }

        foreach ( $elements as &$element ) {
            if ( isset( $element['data']['rich_text'] ) ) {
                $element['data']['rich_text'] = yaymail_kses_post( $element['data']['rich_text'], $allowed );
            }

            if ( isset( $element['data']['title'] ) ) {
                $element['data']['title'] = yaymail_kses_post( $element['data']['title'] );
            }

            // Recursive cho nested elements
            if ( isset( $element['children'] ) ) {
                $element['children'] = self::sanitize_elements_recursive( $element['children'] );
            }
        }

        return $elements;
    }

    public static function replace_color_paths( $value ) {
        return $value;
    }

    /**
     * Append invisible characters so inbox clients do not pull body text into the preview line.
     *
     * @param string $preheader     Visible preheader text.
     * @param int    $target_length Approximate preview window (Gmail ~90–140, Outlook ~130).
     * @return string
     */
    public static function pad_preheader( $preheader, $target_length = 150 ) {
        $preheader = trim( (string) $preheader );

        if ( '' === $preheader ) {
            return '';
        }

        /**
         * Filter the target length for invisible preheader padding.
         *
         * @param int    $target_length Default padding target length.
         * @param string $preheader     Visible preheader text.
         */
        $target_length = (int) apply_filters( 'yaymail_email_preheader_pad_length', $target_length, $preheader );

        if ( $target_length <= 0 ) {
            return $preheader;
        }

        $visible_length = mb_strlen( $preheader );

        if ( $visible_length >= $target_length ) {
            return $preheader;
        }

        // Litmus preview-text hack: zero-width non-joiner + non-breaking space.
        $pad_unit = "\u{200C}\u{00A0}";

        return $preheader . str_repeat( $pad_unit, $target_length - $visible_length );
    }

    /**
     * Process preheader text (YayMail shortcodes).
     *
     * @param string          $preheader   Raw preheader from template meta.
     * @param YayMailTemplate $template    Email template.
     * @param array           $render_data Render context.
     * @return string Plain-text preheader for inbox preview.
     */
    public static function process_email_preheader( $preheader, $template, $args = [] ) {
        $preheader = trim( (string) $preheader );

        if ( '' === $preheader || ! $template instanceof YayMailTemplate ) {
            return '';
        }

        $template_name = $template->get_name();

        if ( false !== strpos( $preheader, '[' ) && $template_name ) {

            $shortcodes = yaymail_get_email_shortcodes( $template_name );
            new ShortcodesExecutor( $shortcodes, $args );
            $preheader = do_shortcode( $preheader );
        }//end if

        $preheader = self::pad_preheader( wp_strip_all_tags( $preheader ) );

        return $preheader;
    }

    /**
     * Output hidden preheader row at the top of the email body (Woo block-editor style).
     *
     * @param YayMailTemplate $template    Email template.
     * @param array           $args Render context.
     */
    public static function render_email_preheader( $template, $args = [] ) {
        if ( ! $template instanceof YayMailTemplate ) {
            return;
        }

        $template_name = $template->get_name();

        if ( str_starts_with( $template_name, 'pattern_' ) || 'yaymail_global_header_footer' === $template_name ) {
            return;
        }

        $preheader = self::process_email_preheader( $template->get_preheader(), $template, $args );

        if ( '' === $preheader ) {
            return;
        }

        $preheader_html = yaymail_get_content(
            'templates/emails/email-preheader.php',
            [
                'preheader' => $preheader,
            ]
        );

        if ( '' !== $preheader_html ) {
            yaymail_kses_post_e( $preheader_html );
        }
    }
}