<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Banner;
use YayMail\Utils\SingletonTrait;

/**
 * Banner4 Elements
 */
class Banner4 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'banner_4';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Banner::TYPE;
        $this->position = 40;
        $this->name     = __( 'Banner 4', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'inner_background_color' => '#ffffff00',
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-banner-4-img-1-scaled.png',
                        'position'   => 'custom',
                        'x_position' => 100,
                        'y_position' => 0,
                        'size'       => 'custom',
                        'repeat'     => 'no-repeat',
                    ],
                    'padding'                => [
                        'top'    => 30,
                        'bottom' => 20,
                        'left'   => 5,
                        'right'  => 5,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-4-img-2.png',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'width'   => 77,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 30px; text-align: center; margin: 0px;"><span style="font-size: 36px;"><strong>Your Product are Waiting!</strong></span></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                            'text_color' => '#312760',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'padding'  => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 10,
                        'right'  => 10,
                    ],
                    'children' => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 14px; text-align: center; margin: 0px;"><strong><span style="font-size: 16px;">Discover the joy of using them today </span><span style="white-space-collapse: preserve; font-size: 13px;">🎉</span></strong></p>',
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 15,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 14px; text-align: center; margin: 0px;"><span style="font-size: 16px;"><span style="text-decoration: underline;">Explore Now</span> <img src="https://images.wpbrandy.com/uploads/yaymail-banner-img-arrow-1.png" style="width:20px !important; height:20px !important;" alt="" width="12" height="12" /></span></p>',
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
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
        ];
    }
}
