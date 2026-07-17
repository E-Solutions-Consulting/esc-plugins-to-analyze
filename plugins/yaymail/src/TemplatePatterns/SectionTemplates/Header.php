<?php
namespace YayMail\TemplatePatterns\SectionTemplates;

use YayMail\Abstracts\BaseSectionTemplate;
use YayMail\Utils\SingletonTrait;

/**
 * Header Elements
 */
class Header extends BaseSectionTemplate {

    use SingletonTrait;

    public const TYPE = 'header';

    private function __construct() {
        $this->id       = uniqid();
        $this->name     = __( 'Header', 'woocommerce' );
        $this->group    = 'section_template';
        $this->icon     = '<svg width="23" height="22" viewBox="0 0 23 22" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g clip-path="url(#clip0_504_6239)">
        <path fill-rule="evenodd" clip-rule="evenodd" d="M2.25 3.44293C2.25 2.50795 3.00795 1.75 3.94293 1.75H19.0571C19.992 1.75 20.75 2.50795 20.75 3.44293V18.5571C20.75 19.492 19.992 20.25 19.0571 20.25H3.94293C3.00795 20.25 2.25 19.492 2.25 18.5571V3.44293ZM3.94293 0.25C2.17953 0.25 0.75 1.67953 0.75 3.44293V18.5571C0.75 20.3205 2.17953 21.75 3.94293 21.75H19.0571C20.8205 21.75 22.25 20.3205 22.25 18.5571V3.44293C22.25 1.67953 20.8205 0.25 19.0571 0.25H3.94293ZM5.41553 4H17.5845C18.0901 4 18.5 4.44503 18.5 4.994V6.006C18.5 6.55497 18.0901 7 17.5845 7H5.41553C4.90989 7 4.5 6.55497 4.5 6.006V4.994C4.5 4.44503 4.90989 4 5.41553 4Z"/>
        </g>
        <defs>
        <clipPath id="clip0_504_6239">
        <rect width="21.5" height="21.5" transform="translate(0.75 0.25)"/>
        </clipPath>
        </defs>
        </svg>';
        $this->position = 10;
    }
}
