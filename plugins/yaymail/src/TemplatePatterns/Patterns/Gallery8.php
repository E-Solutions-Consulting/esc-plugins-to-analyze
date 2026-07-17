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
class Gallery8 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_8';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 17;
        $this->name     = __( 'Gallery 8', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                4,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 20,
                        'bottom' => 2,
                        'left'   => 20,
                        'right'  => 20,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-8-img-1.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),

                                ],
                            ]
                        ),
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-8-img-2.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-8-img-3.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-8-img-4.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'text_color'       => '#242527',
                    'rich_text'        => '<p style="text-align: center; margin: 0;"><span style="font-size: 16px; font-weight: 500;">Explore more at @[yaymail_site_name]</span></p>',
                    'padding'          => [
                        'top'    => 5,
                        'bottom' => 20,
                        'left'   => 50,
                        'right'  => 50,
                    ],
                ]
            ),
        ];
    }
}
