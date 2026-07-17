<?php

use YayMail\Models\SettingModel;
use YayMail\Utils\TemplateHelpers;
use YayMail\Constants\TemplatesData;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\ElementsLoader;
use YayMail\YayMailEmails;
use YayMail\Utils\Logger;

if ( ! function_exists( 'yaymail_get_emails' ) ) {

    /**
     * Get all supported Emails
     *
     * @return BaseEmail[]
     */
    function yaymail_get_emails() {
        $yaymail_emails     = YayMailEmails::get_instance()->get_emails();
        $emails_default     = [];
        $emails_third_party = [];

        foreach ( $yaymail_emails as $email ) {
            if ( in_array( $email->get_id(), TemplatesData::WOO_DEFAULT_EMAIL_IDS, true ) ) {
                $emails_default[] = $email;
            } else {
                $emails_third_party[] = $email;
            }
        }
        $sorted_emails = array_merge( $emails_default, $emails_third_party );
        return $sorted_emails;
    }
}//end if


if ( ! function_exists( 'yaymail_get_email' ) ) {

    /**
     * Get email by email id
     *
     * @param string $email_id
     *
     * @return null|BaseEmail Return null when not found
     */
    function yaymail_get_email( $email_id ) {
        $emails = yaymail_get_emails();

        $find_email = null;

        foreach ( $emails as $email ) {
            if ( $email_id === $email->get_id() ) {
                $find_email = $email;
                break;
            }
        }

        return $find_email;
    }
}//end if

if ( ! function_exists( 'yaymail_is_wc_installed' ) ) {
    function yaymail_is_wc_installed() {
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        $plugin_work = \YayMail\Utils\Helpers::get_plugin_work_info();
        return $plugin_work['yaymail'];
    }
}

if ( ! function_exists( 'yaymail_is_wp_mail_installed' ) ) {
    function yaymail_is_wp_mail_installed() {
        return defined( 'YAYMAIL_WP_VERSION' );
    }
}

if ( ! function_exists( 'yaymail_version' ) ) {
    function yaymail_version() {
        if ( defined( 'YAYMAIL_VERSION' ) ) {
            return YAYMAIL_VERSION;
        }
        return false;
    }
}



if ( ! function_exists( 'yaymail_settings' ) ) {
    function yaymail_settings() {
        global $yaymail_unsaved_settings;
        if ( ! empty( $yaymail_unsaved_settings ) ) {
            foreach ( $yaymail_unsaved_settings as $key => $value ) {
                if ( 'true' === $value ) {
                    $yaymail_unsaved_settings[ $key ] = true;
                }
                if ( 'false' === $value ) {
                    $yaymail_unsaved_settings[ $key ] = false;
                }
            }
            return $yaymail_unsaved_settings;
        }
        return SettingModel::get_instance()::find_all();
    }
}

if ( ! function_exists( 'yaymail_get_content' ) ) {
    function yaymail_get_content( $path, $args = [], $root = YAYMAIL_PLUGIN_PATH ) {

        if ( empty( $path ) ) {
            return '';
        }

        $path = $root . $path;

        if ( $path === false || ! file_exists( $path ) ) {
            return '';
        }

        // TODO: do later
        ob_start();
        include $path;
        // nosemgrep
        $html = ob_get_contents();
        ob_end_clean();
        return yaymail_kses_post( $html );
    }
}//end if

if ( ! function_exists( 'yaymail_kses_post' ) ) {
    /**
     * The function yaymail_kses_post sanitizes HTML content using the allowed HTML tags defined in the
     * TemplateHelpers class.
     *
     * @param html The  parameter is the input string that you want to sanitize and allow only
     * certain HTML tags and attributes.
     *
     * @return the result of the wp_kses() function, which is the sanitized version of the
     * parameter using the  array as the allowed HTML tags and attributes.
     */
    function yaymail_kses_post( $html ) {
        $allowed_html = TemplateHelpers::wp_kses_allowed_html();
        return wp_kses( $html, $allowed_html );
    }
}


