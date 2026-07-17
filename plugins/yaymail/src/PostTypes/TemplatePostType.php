<?php

namespace YayMail\PostTypes;

use YayMail\Utils\SingletonTrait;

/**
 *  Custom Post Type
 *
 * @method static TemplatePostType get_instance()
 */
class TemplatePostType {
    use SingletonTrait;

    public const POST_TYPE = 'yaymail_template';

    /**
     * Constructor
     */
    protected function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks when class init
     */
    protected function init_hooks() {
        // Register Custom Post Type for YayMail Template
        add_action( 'init', [ $this, 'register_template_post_type' ], 20 );
    }

    public function register_template_post_type() {
        $labels = [
            'name'               => __( 'Email Template', 'yaymail' ),
            'singular_name'      => __( 'Email Template', 'yaymail' ),
            'add_new'            => __( 'Add New Email Template', 'yaymail' ),
            'add_new_item'       => __( 'Add a new Email Template', 'yaymail' ),
            'edit_item'          => __( 'Edit Email Template', 'yaymail' ),
            'new_item'           => __( 'New Email Template', 'yaymail' ),
            'view_item'          => __( 'View Email Template', 'yaymail' ),
            'search_items'       => __( 'Search Email Template', 'yaymail' ),
            'not_found'          => __( 'No Email Template found', 'yaymail' ),
            'not_found_in_trash' => __( 'No Email Template currently trashed', 'yaymail' ),
            'parent_item_colon'  => '',
        ];
        $args   = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => false,
            'query_var'           => true,
            'rewrite'             => true,
            'capability_type'     => self::POST_TYPE,
            'capabilities'        => [],
            'hierarchical'        => false,
            'menu_position'       => null,
            'exclude_from_search' => true,
            'supports'            => [ 'title', 'author', 'thumbnail', 'revisions' ],
        ];
        register_post_type( self::POST_TYPE, $args );
    }
}
