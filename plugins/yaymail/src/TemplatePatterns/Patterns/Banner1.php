<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Button;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Banner;
use YayMail\Utils\SingletonTrait;

/** */
class Banner1 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'banner_1';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Banner::TYPE;
        $this->position = 10;
        $this->name     = __( 'Banner 1', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'right'  => 0,
                        'bottom' => 0,
                        'left'   => 0,
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-banner-1-img-1.jpg',
                        'position'   => 'custom',
                        'x_position' => 100,
                        'y_position' => 0,
                        'size'       => 'contain',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-1-img-2.jpg',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'width'   => 605,
                                            'background_color' => '#ffffff00',
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
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-size: 24px"><span><b>Explore our collections</b></span></p>',
                                            'padding'    => [
                                                'top'    => 10,
                                                'right'  => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#ffffff',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-size: 16px"><span>New products for July Check \'em out!</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 70,
                                                'bottom' => 0,
                                                'left'   => 70,
                                            ],
                                            'text_color' => '#ffffff',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Button::get_object_data(
                                        [
                                            'font_size'  => 16,
                                            'text'       => 'Shop Now',
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#377e62',
                                            'button_background_color' => '#ffffff',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'border_radius' => [
                                                'top_left' => '12',
                                                'top_right' => '12',
                                                'bottom_right' => '12',
                                                'bottom_left' => '12',
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
