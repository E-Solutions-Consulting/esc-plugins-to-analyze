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
 * Header3 Elements
 */
class Header3 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_3';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 30;
        $this->name     = __( 'Header 3', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color'       => '#ffffff',
                    'inner_background_color' => '#fffbf1',
                    'vertical_align'         => 'middle',
                    'inner_border_radius'    => [
                        'top_left'     => 10,
                        'top_right'    => 10,
                        'bottom_left'  => 10,
                        'bottom_right' => 10,
                    ],
                    'padding'                => [
                        'top'    => 25,
                        'right'  => 25,
                        'bottom' => 25,
                        'left'   => 25,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Logo::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'align'   => 'left',
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => 195,
                                            'padding' => [
                                                'top'    => 20,
                                                'right'  => 10,
                                                'bottom' => 20,
                                                'left'   => 20,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                            'rich_text'  => '<p style="text-align: right;"><span style="font-size: 16px;"><span style="margin-right: 15px;">Support</span> <span style="margin-right: 15px;">Blog</span> FAQs</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 30,
                                                'bottom' => 0,
                                                'left'   => 30,
                                            ],
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