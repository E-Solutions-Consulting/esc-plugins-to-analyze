<?php

namespace Objectiv\Plugins\Checkout\Model;

use Exception;
use Objectiv\Plugins\Checkout\Interfaces\ItemInterface;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use WC_Product;

class CartItem implements ItemInterface {
	protected $thumbnail;
	protected $quantity;
	protected $title;
	protected $url;
	protected $subtotal;
	protected $subtotal_raw;
	protected $hide_remove_item = false;
	protected $row_class;
	protected $item_key;
	protected $raw_item;
	protected $product;
	protected $data;
	protected $formatted_data;
	protected $disable_cart_editing_at_checkout;
	protected $disable_cart_editing;
	protected $disable_cart_variation_editing;
	protected $disable_cart_variation_editing_checkout;
	protected $max_quantity;
	protected $min_quantity;
	protected $step;

	/**
	 * @param string $key The item key.
	 * @param array  $item The cart item array from WooCommerce.
	 * @throws Exception If the item cannot be created.
	 */
	public function __construct( string $key, array $item ) {
		$this->item_key = $key;
		$this->raw_item = $item;

		/**
		 * The product
		 *
		 * @var WC_Product $_product
		 */
		$product       = cfw_apply_filters( 'woocommerce_cart_item_product', $item['data'], $item, $key );
		$this->product = $product;

		if ( ! $product instanceof WC_Product ) {
			throw new Exception( 'Invalid product, skipping CartItem creation. Item: ' . var_export( $item, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export, WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$woocommerce_filtered_cart_item_row_class = esc_attr( cfw_apply_filters( 'woocommerce_cart_item_class', 'cart_item', $item, $key ) );
		$this->thumbnail                          = cfw_apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image( 'cfw_cart_thumb' ), $item, $key );
		$this->quantity                           = floatval( $item['quantity'] );
		$this->title                              = cfw_apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $item, $key );
		$this->url                                = get_permalink( $item['product_id'] );
		$this->subtotal                           = cfw_apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $item['quantity'] ), $item, $key );
		$this->subtotal_raw                       = (float) ( $item['line_subtotal'] ?? 0 );
		$this->hide_remove_item                   = cfw_apply_filters( 'woocommerce_cart_item_remove_link', 'PLACEHOLDER', $key ) === '';

		/**
		 * Filter the item row class
		 *
		 * @param string $woocommerce_filtered_cart_item_row_class The filtered row class
		 *
		 * @since 8.0.0
		 */
		$this->row_class      = apply_filters( 'cfw_cart_item_row_class', $woocommerce_filtered_cart_item_row_class, $item );
		$this->data           = $this->get_cart_item_data( $item );
		$this->formatted_data = $this->get_formatted_cart_data();

		$quantity_args = $this->get_quantity_args();

		/**
		 * Filters whether to disable cart editing
		 *
		 * @param int $disable_cart_editing Whether to disable cart editing
		 * @param array $cart_item The cart item
		 * @param string $cart_item_key The cart item key
		 *
		 * @since 7.1.7
		 */
		$this->disable_cart_editing_at_checkout = apply_filters( 'cfw_disable_cart_editing', ! PlanManager::can_access_feature( 'enable_cart_editing' ) || true === $quantity_args['readonly'], $this->raw_item, $key );

		/**
		 * Filters whether to disable cart editing in the side cart
		 *
		 * @param int $disable_cart_editing Whether to disable cart editing in the side cart
		 * @param array $cart_item The cart item
		 * @param string $cart_item_key The cart item key
		 *
		 * @since 9.0.0
		 */
		$this->disable_cart_editing = apply_filters( 'cfw_disable_side_cart_item_quantity_control', true === $quantity_args['readonly'], $this->raw_item, $key );

