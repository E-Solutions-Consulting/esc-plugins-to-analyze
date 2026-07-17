<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Intro;
use YayMail\Utils\SingletonTrait;

/**
 * Intro 3 Pattern
 */
class Intro3 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'intro_3';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Intro::TYPE;
        $this->position = 10;
        $this->name     = __( 'Intro 3', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                3,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 15,
                        'bottom' => 15,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-intro-3-bg.png',
                        'position'   => 'center_center',
                        'x_position' => 52,
                        'y_position' => 50,
                        'repeat'     => 'no-repeat',
                        'size'       => 'cover',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            15,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-3-img-4.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 25,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'left',
                                            'background_color' => '#ffffff00',
                                            'width'   => 60,
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            55,
                            [
                                'children' => [

                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 24px; text-align: left; margin: 0px;"><span style="font-size: 24px;">Let’s Go Shopping!!! Grab these while they\'re at their lowest price of the year.</span></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 20,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#FFFFFF',
                                            'rich_text'  => '<p><a style="text-decoration: underline; font-size: 15px; font-weight: 500; color: #333439 !important; text-underline-offset: 4px;" href="[yaymail_site_url]">Shop Now <img style="margin-left: 3px; width: 12px;" src="https://images.wpbrandy.com/uploads/yaymail-offer-2-img.png" alt="arrow_right" /></a></p>',
                                            'padding'    => [
                                                'top'    => 25,
                                                'bottom' => 25,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            30,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-intro-3-img.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'right',
                                            'background_color' => '#ffffff00',
                                            'width'   => 242,
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
        ];
    }
}
