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
 * Footer4 Elements
 */
class Footer4 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'footer_4';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 40;
        $this->name     = __( 'Footer 4', 'yaymail' );
        $this->elements = [
            Logo::get_object_data(
                [
                    'src'              => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                    'width'            => 195,
                    'align'            => 'center',
                    'padding'          => [
                        'top'    => 30,
                        'right'  => 0,
                        'bottom' => 30,
                        'left'   => 0,
                    ],
                    'background_color' => '#ffffff',
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="text-align: center; margin: 0; font-size: 14px; font-weight: 300;">Terms &amp; Condition     <span style="color: #e2e6ee;">|</span>     Return Policy     <span style="color: #e2e6ee;">|</span>     Support     <span style="color: #e2e6ee;">|</span>     Unsubscribe</p>',
                    'padding'    => [
                        'top'    => 0,
                        'right'  => 0,
                        'bottom' => 0,
                        'left'   => 0,
                    ],
                    'text_color' => '#333439',
                ]
            ),
            Divider::get_object_data(
                [
                    'height'        => 1,
                    'width'         => 100,
                    'divider_color' => '#F1F2F5',
                    'padding'       => [
                        'top'    => 30,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'padding'  => [
                        'top'    => 0,
                        'bottom' => 20,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children' => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; margin: 0; font-size: 14px; font-weight: 300;"><span>© 2023 Yaycommerce.com</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    SocialIcon::get_object_data(
                                        [
                                            'align'      => 'right',
                                            'spacing'    => 20,
                                            'width_icon' => 24,
                                            'style'      => 'Colorful',
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
                    ],
                ]
            ),
        ];
    }
}