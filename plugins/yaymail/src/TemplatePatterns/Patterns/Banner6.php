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

/**
 * Banner6 Elements
 */
class Banner6 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'banner_6';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Banner::TYPE;
        $this->position = 60;
        $this->name     = __( 'Banner 6', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                3,
                [
                    'inner_background_color' => '#ffffff00',
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-banner-6-img-1.png',
                        'position'   => 'custom',
                        'x_position' => 100,
                        'y_position' => 0,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 5,
                        'right'  => 5,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            45,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 21px; text-align: left; margin: 0px;"><strong> Join our community </strong></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 20,
                                                'bottom' => 5,
                                                'right'  => 30,
                                                'left'   => 25,
                                            ],
                                            'text_color' => '#ffffff',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 14px; text-align: left; margin: 0px;">Let\'s Make a Plan: Become a Pro+ membership! learn today, apply tomorrow.</p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 5,
                                                'bottom' => 20,
                                                'right'  => 30,
                                                'left'   => 25,
                                            ],
                                            'text_color' => '#ffffff',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Button::get_object_data(
                                        [
                                            'text'       => 'Shop Now',
                                            'font_size'  => 16,
                                            'border_radius' => [
                                                'top_left' => 7,
                                                'top_right' => 7,
                                                'bottom_left' => 7,
                                                'bottom_right' => 7,
                                            ],
                                            'padding'    => [
                                                'top'    => 20,
                                                'bottom' => 20,
                                                'left'   => 15,
                                                'right'  => 15,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'button_background_color' => '#ffffff',
                                            'text_color' => '#323dc7',
                                            'width'      => 100,
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            30,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-6-img-2.png',
                                            'padding' => [
                                                'top'    => 40,
                                                'bottom' => 0,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'align'   => 'right',
                                            'background_color' => '#ffffff00',
                                            'width'   => 190,
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
