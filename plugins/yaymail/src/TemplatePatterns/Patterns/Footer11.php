<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\Image;
use YayMail\Elements\Logo;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/** */
class Footer11 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'footer_11';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 110;
        $this->name     = __( 'Footer 11', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                3,
                [
                    'padding'  => [
                        'top'    => 30,
                        'bottom' => 40,
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
                                            'align'   => 'center',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'width'   => 24,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: center;"><strong><span>Visit help center</span></strong></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 8,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: center;"><span>Let\'s check out <u>FAQs</u> and visit our <u>help center<u/></span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 10,
                                                'left'   => 10,
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
                                            'align'   => 'center',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'width'   => 24,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: center;"><strong><span>Location</span></strong></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 8,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: center;"><span>2370 Washington Square South NY 10012, New York</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 10,
                                                'left'   => 10,
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
                                            'align'   => 'center',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'width'   => 24,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: center;"><strong><span>Contact us</span></strong></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 8,
                                                'right'  => 10,
                                                'left'   => 10,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="margin: 0px; text-align: center;"><span>Call us <u>+1345 678 900</u> or say hi at <u>hello@example.com</u></span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 10,
                                                'left'   => 10,
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
            Divider::get_object_data(
                [
                    'height'        => 1,
                    'width'         => 100,
                    'divider_color' => '#F1F2F5',
                    'padding'       => [
                        'top'    => 0,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="text-align: center;">About us       <span style="color: #f1f2f5;">|</span>       Terms &amp; Condition       <span style="color: #f1f2f5;">|</span>       Return Policy       <span style="color: #f1f2f5;">|</span>       Unsubscribe</p>',
                    'padding'    => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 0,
                        'right'  => 0,
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
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 0,
                        'left'   => 40,
                    ],
                ]
            ),
            Logo::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'align'            => 'center',
                    'src'              => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                    'width'            => '140',
                    'url'              => '#',
                    'padding'          => [
                        'top'    => 30,
                        'right'  => 0,
                        'bottom' => 10,
                        'left'   => 0,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="margin: 0px; text-align: center;">
                    <span>
                    &#169 2023 Made with love
                    </span>
                    </p>',
                    'padding'    => [
                        'top'    => 0,
                        'right'  => 0,
                        'bottom' => 20,
                        'left'   => 0,
                    ],
                    'text_color' => '#77859B',
                ]
            ),
        ];
    }
}