		/**
		 * Filters whether to disable cart variation editing
		 *
		 * @param bool $disable_cart_variation_editing Whether to disable cart editing
		 * @param array $cart_item The cart item
		 * @param string $cart_item_key The cart item key
		 * @param string $context The calling context
		 *
		 * @since 8.0.0
		 */
		$this->disable_cart_variation_editing = apply_filters(
			'cfw_disable_cart_variation_editing',
			! PlanManager::can_access_feature( 'enable_side_cart' )
			|| SettingsManager::instance()->get_setting( 'allow_side_cart_item_variation_changes' ) !== 'yes'
			|| empty( $item['variation_id'] ),
			$item,
			$key
		);

		/**
		 * Filters whether to disable cart variation editing
		 *
		 * @param bool $disable_cart_variation_editing_checkout Whether to disable cart editing
		 * @param array $cart_item The cart item
		 * @param string $cart_item_key The cart item key
		 * @param string $context The calling context
		 *
		 * @since 10.1.6
		 */
		$this->disable_cart_variation_editing_checkout = apply_filters(
			'cfw_disable_cart_variation_editing_checkout',
			! PlanManager::can_access_feature( 'enable_cart_editing' )
			|| SettingsManager::instance()->get_setting( 'allow_checkout_cart_item_variation_changes' ) !== 'yes'
			|| empty( $item['variation_id'] ),
			$item,
			$key
		);

