<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Logo;
use YayMail\Elements\SocialIcon;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/**Footer6 Elements */
class Footer6 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'footer_6';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 60;
        $this->name     = __( 'Footer 6', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                3,
                [
                    'padding'                => [
                        'top'    => 15,
                        'bottom' => 15,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'background_color'       => '#f8fafd',
                    'inner_background_color' => '#ffffff00',
                    'children'               => [
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                <tr>
                                                    <td style="vertical-align: middle; padding-right: 8px;">
                                                        <img src="https://images.wpbrandy.com/uploads/yaymail-footer-img-5.png" alt="" width="30" height="30" />
                                                    </td>
                                                    <td style="vertical-align: middle; font-size: 16px;">
                                                        039 2219 129
                                                    </td>
                                                </tr>
                                                </table>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),

                                ],
                            ]
                        ),
                        Column::get_object_data(
                            30,
                            [
                                'children' => [

                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 10px;">
                                                <tr>
                                                    <td style="vertical-align: middle; padding-right: 8px;">
                                                     <img src="https://images.wpbrandy.com/uploads/yaymail-footer-img-6.png" alt="" width="30" height="30" />
                                                    </td>
                                                    <td style="vertical-align: middle; font-size: 16px;">
                                                     YayCommerce.com
                                                    </td>
                                                </tr>
                                                </table>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),

                                ],
                            ]
                        ),
                        Column::get_object_data(
                            45,
                            [
                                'children' => [
                                    Logo::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'align'   => 'right',
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => '155',
                                            'url'     => '#',
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 40,
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
            SocialIcon::get_object_data(
                [
                    'align'      => 'center',
                    'spacing'    => 24,
                    'width_icon' => 30,
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
                        'top'    => 40,
                        'right'  => 40,
                        'bottom' => 10,
                        'left'   => 40,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'        => '<p style="text-align: center; margin: 0; font-size: 16px; font-weight: 300;">New Arrivals     <span style="color: #e2e6ee;">|</span>     Collections     <span style="color: #e2e6ee;">|</span>     Outlet     <span style="color: #e2e6ee;">|</span>     Contact</p>',
                    'padding'          => [
                        'top'    => 10,
                        'right'  => 40,
                        'bottom' => 30,
                        'left'   => 40,
                    ],
                    'text_color'       => '#333439',
                    'background_color' => '#ffffff',
                ]
            ),
            ColumnLayout::get_object_data(
                3,
                [
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 20,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'background_color'       => '#f8fafd',
                    'inner_background_color' => '#ffffff00',
                    'children'               => [
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; margin: 0; font-size: 12px; font-weight: 300;"><span style="margin: 0px 10px;">Terms &amp; Condition</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#77859B',
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
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-size: 12px; font-weight: 300;"><span>© 2023 Made with love</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#77859B',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: right; margin: 0; font-size: 12px; font-weight: 300;"><span style="margin: 0px 10px;">Unsubscribe</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#77859B',
                                            'background_color' => '#ffffff00',
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