<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\Image;
use YayMail\Elements\ImageList;
use YayMail\Elements\SocialIcon;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/** */
class Footer10 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'footer_10';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 100;
        $this->name     = __( 'Footer 10', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color'       => '#F8FAFD',
                    'inner_background_color' => '#F8FAFD',
                    'padding'                => [
                        'top'    => 20,
                        'bottom' => 20,
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
                                            'background_color' => '#ffffff00',
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
                                            'background_color' => '#ffffff00',
                                            'number_column' => 2,
                                            'column_1' =>
                                            [
                                                'image'   => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-1.png',
                                                'align'   => 'left',
                                                'width'   => 108,
                                                'padding' => [
                                                    'top'  => '10',
                                                    'right' => '0',
                                                    'bottom' => '0',
                                                    'left' => '10',

                                                ],
                                            ],
                                            'column_2' =>
                                            [
                                                'image'   => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-2.png',
                                                'align'   => 'right',
                                                'width'   => 108,
                                                'padding' => [
                                                    'top'  => '10',
                                                    'right' => '10',
                                                    'bottom' => '0',
                                                    'left' => '0',

                                                ],
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
                3,
                [
                    'padding'  => [
                        'top'    => 30,
                        'bottom' => 30,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children' => [
                        Column::get_object_data(
                            31,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-2.png',
                                            'align'   => 'left',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 14,
                                                'left'   => 0,
                                                'right'  => 10,
                                            ],
                                            'width'   => 24,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: left;"><strong><span>Visit help center</span></strong></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 10,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: left;"><span>Let\'s check out <u>FAQs</u> and visit our <u>help center<u/></span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 10,
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
                            34,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-3.png',
                                            'align'   => 'left',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 14,
                                                'left'   => 0,
                                                'right'  => 10,
                                            ],
                                            'width'   => 24,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: left;"><strong><span>Location</span></strong></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 10,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: left;"><span>2370 Washington Square South NY 10012, New York</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 10,
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
                            35,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-4.png',
                                            'align'   => 'left',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 14,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'width'   => 24,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: left;"><strong><span>Contact us</span></strong></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: left;"><span>Call us <u>+1345 678 900</u> or say hi at <u>hello@example.com</u></span></p>',
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
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'background_color' => '#ffffff',
                    'padding'          => [
                        'top'    => 0,
                        'bottom' => 30,
                        'right'  => 40,
                        'left'   => 40,
                    ],
                    'children'         => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Divider::get_object_data(
                                        [
                                            'height'  => 1,
                                            'width'   => 100,
                                            'divider_color' => '#F1F2F5',
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 30,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text' => '<p style="margin: 0px; text-align: left;">For your privacy, please do not forward this mail to anyone as it allows you to get automatically logged into your account. If you do not want to receive this mailer, <u>unsubscribe</u>. To make sure this email is not sent to your "junk/bulk" folder, select "Add/save to Address Book" in your email browser and follow the appropriate instructions.</p>
                                            <p>© 2023 Made with love</p>',
                                            'padding'   => [
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
