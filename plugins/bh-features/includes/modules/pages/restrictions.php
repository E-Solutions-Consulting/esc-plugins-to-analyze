<?php
/**
 * AH Restrictions
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Restrictions' ) ) {

class AH_Restrictions {

    /**
     * Constructor.
     */
    public function __construct() {
        /**
		 * Add Metabox for display page only for logged in
		 * */
		add_action('add_meta_boxes', [$this, 'add_logged_in_only_metabox']);
		add_action('save_post', [$this, 'save_logged_in_only_metabox']);

        /**
		 * Page Restriction for non-logged-in users
		 * */
		add_action('template_redirect', [$this, 'check_page_access']);
    }

    /**
	 * Add metabox Access Restriction to pages
	 * */
	function add_logged_in_only_metabox() {
	    add_meta_box(
	        'logged_in_only_metabox',
	        'User Access Restriction',
	        [$this, 'display_logged_in_only_metabox'],
	        'page',
	        'side',
	        'high'
	    );
	}
	// Display the checkbox in metabox
	function display_logged_in_only_metabox($post) {
	    $logged_in_only = get_post_meta($post->ID, '_logged_in_only', true);
	    wp_nonce_field('save_logged_in_only', 'logged_in_only_nonce');
	    ?>
	    <label>
	        <input type="checkbox" name="logged_in_only" value="1" <?php checked($logged_in_only, '1'); ?> />
	        Show only for logged-in users
	    </label>
	    <p class="description">If checked, non-logged-in users will be redirected to home page.</p>
	    <?php
	}
	// Save the checkbox value
	function save_logged_in_only_metabox($post_id) {
	    if (!isset($_POST['logged_in_only_nonce']) || !wp_verify_nonce($_POST['logged_in_only_nonce'], 'save_logged_in_only')) {
	        return;
	    }
	    if (!current_user_can('edit_post', $post_id)) {
	        return;
	    }
	    if (isset($_POST['logged_in_only']) && $_POST['logged_in_only'] == '1') {
	        update_post_meta($post_id, '_logged_in_only', '1');
	    } else {
	        delete_post_meta($post_id, '_logged_in_only');
	    }
	}

    // Redirect non-logged-in users
	
	function check_page_access() {

		if ( ! is_singular( 'page' ) ) {
			return;
		}

		global $post;
		$logged_in_only = get_post_meta( $post->ID, '_logged_in_only', true );
		$requires_login = ( $logged_in_only == '1' );

		$allow_access = apply_filters(
			'ah_page_access_allow',
			null,            // null = no override yet
			$post->ID,
			get_current_user_id()
		);

		// If filter explicitly allows access
		if ( $allow_access === true ) {
			return;
		}

		// If filter explicitly denies access
		if ( $allow_access === false ) {
			wp_redirect( home_url() );
			exit;
		}

		// Default behavior
		if ( $requires_login && ! is_user_logged_in() ) {
			wp_redirect( home_url() );
			exit;
		}
	}

}

new AH_Restrictions();

}
