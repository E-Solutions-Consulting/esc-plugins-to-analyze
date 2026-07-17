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
 * Banner7 Elements
 * */
class Banner7 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'banner_7';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Banner::TYPE;
        $this->position = 70;
        $this->name     = __( 'Banner 7', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-banner-7-img-1.jpg',
                        'position'   => 'top_left',
                        'x_position' => 100,
                        'y_position' => 0,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 0,
                        'right'  => 0,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            48,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 20px; text-align: left; margin: 0px;"><span style="font-size: 24px;"><strong> It\'s time to enjoy your new products.</strong></span></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 20,
                                                'bottom' => 0,
                                                'right'  => 20,
                                                'left'   => 30,
                                            ],
                                            'text_color' => '#ffffff',
                                        ]
                                    ),
                                    Button::get_object_data(
                                        [
                                            'text'       => 'Check it Out',
                                            'font_size'  => 14,
                                            'border_radius' => [
                                                'top_left' => 30,
                                                'top_right' => 30,
                                                'bottom_left' => 30,
                                                'bottom_right' => 30,
                                            ],
                                            'padding'    => [
                                                'top'    => 20,
                                                'bottom' => 20,
                                                'left'   => 30,
                                                'right'  => 30,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'button_background_color' => '#ffffff',
                                            'text_color' => '#C85E1E',
                                            'width'      => 65,
                                            'align'      => 'left',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            52,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-7-img-3.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'right',
                                            'background_color' => '#ffffff00',
                                            'width'   => 605,
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
