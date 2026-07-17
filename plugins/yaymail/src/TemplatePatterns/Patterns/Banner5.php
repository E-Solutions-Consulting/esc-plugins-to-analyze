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
 * Banner5 Elements
 */
class Banner5 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'banner_5';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Banner::TYPE;
        $this->position = 50;
        $this->name     = __( 'Banner 5', 'yaymail' );
        $this->elements = [
            // TODO: Add element border radius setting + fix border radius of this banner
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-banner-5-img-1-scaled.png',
                        'position'   => 'custom',
                        'x_position' => 100,
                        'y_position' => 50,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'background_color'       => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 0,
                        'right'  => 0,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            70,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 30px; text-align: left; margin: 0px;"><strong><span style="font-size: 36px;"> It\'s time to relax and enjoy using your products! <span style="white-space-collapse: preserve;">🎉</span></span></strong></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 20,
                                                'bottom' => 20,
                                                'right'  => 0,
                                                'left'   => 40,
                                            ],
                                            'text_color' => '#ffffff',
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-banner-5-img-2.png',
                                            'padding' => [
                                                'top'    => 36,
                                                'bottom' => 0,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'align'   => 'right',
                                            'background_color' => '#ffffff00',
                                            'width'   => 188,
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
