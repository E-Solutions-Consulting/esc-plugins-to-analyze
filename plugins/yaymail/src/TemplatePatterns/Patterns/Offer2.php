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

/**
 * Offer 2
 */
class Offer2 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'offer_2';
    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Offer::TYPE;
        $this->position = 11;
        $this->name     = __( 'Offer 2', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 10,
                        'bottom' => 10,
                        'right'  => 40,
                        'left'   => 40,
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-offer-2-bg.png',
                        'position'   => 'center_center',
                        'x_position' => 52,
                        'y_position' => 50,
                        'repeat'     => 'no-repeat',
                        'size'       => 'cover',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#E2E6EE',
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 25px; font-weight: 700;"><b>Enjoy Up to 50% Off!</b></span></p>',
                                            'padding'    => [
                                                'top'    => 15,
                                                'bottom' => 5,
                                                'right'  => 50,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#E2E6EE',
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 15px; font-weight: 300;">Copy and paste the promo code bellow during checkout to enjoy exclusive discounts on your purchase:</span></p>',
                                            'padding'    => [
                                                'top'    => 5,
                                                'bottom' => 10,
                                                'right'  => 50,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#FFFFFF',
                                            'rich_text'  => '<p><span style="border: 1px dashed #ffffff59; border-radius: 10px; font-size: 15; font-weight: 500; text-align: center; width: fit-content; padding: 10px 15px; letter-spacing: 3px;">COUPON50F</span><a style="padding-left: 20px; text-decoration: underline; font-size: 15px; font-weight: 500; color: inherit; text-underline-offset: 4px;" href="#">Shop Now <img style="margin-left: 3px; width: 12px;" src="https://images.wpbrandy.com/uploads/yaymail-offer-2-img.png" alt="arrow_right" /></a></p>',
                                            'padding'    => [
                                                'top'    => 25,
                                                'bottom' => 25,
                                                'right'  => 5,
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
