<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Divider;
use YayMail\Elements\Logo;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Header;
use YayMail\Utils\SingletonTrait;

/**
 * Header5 Elements
 */
class Header5 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_5';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 50;
        $this->name     = __( 'Header 5', 'yaymail' );
        $this->elements = [
            Text::get_object_data(
                [
                    'rich_text'        => '<p style="margin: 0px; text-align: center;"><span><span style="color: #ffffff;">YOUR SHOPPING LIST FOR THE UPCOMING SEASON!</span> <strong><span style="color: #333439;">EXPLORE NOW</span></strong></span></p>',
                    'background_color' => '#ffc900',
                    'padding'          => [
                        'top'    => 10,
                        'right'  => 20,
                        'bottom' => 10,
                        'left'   => 20,
                    ],
                ]
            ),
            Logo::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                    'width'   => 195,
                    'align'   => 'center',
                    'padding' => [
                        'top'    => 20,
                        'right'  => 20,
                        'bottom' => 20,
                        'left'   => 20,
                    ],
                ]
            ),
            Divider::get_object_data(
                [
                    'height'        => 1,
                    'width'         => 100,
                    'divider_color' => '#f1f2f5',
                    'padding'       => [
                        'top'    => 0,
                        'right'  => 0,
                        'bottom' => 0,
                        'left'   => 0,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="margin: 0px; text-align: center;"><strong><span style="font-size: 18px;">New Arrivals        New Arrivals</span></strong><strong><span style="font-size: 18px;">         </span></strong><strong><span style="font-size: 18px;">Outlet</span></strong><strong><span style="font-size: 18px;">         </span></strong><strong><span style="font-size: 18px;">Contact</span></strong></p>',
                    'padding'    => [
                        'top'    => 15,
                        'right'  => 20,
                        'bottom' => 15,
                        'left'   => 20,
                    ],
                    'text_color' => '#333439',
                ]
            ),
        ];
    }
}