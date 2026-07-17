<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;
/**
 * SkeletonDivider Elements
 * This element is only used as a divider on email customizers, it should not be displayed on any test mail/ real mail.
 */
class SkeletonDivider extends BaseElement {

    use SingletonTrait;

    protected static $type = 'skeleton_divider';

    public $available_email_ids = [ YAYMAIL_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Skeleton Divider', 'yaymail' ),
            'group'     => 'hidden',
            'available' => true,
            'position'  => -1,
            'data'      => [],
        ];
    }
}
