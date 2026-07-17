<?php

namespace YayMail\TemplatePatterns;

use YayMail\TemplatePatterns\PatternService;
use YayMail\Utils\SingletonTrait;

/**
 * @method static PatternLoaders get_instance()
 */
class PatternsLoader {
    use SingletonTrait;

    /**
     * @var PatternService
     */
    public $service;

    private function __construct() {

        $this->service = PatternService::get_instance();

        $dir = new \DirectoryIterator( YAYMAIL_PLUGIN_PATH . '/src/TemplatePatterns/Patterns' );
        foreach ( $dir as $fileinfo ) {
            if ( ! $fileinfo->isDot() ) {
                $file_name  = $fileinfo->getFilename();
                $class_name = basename( $file_name, '.php' );
                $class      = 'YayMail\\TemplatePatterns\\Patterns\\' . $class_name;
                if ( class_exists( $class ) ) {
                    $this->service->register( $class::get_instance() );
                }
            }
        }

        do_action( 'yaymail_register_patterns', $this->service );
    }

}
