<?php
/**
 * One-Click Upsell Gateway Registry
 *
 * Manages registration and loading of payment gateway integrations for
 * one-click upsells. Mirrors CartFlows' gateway loading pattern.
 *
 * @package Objectiv\Plugins\Checkout\Features\OneClick
 */

namespace Objectiv\Plugins\Checkout\Features\OneClick;

/**
 * Gateway Registry Class
 *
 * @since 10.4.0
 */
class GatewayRegistry {
	/**
	 * Singleton instance
	 *
	 * @since 10.4.0
	 * @var GatewayRegistry
	 */
	private static $instance;

	/**
	 * Loaded gateway instances (cached)
	 *
	 * @since 10.4.0
	 * @var array
	 */
	private $gateway_instances = array();

	/**
	 * Get singleton instance
	 *
	 * @since 10.4.0
	 * @return GatewayRegistry
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 10.4.0
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Get supported gateways
	 *
	 * Returns array of registered gateways. Mirrors CartFlows pattern.
	 * Third parties register gateways via the filter.
	 *
	 * @since 10.4.0
	 *
	 * @return array Gateway array with structure:
	 *               [
	 *                   'gateway_key' => [
	 *                       'class' => 'Class_Name',           // Required
	 *                       'file'  => 'filename.php',         // Optional - for auto-path
	 *                       'path'  => '/full/path/file.php',  // Optional - overrides auto-path
	 *                   ]
	 *               ]
	 */
	public function get_supported_gateways() {
		$supported_gateways = array(
			// Core gateways will be added here in the future
		);

		/**
		 * Filter supported one-click upsell gateways
		 *
		 * Allows third parties to register gateway support.
		 * Mirrors CartFlows' cartflows_offer_supported_payment_gateways filter.
		 *
		 * @since 10.4.0
		 *
		 * @param array $supported_gateways Gateway array keyed by WooCommerce gateway ID.
		 */
		return apply_filters( 'cfw_one_click_supported_gateways', $supported_gateways );
	}

	/**
	 * Load gateway by key
	 *
	 * Loads and instantiates a gateway class. Returns cached instance if already loaded.
	 * Mirrors CartFlows' load_gateway() method exactly.
	 *
	 * @since 10.4.0
	 *
	 * @param string $gateway_key WooCommerce gateway ID (e.g., 'stripe', 'paypal').
	 * @return GatewayInterface|false Gateway instance or false if not supported/failed.
	 */
	public function load_gateway( $gateway_key ) {
		// Return cached instance if already loaded
		if ( isset( $this->gateway_instances[ $gateway_key ] ) ) {
			return $this->gateway_instances[ $gateway_key ];
		}

		$gateways = $this->get_supported_gateways();

		// Check if gateway registered
		if ( ! isset( $gateways[ $gateway_key ] ) ) {
			return false;
		}

		$gateway_data = $gateways[ $gateway_key ];

		// Determine file path - custom 'path' or auto-generated from 'file'
		$gateway_path = isset( $gateway_data['path'] )
			? $gateway_data['path']
			: CFW_PATH . 'includes/Features/OneClick/Gateways/class-cfw-gateway-' . $gateway_data['file'];

		// Check file exists
		if ( ! file_exists( $gateway_path ) ) {
			return false;
		}

		// Include file
		include_once $gateway_path;

		$class_name = $gateway_data['class'];

		// Check if class exists after include
		if ( ! class_exists( $class_name ) ) {
			return false;
		}

		// Call get_instance() - CartFlows pattern
		$this->gateway_instances[ $gateway_key ] = call_user_func( array( $class_name, 'get_instance' ) );

		return $this->gateway_instances[ $gateway_key ];
	}

	/**
	 * Check if gateway is supported
	 *
	 * @since 10.4.0
	 *
	 * @param string $gateway_key WooCommerce gateway ID.
	 * @return bool True if gateway registered, false otherwise.
	 */
	public function is_gateway_supported( $gateway_key ) {
		$gateways = $this->get_supported_gateways();
		return isset( $gateways[ $gateway_key ] );
	}

	/**
	 * Load all registered gateways
	 *
	 * Instantiates all registered gateways. Should be called on wp_loaded.
	 * Mirrors CartFlows' load_required_integrations() method.
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function load_all_gateways() {
		$gateways = $this->get_supported_gateways();

		if ( is_array( $gateways ) ) {
			foreach ( $gateways as $key => $gateway ) {
				$this->load_gateway( $key );
			}
		}
	}
}
