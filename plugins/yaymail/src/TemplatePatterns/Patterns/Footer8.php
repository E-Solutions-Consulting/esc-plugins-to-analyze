<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\ImageList;
use YayMail\Elements\Logo;
use YayMail\Elements\SocialIcon;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/** */
class Footer8 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'footer_8';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 80;
        $this->name     = __( 'Footer 8', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 40,
                        'bottom' => 0,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Logo::get_object_data(
                                        [
                                            'background_color' => '#ffffff',
                                            'align'   => 'left',
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => '142',
                                            'url'     => '#',
                                            'padding' => [
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
                                            'rich_text'  => '<p style="text-align: left; font-size: 16px; font-weight: 500; margin: 0;">Get the app</p>',
                                            'text_color' => '#333439',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),

                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text' => '<p style="text-align: left; font-weight: 300;"><span>5470 Washington Square South, NY 10012, New York City, United States.</span></p>',
                                            'padding'   => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
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
                                            'rich_text' => '<p style="text-align: left; font-weight: 300;"><span>Try the our mobile app to comment, replies, when you\'re on the go.</span></p>',
                                            'padding'   => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    SocialIcon::get_object_data(
                                        [
                                            'align'      => 'left',
                                            'spacing'    => 24,
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
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    ImageList::get_object_data(
                                        [
                                            'background_color' => '#ffffff',
                                            'number_column' => 2,
                                            'column_1' => [
                                                'align'   => 'left',
                                                'padding' => [
                                                    'top'  => 10,
                                                    'bottom' => 0,
                                                    'right' => 10,
                                                    'left' => 0,
                                                ],
                                                'image'   => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-1.png',
                                                'width'   => 108,
                                            ],
                                            'column_2' => [
                                                'align'   => 'left',
                                                'padding' => [
                                                    'top'  => 10,
                                                    'bottom' => 0,
                                                    'right' => 0,
                                                    'left' => 0,
                                                ],
                                                'image'   => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-2.png',
                                                'width'   => 108,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
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
                        'bottom' => 15,
                        'left'   => 40,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="text-align: left; font-size: 12px; font-weight: 300;">For your privacy, please do not forward this mail to anyone as it allows you to get automatically logged into your account. If you do not want to receive this mailer, <u>unsubcribe</u>. To make sure this email is not sent to your "junk/bulk" folder, select "Add/save to Address Book" in your email browser and follow the appropriate instructions.</p>',
                    'padding'    => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'text_color' => '#77859B',
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; font-size: 12px; font-weight: 300;">© 2023 Made with love</p>',
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
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: right; margin: 0; font-size: 12px; font-weight: 300;"><span style="margin: 0px 10px;">About us         Support Return         Policy</span></p>',
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
