<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\Logo;
use YayMail\Elements\SocialIcon;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/**
 * Footer5 Elements
 */
class Footer5 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'footer_5';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 50;
        $this->name     = __( 'Footer 5', 'yaymail' );
        $this->elements = [
            Logo::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'align'            => 'center',
                    'src'              => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                    'width'            => '195',
                    'url'              => '#',
                    'padding'          => [
                        'top'    => 40,
                        'right'  => 40,
                        'bottom' => 0,
                        'left'   => 40,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="margin: 0px; text-align: center; font-weight: 300;"><span style="font-size: 14px; margin: 0;">70 Washington Square South New York, NY 10012, United States</span></p>
                    <p style="text-align: center; font-weight: 300;"><span style="font-size: 14px; text-align: center;">© 2023 All Rights Reserved</span></p>',
                    'text_color' => '#333439',
                    'padding'    =>
                    [
                        'top'    => 20,
                        'bottom' => 0,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                ]
            ),
            Divider::get_object_data(
                [
                    'height'        => 1,
                    'width'         => 100,
                    'divider_color' => '#F1F2F5',
                    'padding'       => [
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 5,
                        'left'   => 40,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color' => '#ffffff',
                    'padding'          => [
                        'top'    => 0,
                        'bottom' => 40,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'         => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    SocialIcon::get_object_data(
                                        [
                                            'align'      => 'left',
                                            'spacing'    => 24,
                                            'width_icon' => 24,
                                            'style'      => 'SolidDark',
                                            'icon_list'  => [
                                                [
                                                    'icon' => 'facebook',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'instagram',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'tiktok',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'youtube',
                                                    'url'  => '#',
                                                ],
                                            ],
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
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
                                            'rich_text'  => '<p style="text-align: right; margin: 0; font-size: 14px; font-weight: 300;"><span style="margin: 0px 10px;">Unsubscribe</span> <span style="margin: 0px 10px;">Terms &amp; Condition</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#77859B',
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
