<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Button;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Offer;
use YayMail\Utils\SingletonTrait;

/** */
class Offer1 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'offer_1';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Offer::TYPE;
        $this->position = 10;
        $this->name     = __( 'Offer 1', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'inner_background_color' => '#ffffff00',
                    'border_radius'          => [
                        'top_left'     => 30,
                        'top_right'    => 30,
                        'bottom_right' => 30,
                        'bottom_left'  => 30,
                    ],
                    'padding'                => [
                        'top'    => 50,
                        'bottom' => 50,
                        'right'  => 30,
                        'left'   => 30,
                    ],
                    'background_image'       => [
                        'url'         => 'https://images.wpbrandy.com/uploads/yaymail-offer-1-bg.png',
                        'position'    => 'custom',
                        'x_position'  => 52,
                        'y_position'  => 50,
                        'repeat'      => 'no-repeat',
                        'size'        => 'cover',
                        'custom_size' => 92,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-offer-1-icon.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 10,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                            'align'   => 'center',
                                            'width'   => 90,
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 20px; font-weight: 700;"><b>Welcome to [yaymail_site_name]</b></span></p>',
                                            'padding'    => [
                                                'top'    => 15,
                                                'bottom' => 5,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 16px; font-weight: 300;">Congratulations! Enjoy an exclusive discount on your first-time order and receive a gift offer.</span></p>',
                                            'padding'    => [
                                                'top'    => 5,
                                                'bottom' => 10,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                    Button::get_object_data(
                                        [
                                            'text'       => 'Place your first order',
                                            'background_color' => '#ffffff00',
                                            'button_background_color' => '#FFFFFF',
                                            'text_color' => '#FFA179',
                                            'width'      => 50,
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                            'border_radius' => [
                                                'top_left' => 15,
                                                'top_right' => 15,
                                                'bottom_right' => 15,
                                                'bottom_left' => 15,
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
