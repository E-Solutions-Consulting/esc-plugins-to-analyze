<?php
/**
 * Comments Disabler Module
 *
 * Disables all comments site-wide for blog posts.
 * Closes existing open comments via one-time SQL migration.
 * Removes comment support, feed links, and REST endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Comments_Disabler {

    public static function init() {
        $instance = new self();
        $instance->hooks();
    }

    private function hooks() {
        add_filter( 'comments_open',           '__return_false', 20, 2 );
        add_filter( 'pings_open',              '__return_false', 20, 2 );
        add_filter( 'comments_array',          '__return_empty_array', 20, 2 );
        add_action( 'init',                    [ $this, 'remove_comment_support' ] );
        add_action( 'wp_before_admin_bar_render', [ $this, 'remove_admin_bar_comments' ] );
        add_action( 'admin_init',              [ $this, 'redirect_comments_admin_page' ] );
        add_action( 'admin_menu',              [ $this, 'remove_comments_menu' ] );
        add_filter( 'feed_links_show_comments_feed', '__return_false' );
        add_filter( 'rest_endpoints',          [ $this, 'disable_comments_rest_endpoint' ] );
        add_action( 'ah_close_existing_comments', [ $this, 'run_close_existing_comments' ] );
        add_action( 'ah_trash_spam_comments',  [ $this, 'run_trash_spam_comments' ] );
    }

    /**
     * Remove comment and trackback support from all post types.
     */
    public function remove_comment_support() {
        foreach ( get_post_types() as $post_type ) {
            if ( post_type_supports( $post_type, 'comments' ) ) {
                remove_post_type_support( $post_type, 'comments' );
                remove_post_type_support( $post_type, 'trackbacks' );
            }
        }
    }

    /**
     * Remove the Comments node from the WP admin bar.
     */
    public function remove_admin_bar_comments() {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu( 'comments' );
    }

    /**
     * Redirect direct access to edit-comments.php to the dashboard.
     */
    public function redirect_comments_admin_page() {
        global $pagenow;
        if ( $pagenow === 'edit-comments.php' ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }

    /**
     * Remove Comments from the Admin sidebar menu.
     */
    public function remove_comments_menu() {
        remove_menu_page( 'edit-comments.php' );
    }

    /**
     * Remove the /wp/v2/comments REST endpoint.
     */
    public function disable_comments_rest_endpoint( $endpoints ) {
        if ( isset( $endpoints['/wp/v2/comments'] ) ) {
            unset( $endpoints['/wp/v2/comments'] );
        }
        if ( isset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] ) ) {
            unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
        }
        return $endpoints;
    }

    /**
     * Set comment_status = 'closed' and ping_status = 'closed'
     * on all existing posts of type 'post'.
     * Intended to be called once via WP-CLI or admin trigger.
     */
    public function run_close_existing_comments() {
        global $wpdb;

        $updated = $wpdb->query(
            "UPDATE {$wpdb->prefix}posts
             SET comment_status = 'closed', ping_status = 'closed'
             WHERE post_type = 'post'
               AND post_status IN ('publish', 'draft', 'future', 'pending')
               AND (comment_status = 'open' OR ping_status = 'open')"
        );

        return $updated;
    }

    /**
     * Move all spam and trash comments to trash, then permanently delete them.
     * Intended to be called once via WP-CLI or admin trigger.
     */
    public function run_trash_spam_comments() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}comments
             WHERE comment_approved IN ('spam', 'trash')"
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}commentmeta
             WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->prefix}comments)"
        );

        return $deleted;
    }
}