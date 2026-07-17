<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Button;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Shipping;
use YayMail\Utils\SingletonTrait;

/** */
class Shipping2 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'shipping_2';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Shipping::TYPE;
        $this->position = 11;
        $this->name     = __( 'Shipping 2', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'border_radius'          => [
                        'top_left'     => 20,
                        'top_right'    => 20,
                        'bottom_right' => 20,
                        'bottom_left'  => 20,
                    ],
                    'padding'                => [
                        'top'    => 30,
                        'left'   => 40,
                        'right'  => 40,
                        'bottom' => 15,
                    ],
                    'inner_background_color' => '#ffffff00',
                    'background_image'       => [
                        'url'      => 'https://images.wpbrandy.com/uploads/yaymail-shipping-2-bg.png',
                        'position' => 'top_center',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-shipping-2-icon.png',
                                            'width'   => 159,
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 50,
                                                'bottom' => 0,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center;"><span style="margin: 0; font-size: 30px; font-weight: 700;">Yay!! Order Has Shipped!</span></p>',
                                            'padding'    => [
                                                'top'    => 15,
                                                'right'  => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center;"><span style="margin: 0px; font-size: 21px; font-weight: 300;">Great news! Your order is on its way. You can find the shipping details below.</span></p>',
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
                                    Button::get_object_data(
                                        [
                                            'text'       => 'Check your order',
                                            'font_size'  => 16,
                                            'border_radius' => [
                                                'top_left' => 7,
                                                'top_right' => 7,
                                                'bottom_left' => 7,
                                                'bottom_right' => 7,
                                            ],
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'left'   => 15,
                                                'right'  => 15,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'button_background_color' => '#ffffff00',
                                            'text_color' => '#2391F0',
                                            'width'      => 50,
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
