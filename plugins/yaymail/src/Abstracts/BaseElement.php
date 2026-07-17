<?php
namespace YayMail\Abstracts;

/**
 * BaseElement Class
 */
abstract class BaseElement {

    protected static $type = null;

    /**
     * List of available email IDs
     *
     * @var array
     */
    public $available_email_ids = [ YAYMAIL_WITH_ORDER_EMAILS ];

    public static $icon = '<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
    viewBox="0 0 24 24" style="enable-background:new 0 0 24 24;" xml:space="preserve">
    <title>Third party elements</title>
    <path d="M18,2h-7.6c-0.1,0-0.1,0-0.2,0.1l0,0c-0.1,0-0.1,0.1-0.2,0.1L4.6,7.4c0,0-0.1,0.1-0.1,0.2l0,0c0,0.1,0,0.1-0.1,0.2l0,0
    c0,0.1,0,0.1,0,0.2v12.5C4.4,21.3,5.1,22,6,22h12c0.9,0,1.6-0.7,1.6-1.6l0,0V3.6C19.6,2.7,18.9,2,18,2L18,2z M9.8,4.5v2.7H7L9.8,4.5
    z M18.1,20.3c0,0.1-0.1,0.2-0.2,0.2c0,0,0,0,0,0H6.1c-0.1,0-0.2-0.1-0.2-0.2c0,0,0,0,0,0V8.7h4.6c0.4,0,0.7-0.3,0.8-0.7V3.5h6.6
    c0.1,0,0.2,0.1,0.2,0.2c0,0,0,0,0,0L18.1,20.3z M7.4,11.6c0-0.4,0.3-0.7,0.7-0.7c0,0,0,0,0,0h4.8c0.4,0,0.8,0.2,0.8,0.7
    c0,0.4-0.2,0.8-0.7,0.8c-0.1,0-0.1,0-0.2,0H8.1C7.7,12.4,7.4,12.1,7.4,11.6C7.4,11.7,7.4,11.7,7.4,11.6z M16.6,14.6
    c0,0.4-0.3,0.7-0.8,0.8H8.1c-0.4,0-0.8-0.3-0.8-0.8s0.3-0.8,0.8-0.8h7.7C16.3,13.9,16.6,14.2,16.6,14.6z M8.6,17.6
    c0,0.4-0.3,0.7-0.7,0.7S7.2,18,7.2,17.6s0.3-0.7,0.7-0.7S8.6,17.2,8.6,17.6z M11,17.6c0,0.4-0.3,0.7-0.7,0.7S9.6,18,9.6,17.6
    c0-0.4,0.3-0.7,0.7-0.7C10.7,16.9,11,17.2,11,17.6L11,17.6z M13.4,17.6c0,0.4-0.2,0.7-0.6,0.8c-0.4,0-0.7-0.2-0.8-0.6
    c0-0.1,0-0.1,0-0.2c0-0.4,0.2-0.7,0.6-0.8c0.4,0,0.7,0.2,0.8,0.6C13.4,17.5,13.4,17.5,13.4,17.6z"/>
    </svg>';

    abstract public static function get_data( $attributes = [] );
    /**
     * Render list elements
     *
     * @param $element Element data
     * @param $args includes
     * $render_data
     * $template
     * $is_nested
     */
    public static function get_layout( $element_data, $args ) {
        $file_name = str_replace( '_', '-', static::$type );
        $path      = 'templates/elements/' . $file_name . '.php';
        return apply_filters( 'yaymail_' . static::$type . '_layout', yaymail_get_content( $path, array_merge( [ 'element' => $element_data ], $args ) ), $element_data, $args );
    }

    /**
     * Check available in email.
     *
     * @param string|array $email_ids The email ID(s) to be set as available. Special placeholders such as
     *     `NON_ORDER_EMAILS`, `WITH_ORDER_EMAILS`, and `GLOBAL_HEADER_FOOTER_ID` can be used to include predefined sets
     *     of email IDs or the global header/footer ID.
     *     If provided as a string, it represents a single email ID.
     *     If provided as an array, it represents multiple email IDs.
     * @return void
     */
    public function is_available_in_email( $email ) {

        if ( ! ( $email instanceof BaseEmail ) && ! is_string( $email ) ) {
            return false;
        }

        if ( is_string( $email ) ) {

            $email = yaymail_get_email( $email );

            if ( empty( $email ) || ! $email instanceof BaseEmail ) {
                return false;
            }
        }

        $available_email_ids = (array) $this->available_email_ids;
        $available_email_ids = apply_filters( 'yaymail_element_available_email_ids', $available_email_ids, $this );

        return ( in_array( YAYMAIL_ALL_EMAILS, $available_email_ids, true ) )
        || ( in_array( YAYMAIL_NON_ORDER_EMAILS, $available_email_ids, true ) && in_array( YAYMAIL_NON_ORDER_EMAILS, $email->email_types, true ) )
        || ( in_array( YAYMAIL_WITH_ORDER_EMAILS, $available_email_ids, true ) && in_array( YAYMAIL_WITH_ORDER_EMAILS, $email->email_types, true ) )
        || ( in_array( YAYMAIL_GLOBAL_HEADER_FOOTER_ID, $available_email_ids, true ) && in_array( YAYMAIL_GLOBAL_HEADER_FOOTER_ID, $email->email_types, true ) )
        || in_array( $email->get_id(), $available_email_ids, true );
    }

    public static function get_type() {
        return static::$type;
    }

    /**
     * Converts an element's data to use default values.
     *
     * This function iterates through the 'data' array of an element, replacing each value with its 'default_value'.
     * It then updates the element's 'data' with the formatted data and returns the modified element.
     *
     * @param array $element The element to be modified.
     * @return array The modified element with default values applied.
     */
    public static function reduce_new_element( $element ) {
        $data           = $element['data'];
        $formatted_data = [];
        foreach ( $data as $key => $value ) {
            $fallback_value = $value;
            if ( isset( $value['value_path'] ) ) {
                $fallback_value = '';
            }
            $formatted_data[ $key ] = $value['default_value'] ?? $fallback_value;
        }

        $element['data'] = $formatted_data;
        return $element;
    }

    public static function merge_common_data( $element, $attributes = [] ) {
        if ( empty( $attributes['custom_css_classes'] ) ) {
            return $element;
        }

        $element['data']['custom_css_classes'] = $attributes['custom_css_classes'];

        return $element;
    }

    public static function get_object_data( ...$args ) {
        return self::reduce_new_element( static::get_data( ...$args ) );
    }
}
