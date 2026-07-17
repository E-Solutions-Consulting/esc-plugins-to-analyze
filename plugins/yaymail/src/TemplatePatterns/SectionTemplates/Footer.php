<?php
namespace YayMail\TemplatePatterns\SectionTemplates;

use YayMail\Abstracts\BaseSectionTemplate;
use YayMail\Utils\SingletonTrait;

/**
 * Footer Elements
 */
class Footer extends BaseSectionTemplate {

    use SingletonTrait;

    public const TYPE = 'footer';

    private function __construct() {
        $this->id       = uniqid();
        $this->name     = __( 'Footer', 'woocommerce' );
        $this->group    = 'section_template';
        $this->icon     = '
        <svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd" clip-rule="evenodd" d="M2 3.0093C2 2.17547 2.67595 1.49951 3.50979 1.49951H17.4908C18.3246 1.49951 19.0006 2.17547 19.0006 3.0093V16.9903C19.0006 17.8241 18.3246 18.5001 17.4908 18.5001H3.50979C2.67595 18.5001 2 17.8241 2 16.9903V3.0093ZM3.50979 -0.000488281C1.84753 -0.000488281 0.5 1.34704 0.5 3.0093V16.9903C0.5 18.6526 1.84753 20.0001 3.50979 20.0001H17.4908C19.1531 20.0001 20.5006 18.6526 20.5006 16.9903V3.0093C20.5006 1.34704 19.1531 -0.000488281 17.4908 -0.000488281H3.50979ZM4.86924 13.6998H16.1259C16.5936 13.6998 16.9728 14.1114 16.9728 14.6193V15.5554C16.9728 16.0632 16.5936 16.4749 16.1259 16.4749H4.86924C4.40152 16.4749 4.02236 16.0632 4.02236 15.5554V14.6193C4.02236 14.1114 4.40152 13.6998 4.86924 13.6998Z" />
        </svg>
        ';
        $this->position = 11;
    }
}
