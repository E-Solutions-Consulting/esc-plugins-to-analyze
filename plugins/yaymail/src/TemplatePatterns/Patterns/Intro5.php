<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Button;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Intro;
use YayMail\Utils\SingletonTrait;

/**
 * Intro 5 Pattern
 */
class Intro5 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'intro_5';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Intro::TYPE;
        $this->position = 10;
        $this->name     = __( 'Intro 5', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'background_color'       => '#ffffff00',
                    'padding'                => [
                        'top'    => 15,
                        'left'   => 15,
                        'bottom' => 15,
                        'right'  => 15,
                    ],
                    'inner_background_color' => '#ffffff00',
                    'background_image'       => [
                        'url'      => 'https://images.wpbrandy.com/uploads/yaymail-intro-5-bg.png',
                        'position' => 'center_right',
                        'size'     => 'cover',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-yay-logo-v2.png',
                                            'width'   => 221,
                                            'align'   => 'center',
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),

                                ],
                            ]
                        ),
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'        => '<p style="text-align: center; margin: 0; font-weight: 500;"><span style="font-size: 24px;">Welcome [yaymail_customer_name]! We\'re excited to unveil our latest product, meticulously crafted with you in mind!</span></p>',
                    'padding'          => [
                        'top'    => 40,
                        'right'  => 80,
                        'bottom' => 23,
                        'left'   => 80,
                    ],
                    'text_color'       => '#333439',
                    'background_color' => '#FFFFFF',
                ]
            ),
            Button::get_object_data(
                [
                    'text'                    => 'Explore Now',
                    'background_color'        => '#ffffff',
                    'button_background_color' => '#FFC900',
                    'text_color'              => '#ffffff',
                    'align'                   => 'center',
                    'width'                   => 20,
                    'url'                     => '[yaymail_site_url]',
                    'padding'                 => [
                        'top'    => 10,
                        'bottom' => 40,
                        'right'  => 0,
                        'left'   => 0,
                    ],
                    'border_radius'           => [
                        'top_left'     => 12,
                        'top_right'    => 12,
                        'bottom_right' => 12,
                        'bottom_left'  => 12,
                    ],
                ]
            ),
        ];
    }
}
