<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Divider;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/**
 * Header1 Elements
 */
class Footer2 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'footer_2';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 11;
        $this->name     = __( 'Footer 2', 'yaymail' );
        $this->elements = [
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 16px; margin: 0px;"> Don\'t forget</span> <span style="font-size: 16px; margin: 0px;">to</span><span style="font-size: 16px; margin: 0px;"> update the Yaycommerce application</span></p>',
                    'padding'    => [
                        'top'    => 30,
                        'right'  => 0,
                        'bottom' => 10,
                        'left'   => 0,
                    ],
                    'text_color' => '#333439',
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'children' => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    // There are no elements to display
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-1.png',
                                            'align'   => 'right',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 10,
                                            ],
                                            'width'   => 108,
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-2-img-2.png',
                                            'align'   => 'left',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'left'   => 10,
                                                'right'  => 0,
                                            ],
                                            'width'   => 108,
                                        ]
                                    ),
                                ],
                            ]
                        ),

                    ],
                    'padding'  => [
                        'top'    => 0,
                        'right'  => 100,
                        'bottom' => 0,
                        'left'   => 100,
                    ],
                ]
            ),
            Divider::get_object_data(
                [
                    'height'        => 1,
                    'width'         => 100,
                    'divider_color' => '#f1f2f5',
                    'padding'       => [
                        'top'    => 30,
                        'right'  => 40,
                        'bottom' => 30,
                        'left'   => 40,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="margin: 0px; text-align: center; font-weight: 300;"><span style="font-size: 14px; margin: 0;">© 2023 YayCommerce.com<br /></span><span style="font-size: 14px; text-align: center;">70 Washington Square South New York, NY 10012, United States</span></p>
                    <p style="text-align: center; font-weight: 300;"><span style="font-size: 14px; margin: 0;">Contact us      <span style="color: #e2e6ee;">|</span></span><span>     </span> <span style="text-align: center,font-size: 14px; margin: 0;">Terms &amp; Condition</span><span style="font-size: 14px; margin: 0;">      <span style="color: #e2e6ee;">|</span></span><span>     </span> <span style="font-size: 14px; margin: 0;">Return Policy</span><span style="font-size: 14px; margin: 0;"><span style="font-size: 14px; margin: 0;">      <span style="color: #e2e6ee;">|</span></span>      Support</span><span style="font-size: 14px; margin: 0;"><span style="font-size: 14px; margin: 0;">      <span style="color: #e2e6ee;">|</span></span>      Unsubscribe</span></p>',
                    'padding'    => [
                        'top'    => 0,
                        'right'  => 0,
                        'bottom' => 0,
                        'left'   => 0,
                    ],
                    'text_color' => '#333439',
                ]
            ),
        ];
    }
}
