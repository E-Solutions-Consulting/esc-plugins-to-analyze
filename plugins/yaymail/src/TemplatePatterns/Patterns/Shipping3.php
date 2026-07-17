<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Shipping;
use YayMail\Utils\SingletonTrait;

/** */
class Shipping3 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'shipping_3';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Shipping::TYPE;
        $this->position = 12;
        $this->name     = __( 'Shipping 3', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'background_color'       => '#F1F2F5',
                    'padding'                => [
                        'top'    => 20,
                        'left'   => 20,
                        'bottom' => 20,
                        'right'  => 20,
                    ],
                    'inner_border_radius'    => [
                        'top_left'     => 15,
                        'top_right'    => 15,
                        'bottom_left'  => 15,
                        'bottom_right' => 15,
                    ],
                    'inner_background_color' => '#FFFFFF',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-footer-img-1.png',
                                            'width'   => 162,
                                            'align'   => 'left',
                                            'padding' => [
                                                'top'    => 30,
                                                'right'  => 40,
                                                'bottom' => 10,
                                                'left'   => 40,
                                            ],
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; margin: 0; font-weight: 700;"><span style="font-size: 21px;">Ta da!!! Your Order has Shipped!</span></p>',
                                            'padding'    => [
                                                'top'    => 5,
                                                'right'  => 40,
                                                'bottom' => 5,
                                                'left'   => 40,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => 'Your tracking number is [tracking number]. Now you can track your order\'s every move, just like a pirate tracking a treasure. 🏴‍☠️👀',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 40,
                                                'bottom' => 10,
                                                'left'   => 40,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-order-status.png',
                                            'width'   => 399,
                                            'align'   => 'left',
                                            'padding' => [
                                                'top'    => 10,
                                                'right'  => 40,
                                                'bottom' => 30,
                                                'left'   => 40,
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
        ];
    }
}
