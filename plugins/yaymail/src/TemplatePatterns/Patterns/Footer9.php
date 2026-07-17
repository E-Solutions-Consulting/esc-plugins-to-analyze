<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\ImageList;
use YayMail\Elements\Logo;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/** */
class Footer9 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'footer_9';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 90;
        $this->name     = __( 'Footer 9', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff',
                    'padding'                => [
                        'top'    => 40,
                        'bottom' => 10,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text' => '<p style="font-size: 16px"><span><b>Get the app</b></span></p>',
                                            'padding'   => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text' => '<p style="font-size:14px; margin: 7px 0px;"><span>Try the our mobile app to comment, replies, when you\'re on the go.</span></p>',
                                            'padding'   => [
                                                'top'    => 0,
                                                'bottom' => 13,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                    ImageList::get_object_data(
                                        [
                                            'number_column' => 2,
                                            'column_1' =>
                                            [
                                                'align'   => 'left',
                                                'width'   => 108,
                                                'padding' => [
                                                    'top'  => '0',
                                                    'right' => '10',
                                                    'bottom' => '0',
                                                    'left' => '0',

                                                ],
                                                'image'   => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-1.png',
                                            ],
                                            'column_2' =>
                                            [
                                                'align'   => 'left',
                                                'width'   => 108,
                                                'padding' => [
                                                    'top'  => '0',
                                                    'right' => '0',
                                                    'bottom' => '0',
                                                    'left' => '0',

                                                ],
                                                'image'   => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-2.png',
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
                                            'rich_text' => '<p style="display: grid; font-size:14px;; grid-template-columns: 1fr 1fr; gap: 55px; margin: 0;">
                                            <span style="padding-left: 10px">About us</span>
                                            <span>Facebook</span>
                                            </p>',
                                            'padding'   => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'left'   => 20,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text' => '<p style="display: grid; font-size:14px;;grid-template-columns: 1fr 1fr; gap: 55px; margin: 0;">
                                            <span style="padding-left: 10px">Contact us</span>
                                            <span>Instagram</span>
                                            </p>',
                                            'padding'   => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'left'   => 20,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text' => '<p style="display: grid; font-size:14px; grid-template-columns: 1fr 1fr; gap: 55px; margin: 0;">
                                            <span style="padding-left: 10px">Return Policy</span> 
                                            <span>Tiktok</span>
                                            </p>',
                                            'padding'   => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'left'   => 20,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text' => '<p style="display: grid; font-size:14px; grid-template-columns: 1fr 1fr; gap: 55px; margin: 0;">
                                            <span style="padding-left: 10px">Unsubcribe</span> 
                                            <span>Youtube</span>
                                            </p>',
                                            'padding'   => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'left'   => 20,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            Divider::get_object_data(
                [
                    'height'        => 1,
                    'width'         => 100,
                    'divider_color' => '#F1F2F5',
                    'padding'       => [
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 40,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            40,
                            [
                                'children' =>
                                [
                                    Logo::get_object_data(
                                        [
                                            'background_color' => '#ffffff',
                                            'align'   => 'left',
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => '157',
                                            'url'     => '#',
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
                            60,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 12px, margin: 0px; text-align: right">
                                            <span>&#169 2023 Made with love</span>
                                            </p>',
                                            'text_color' => '#77859B',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
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
