<?php
namespace YayMail\Abstracts;

/**
 * BaseShortcode Class
 */
abstract class BaseShortcode {

    public $available_email_ids = [ YAYMAIL_WITH_ORDER_EMAILS ];

    /**
     * Define list of shortcodes
     *
     * Shortcode structure: name, description, group, callback
     *
     * @return array list shortcodes
     */
    abstract public function get_shortcodes();

    protected function __construct() {
        foreach ( $this->available_email_ids as $email_id ) {
            add_action( 'yaymail_' . $email_id . '_register_shortcodes', [ $this, 'add_shortcodes_to_email' ] );
        }
    }

    public function add_shortcodes_to_email( $email ) {
        $email->register_shortcodes( $this->get_shortcodes() );
    }
}
