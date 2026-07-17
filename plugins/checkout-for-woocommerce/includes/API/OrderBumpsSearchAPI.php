<?php

namespace Objectiv\Plugins\Checkout\API;

use Objectiv\Plugins\Checkout\Model\Bumps\BumpAbstract;
use WP_REST_Response;

class OrderBumpsSearchAPI {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'checkoutwc/v1',
			'order-bumps',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_order_bumps' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			'checkoutwc/v1',
			'order-bumps/(?P<parent_id>\d+)/variants',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_order_bump_variants' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	public function get_order_bumps( $request ) {
		$search  = $request->get_param( 'term' ) ?? '';
		$exclude = $request->get_param( 'exclude' ) ?? '';

		$args = array(
			'post_type'      => BumpAbstract::get_post_type(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'post_parent'    => 0, // Exclude variants (only show parent order bumps)
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = sanitize_text_field( $search );
		}

		if ( ! empty( $exclude ) ) {
			$exclude_ids          = array_map( 'absint', explode( ',', $exclude ) );
			$args['post__not_in'] = $exclude_ids;
		}

		$posts = get_posts( $args );

		$results = array();
		foreach ( $posts as $post ) {
			$title = trim( $post->post_title );
			if ( empty( $title ) ) {
				$title = sprintf( __( 'No Title #%d', 'checkout-wc' ), $post->ID );
			}
			$results[] = array(
				'id'   => $post->ID,
				'text' => $title,
			);
		}

		return new WP_REST_Response( $results, 200 );
	}

	public function get_order_bump_variants( $request ) {
		$parent_id = $request->get_param( 'parent_id' );
		$search    = $request->get_param( 'term' ) ?? '';

		if ( empty( $parent_id ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		$args = array(
			'post_type'      => BumpAbstract::get_post_type(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'post_parent'    => absint( $parent_id ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$posts = get_posts( $args );

		$results = array();
		foreach ( $posts as $post ) {
			$title = trim( $post->post_title );
			if ( empty( $title ) ) {
				$title = sprintf( __( 'No Title #%d', 'checkout-wc' ), $post->ID );
			}
			$results[] = array(
				'id'   => $post->ID,
				'text' => $title,
			);
		}

		return new WP_REST_Response( $results, 200 );
	}

	public function check_permissions( $request ) {
		return current_user_can( 'cfw_manage_order_bumps' );
	}
}
