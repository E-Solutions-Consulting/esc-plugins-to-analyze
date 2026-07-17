<?php

namespace YayMail;

use YayMail\Models\TemplateModel;
use YayMail\Utils\Helpers;

/**
 * GlobalHeaderFooter Class
 *
 * This is an YayMail element, not an email template. But its customizer page (for editing, saving, etc...) shares the same logic as email template customizer.
 *
 * @since 4.1.0
 * @method static GlobalHeaderFooter get_instance()
 */
class GlobalHeaderFooter {

    /**
     * Get global header and footer elements
     * Each result is an array of elements
     * If empty, it means the global header or footer is hidden or not enabled
     *
     * @param string|YayMailTemplate $template
     *
     * @return array
     */
    public static function get_elements( $template ) {

        $fallback_result = [
            'global_header_elements' => [],
            'global_footer_elements' => [],
        ];

        if ( ! self::is_global_header_footer_enabled() ) {
            return $fallback_result;
        }

        $template = self::get_template_from_input( $template );

        if ( empty( $template ) ) {
            return $fallback_result;
        }

        $global_header_footer_elements = TemplateModel::get_instance()->get_global_header_and_footer( $template->get_language() );

        if ( empty( $global_header_footer_elements ) ) {
            return $fallback_result;
        }

        if ( self::is_global_header_hidden( $template ) ) {
            $global_header_footer_elements['global_header_elements'] = [];
        }

        if ( self::is_global_footer_hidden( $template ) ) {
            $global_header_footer_elements['global_footer_elements'] = [];
        }

        return $global_header_footer_elements;
    }

    /**
     * Get template from input
     *
     * @param string|YayMailTemplate $template
     *
     * @return YayMailTemplate|null
     */
    private static function get_template_from_input( $template ) {

        if ( is_string( $template ) ) {
            $template = new YayMailTemplate( $template );
        }

        if ( ! ( $template instanceof YayMailTemplate ) ) {
            return null;
        }

        if ( empty( $template ) ) {
            return null;
        }

        return $template;
    }

    /**
     * Check if global header is hidden
     *
     * @param string|YayMailTemplate $template
     *
     * @return bool
     */
    public static function is_global_header_hidden( $template ) {
        $template = self::get_template_from_input( $template );

        if ( empty( $template ) ) {
            return true;
        }

        $global_header_settings = $template->get_global_header_settings();
        $hidden_value           = $global_header_settings['hidden'];

        // validate boolean
        return filter_var( $hidden_value, FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore
    }

    /**
     * Check if global footer is hidden
     *
     * @param string|YayMailTemplate $template
     *
     * @return bool
     */
    public static function is_global_footer_hidden( $template ) {
        $template = self::get_template_from_input( $template );

        if ( empty( $template ) ) {
            return true;
        }

        $global_footer_settings = $template->get_global_footer_settings();
        $hidden_value           = $global_footer_settings['hidden'];

        // validate boolean
        return filter_var( $hidden_value, FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore
    }

    /**
     * Get global header override heading content
     *
     * @param string|YayMailTemplate $template
     *
     * @return string|null
     */
    public static function get_global_header_override_heading_content( $template ) {

        if ( ! self::is_global_header_footer_enabled() ) {
            return null;
        }

        $template = self::get_template_from_input( $template );

        if ( empty( $template ) ) {
            return null;
        }

        $global_header_settings = $template->get_global_header_settings();

        if ( ! Helpers::is_true( $global_header_settings['content_override'] ) ) {
            return null;
        }

        return $global_header_settings['heading_content'] ?? YayMailTemplate::DEFAULT_DATA['global_header_settings']['heading_content'];
    }

    /**
     * Get global footer override content
     *
     * @param string|YayMailTemplate $template
     *
     * @return string|null
     */
    public static function get_global_footer_override_content( $template ) {

        if ( ! self::is_global_header_footer_enabled() ) {
            return null;
        }

        $template = self::get_template_from_input( $template );

        if ( empty( $template ) ) {
            return null;
        }

        $global_footer_settings = $template->get_global_footer_settings();

        if ( ! Helpers::is_true( $global_footer_settings['content_override'] ) ) {
            return null;
        }

        return $global_footer_settings['footer_content'] ?? YayMailTemplate::DEFAULT_DATA['global_footer_settings']['footer_content'];
    }

    /**
     * Check if element is in global header
     *
     * @param array                  $element
     * @param string|YayMailTemplate $template
     *
     * @return bool
     */
    public static function is_element_in_global_header( $element, $template ) {
        $elements = self::get_elements( $template );

        if ( empty( $elements['global_header_elements'] ) ) {
            return false;
        }

        return count(
            array_filter(
                $elements['global_header_elements'],
                function( $el ) use ( $element ) {
                    return $el['id'] === $element['id'];
                }
            )
        ) > 0;
    }

    /**
     * Check if element is in global footer
     *
     * @param array                  $element
     * @param string|YayMailTemplate $template
     *
     * @return bool
     */
    public static function is_element_in_global_footer( $element, $template ) {
        $elements = self::get_elements( $template );

        if ( empty( $elements['global_footer_elements'] ) ) {
            return false;
        }

        return count(
            array_filter(
                $elements['global_footer_elements'],
                function( $el ) use ( $element ) {
                    return $el['id'] === $element['id'];
                }
            )
        ) > 0;
    }

    public static function is_global_header_footer_enabled() {
        return yaymail_settings()['global_header_footer_enabled'] ?? false;
    }
}
