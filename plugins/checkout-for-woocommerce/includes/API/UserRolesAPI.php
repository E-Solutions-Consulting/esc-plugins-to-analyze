<?php
namespace Objectiv\Plugins\Checkout\API;

class UserRolesAPI {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}
	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'checkoutwc/v1',
			'/user-roles',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'get_user_roles' ),
			)
		);
	}

	/**
	 * Get all user roles
	 *
	 * @return \WP_REST_Response
	 */
	public function get_user_roles() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		$roles = array();

		// Add guest (not logged in) role
		$roles[] = array(
			'slug' => 'guest',
			'name' => __( 'Guest (Not Logged In)', 'checkout-wc' ),
		);

		foreach ( $wp_roles->get_names() as $role_slug => $role_name ) {
			$roles[] = array(
				'slug' => $role_slug,
				'name' => $role_name,
			);
		}

		return rest_ensure_response( $roles );
	}
}