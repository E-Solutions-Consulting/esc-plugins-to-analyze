<?php

namespace Objectiv\Plugins\Checkout\API;

use Exception;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use WP_REST_Request;

class ModalOrderBumpProductFormAPI {
	protected $route                           = 'modal-order-bump-product-form';
	protected $cfw_ob_offer_cancel_button_text = '';

	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'checkoutwc/v1',
					$this->route . '/(?P<bump_id>\d{1,12})',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_product_form' ),
						'permission_callback' => function () {
							return true;
						},
					)
				);
			}
		);
	}

	/**
	 * Get the bumps
	 *
	 * @param WP_REST_Request $data The request data.
	 * @throws Exception If the bump cannot be retrieved.
	 */
	public function get_product_form( WP_REST_Request $data ) {
		$bump = BumpFactory::get( $data->get_param( 'bump_id' ) );

		return rest_ensure_response(
			array(
				'html' => cfw_get_order_bump_product_form( $bump->get_id() ),
			)
		);
	}
}
