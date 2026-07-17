<?php

namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Banner;
use YayMail\Utils\SingletonTrait;

/** */
class Banner3 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'banner_3';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Banner::TYPE;
        $this->position = 30;
        $this->name     = __( 'Banner 3', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => '40',
                        'right'  => '0',
                        'bottom' => '70',
                        'left'   => '0',
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-banner-3-img-1-scaled.jpg',
                        'position'   => 'custom',
                        'x_position' => 100,
                        'y_position' => 0,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-3-img-4.png',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'width'   => 55,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 18px; text-align: center; margin: 0px;"><span style="font-size: 30px;"><strong>Unleash the fun!</strong> <br /><strong>It\'s time to enjoy your new product</strong></span></p>',
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="display: flex; justify-content: center; font-size: 14px; margin: 0px;"><u>Explore Now</u> <span style="padding: 1px 0px 0px 5px;"> <img src="https://images.wpbrandy.com/uploads/yaymail-banner-img-arrow-1.png" style="width:20px !important; height:20px !important;" alt="arrow banner 3" width="12" height="12" /> </span></p>',
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-3-img-2.png',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'width'   => 300,
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
