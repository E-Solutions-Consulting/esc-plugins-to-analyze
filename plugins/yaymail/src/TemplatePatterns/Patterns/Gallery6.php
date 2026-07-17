<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Gallery;
use YayMail\Utils\SingletonTrait;

/** */
class Gallery6 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_6';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 15;
        $this->name     = __( 'Gallery 6', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 10,
                        'bottom' => 0,
                        'left'   => 0,
                        'right'  => 0,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            45,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-instagram-icon-1.png',
                                            'width'   => 30,
                                            'align'   => 'right',
                                            'padding' => [
                                                'top'    => 13,
                                                'bottom' => 10,
                                                'right'  => 5,
                                                'left'   => 10,
                                            ],
                                            'background_color' => '#ffffff00',
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
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#0075FF',
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 18px; font-weight: 600;">@[yaymail_site_name]</span></p>',
                                            'padding'    => [
                                                'top'    => 16,
                                                'bottom' => 10,
                                                'right'  => 10,
                                                'left'   => 2,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                3,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 20,
                        'left'   => 20,
                        'right'  => 20,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            33,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-3-image-3.png',
                                            'width'   => 178,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 5,
                                                'left'   => 10,
                                            ],
                                        ]
                                    ),

                                ],
                            ]
                        ),
                        Column::get_object_data(
                            33,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-3-image-4.png',
                                            'width'   => 173,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 5,
                                                'left'   => 5,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            33,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-3-image-5.png',
                                            'width'   => 178,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 10,
                                                'left'   => 5,
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
