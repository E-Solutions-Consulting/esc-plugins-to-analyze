<?php
namespace YayMail\Constants;

/**
 * Attributes Data
 */
class AttributesData {
    const TITLE_SIZE_OPTIONS = [
        'default' => 26,
        'small'   => 15,
        'medium'  => 19,
        'large'   => 29,
        'xl'      => 39,
        'xxl'     => 59,
    ];

    const BORDER_DEFAULT = [
        'side'   => 'none',
        'width'  => '1',
        'style'  => 'solid',
        'color'  => YAYMAIL_COLOR_BORDER_DEFAULT,
        'custom' => [
            'top'    => '1',
            'right'  => '1',
            'bottom' => '1',
            'left'   => '1',
        ],
    ];
}
