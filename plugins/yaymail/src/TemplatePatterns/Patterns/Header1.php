<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Logo;
use YayMail\TemplatePatterns\SectionTemplates\Header;
use YayMail\Utils\SingletonTrait;

/**
 * Header1 Elements
 */
class Header1 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_1';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 10;
        $this->name     = __( 'Header 1', 'yaymail' );
        $this->elements = [
            Logo::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'align'            => 'center',
                    'src'              => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                    'width'            => '195',
                    'url'              => '#',
                    'padding'          => [
                        'top'    => 30,
                        'right'  => 40,
                        'bottom' => 30,
                        'left'   => 40,
                    ],
                ]
            ),
        ];
    }
}
