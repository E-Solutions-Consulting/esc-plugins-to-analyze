<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Button;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Gallery;
use YayMail\Utils\SingletonTrait;

/** */
class Gallery9 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_9';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 18;
        $this->name     = __( 'Gallery 9', 'yaymail' );
        $this->elements = [
            Text::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'text_color'       => '#242527',
                    'rich_text'        => '<p style="text-align: left; margin: 0;"><span style="font-size: 18px; font-weight: 600;">Discover the Bloom: <br> Explore Our Latest Spring 2024 Collection!</span></p>',
                    'padding'          => [
                        'top'    => 15,
                        'bottom' => 10,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                4,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-1.png',
                                            'width'   => 125,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 5,
                                                'right'  => 5,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-2.png',
                                            'width'   => 128,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 0,
                                                'right'  => 5,
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-4.png',
                                            'width'   => 127,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 5,
                                                'right'  => 5,
                                                'left'   => 5,
                                            ],
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-3.png',
                                            'width'   => 127,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 0,
                                                'right'  => 5,
                                                'left'   => 5,
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-5.png',
                                            'width'   => 127,
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
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-7.png',
                                            'width'   => 122,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 0,
                                                'right'  => 5,
                                                'left'   => 5,
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-6.png',
                                            'width'   => 124,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 5,
                                            ],
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-9-img-8.png',
                                            'width'   => 124,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 0,
                                                'right'  => 0,
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
            Text::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'text_color'       => '#242527',
                    'rich_text'        => '<p style="text-align: left; margin: 0;"><span style="font-size: 13px; font-weight: 300;">Immerse yourself in a symphony of vibrant hues, trend-setting designs, and seasonal elegance. From breezy florals to modern silhouettes, each piece is curated to elevate your spring wardrobe. Embrace the spirit of spring with our meticulously crafted collection - where every piece tells a story of renewal and timeless allure</span></p>',
                    'padding'          => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                ]
            ),
            Button::get_object_data(
                [
                    'align'                   => 'left',
                    'button_background_color' => '#FFC900',
                    'text_color'              => '#ffffff',
                    'text'                    => 'Explore Our Collection',
                    'border_radius'           => [
                        'top_left'     => 12,
                        'top_right'    => 12,
                        'bottom_right' => 12,
                        'bottom_left'  => 12,
                    ],
                    'padding'                 => [
                        'top'    => 10,
                        'bottom' => 15,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                    'width'                   => 45,
                    'font_size'               => 14,
                ]
            ),
        ];
    }
}
