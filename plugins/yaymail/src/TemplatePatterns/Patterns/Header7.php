<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Header;
use YayMail\Utils\SingletonTrait;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Column;
use YayMail\Elements\Logo;

/**
 * Header6 Elements
 */
class Header7 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_7';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 70;
        $this->name     = __( 'Header 7', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color' => '#ffffff',
                    'vertical_align'   => 'middle',
                    'children'         => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Logo::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'align'   => 'left',
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => 195,
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 10,
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
                                            'rich_text'  => '<p style="text-align: right;"><span style="font-size: 16px;">Support     Blog</span><span style="font-size: 16px;">     </span><span style="font-size: 16px;">FAQs</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 10,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'padding'          => [
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'background_color'       => '#ffffff',
                    'inner_background_color' => '#ffffff00',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<h1 style="font-size: 22px; font-weight: 300; line-height: normal; margin: 0px; color: inherit; text-align: left;"><span style="font-size: 24px;"><strong>Order #[yaymail_order_number]</strong></span></h1>
                                            <h1 style="font-size: 22px; font-weight: 300; line-height: normal; margin: 0px; color: inherit; text-align: left;"><span style="font-size: 24px;"><strong>has been completed</strong></span></h1>',
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 40,
                                                'bottom' => 0,
                                                'left'   => 88,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-header-img-1-scaled.png',
                        'position'   => 'custom',
                        'x_position' => 70,
                        'y_position' => 0,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'padding'                => [
                        'top'    => 30,
                        'right'  => 0,
                        'bottom' => 30,
                        'left'   => 0,
                    ],
                ]
            ),

        ];
    }
}