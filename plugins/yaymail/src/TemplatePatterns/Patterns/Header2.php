<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Logo;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Header;
use YayMail\Utils\SingletonTrait;

/**
 * Header2 Elements
 */
class Header2 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_2';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 20;
        $this->name     = __( 'Header 2', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                3,
                [
                    'background_color' => '#ffffff',
                    'children'         => [
                        Column::get_object_data(
                            32,
                            [
                                'children' => [
                                    Logo::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => 195,
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            5,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 18px; font-weight: 100;">|</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#C4C6CC',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            68,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 18px; margin: 0px;">A store where you can shop everything</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'padding'          => [
                        'top'    => 20,
                        'left'   => 30,
                        'bottom' => 10,
                        'right'  => 30,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 16px;"><span style="margin-right: 25px;">Support</span> <span style="margin-right: 25px;">Blog</span> <span style="margin-right: 25px;">FAQs</span></span></p>',
                    'padding'    => [
                        'top'    => 0,
                        'right'  => 30,
                        'bottom' => 20,
                        'left'   => 30,
                    ],
                    'text_color' => '#333439',
                ]
            ),
        ];
    }
}