if ( ! function_exists( 'yaymail_kses_post_e' ) ) {
    /**
     * The function `yaymail_kses_post_e` echoes the HTML content after sanitizing it using the allowed
     * HTML tags defined in the `TemplateHelpers::wp_kses_allowed_html()` method.
     *
     * @param html The  parameter is the content that you want to sanitize and filter using the
     * wp_kses() function. It could be any HTML content that you want to ensure is safe and free from
     * any potentially harmful or malicious code.
     */
    function yaymail_kses_post_e( $html ) {
        if ( ! empty( $html ) ) {
            $allowed_html = TemplateHelpers::wp_kses_allowed_html();
            echo wp_kses( $html, $allowed_html );
        } else {
            echo '';
        }
    }
}

if ( ! function_exists( 'yaymail_get_text_align' ) ) {
    function yaymail_get_text_align() {
        $container_direction = yaymail_get_email_direction();

        if ( 'rtl' === $container_direction ) {
            return 'right';
        }

        return is_rtl() ? 'right' : 'left';
    }
}

if ( ! function_exists( 'yaymail_get_default_elements' ) ) {

    /**
     * Get default elements data of given email
     *
     * @param string $email_id
     *
     * @return array Return empty string when not found email
     */
    function yaymail_get_default_elements( $email_id ) {
        $find_email = yaymail_get_email( $email_id );

        if ( ! $find_email ) {
            return [];
        }

        return $find_email->get_default_elements();
    }
}

if ( ! function_exists( 'yaymail_get_all_elements' ) ) {

    /**
     * Get all registered elements
     *
     * @return BaseElement[]
     */
    function yaymail_get_all_elements() {
        return ElementsLoader::get_instance()->get_all();
    }
}

if ( ! function_exists( 'yaymail_get_email_available_elements' ) ) {

    /**
     * Get all available elements of given email
     *
     * @param string $email_id
     *
     * @return BaseElement[]
     */
    function yaymail_get_email_available_elements( $email_id ) {
        $find_email = yaymail_get_email( $email_id );

        if ( ! $find_email ) {
            return [];
        }

        return $find_email->get_elements();
    }
}

if ( ! function_exists( 'yaymail_get_email_elements_data' ) ) {

    /**
     * Get all elements data of given email
     *
     * @param string $email_id
     *
     * @return array
     */
    function yaymail_get_email_elements_data( $email_id ) {
        $find_email = yaymail_get_email( $email_id );

        if ( ! $find_email ) {
            return [];
        }

        $all_elements = yaymail_get_all_elements();
        $result       = [];

        foreach ( $all_elements as $element ) {
            $element_data              = merge_extra_element_attributes( $element->get_data() );
            $element_data['available'] = false;
            if ( $element->is_available_in_email( $find_email ) ) {
                $element_data['available'] = true;
            }

            $result[] = $element_data;

            /**
             * Add columns element
             */
            if ( ColumnLayout::get_type() === $element::get_type() ) {
                foreach ( [ 2, 3, 4 ] as $col ) {
                    $child_element_data              = merge_extra_element_attributes( $element->get_data( $col ) );
                    $child_element_data['available'] = $element_data['available'];
                    $result[]                        = $child_element_data;
                }
            }
        }//end foreach

        return $result;
    }

    /**
     * Merge extra attributes into element
     *
     * @param array $element_data
     *
     * @return array
     */
    function merge_extra_element_attributes( $element ) {
        $extra_attributes = apply_filters( 'yaymail_extra_element_attributes', [], $element['type'] );
        if ( empty( $extra_attributes ) ) {
            return $element;
        }

        $data = &$element['data'];
        foreach ( $extra_attributes as $key => $value ) {
            if ( isset( $data[ $key ] ) || ! isset( $value ) ) {
                continue;
            }
            $data[ $key ] = $value;
        }

        return $element;
    }
}//end if

if ( ! function_exists( 'yaymail_get_element' ) ) {

    /**
     * Get element by given type
     *
     * @param string $element_type
     *
     * @return null|BaseElement Return null when not found element
     */
    function yaymail_get_element( $element_type ) {

        $elements = yaymail_get_all_elements();

        $find_element = null;

        foreach ( $elements as $element ) {
            if ( $element::get_type() === $element_type ) {
                $find_element = $element;
                break;
            }
        }

        return $find_element;
    }
}//end if

