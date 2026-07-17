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
class Offer3 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'offer_3';
    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Offer::TYPE;
        $this->position = 12;
        $this->name     = __( 'Offer 3', 'yaymail' );

        $special_text = <<<HTML
<p style="margin: 0; font-weight: bold;">
<!-- DAYS -->
<span style="display: inline-block; width: 28px; padding: 10px 0; vertical-align: top; text-align: center;">
<span style="font-size: 20px; display: block;">20</span>
<span style="font-size: 10px; font-weight: 300;">DAYS</span>
</span>

<!-- Colon -->
<span style="display: inline-block; width: 20px; font-size: 20px; position: relative; top: -6px; vertical-align: top; padding-top: 19px; padding-left: 12px;">:</span>

<!-- HOURS -->
<span style="display: inline-block; width: 50px; padding: 10px 0; vertical-align: top; text-align: center;">
<span style="font-size: 20px; display: block;">12</span>
<span style="font-size: 10px; font-weight: 300;">HOURS</span>
</span>

<!-- Colon -->
<span style="display: inline-block; width: 20px; font-size: 20px; position: relative; top: -6px; vertical-align: top; padding-top: 19px; padding-left: 12px;">:</span>

<!-- MINUTES -->
<span style="display: inline-block; width: 50px; padding: 10px 0; vertical-align: top; text-align: center;">
<span style="font-size: 20px; display: block;">21</span>
<span style="font-size: 10px; font-weight: 300;">MINUTES</span>
</span>

<!-- Colon -->
<span style="display: inline-block; width: 20px; font-size: 20px; position: relative; top: -6px; vertical-align: top; padding-top: 19px; padding-left: 12px;">:</span>

<!-- SECONDS -->
<span style="display: inline-block; width: 50px; padding: 10px 0; vertical-align: top; text-align: center;">
<span style="font-size: 20px; display: block;">54</span>
<span style="font-size: 10px; font-weight: 300;">SECONDS</span>
</span>
</p>
HTML;

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
                        'url'        => 'https://images.wpbrandy.com/uploads/yaymail-offer-3-bg.png',
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
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 18px; font-weight: 500;">Back to School Sale<br> Current Offer Ends in</span></p>',
                                            'padding'    => [
                                                'top'    => 20,
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
                                            'rich_text'  => wp_kses_post( $special_text ),
                                            'padding'    => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 50,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Button::get_object_data(
                                        [
                                            'text'       => 'Shop All Products',
                                            'background_color' => '#ffffff00',
                                            'button_background_color' => '#FFFFFF',
                                            'text_color' => '#323DC7',
                                            'align'      => 'left',
                                            'width'      => 43,
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 20,
                                                'right'  => 50,
                                                'left'   => 0,
                                            ],
                                            'border_radius' => [
                                                'top_left' => 10,
                                                'top_right' => 10,
                                                'bottom_right' => 10,
                                                'bottom_left' => 10,
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
