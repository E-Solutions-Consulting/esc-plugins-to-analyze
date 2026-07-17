<?php

namespace Objectiv\Plugins\Checkout\API;

use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use WP_REST_Request;
use WP_REST_Response;

class OrderBumpOfferFormPreviewAPI {
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'checkoutwc/v1',
					'order-bump-offer-form-preview/(?P<product_id>\d{1,12})/(?P<bump_id>\d{1,12})',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_preview' ),
						'permission_callback' => function () {
							return current_user_can( 'cfw_manage_order_bumps' );
						},
						'args'                => array(
							'product_id' => array(
								'validate_callback' => function ( $param ) {
									return is_numeric( $param );
								},
							),
							'bump_id'    => array(
								'validate_callback' => function ( $param ) {
									return is_numeric( $param );
								},
							),
						),
					)
				);
			}
		);
	}

	public function get_preview( WP_REST_Request $data ) {
		$product = wc_get_product( $data->get_param( 'product_id' ) );
		$bump    = BumpFactory::get( $data->get_param( 'bump_id' ) );

		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', __( 'Product not found', 'checkout-wc' ), array( 'status' => 404 ) );
		}

		if ( $product->is_type( 'variable' ) && 0 === $product->get_parent_id() ) {
			$output = cfw_get_order_bump_variable_product_form( $product, $bump );
		} else {
			$output = cfw_get_order_bump_regular_product_form( $product, $bump );
		}

		$output = sprintf( '<div class="cfw-order-bump-offer-form-wrap cfw-grid">%s</div>', $output );

		return new WP_REST_Response( $output, 200 );
	}
}
