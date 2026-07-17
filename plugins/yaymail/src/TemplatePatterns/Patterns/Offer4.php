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
class Offer4 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'offer_4';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Offer::TYPE;
        $this->position = 13;
        $this->name     = __( 'Offer 4', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'border_radius'          => [
                        'top_left'     => 20,
                        'top_right'    => 20,
                        'bottom_right' => 20,
                        'bottom_left'  => 20,
                    ],
                    'padding'                => [
                        'top'    => 25,
                        'bottom' => 25,
                        'right'  => 40,
                        'left'   => 40,
                    ],
                    'background_image'       => [
                        'url'         => 'https://images.wpbrandy.com/uploads/yaymail-offer-4-bg.png',
                        'position'    => 'center_center',
                        'repeat'      => 'no-repeat',
                        'size'        => 'cover',
                        'custom_size' => 92,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            68,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#FFFFFF',
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 24px; font-weight: bold;">Enjoy Up to 50% Off!</span></p>',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'right'  => 30,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#FFFFFF',
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 12px; font-weight: 400;">Copy and paste the promo code during checkout to enjoy exclusive discounts on your purchase</span></p>',
                                            'padding'    => [
                                                'top'    => 5,
                                                'bottom' => 0,
                                                'right'  => 30,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            32,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#FFFFFF',
                                            'rich_text'  => '<p style="border: 1px dashed #ffffff80; border-radius: 10px; font-size: 15; font-weight: 600; text-align: center; width: fit-content; padding: 10px 15px; letter-spacing: 3px;">COUPON50F</p>',
                                            'padding'    => [
                                                'top'    => 25,
                                                'bottom' => 25,
                                                'right'  => 0,
                                                'left'   => 5,
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
