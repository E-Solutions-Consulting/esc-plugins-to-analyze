<?php

namespace YayMail\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers Classes
 */
class Helpers {

    // TODO: need to refactor this function
    public static function prevent_xss_elements( $elements ) {
        foreach ( $elements as $key => $element ) {
            if ( isset( $element['settingRow']['content'] ) ) {
                $elements[ $key ]['settingRow']['content'] = wp_kses_post( html_entity_decode( $element['settingRow']['content'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['contentTitle'] ) ) {
                $elements[ $key ]['settingRow']['contentTitle'] = wp_kses_post( html_entity_decode( $element['settingRow']['contentTitle'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['contentAfter'] ) ) {
                $elements[ $key ]['settingRow']['contentAfter'] = wp_kses_post( html_entity_decode( $element['settingRow']['contentAfter'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['contentBefore'] ) ) {
                $elements[ $key ]['settingRow']['contentBefore'] = wp_kses_post( html_entity_decode( $element['settingRow']['contentBefore'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['col1TtContent'] ) ) {
                $elements[ $key ]['settingRow']['col1TtContent'] = wp_kses_post( html_entity_decode( $element['settingRow']['col1TtContent'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['col2TtContent'] ) ) {
                $elements[ $key ]['settingRow']['col2TtContent'] = wp_kses_post( html_entity_decode( $element['settingRow']['col2TtContent'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['col3TtContent'] ) ) {
                $elements[ $key ]['settingRow']['col3TtContent'] = wp_kses_post( html_entity_decode( $element['settingRow']['col3TtContent'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['HTMLContent'] ) ) {
                $elements[ $key ]['settingRow']['HTMLContent'] = wp_kses_post( html_entity_decode( $element['settingRow']['HTMLContent'], ENT_COMPAT, 'UTF-8' ) );
            }
            if ( isset( $element['settingRow']['col2Content'] ) ) {
                $elements[ $key ]['settingRow']['col2Content'] = wp_kses_post( html_entity_decode( $element['settingRow']['col2Content'], ENT_COMPAT, 'UTF-8' ) );
            }

            // for column
            // column1
            if ( isset( $element['settingRow']['column1'] ) ) {
                foreach ( $element['settingRow']['column1'] as $key1 => $element1 ) {
                    if ( isset( $element1['settingRow']['content'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentTitle'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['contentTitle'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentTitle'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentAfter'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['contentAfter'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentAfter'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentBefore'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['contentBefore'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentBefore'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col1TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['col1TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col1TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['col2TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col3TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['col3TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col3TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['HTMLContent'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['HTMLContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['HTMLContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2Content'] ) ) {
                        $elements[ $key ]['settingRow']['column1'][ $key1 ]['settingRow']['col2Content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2Content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                }//end foreach
            }//end if
            // column2
            if ( isset( $element['settingRow']['column2'] ) ) {
                foreach ( $element['settingRow']['column2'] as $key1 => $element1 ) {
                    if ( isset( $element1['settingRow']['content'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentTitle'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['contentTitle'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentTitle'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentAfter'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['contentAfter'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentAfter'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentBefore'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['contentBefore'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentBefore'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col1TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['col1TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col1TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['col2TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col3TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['col3TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col3TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['HTMLContent'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['HTMLContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['HTMLContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2Content'] ) ) {
                        $elements[ $key ]['settingRow']['column2'][ $key1 ]['settingRow']['col2Content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2Content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                }//end foreach
            }//end if
            // column3
            if ( isset( $element['settingRow']['column3'] ) ) {
                foreach ( $element['settingRow']['column3'] as $key1 => $element1 ) {
                    if ( isset( $element1['settingRow']['content'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentTitle'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['contentTitle'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentTitle'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentAfter'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['contentAfter'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentAfter'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentBefore'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['contentBefore'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentBefore'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col1TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['col1TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col1TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['col2TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col3TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['col3TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col3TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['HTMLContent'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['HTMLContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['HTMLContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2Content'] ) ) {
                        $elements[ $key ]['settingRow']['column3'][ $key1 ]['settingRow']['col2Content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2Content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                }//end foreach
            }//end if

            // column4
            if ( isset( $element['settingRow']['column4'] ) ) {
                foreach ( $element['settingRow']['column4'] as $key1 => $element1 ) {
                    if ( isset( $element1['settingRow']['content'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentTitle'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['contentTitle'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentTitle'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentAfter'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['contentAfter'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentAfter'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['contentBefore'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['contentBefore'] = wp_kses_post( html_entity_decode( $element1['settingRow']['contentBefore'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col1TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['col1TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col1TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['col2TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col3TtContent'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['col3TtContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col3TtContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['HTMLContent'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['HTMLContent'] = wp_kses_post( html_entity_decode( $element1['settingRow']['HTMLContent'], ENT_COMPAT, 'UTF-8' ) );
                    }
                    if ( isset( $element1['settingRow']['col2Content'] ) ) {
                        $elements[ $key ]['settingRow']['column4'][ $key1 ]['settingRow']['col2Content'] = wp_kses_post( html_entity_decode( $element1['settingRow']['col2Content'], ENT_COMPAT, 'UTF-8' ) );
                    }
                }//end foreach
            }//end if
        }//end foreach
        return $elements;
    }

    public static function elements_remove_settings_empty( $elements ) {
        foreach ( $elements as $key => $element ) {
            if ( 'TwoColumns' === $element['type'] || 'ThreeColumns' === $element['type'] || 'FourColumns' === $element['type'] ) {
                if ( ! array_key_exists( 'column1', $elements[ $key ]['settingRow'] ) ) {
                    $elements[ $key ]['settingRow']['column1'] = [];
                }
                if ( ! array_key_exists( 'column2', $elements[ $key ]['settingRow'] ) ) {
                    $elements[ $key ]['settingRow']['column2'] = [];
                }
                if ( ( 'ThreeColumns' === $element['type'] || 'FourColumns' === $element['type'] ) && ! array_key_exists( 'column3', $elements[ $key ]['settingRow'] ) ) {
                    $elements[ $key ]['settingRow']['column3'] = [];
                }
                if ( 'FourColumns' === $element['type'] && ! array_key_exists( 'column4', $elements[ $key ]['settingRow'] ) ) {
                    $elements[ $key ]['settingRow']['column4'] = [];
                }
            }
            if ( 'FeaturedProducts' === $element['type'] ) {
                if ( ! isset( $element['settingRow']['showingItems'] ) ) {
                    $elements[ $key ]['settingRow']['showingItems'] = [];
                }
                if ( ! isset( $element['settingRow']['categories'] ) ) {
                    $elements[ $key ]['settingRow']['categories'] = [];
                }
                if ( ! isset( $element['settingRow']['tags'] ) ) {
                    $elements[ $key ]['settingRow']['tags'] = [];
                }
                if ( ! isset( $element['settingRow']['products'] ) ) {
                    $elements[ $key ]['settingRow']['products'] = [];
                }
            }
            if ( 'SingleBanner' === $element['type'] ) {
                if ( ! isset( $element['settingRow']['showingItems'] ) ) {
                    $elements[ $key ]['settingRow']['showingItems'] = [];
                }
            }
            if ( 'SimpleOffer' === $element['type'] ) {
                if ( ! isset( $element['settingRow']['showingItems'] ) ) {
                    $elements[ $key ]['settingRow']['showingItems'] = [];
                }
            }
        }//end foreach
    }

    public static function is_yaymail_email( $template_name ) {
        $all_emails = \yaymail_get_emails();
        foreach ( $all_emails as $email ) {
            if ( $template_name === $email->get_id() ) {
                return true;
            }
        }
        return false;
    }

    public static function snake_to_pascal( $input ) {
        // Split the input string into words using hyphen as the delimiter
        $words = explode( '_', $input );

        // Capitalize the first letter of each word and join them
        $pascal_case = implode( '', array_map( 'ucfirst', $words ) );

        return $pascal_case;
    }

    public static function check_plugin_installed( $plugin_slug ) {
        $installed_plugins = get_plugins();
        return array_key_exists( $plugin_slug, $installed_plugins ) || in_array( $plugin_slug, $installed_plugins, true );
    }

    public static function get_order_from_shortcode_data( $data ) {
        $order = $data['order'] ?? null;

        if ( self::is_woocommerce_order( $order ) ) {
            return $order;
        }
        return null;
    }

    public static function is_woocommerce_order( $order ) {
        $is_wc_order = false;
        if ( ! empty( $order ) && $order instanceof \WC_Order ) {
            $is_wc_order = true;
        }
        return apply_filters( 'yaymail_is_woocommerce_order', $is_wc_order, $order );
    }

    /**
     * Convert a given string to snake case.
     *
     * This function handles various text cases, including camel case, kebab case,
     * and words with spaces. It replaces spaces and hyphens with underscores,
     * and converts uppercase letters to underscore + lowercase.
     *
     * @param string $input The input string to be converted.
     *
     * @return string The converted string in snake case.
     */
    public static function to_snake_case( $input ) {
        // Replace spaces with underscores
        $snake_case = str_replace( ' ', '_', $input );

        // Replace hyphens with underscores
        $snake_case = str_replace( '-', '_', $snake_case );

        // Replace uppercase letters with underscore + lowercase
        $snake_case = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $snake_case );

        // Convert to lowercase
        $snake_case = strtolower( $snake_case );

        return $snake_case;
    }

    public static function snake_case_to_capitalized_words( $snake_case_string ) {
        // Replace underscores with space
        $space_separated_string = str_replace( '_', ' ', $snake_case_string );

        // Capitalize each word
        $capitalized_words = ucwords( $space_separated_string );
        $capitalized_words = trim( $capitalized_words );

        return $capitalized_words;
    }

    /**
     * Checks if all keys from the specified array exist in another array.
     *
     * @param array $keys An array containing the keys to check.
     * @param array $arr  The array to search for the keys.
     *
     * @return bool True if all keys exist in the array, false otherwise.
     */
    public static function array_keys_exists( $keys, $arr ) {
        return ! array_diff_key( array_flip( $keys ), $arr );
    }

    /**
     * Get value of object recursively
     *
     * @param array        $object
     * @param array|string $path
     * @return mixed|null
     */
    public static function get_object_value( $object, $path ) {
        if ( ! is_array( $path ) ) {
            // Make sure path is an array
            $path = [ $path ];
        }

        $current = $object;

        foreach ( $path as $key ) {
            if ( isset( $current[ $key ] ) ) {
                $current = $current[ $key ];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Set value of object recursively
     *
     * @param array        &$object
     * @param array|string $path
     * @param mixed        $value
     * @return void
     */
    public static function set_object_value( &$object, $path, $value ) {
        if ( ! is_array( $path ) ) {
            $path = [ $path ];
        }

        $current =& $object;

        foreach ( $path as $key ) {
            if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
                $current[ $key ] = [];
            }
            $current =& $current[ $key ];
        }

        $current = $value;
    }

    public static function get_dummy_order( $order_status = \Automattic\WooCommerce\Enums\OrderStatus::COMPLETED ) {
        $product = new \WC_Product();
        $product->set_name( __( 'Happy YayCommerce', 'yaymail' ) );
        $product->set_price( 18 );

        $order = new \WC_Order();
        if ( $product ) {
            $order->add_product( $product, 2 );
        }
        $order->set_id( 1 );
        $order->set_status( $order_status );
        $order->set_date_created( time() );
        $order->set_currency( 'USD' );
        $order->set_discount_total( 18 );
        $order->set_shipping_total( 0 );
        $order->set_total( 18 );
        $order->set_payment_method_title( __( 'Direct bank transfer', 'woocommerce' ) );
        $order->set_customer_note( __( "This is a customer note. Customers can add a note to their order on checkout.\n\nIt can be multiple lines. If there’s no note, this section is hidden.", 'woocommerce' ) );

        $address = [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'company'    => 'YayCommerce',
            'email'      => 'johndoe@gmail.com',
            'phone'      => '(910) 529-1147',
            'address_1'  => '7400 Edwards Rd',
            'city'       => 'Mayville, Michigan',
            'postcode'   => '7400',
            'country'    => 'US',
            'state'      => 'CA',
        ];
        $order->set_billing_address( $address );
        $order->set_shipping_address( $address );
        return $order;
    }

    public static function is_true( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
    }

    /**
     * Sanitize array data recursively
     *
     * @param mixed $data The data to sanitize.
     * @return mixed Sanitized data.
     */
    public static function sanitize_array_recursive( $data ) {
        if ( is_array( $data ) ) {
            $sanitized = [];
            foreach ( $data as $key => $value ) {
                // Recursively sanitize values
                $sanitized[ $key ] = self::sanitize_array_recursive( $value );
            }
            return $sanitized;
        } elseif ( is_string( $data ) ) {
            // Special handling for color values
            if ( self::is_color_value( $data ) ) {
                return sanitize_hex_color( $data );
            }
            // Regular text sanitization
            return sanitize_text_field( $data );
        }

        return $data;
    }

    /**
     * Check if a string is a color value
     *
     * @param string $value The value to check.
     * @return bool True if it's a color value.
     */
    public static function is_color_value( $value ) {
        // Check if it's a hex color (#RRGGBB or #RGB)
        return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value );
    }

    /**
     * Check if a WooCommerce feature is enabled
     *
     * @param string $feature_id The feature ID to check.
     * @return bool True if the feature is enabled, false otherwise.
     */
    public static function check_wc_feature( $feature_id = '' ) {
        if ( empty( $feature_id ) || ! class_exists( '\Automattic\WooCommerce\Internal\Features\FeaturesController' ) ) {
            return false;
        }

        try {
            $feature_controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\Features\FeaturesController::class );

            if ( ! $feature_controller ) {
                return false;
            }

            return $feature_controller->feature_is_enabled( $feature_id );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Get the URL of the email setting page
     *
     * @param string $email_id The email ID.
     * @return string The URL of the email setting page.
     */
    public static function yaymail_get_url_email_setting_page( $email_id ) {
        if ( ! is_string( $email_id ) ) {
            return '';
        }

        return add_query_arg(
            [
                'page'    => 'wc-settings',
                'tab'     => 'email',
                'section' => 'wc_email_' . $email_id,
            ],
            admin_url( 'admin.php' )
        );
    }

    public static function is_yaymail_lite_and_yay_wp_pro_active() {
        $is_yaymail_active    = function_exists( 'is_plugin_active' ) && is_plugin_active( 'yaymail/yaymail.php' );
        $is_yay_wp_pro_active = function_exists( 'YayMail\wp_mail_init' ) && class_exists( 'YaymailWpPluginAdapter', false ) && method_exists( 'YaymailWpPluginAdapter', 'is_licensed' );
        return $is_yaymail_active && $is_yay_wp_pro_active;
    }

    public static function get_plugin_work_info() {
        $plugin_work = [
            'yaymail'                 => false,
            'yay-wp-email-customizer' => false,
        ];

        if ( function_exists( '\YayMail\init' ) && function_exists( 'WC' ) ) {
            if ( class_exists( 'YaymailPluginAdapter', false ) && method_exists( 'YaymailPluginAdapter', 'is_licensed' ) ) {
                $plugin_work['yaymail'] = \YaymailPluginAdapter::is_licensed();
            } else {
                $plugin_work['yaymail'] = true;
            }
        }

        if ( function_exists( '\YayMail\wp_mail_init' ) ) {
            if ( class_exists( 'YaymailWpPluginAdapter', false ) && method_exists( 'YaymailWpPluginAdapter', 'is_licensed' ) ) {
                $plugin_work['yay-wp-email-customizer'] = \YaymailWpPluginAdapter::is_licensed();
            } else {
                $plugin_work['yay-wp-email-customizer'] = true;
            }
        }

        return $plugin_work;
    }

    public static function get_plugin_path() {
        return self::is_yaymail_lite_and_yay_wp_pro_active() ? YAYMAIL_WP_PLUGIN_PATH : YAYMAIL_PLUGIN_PATH;
    }

    public static function get_plugin_url() {
        return self::is_yaymail_lite_and_yay_wp_pro_active() ? YAYMAIL_WP_PLUGIN_URL : YAYMAIL_PLUGIN_URL;
    }
    public static function scope_css_block( $css, $scope_selector ) {
        $result = '';
        $length = strlen( $css );
        $offset = 0;

        while ( $offset < $length ) {
            $open_brace_pos = strpos( $css, '{', $offset );
            if ( false === $open_brace_pos ) {
                $result .= substr( $css, $offset );
                break;
            }

            $selector_text = trim( substr( $css, $offset, $open_brace_pos - $offset ) );
            $cursor        = $open_brace_pos + 1;
            $depth         = 1;

            while ( $cursor < $length && $depth > 0 ) {
                $char = $css[ $cursor ];
                if ( '{' === $char ) {
                    ++$depth;
                } elseif ( '}' === $char ) {
                    --$depth;
                }
                ++$cursor;
            }

            $body_text = substr( $css, $open_brace_pos + 1, $cursor - $open_brace_pos - 2 );
            $offset    = $cursor;

            if ( '' === $selector_text ) {
                continue;
            }

            if ( str_starts_with( $selector_text, '@media' ) || str_starts_with( $selector_text, '@supports' ) ) {
                $result .= $selector_text . '{' . self::scope_css_block( $body_text, $scope_selector ) . '}';
                continue;
            }

            if ( str_starts_with( $selector_text, '@' ) ) {
                $result .= $selector_text . '{' . $body_text . '}';
                continue;
            }

            $selector_list = array_filter(
                array_map( 'trim', explode( ',', $selector_text ) ),
                static function( $selector ) {
                    return '' !== $selector;
                }
            );

            if ( empty( $selector_list ) ) {
                continue;
            }

            $scoped_selectors = array_map(
                static function( $selector ) use ( $scope_selector ) {
                    if ( str_starts_with( $selector, $scope_selector ) ) {
                        return $selector;
                    }
                    return $scope_selector . ' ' . $selector;
                },
                $selector_list
            );

            $result .= implode( ', ', $scoped_selectors ) . '{' . $body_text . '}';
        }//end while

        return $result;
    }
}
