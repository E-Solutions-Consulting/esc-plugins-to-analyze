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
class Offer5 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'offer_5';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Offer::TYPE;
        $this->position = 14;
        $this->name     = __( 'Offer 5', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
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
                        'url'         => 'https://images.wpbrandy.com/uploads/yaymail-offer-5-bg.png',
                        'position'    => 'center_center',
                        'repeat'      => 'no-repeat',
                        'size'        => 'cover',
                        'custom_size' => 92,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#E2AE4E',
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 30px; font-weight: 700;">BLACK FRIDAY</span></p>',
                                            'padding'    => [
                                                'top'    => 5,
                                                'bottom' => 0,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#E2E6EE',
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 12; font-weight: 300;">Get the hottest deals today!</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 10,
                                                'right'  => 0,
                                                'left'   => 0,
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
