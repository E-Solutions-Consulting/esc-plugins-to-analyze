<?php

namespace Objectiv\Plugins\Checkout\API;

use DateTime;
use Exception;
use WP_REST_Request;

class AbandonedCartsAPI {
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'checkoutwc/v1',
					'acr/carts/(?P<page>\d{1,4})/(?P<perPage>\d{1,3})/(?P<sortColumn>\w{2,20})/(?P<sortOrder>\w{3,4})/(?P<status>\w{2,20})',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_carts' ),
						'permission_callback' => function () {
							return current_user_can( 'cfw_view_acr_reports' );
						},
					)
				);
			}
		);
	}

	/**
	 * Get the acr report
	 *
	 * @param WP_REST_Request $data The request data.
	 * @throws Exception If the cart cannot be retrieved.
	 */
	public function get_carts( WP_REST_Request $data ): array {
		global $wpdb;

		$page        = $data->get_param( 'page' );
		$per_page    = $data->get_param( 'perPage' );
		$sort_column = $data->get_param( 'sortColumn' );
		$sort_order  = $data->get_param( 'sortOrder' );
		$status      = $data->get_param( 'status' );
		$table_name  = $wpdb->prefix . 'cfw_acr_carts';

		$offset = ( $page - 1 ) * $per_page;

		$where = '';

		if ( 'all' !== $status ) {
			$where = "WHERE status = '{$status}'";
		}

		$carts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} {$where} ORDER BY {$sort_column} {$sort_order} LIMIT {$per_page} OFFSET {$offset}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);

		/**
		 * Filter the carts for the abandoned cart recovery report table.
		 *
		 * @param array  $carts     The carts.
		 * @param string $context   The calling context
		 * @since 8.2.28
		 */
		$carts = apply_filters( 'cfw_acr_carts', $carts, 'table' );

		foreach ( $carts as $index => $cart ) {
			$subtotal = $cart->subtotal;
			$subtotal = wc_price( $subtotal );
			$subtotal = wp_strip_all_tags( $subtotal );
			$subtotal = html_entity_decode( $subtotal );

			$carts[ $index ]->subtotal = $subtotal;
		}

		return array(
			'data'  => $carts,
			'total' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} {$where}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
