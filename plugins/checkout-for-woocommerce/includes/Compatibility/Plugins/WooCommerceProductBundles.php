<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class WooCommerceProductBundles extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'WC_PB' );
	}

	public function pre_init() {
		add_action( 'cfw_order_bump_add_to_cart_product_type_bundle', array( $this, 'bundle_add_to_cart' ), 10, 6 );
		add_filter( 'cfw_cart_item_quantity_max_value', array( $this, 'handle_bundled_item_max_quantity' ), 10, 3 );
		add_filter( 'cfw_cart_item_quantity_min_value', array( $this, 'handle_bundled_item_min_quantity' ), 10, 3 );
		add_filter( 'cfw_cart_quantity_input_has_override', array( $this, 'handle_bundled_item_quantity_input_override' ), 10, 2 );
	}

	public function bundle_add_to_cart( $product_id, $quantity, $variation_id, $variation_data, $metadata, $product ) {
		if ( ! $this->is_available() ) {
			return;
		}

		$configuration = $this->get_default_attributes( $product );
		\WC_PB_Cart::instance()->add_bundle_to_cart( $product_id, $quantity, $configuration, $metadata );
	}

	public function get_default_attributes( $product ): array {
		$configuration = array();

		/**
		 * The WC_PB_Cart instance
		 *
		 * @var \WC_PB_Cart $parsed
		 */
		$parsed = \WC_PB_Cart::instance()->parse_bundle_configuration( $product );

		if ( empty( $parsed ) ) {
			return $configuration;
		}

		$configuration = $parsed;

		foreach ( $parsed as $item_id => $item_configuration ) {
			$product = wc_get_product( $item_configuration['product_id'] );

			if ( ! $product->is_type( 'variable' ) ) {
				continue;
			}

			$default_attributes                        = $product->get_default_attributes();
			$configuration[ $item_id ]                 = $item_configuration;
			$configuration[ $item_id ]['attributes']   = array();
			$configuration[ $item_id ]['variation_id'] = cfw_get_variation_id_from_attributes( $product, $default_attributes );

			foreach ( $default_attributes as $attribute_name => $attribute_value ) {
				$configuration[ $item_id ]['attributes'][ wc_variation_attribute_name( $attribute_name ) ] = $attribute_value;
			}
		}

		return $configuration;
	}

	public function handle_bundled_item_max_quantity( $max, $cart_item, $cart_item_key ) {
		if ( ! $this->is_available() ) {
			return $max;
		}

		$cart_item = WC()->cart->cart_contents[ $cart_item_key ];

		$container_item = \wc_pb_get_bundled_cart_item_container( $cart_item );

		if ( $container_item ) {
			$bundled_item_id = $cart_item['bundled_item_id'];
			$bundled_item    = $container_item['data']->get_bundled_item( $bundled_item_id );
			$max             = $bundled_item->get_quantity( 'max' );

			return ! empty( $max ) ? $max : PHP_INT_MAX;
		}

		return $max;
	}

	public function handle_bundled_item_min_quantity( $min, $cart_item, $cart_item_key ) {
		if ( ! $this->is_available() ) {
			return $min;
		}

		$cart_item = WC()->cart->cart_contents[ $cart_item_key ];

		$container_item = \wc_pb_get_bundled_cart_item_container( $cart_item );

		if ( $container_item ) {
			$bundled_item_id = $cart_item['bundled_item_id'];
			$bundled_item    = $container_item['data']->get_bundled_item( $bundled_item_id );

			return $bundled_item->get_quantity( 'min' );
		}

		return $min;
	}

	public function handle_bundled_item_quantity_input_override( $override, $cart_item_key ): bool {
		if ( ! $this->is_available() ) {
			return $override;
		}

		$cart_item = WC()->cart->cart_contents[ $cart_item_key ];

		$container_item = wc_pb_get_bundled_cart_item_container( $cart_item );

		if ( $container_item ) {
			$bundled_item_id = $cart_item['bundled_item_id'];
			$bundled_item    = $container_item['data']->get_bundled_item( $bundled_item_id );

			$min_quantity = $bundled_item->get_quantity( 'min' );
			$max_quantity = $bundled_item->get_quantity( 'max' );

			if ( $min_quantity === $max_quantity ) {
				return true; // mark it as overridden - ie, hide the quantity control
			} else {
				$parent_quantity = $container_item['quantity'];

				$min_qty = $parent_quantity * $min_quantity;
				$max_qty = '' !== $max_quantity ? $parent_quantity * $max_quantity : '';

				if ( ( $max_qty > $min_qty || '' === $max_qty ) && ! $cart_item['data']->is_sold_individually() ) {
					return false; // allow the quantity control to show
				} else {
					return false; // don't allow the quantity control to show;
				}
			}
		}

		return $override;
	}
}
