<?php
namespace YayMail\TemplatePatterns\Patterns;

use YayMail\Abstracts\BasePattern;
use YayMail\Elements\Column;
use YayMail\Elements\ColumnLayout;
use YayMail\Elements\Button;
use YayMail\Elements\Image;
use YayMail\Elements\Text;
use YayMail\TemplatePatterns\SectionTemplates\Intro;
use YayMail\Utils\SingletonTrait;

/**
 * Intro 2 Pattern
 */
class Intro2 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'intro_2';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Intro::TYPE;
        $this->position = 10;
        $this->name     = __( 'Intro 2', 'yaymail' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff',
                    'column_spacing'         => 10,
                    'padding'                => [
                        'top'    => 25,
                        'bottom' => 25,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            65,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 18px; text-align: left; margin: 0px;"><span style="font-size: 18px;font-weight: 400;text-transform: uppercase;">New arrivals!</span></p>',
                                            'background_color' => '#ffffff',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 24px; text-align: left; margin: 0px;"><span style="font-size: 24px;"><strong>Let\'s explore your Shopping List for the Upcoming Season</strong></span></p>',
                                            'background_color' => '#ffffff',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Button::get_object_data(
                                        [
                                            'text'       => 'Explore Now',
                                            'font_size'  => 14,
                                            'border_radius' => [
                                                'top_left' => 12,
                                                'top_right' => 12,
                                                'bottom_left' => 12,
                                                'bottom_right' => 12,
                                            ],
                                            'border'     => [
                                                'side'   => 'all',
                                                'width'  => 1,
                                                'style'  => 'solid',
                                                'color'  => '#FFC900',
                                                'custom' => [
                                                    'top'  => 1,
                                                    'right' => 1,
                                                    'bottom' => 1,
                                                    'left' => 1,
                                                ],
                                            ],
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'background_color' => '#FFFFFF',
                                            'button_background_color' => '#ffffff',
                                            'text_color' => '#FFC900',
                                            'width'      => 40,
                                            'align'      => 'left',
                                            'url'        => '[yaymail_site_url]',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            35,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/yaymail-intro-2-img.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'right',
                                            'background_color' => '#ffffff',
                                            'width'   => 242,
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
