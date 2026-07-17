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
class Gallery5 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_5';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 14;
        $this->name     = __( 'Gallery 5', 'yaymail' );
        $this->elements = [
            Image::get_object_data(
                [
                    'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-5-icon.png',
                    'width'   => 50,
                    'align'   => 'center',
                    'alt'     => 'Icon',
                    'padding' => [
                        'top'    => 15,
                        'bottom' => 10,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'text_color'       => '#242527',
                    'rich_text'        => '<p style="text-align: center; margin: 0;"><span style="font-size: 18px; font-weight: 600;">Moments that suddenly make us realize that this life always brings us things the most surprising and wonderful!</span></p>',
                    'padding'          => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                3,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 10,
                        'left'   => 0,
                        'right'  => 0,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            33,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-5-img-1.png',
                                            'width'   => 176,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 7,
                                                'right'  => 0,
                                                'left'   => 18,
                                            ],
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-5-img-2.png',
                                            'width'   => 186,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 11,
                                                'bottom' => 7,
                                                'right'  => 4,
                                                'left'   => 22,
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-5-img-3.png',
                                            'width'   => 175,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 8,
                                                'right'  => 7,
                                                'left'   => 7,
                                            ],
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-5-img-4.png',
                                            'width'   => 175,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 10,
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-5-img-5.png',
                                            'width'   => 176,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 7,
                                                'right'  => 18,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-5-img-6.png',
                                            'width'   => 180,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 11,
                                                'bottom' => 5,
                                                'right'  => 22,
                                                'left'   => 4,
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
