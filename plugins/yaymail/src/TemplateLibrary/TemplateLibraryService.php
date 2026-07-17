<?php

namespace YayMail\TemplateLibrary;

use YayMail\Abstracts\BaseTemplate;
use YayMail\Models\LibraryTemplateModel;
use YayMail\Utils\SingletonTrait;

/**
 * Service responsible for registering and exposing code-defined email templates
 * for the Template Library feature.
 */
class TemplateLibraryService {
    use SingletonTrait;

    /**
     * @var BaseTemplate[]
     */
    protected $templates = [];

    /**
     * Constructor.
     *
     * @return void
     */
    protected function __construct() {
    }

    /**
     * Programmatically register a template class.
     *
     * @param BaseTemplate $template Template instance.
     *
     * @return void
     */
    public function register( BaseTemplate $template ) {
        if ( ! $template instanceof BaseTemplate ) {
            return;
        }

        if ( in_array( $template, $this->templates, true ) ) {
            return;
        }

        $template_key = $template->get_id();

        $this->templates[ $template_key ] = $template;
    }

    /**
     * Get list of template summaries for given email type
     *
     * @param string $email_type YayMail email/template name. Example: 'new_order'.
     *
     * @return array[]
     */
    public function get_list( $email_type ) {
        $results = [];

        foreach ( $this->templates as $template ) {
            if ( $template->get_email_type() !== $email_type ) {
                continue;
            }

            $results[] = $template->get_template_data();
        }

        usort(
            $results,
            function( $a, $b ) {
                $pos_a = isset( $a['position'] ) ? (int) $a['position'] : 10;
                $pos_b = isset( $b['position'] ) ? (int) $b['position'] : 10;

                if ( $pos_a === $pos_b ) {
                    return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
                }

                return $pos_a - $pos_b;
            }
        );

        return $results;
    }

    /**
     * Built-in + user-saved templates for the library modal.
     * Order: saved (newest first via DB query), then builtin (by position).
     *
     * @param string $email_type Email type slug.
     *
     * @return array[]
     */
    public function get_merged_list( $email_type ) {
        $builtin = $this->get_list( $email_type );

        $saved = LibraryTemplateModel::list_by_email_type( $email_type );

        $saved = array_map(
            function( $template ) {
                $template['source'] = 'saved';
                return $template;
            },
            $saved
        );

        return array_merge( $saved, $builtin );
    }

    /**
     * Email types eligible for scope "all" bulk save.
     * Uses registered YayMail emails (same pool as editable customizer emails).
     *
     * @return string[]
     */
    public function get_supported_email_types_for_library() {
        $types = [];

        if ( ! function_exists( 'yaymail_get_emails' ) ) {
            return $types;
        }

        foreach ( yaymail_get_emails() as $email ) {
            $template_id = $email->get_id();
            if ( ! self::is_library_bulk_email_type( $template_id ) ) {
                continue;
            }
            $types[] = $template_id;
        }

        return $types;
    }

    /**
     * @param string|null $template_id Template id.
     *
     * @return bool
     */
    private static function is_library_bulk_email_type( $template_id ) {
        if ( empty( $template_id ) || ! is_string( $template_id ) ) {
            return false;
        }

        if ( 0 === strpos( $template_id, 'pattern_' ) ) {
            return false;
        }

        if ( 0 === strpos( $template_id, 'wp-core-' ) ) {
            return false;
        }

        return true;
    }
}