		$this->max_quantity = (float) $quantity_args['max_value'];
		$this->min_quantity = (float) $quantity_args['min_value'];
		$this->step         = (float) $quantity_args['step'];
	}

	protected function get_cart_item_data( array $cart_item ): array {
		$item_data = array();

		// Variation values are shown only if they are not found in the title as of 3.0.
		// This is because variation titles display the attributes.
		if ( $cart_item['data']->is_type( 'variation' ) && is_array( $cart_item['variation'] ) ) {
			foreach ( $cart_item['variation'] as $name => $value ) {
				$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_pa_', '', urldecode( $name ) ) );

				if ( taxonomy_exists( $taxonomy ) ) {
					// If this is a term slug, get the term's nice name.
					$term = get_term_by( 'slug', $value, $taxonomy );
					if ( ! is_wp_error( $term ) && $term && $term->name ) {
						$value = $term->name;
					}
					$label = wc_attribute_label( $taxonomy );
				} else {
					// If this is a custom option slug, get the options name.
					$value = cfw_apply_filters( 'woocommerce_variation_option_name', $value, null, $taxonomy, $cart_item['data'] );
					$label = wc_attribute_label( str_replace( 'attribute_', '', $name ), $cart_item['data'] );
				}

				// Check the nicename against the title.
				if ( '' === $value || wc_is_attribute_in_product_name( $value, $cart_item['data']->get_name() ) ) {
					continue;
				}

				$item_data[] = array(
					'key'   => $label,
					'value' => $value,
				);
			}
		}

		// Filter item data to allow 3rd parties to add more to the array.
		$item_data = cfw_apply_filters( 'woocommerce_get_item_data', $item_data, $cart_item );

		$prepared_data = array();

		// Format item data ready to display.
		foreach ( $item_data as $key => $data ) {
			// Set hidden to true to not display meta on cart.
			if ( ! empty( $data['hidden'] ) ) {
				unset( $item_data[ $key ] );
				continue;
			}

			$key                   = ! empty( $data['key'] ) ? $data['key'] : $data['name'];
			$display               = ! empty( $data['display'] ) ? $data['display'] : $data['value'];
			$prepared_data[ $key ] = $display;
		}

		return $prepared_data;
	}

	protected function get_formatted_cart_data(): string {
		/**
		 * Filter whether to display cart item data in the expanded format.
		 *
		 * @param bool $expanded Whether to display cart item data in the expanded format.
		 *
		 * @since 5.0.0
		 */
		if ( apply_filters( 'cfw_cart_item_data_expanded', SettingsManager::instance()->get_setting( 'cart_item_data_display' ) === 'woocommerce' ) ) {
			$output = wc_get_formatted_cart_item_data( $this->get_raw_item() );

			return str_replace( ' :', ':', $output );
		}

		$item_data = $this->get_data();

		if ( empty( $item_data ) ) {
			return '';
		}

		$display_outputs = array();

		foreach ( $item_data as $raw_key => $raw_value ) {
			if ( ! is_string( $raw_value ) ) {
				continue;
			}

			$key = wp_kses_post( $raw_key );

			/**
			 * Filter whether to allow HTML in formatted item data value.
			 *
			 * @since 9.0.33
			 * @param bool $allow_html_in_formatted_item_data_value Whether to allow HTML in formatted item data value.
			 */
			$value             = apply_filters( 'cfw_allow_html_in_formatted_item_data_value', false ) ? $raw_value : wp_strip_all_tags( $raw_value );
			$display_outputs[] = "$key: $value";
		}

		return join( ' / ', $display_outputs );
	}


	public function get_thumbnail(): string {
		return $this->thumbnail;
	}

	public function get_quantity(): float {
		return floatval( $this->quantity );
	}

	public function get_title(): string {
		return strval( $this->title );
	}

	public function get_url(): string {
		return strval( $this->url );
	}

	public function get_subtotal(): string {
		return strval( $this->subtotal );
	}

	public function get_subtotal_raw(): float {
		return (float) $this->subtotal_raw;
	}

	public function get_row_class(): string {
		return strval( $this->row_class );
	}

	public function get_item_key(): string {
		return strval( $this->item_key );
	}

	public function get_raw_item() {
		// TODO: Eliminate the necessity of this workaround in a future major version
		return $this->raw_item;
	}

	public function get_product(): WC_Product {
		return $this->product;
	}

	public function get_data(): array {
		return $this->data ?? array();
	}

	public function get_formatted_data(): string {
		return $this->formatted_data;
	}

	private function get_quantity_args(): array {
		$product = $this->get_product();

		if ( $product->is_sold_individually() ) {
			$min_quantity = 1;
			$max_quantity = 1;
		} else {
			$min_quantity = 0;
			$max_quantity = $product->get_max_purchase_quantity();
		}

		$defaults = array(
			'max_value' => cfw_apply_filters( 'woocommerce_quantity_input_max', $max_quantity, $product ),
			'min_value' => cfw_apply_filters( 'woocommerce_quantity_input_min', $min_quantity, $product ),
			'step'      => cfw_apply_filters( 'woocommerce_quantity_input_step', 1, $product ),
			'readonly'  => false,
			'classes'   => array(), // ticket #19016
		);

		$args = cfw_apply_filters( 'woocommerce_quantity_input_args', $defaults, $product );

		// Apply sanity to min/max args - min cannot be lower than 0.
		$args['min_value'] = max( $args['min_value'], 0 );
		$args['max_value'] = 0 < $args['max_value'] ? $args['max_value'] : PHP_INT_MAX;

		// Max cannot be lower than min if defined.
		if ( PHP_INT_MAX !== $args['max_value'] && $args['max_value'] < $args['min_value'] ) {
			$args['max_value'] = $args['min_value'];
		}

		return $args;
	}

	/**
	 * @return mixed|null
	 */
	public function get_disable_cart_editing_at_checkout() {
		return $this->disable_cart_editing_at_checkout;
	}

	/**
	 * @return mixed|null
	 */
	public function get_disable_cart_editing() {
		return $this->disable_cart_editing;
	}

	/**
	 * @return mixed
	 */
	public function get_max_quantity() {
		return $this->max_quantity;
	}

	/**
	 * @return mixed
	 */
	public function get_min_quantity() {
		return $this->min_quantity;
	}

	/**
	 * @return mixed
	 */
	public function get_step() {
		return $this->step;
	}

	/**
	 * @return mixed|null
	 */
	public function get_disable_cart_variation_editing() {
		return $this->disable_cart_variation_editing;
	}

	/**
	 * @return mixed|null
	 */
	public function get_disable_cart_variation_editing_checkout() {
		return $this->disable_cart_variation_editing_checkout;
	}

	public function get_hide_remove_item(): bool {
		return $this->hide_remove_item;
	}
}
