<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Shipping;
use YayMail\Utils\SingletonTrait;

/** */
class Shipping1 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'shipping_1';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Shipping::TYPE;
        $this->position = 10;
        $this->name     = __( 'Shipping 1', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 40,
                        'right'  => 40,
                        'bottom' => 40,
                        'left'   => 40,
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-shipping-1-bg.png',
                        'position'   => 'custom',
                        'repeat'     => 'no-repeat',
                        'x_position' => 100,
                        'y_position' => 0,
                        'size'       => 'cover',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left;"><span style="margin: 0px; font-size: 24px; font-weight: bold;">Your order is now on its way to you! 🚚💨</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 15,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#ffffff',
                                            'background_color' => '#ffffff00',
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
                                            'rich_text'  => '<p style="text-align: left;"><span style="margin: 0; font-size: 14px">We\'ve carefully packed your goodies, ensuring they\'ll arrive in perfect condition, ready to embark on their next exciting chapter. 📦✨</span></p>',
                                            'padding'    => [
                                                'top'    => 8,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 15,
                                            ],
                                            'text_color' => '#ffffff',
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
