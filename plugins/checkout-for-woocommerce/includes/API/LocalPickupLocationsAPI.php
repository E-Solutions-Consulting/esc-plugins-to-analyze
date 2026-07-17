<?php

namespace Objectiv\Plugins\Checkout\API;

use Objectiv\Plugins\Checkout\Features\LocalPickup;

class LocalPickupLocationsAPI {
	const META_ADDRESS        = 'cfw_pl_address';
	const META_INSTRUCTIONS   = 'cfw_pl_instructions';
	const META_ESTIMATED_TIME = 'cfw_pl_estimated_time';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'checkoutwc/v1',
			'pickup-locations(?:/(?P<id>\d+))?',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pickup_locations' ),
				'permission_callback' => array( $this, 'check_woo_api_keys' ),
			)
		);
	}

	public function get_pickup_locations( $request ) {
		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : null;
		$data    = array();

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || LocalPickup::get_post_type() !== $post->post_type ) {
				return new \WP_Error( 'no_post', 'No post found', array( 'status' => 404 ) );
			}

			$data[] = $this->prepare_post_data( $post );

			return new \WP_REST_Response( $data, 200 );
		}

		$args = array(
			'post_type'      => LocalPickup::get_post_type(),
			'post_status'    => 'publish',
			'posts_per_page' => 10,  // Adjust this as needed
		);

		$posts = get_posts( $args );

		foreach ( $posts as $post ) {
			$data[] = $this->prepare_post_data( $post );
		}

		return new \WP_REST_Response( $data, 200 );
	}

	private function prepare_post_data( $post ): array {
		return array(
			'title'          => $post->post_title,
			'address'        => get_post_meta( $post->ID, self::META_ADDRESS, true ),
			'instructions'   => get_post_meta( $post->ID, self::META_INSTRUCTIONS, true ),
			'estimated_time' => get_post_meta( $post->ID, self::META_ESTIMATED_TIME, true ),
		);
	}

	public function check_woo_api_keys( \WP_REST_Request $request ) {
		$consumer_key    = $request->get_header( 'x_wc_api_consumer_key' );
		$consumer_secret = $request->get_header( 'x_wc_api_consumer_secret' );

		if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
			return new \WP_Error( 'invalid_key', 'API keys are missing.', array( 'status' => 401 ) );
		}

		global $wpdb;

		$key = $wpdb->get_row( $wpdb->prepare( "SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s", wc_api_hash( $consumer_key ) ) );

		if ( empty( $key ) || ! hash_equals( $key->consumer_secret, $consumer_secret ) ) {
			return new \WP_Error( 'invalid_key', 'API keys are invalid.', array( 'status' => 401 ) );
		}

		return true;
	}
}
