<?php
namespace YayMail\TemplatePatterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Utils\SingletonTrait;

/**
 * Class PatternService
 */
class PatternService {

    use SingletonTrait;

    protected $patterns = [];

    /**
     * @param BasePattern $section_template_instance SectionTemplate object
     */
    public function register( BasePattern $pattern_instance ) {
        if ( ! $pattern_instance instanceof BasePattern ) {
            return;
        }

        $registered_patterns = array_map(
            function( $item ) {
                return $item->get_type();
            },
            $this->patterns
        );

        if ( in_array( $pattern_instance->get_type(), $registered_patterns, true ) ) {
            return;
        }

        $this->patterns[] = $pattern_instance;

        $registered_sections = SectionTemplateService::get_instance()->get_list();

        foreach ( $registered_sections as $section ) {
            if ( $section->get_type() === $pattern_instance->get_section() ) {

                $section->add_pattern( $pattern_instance );
            }
        }

    }

    public function get_list() {
        return $this->patterns;
    }

    public function get_list_data() {
        return array_map(
            function( BasePattern $item ) {
                return $item->get_raw_data();
            },
            $this->patterns
        );
    }
}
