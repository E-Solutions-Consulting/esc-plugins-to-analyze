<?php

namespace YayMail\Utils;

use YayMail\Utils\SingletonTrait;

/**
 * StyleInline utility class
 *
 * Extends WC_Email to use style_inline function
 *
 * @method static StyleInline get_instance()
 */
class StyleInline extends \WC_Email {

    use SingletonTrait;

    /**
     * Convert CSS to inline styles for email
     *
     * @param string $content HTML content to process
     * @return string HTML content with inline styles
     */
    public function convert_style_inline( $content ) {
        if ( empty( $content ) || ! is_string( $content ) ) {
            return $content;
        }

        return $this->style_inline( $content );
    }
}
