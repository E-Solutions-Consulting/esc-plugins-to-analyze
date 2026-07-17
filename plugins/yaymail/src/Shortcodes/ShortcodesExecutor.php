<?php

namespace YayMail\Shortcodes;

/**
 * Class declaration
 */
class ShortcodesExecutor {

    private $shortcodes = [];

    private $data = [
        'is_sample' => true,
    ];

    public function __construct( $shortcodes = [], $data = [] ) {
        if ( ! isset( $shortcodes ) || ! is_array( $shortcodes ) ) {
            return;
        }

        /**
         * Extra shortcodes: order meta...
         */
        $this->shortcodes = apply_filters( 'yaymail_extra_shortcodes', $shortcodes, $data );

        if ( isset( $data ) && is_array( $data ) ) {
            $this->data = $data;
        }

        $this->initialize_shortcodes();
    }

    public function initialize_shortcodes() {
        if ( empty( $this->shortcodes ) ) {
            return;
        }

        foreach ( $this->shortcodes as $shortcode_information ) {
            $callback      = isset( $shortcode_information['callback'] ) ? $shortcode_information['callback'] : '';
            $callback_args = isset( $shortcode_information['callback_args'] ) ? $shortcode_information['callback_args'] : '';

            $data = ! empty( $callback_args ) && is_array( $callback_args ) ? array_merge( $this->data, $callback_args ) : $this->data;

            if ( is_callable( $callback ) ) {
                add_shortcode(
                    $shortcode_information['name'],
                    function( $shortcode_atts ) use ( $callback, $data ) {
                        return call_user_func( $callback, $data, $shortcode_atts );
                    }
                );
            }
        }
    }

    public function get_shortcodes_content() {
        if ( empty( $this->shortcodes ) ) {
            return [];
        }

        $result = [];

        foreach ( $this->shortcodes as $shortcode_information ) {
            $shortcode_name = '[' . $shortcode_information['name'] . ']';
            $result[]       = array_merge(
                $shortcode_information,
                [
                    'content' => do_shortcode( $shortcode_name ),
                ]
            );
        }

        return $result;
    }
}
