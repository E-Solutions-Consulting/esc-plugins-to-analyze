<?php

namespace YayMail\Emails;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;

/**
 * GlobalHeaderFooter Class
 *
 * This is an YayMail element, not an email template. But its customizer page (for editing, saving, etc...) shares the same logic as email template customizer.
 *
 * @method static GlobalHeaderFooter get_instance()
 */
class GlobalHeaderFooter extends BaseEmail {
    use SingletonTrait;

    public $email_types = [ YAYMAIL_GLOBAL_HEADER_FOOTER_ID ];

    protected function __construct() {
        $this->id        = 'yaymail_global_header_footer';
        $this->title     = __( 'Global header footer', 'yaymail' );
        $this->recipient = __( 'Global header footer recipient placeholder', 'yaymail' );
    }

    public function get_default_elements() {
        $default_elements = ElementsLoader::load_elements(
            [
                [
                    'type'       => 'Heading',
                    'attributes' => [
                        'rich_text' => __( 'Email Heading', 'yaymail' ),
                    ],
                ],
                [
                    'type' => 'SkeletonDivider',
                ],
                [
                    'type' => 'Footer',
                ],
            ]
        );

        return $default_elements;
    }

    public function get_all_elements() {
        return parent::get_elements();
    }

    public function get_template_file( $located, $template_name, $args ) {
    }

    public function get_template_path() {
    }
}
