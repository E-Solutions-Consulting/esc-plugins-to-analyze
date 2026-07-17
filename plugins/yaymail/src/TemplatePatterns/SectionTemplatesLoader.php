<?php

namespace YayMail\TemplatePatterns;

use YayMail\TemplatePatterns\SectionTemplateService;
use YayMail\Utils\SingletonTrait;

/**
 * @method static SectionTemplatesLoader get_instance()
 */
class SectionTemplatesLoader {

    use SingletonTrait;

    /**
     * @var SectionTemplateService
     */
    public $service;

    private function __construct() {

        $this->service = SectionTemplateService::get_instance();

        $dir = new \DirectoryIterator( YAYMAIL_PLUGIN_PATH . '/src/TemplatePatterns/SectionTemplates' );
        foreach ( $dir as $fileinfo ) {
            if ( ! $fileinfo->isDot() ) {
                $file_name  = $fileinfo->getFilename();
                $class_name = basename( $file_name, '.php' );
                $class      = 'YayMail\\TemplatePatterns\\SectionTemplates\\' . $class_name;
                if ( __CLASS__ === $class ) {
                    continue;
                }
                if ( class_exists( $class ) ) {
                    $this->service->register( $class::get_instance() );
                }
            }
        }

        do_action( 'yaymail_register_template_sections', $this->service );
    }
}
