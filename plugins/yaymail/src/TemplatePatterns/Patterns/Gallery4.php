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
class Gallery4 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_4';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 13;
        $this->name     = __( 'Gallery 4', 'yaymail' );
        $this->elements = [
            Text::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'text_color'       => '#242527',
                    'rich_text'        => '<p style="text-align: center; margin: 0;"><span style="font-size: 18px; font-weight: 600;">Discover Our Newest Collection at @[yaymail_site_name]</span></p>',
                    'padding'          => [
                        'top'    => 15,
                        'bottom' => 15,
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
                            19,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-4-img-1.png',
                                            'width'   => 107,
                                            'align'   => 'left',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 5,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),

                                ],
                            ]
                        ),
                        Column::get_object_data(
                            62,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-4-img-2.png',
                                            'width'   => 360,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            19,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-gallery-4-img-3.png',
                                            'width'   => 107,
                                            'align'   => 'right',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
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
                    'rich_text'        => '<p style="text-align: center; margin: 0;"><span style="font-size: 13px; font-weight: 300;">From breezy florals to modern silhouettes, each piece is curated to elevate your spring wardrobe. Embrace the spirit of spring with our meticulously crafted collection - where every piece tells a story of renewal and timeless allure</span></p>',
                    'padding'          => [
                        'top'    => 0,
                        'bottom' => 5,
                        'left'   => 50,
                        'right'  => 50,
                    ],
                ]
            ),
            Button::get_object_data(
                [
                    'align'                   => 'center',
                    'button_background_color' => '#FFC900',
                    'text_color'              => '#ffffff',
                    'text'                    => 'Explore Collection',
                    'border_radius'           => [
                        'top_left'     => 12,
                        'top_right'    => 12,
                        'bottom_right' => 12,
                        'bottom_left'  => 12,
                    ],
                    'padding'                 => [
                        'top'    => 10,
                        'bottom' => 17,
                        'left'   => 19,
                        'right'  => 19,
                    ],
                    'width'                   => 31,
                ]
            ),
        ];
    }
}
