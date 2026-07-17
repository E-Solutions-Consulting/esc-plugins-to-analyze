<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Divider;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Header;
use YayMail\Utils\SingletonTrait;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Column;
use YayMail\Elements\Logo;

/**
 * Header6 Elements
 */
class Header6 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_6';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 60;
        $this->name     = __( 'Header 6', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color' => '#ffffff',
                    'vertical_align'   => 'middle',
                    'children'         => [
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
                                                'top'    => 0,
                                                'right'  => 10,
                                                'bottom' => 0,
                                                'left'   => 0,
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
                                            'rich_text' => '<p style="text-align: right; margin: 0;"><span style="font-size: 16px; color: #333439;">Contact customer service</span></p>
                                            <p style="text-align: right; margin: 0;"><span style="font-size: 16px;"><span style="color: #ffc900; font-weight: 500;">1-987-654-1234</span></span></p>',
                                            'padding'   => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 10,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'padding'          => [
                        'top'    => 30,
                        'right'  => 40,
                        'bottom' => 30,
                        'left'   => 40,
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
                    'rich_text'  => '<p style="margin: 0px; text-align: center;"><span style="font-size: 16px;"><strong>New Arrivals         New Arrivals</strong></span><strong style="font-size: 16px;"><strong>          </strong>Outlet</strong><strong style="font-size: 16px;"><strong>          </strong>Contact</strong></p>',
                    'padding'    => [
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                    'text_color' => '#333439',
                ]
            ),
        ];
    }
}