if ( ! function_exists( 'yaymail_get_email_shortcodes' ) ) {

    /**
     * Get all shortcodes of given email
     *
     * @param string $email_id
     *
     * @return array
     */
    function yaymail_get_email_shortcodes( $email_id ) {
        $find_email = yaymail_get_email( $email_id );

        if ( ! $find_email ) {
            return [];
        }

        return $find_email->get_shortcodes();
    }
}

if ( ! function_exists( 'yaymail_get_logger' ) ) {

    /**
     * Get logger instance
     */
    function yaymail_get_logger( $message, $log_type = 'error', $additional_data = null ) {
        $logger = new Logger();
        $logger->log_exception_message( new \Exception( $message ), $log_type, $additional_data );
    }
}

if ( ! function_exists( 'yaymail_get_wc_email_settings' ) ) {
    /**
     * Get WooCommerce email settings
     *
     * @return array An object of WooCommerce email settings which has these properties:
     *   - 'header_image': The header image URL.
     *   - 'base_color': The base color.
     *   - 'background_color': The background color.
     *   - 'body_background_color': The body background color.
     *   - 'body_text_color': The body text color.
     *   - 'footer_text': The footer text.
     *   - 'footer_text_color': The footer text color.
     */
    function yaymail_get_wc_email_settings() {
        return [
            'header_image'          => get_option( 'woocommerce_email_header_image', '' ),
            'base_color'            => get_option( 'woocommerce_email_base_color', '#873EFF' ),
            'background_color'      => get_option( 'woocommerce_email_background_color', '#f7f7f7' ),
            'body_background_color' => get_option( 'woocommerce_email_body_background_color', '#ffffff' ),
            'body_text_color'       => get_option( 'woocommerce_email_body_text_color', '#3c3c3c' ),
            'footer_text'           => get_option( 'woocommerce_email_footer_text', '[yaymail_site_name] &mdash; Built with WooCommerce' ),
            'footer_text_color'     => get_option( 'woocommerce_email_footer_text_color', '#3c3c3c' ),
        ];
    }
}//end if


if ( ! function_exists( 'yaymail_get_email_direction' ) ) {
    function yaymail_get_email_direction() {
        $yaymail_settings = yaymail_settings();
        return isset( $yaymail_settings['direction'] ) && 'rtl' === $yaymail_settings['direction'] ? 'rtl' : 'ltr';
    }
}//end if

/**
 * Get email recipient zone
 *
 * @param \WC_Email $email
 * @since 4.0.3
 *
 * @return string
 */
if ( ! function_exists( 'yaymail_get_email_recipient_zone' ) ) {
    function yaymail_get_email_recipient_zone( $email ) {
        $is_customer_email = $email instanceof \WC_Email && method_exists( $email, 'is_customer_email' ) ? $email->is_customer_email() : true;
        if ( $is_customer_email ) {
            return __( 'Customer', 'woocommerce' );
        }

        $recipient = '';
        if ( $email instanceof \WC_Email ) {
            $recipient = ! empty( $email->recipient ) ? $email->recipient : $email->get_recipient();
            if ( empty( $recipient ) ) {
                $recipient = __( 'Recipient', 'yaymail' );
            }
        }

        $recipients = array_map(
            function( $email_recipient ) {
                $recipient_user = get_user_by( 'email', $email_recipient );
                if ( $recipient_user && user_can( $recipient_user, 'manage_options' ) ) {
                        return __( 'Admin', 'woocommerce' );
                }
                if ( empty( $email_recipient ) ) {
                    return __( 'Recipient', 'yaymail' );
                }
                return $email_recipient;
            },
            explode( ',', $recipient )
        );
        $recipients = array_unique( $recipients );
        return implode( ', ', $recipients );
    }
}//end if

if ( ! function_exists( 'yaymail_get_template' ) ) {
    function yaymail_get_template( $template_name, $template_path = '', $default_path = '' ) {
        if ( ! $template_path ) {
            $template_path = 'yaymail';
        }

        $template = locate_template(
            [
                trailingslashit( $template_path ) . $template_name,
                $template_name,
            ]
        );

        if ( ! $template ) {
            $template = $default_path . $template_name;
        }

        return $template;
    }
}
