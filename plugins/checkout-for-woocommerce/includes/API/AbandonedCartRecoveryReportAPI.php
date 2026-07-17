<?php

namespace Objectiv\Plugins\Checkout\API;

use DateTime;
use Exception;
use WP_REST_Request;

class AbandonedCartRecoveryReportAPI {
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'checkoutwc/v1',
					'acr/(?P<startDate>\d{4}-\d{2}-\d{2})/(?P<endDate>\d{4}-\d{2}-\d{2})',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_acr_report' ),
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
	 * @throws Exception If the report cannot be retrieved.
	 */
	public function get_acr_report( WP_REST_Request $data ) {
		global $wpdb;

		$startDate          = new DateTime( $data->get_param( 'startDate' ) );
		$endDate            = new DateTime( $data->get_param( 'endDate' ) );
		$table_name         = $wpdb->prefix . 'cfw_acr_carts';
		$decimal_separator  = wc_get_price_decimal_separator();
		$thousand_separator = wc_get_price_thousand_separator();
		$decimals           = wc_get_price_decimals();
		$price_format       = get_woocommerce_price_format();

		$endDate->modify( '+1 day' );

		$carts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE created >= %s AND created <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$startDate->format( 'Y-m-d H:i:s' ),
				$endDate->format( 'Y-m-d H:i:s' )
			)
		);

		$counts = array(
			'new'                 => 0,
			'abandoned'           => 0,
			'lost'                => 0,
			'recovered'           => 0,
			'recoverable_revenue' => 0,
			'recovered_revenue'   => 0,
		);

		/**
		 * Filter the carts for the abandoned cart recovery report stats dashboard.
		 *
		 * @param array  $carts The carts.
		 * @param string $context The calling context
		 * @since 8.2.28
		 */
		$carts = apply_filters( 'cfw_acr_carts', $carts, 'dashboard-stats' );

		foreach ( $carts as $cart ) {
			switch ( $cart->status ) {
				case 'new':
					++$counts['new'];
					$counts['recoverable_revenue'] += $cart->subtotal;
					break;
				case 'abandoned':
					++$counts['abandoned'];
					$counts['recoverable_revenue'] += $cart->subtotal;
					break;
				case 'lost':
					++$counts['lost'];
					break;
				case 'recovered':
					++$counts['recovered'];
					$counts['recovered_revenue'] += $cart->subtotal;
					break;
			}
		}

		$recoverable_revenue = number_format( (float) $counts['recoverable_revenue'], $decimals, $decimal_separator, $thousand_separator );
		$recovered_revenue   = number_format( (float) $counts['recovered_revenue'], $decimals, $decimal_separator, $thousand_separator );

		return array(
			array(
				'name' => __( 'Recoverable Orders', 'checkout-wc' ),
				'stat' => $counts['new'] + $counts['abandoned'],
			),
			array(
				'name' => __( 'Recovered Orders', 'checkout-wc' ),
				'stat' => $counts['recovered'],
			),
			array(
				'name' => __( 'Lost Orders', 'checkout-wc' ),
				'stat' => $counts['lost'],
			),
			array(
				'name' => __( 'Recoverable Revenue', 'checkout-wc' ),
				'stat' => html_entity_decode( sprintf( $price_format, get_woocommerce_currency_symbol(), $recoverable_revenue ) ),
			),
			array(
				'name' => __( 'Recovered Revenue', 'checkout-wc' ),
				'stat' => html_entity_decode( sprintf( $price_format, get_woocommerce_currency_symbol(), $recovered_revenue ) ),
			),
			array(
				'name' => __( 'Recovery Rate', 'checkout-wc' ),
				'stat' => $counts['recovered'] ? round( $counts['recovered'] / ( $counts['new'] + $counts['abandoned'] + $counts['lost'] + $counts['recovered'] ) * 100 ) . '%' : '0%',
			),
		);
	}
}
