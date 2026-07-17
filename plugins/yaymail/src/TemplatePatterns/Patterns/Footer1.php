<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Column;
use YayMail\Elements\Divider;
use YayMail\Elements\Logo;
use YayMail\Elements\Text;

use YayMail\TemplatePatterns\SectionTemplates\Footer;
use YayMail\Utils\SingletonTrait;

/**
 * Header1 Elements
 */
class Footer1 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'footer_1';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 10;
        $this->name     = __( 'Footer 1', 'yaymail' );
        $this->elements = [
            Divider::get_object_data(
                [
                    'height'        => 2,
                    'width'         => 100,
                    'divider_color' => '#333439',
                    'padding'       => [
                        'top'    => 40,
                        'right'  => 40,
                        'bottom' => 50,
                        'left'   => 40,
                    ],
                ]
            ),

            ColumnLayout::get_object_data(
                1,
                [
                    'background_color'       => '#ffffff',
                    'padding'                => [
                        'top'    => 0,
                        'left'   => 40,
                        'bottom' => 40,
                        'right'  => 40,
                    ],
                    'inner_border_radius'    => [
                        'top_left'     => 20,
                        'top_right'    => 20,
                        'bottom_left'  => 20,
                        'bottom_right' => 20,
                    ],
                    'inner_background_color' => '#F8FAFD',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Logo::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => 195,
                                            'align'   => 'left',
                                            'padding' => [
                                                'top'    => 30,
                                                'right'  => 30,
                                                'bottom' => 0,
                                                'left'   => 30,
                                            ],
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; margin: 0; font-weight: 300;"><span style="font-size: 14px; margin: 0;"> By engaging with this email, you agree to Yaycommerce <span style="color: #333439;">Terms Conditions</span> and <span style="color: #333439;">Privacy Policy</span> in relation to your privacy information.</span></p>',
                                            'padding'    => [
                                                'top'    => 25,
                                                'right'  => 30,
                                                'bottom' => 25,
                                                'left'   => 30,
                                            ],
                                            'text_color' => '#77859B',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; margin: 0; font-weight: 300;"><span style="font-size: 14px; margin: 0;">© 2023 YayCommerce.com</span>     <span style="color: #e2e6ee;">|</span><span style="font-size: 14px; margin: 0;"><span style="font-size: 13px;">     </span>Unsubscribe</span><span style="font-size: 14px; margin: 0;"><span style="font-size: 13px;">     <span style="color: #e2e6ee;">|</span></span><span style="font-size: 14px; margin: 0;"><span style="font-size: 13px;">     </span></span>Support</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 30,
                                                'bottom' => 20,
                                                'left'   => 30,
                                            ],
                                            'text_color' => '#333439',
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