<?php
namespace Objectiv\Plugins\Checkout\Model;

use Objectiv\Plugins\Checkout\Managers\PlanManager;
use WP_Term;

class RulesProcessor {
	protected $rules;

	protected $exclude_bump_id = null;

	protected $cart;

	protected $stockStatusMap = array(
		'inStock'     => 'instock',
		'outOfStock'  => 'outofstock',
		'onBackorder' => 'onbackorder',
	);

	public $lastRuleEvaluated;

	public function __construct( $rules, $exclude_bump_id = null, $cart = null ) {
		$this->rules           = (array) $rules;
		$this->exclude_bump_id = $exclude_bump_id;
		$this->cart            = $cart ?? WC()->cart;
	}

	public function evaluate(): bool {
		// If cart is null, bail on rules
		if ( ! $this->cart ) {
			return false;
		}

		foreach ( $this->rules as $ruleData ) {
			$rule   = new Rule( $ruleData['fieldKey'], $ruleData['subFields'] );
			$result = $this->evaluateRule( $rule );
			if ( ! $result ) {
				// Rule failed, so the overall result is false
				$this->lastRuleEvaluated = $rule;
				return false;
			}
		}

		// All rules passed
		return true;
	}

	protected function evaluateRule( $rule ): bool {
		switch ( $rule->fieldKey ) {
			case 'cartContents':
				return $this->evaluateCartContents( $rule->subFields );
			case 'cartTotalQuantity':
				return $this->evaluateCartTotalQuantity( $rule->subFields );
			case 'cartTotalValue':
				return $this->evaluateCartTotalValue( $rule->subFields );
			case 'cartTotalValueAfterDiscounts':
				return $this->evaluateCartTotalValueAfterDiscounts( $rule->subFields );
			case 'cartProductQuantity':
				return $this->evaluateCartProductQuantity( $rule->subFields );
			case 'cartCategoryQuantity':
				return $this->evaluateCartCategoryQuantity( $rule->subFields );
			case 'cartCoupons':
				return $this->evaluateCartCoupons( $rule->subFields );
			case 'customerTotalSpent':
				return $this->evaluateCustomerTotalSpent( $rule->subFields );
			case 'customerAverageOrderValue':
				return $this->evaluateCustomerAverageOrderValue( $rule->subFields );
			case 'customerTotalOrders':
				return $this->evaluateCustomerTotalOrders( $rule->subFields );
			case 'customerQuantityProductOrdered':
				return $this->evaluateCustomerQuantityProductOrdered( $rule->subFields );
			case 'customerQuantityCategoryOrdered':
				return $this->evaluateCustomerQuantityCategoryOrdered( $rule->subFields );
			case 'customerTimeSinceOrder':
				return $this->evaluateCustomerTimeSinceOrder( $rule->subFields );
			case 'customerTimeSinceProductOrdered':
				return $this->evaluateCustomerTimeSinceProductOrdered( $rule->subFields );
			case 'customerTimeSinceCategoryOrdered':
				return $this->evaluateCustomerTimeSinceCategoryOrdered( $rule->subFields );
			case 'customerDateOfOrder':
				return $this->evaluateCustomerDateOfOrder( $rule->subFields );
			case 'customerDateOfProductOrdered':
				return $this->evaluateCustomerDateOfProductOrdered( $rule->subFields );
			case 'customerDateOfCategoryOrdered':
				return $this->evaluateCustomerDateOfCategoryOrdered( $rule->subFields );
			case 'productInventory':
				return $this->evaluateProductInventory( $rule->subFields );
			case 'productQuantityInStock':
				return $this->evaluateProductQuantityInStock( $rule->subFields );
			case 'shippingCountry':
				return $this->evaluateShippingCountry( $rule->subFields );
			case 'billingCountry':
				return $this->evaluateBillingCountry( $rule->subFields );
			case 'userRole':
				return $this->evaluateUserRole( $rule->subFields );
			default:
				// Unknown fieldKey
				return false;
		}
	}

	protected function evaluateCartContents( $subFields ): bool {
		$condition = $subFields['cartContents'] ?? null;
		$operator  = $subFields['field_1'] ?? null;
		$value     = $subFields['field_2'] ?? null;

		switch ( $condition ) {
			case 'empty':
				return $this->cart->is_empty();
			case 'notEmpty':
				return ! $this->cart->is_empty();
			case 'containsProducts':
				return $this->cartContainsProducts( $value, $operator );
			case 'containsCategories':
				return $this->cartContainsCategories( $value, $operator );
			case 'containsTags':
				return $this->cartContainsTags( $value, $operator );
			case 'containsAttributes':
				return $this->cartContainsAttributes( $value, $operator );
			default:
				return false;
		}
	}

	protected function cartContainsProducts( $products, $operator ): bool {
		// Ensure products is an array to prevent fatal errors
		if ( ! is_array( $products ) || empty( $products ) ) {
			return false;
		}

		$product_ids = array_map(
			function ( $product ) {
				return $product['key'];
			},
			$products
		);

		$cart_product_ids = array_map(
			function ( $cart_item ) use ( $product_ids ) {
				foreach ( $product_ids as $product_id ) {
					// Maybe skip bump items
					if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
						continue;
					}

					if ( $this->cartItemIsProduct( $cart_item, $product_id ) ) {
						return $product_id;
					}
				}

				return false;
			},
			$this->cart->get_cart_contents()
		);

		$product_ids      = array_values( $product_ids );
		$cart_product_ids = array_values( $cart_product_ids );

		switch ( $operator ) {
			case 'atLeastOne':
				return count( array_intersect( $product_ids, $cart_product_ids ) ) > 0;
			case 'all':
				return count( array_diff( $product_ids, $cart_product_ids ) ) === 0;
			case 'none':
				return count( array_intersect( $product_ids, $cart_product_ids ) ) === 0;
			default:
				return false;
		}
	}

	protected function cartItemIsProduct( array $cart_item, int $product_id ): bool {
		if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
			return false;
		}

		if ( $cart_item['product_id'] === $product_id ) {
			return true;
		}

		if ( empty( $cart_item['variation_id'] ) ) {
			return false;
		}

		if ( $cart_item['variation_id'] === $product_id ) {
			return true;
		}

		return wp_get_post_parent_id( $cart_item['variation_id'] ) === $product_id;
	}

	protected function cartContainsCategories( $categories, $operator ): bool {
		// Ensure categories is an array to prevent fatal errors
		if ( ! is_array( $categories ) || empty( $categories ) ) {
			return false;
		}

		$category_ids = array_map(
			function ( $category ) {
				return $category['key'];
			},
			$categories
		);

		$cart_category_ids = array();

		foreach ( $this->cart->get_cart_contents() as $cart_item ) {
			// Maybe skip bump products
			if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				continue;
			}

			$cart_item_terms = wp_get_post_terms( $cart_item['product_id'], 'product_cat' );

			foreach ( $cart_item_terms as $cart_item_term ) {
				$cart_category_ids[] = $cart_item_term->term_id;
			}
		}

		// Remove duplicate category IDs
		$cart_category_ids = array_unique( $cart_category_ids );

		switch ( $operator ) {
			case 'atLeastOne':
				return count( array_intersect( $category_ids, $cart_category_ids ) ) > 0;
			case 'all':
				return count( array_diff( $category_ids, $cart_category_ids ) ) === 0;
			case 'none':
				return count( array_intersect( $category_ids, $cart_category_ids ) ) === 0;
			default:
				return false;
		}
	}

	protected function cartContainsTags( $tags, $operator ): bool {
		// Ensure tags is an array to prevent fatal errors
		if ( ! is_array( $tags ) || empty( $tags ) ) {
			return false;
		}

		$tag_ids = array_map(
			function ( $tag ) {
				return $tag['key'];
			},
			$tags
		);

		$cart_tag_ids = array();

		foreach ( $this->cart->get_cart_contents() as $cart_item ) {
			// Maybe skip bump products
			if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				continue;
			}

			$cart_item_terms = wp_get_post_terms( $cart_item['product_id'], 'product_tag' );

			foreach ( $cart_item_terms as $cart_item_term ) {
				$cart_tag_ids[] = $cart_item_term->term_id;
			}
		}

		// Remove duplicates
		$cart_tag_ids = array_unique( $cart_tag_ids );

		switch ( $operator ) {
			case 'atLeastOne':
				return count( array_intersect( $tag_ids, $cart_tag_ids ) ) > 0;
			case 'all':
				return count( array_diff( $tag_ids, $cart_tag_ids ) ) === 0;
			case 'none':
				return count( array_intersect( $tag_ids, $cart_tag_ids ) ) === 0;
			default:
				return false;
		}
	}

	protected function cartContainsAttributes( $attributes, $operator ): bool {
		// Ensure attributes is an array to prevent fatal errors
		if ( ! is_array( $attributes ) || empty( $attributes ) ) {
			return false;
		}

		$attribute_term_ids = array_map(
			function ( $attribute ) {
				return $attribute['key'];
			},
			$attributes
		);

		$cart_attribute_term_ids = array();

		foreach ( $this->cart->get_cart_contents() as $cart_item ) {
			// Maybe skip bump items
			if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				continue;
			}

			$product = $cart_item['data'] ?? null;

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$product_attributes = $product->get_attributes();

			foreach ( $product_attributes as $attribute_key => $attribute ) {
				// Handle variation attributes (strings) - $attribute_key is taxonomy, $attribute is term slug
				if ( is_string( $attribute ) && taxonomy_exists( $attribute_key ) ) {
					$term = get_term_by( 'slug', $attribute, $attribute_key );

					if ( $term && ! is_wp_error( $term ) ) {
						$cart_attribute_term_ids[] = $term->term_id;
					}

					continue;
				}

				// Handle simple/variable product attributes (objects with is_taxonomy method)
				if ( ! method_exists( $attribute, 'is_taxonomy' ) || ! $attribute->is_taxonomy() ) {
					continue;
				}

				$terms = wp_get_post_terms( $product->get_id(), $attribute->get_name() );

				foreach ( $terms as $term ) {
					$cart_attribute_term_ids[] = $term->term_id;
				}
			}
		}

		// Remove duplicate term IDs
		$cart_attribute_term_ids = array_unique( $cart_attribute_term_ids );

		switch ( $operator ) {
			case 'atLeastOne':
				return count( array_intersect( $attribute_term_ids, $cart_attribute_term_ids ) ) > 0;
			case 'all':
				return count( array_diff( $attribute_term_ids, $cart_attribute_term_ids ) ) === 0;
			case 'none':
				return count( array_intersect( $attribute_term_ids, $cart_attribute_term_ids ) ) === 0;
			default:
				return false;
		}
	}

	protected function evaluateCartTotalQuantity( $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? null );

		$cart_quantity = 0;
		foreach ( $this->cart->get_cart() as $cart_item ) {
			if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				continue;
			}
			$cart_quantity += $cart_item['quantity'];
		}

		return $this->compareValues( $cart_quantity, $operator, $value );
	}

	protected function evaluateCartTotalValue( $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? null );

		$cart_total = $this->get_subtotal();

		return $this->compareValues( $cart_total, $operator, $value );
	}

	protected function evaluateCartTotalValueAfterDiscounts( $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? null );

		$cart_subtotal_after_discounts = $this->get_subtotal_after_discounts();

		return $this->compareValues( $cart_subtotal_after_discounts, $operator, $value );
	}

	protected function evaluateCartProductQuantity( $subFields ): bool {
		$products = $subFields['products'] ?? null;
		$operator = $subFields['operator'] ?? ( $subFields['field_1'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_2'] ?? null );

		if ( ! is_array( $products ) || empty( $products ) ) {
			return false;
		}

		$product_ids = array_map(
			function ( $product ) {
				return $product['key'];
			},
			$products
		);

		$quantity = $this->getCartProductQuantity( $product_ids );

		return $this->compareValues( $quantity, $operator, $value );
	}

	protected function evaluateCartCategoryQuantity( $subFields ): bool {
		$categories = $subFields['categories'] ?? null;
		$operator   = $subFields['operator'] ?? ( $subFields['field_1'] ?? null );
		$value      = $subFields['value'] ?? ( $subFields['field_2'] ?? null );

		if ( ! is_array( $categories ) || empty( $categories ) ) {
			return false;
		}

		$category_ids = array_map(
			function ( $category ) {
				return $category['key'];
			},
			$categories
		);

		$quantity = $this->getCartCategoryQuantity( $category_ids );

		return $this->compareValues( $quantity, $operator, $value );
	}

	protected function evaluateCartCoupons( $subFields ): bool {
		/**
		 * Array
		 * (
		 * [0] => Array
		 * (
		 * [fieldKey] => cartCoupons
		 * [subFields] => Array
		 * (
		 * [hasCoupon] => hasCoupon
		 * [coupons] => foo
		 * )
		 *
		 * )
		 *
		 * [1] => Array
		 * (
		 * [fieldKey] => cartCoupons
		 * [subFields] => Array
		 * (
		 * [hasCoupon] => noCoupon
		 * )
		 *
		 * )
		 *
		 * )
		 */
		$hasCoupon = $subFields['hasCoupon'] ?? null;
		$coupons   = $subFields['coupons'] ?? '';

		$applied_coupons = $this->cart->get_applied_coupons();

		// Normalize applied coupons to lowercase
		$applied_coupons = array_map( 'strtolower', $applied_coupons );

		if ( 'noCoupon' === $hasCoupon ) {
			// Check that no coupons are applied
			return empty( $applied_coupons );
		} elseif ( 'hasCoupon' === $hasCoupon ) {
			// Check that at least one of the specified coupons is applied
			$entered_coupons = array_filter( array_map( 'trim', explode( ',', $coupons ) ) );
			$entered_coupons = array_map( 'strtolower', $entered_coupons );

			if ( empty( $entered_coupons ) ) {
				// If no coupons specified, just return true if there's any coupon applied
				return ! empty( $applied_coupons );
			}

			// Return true if intersection is not empty
			return (bool) array_intersect( $entered_coupons, $applied_coupons );
		}

		return false;
	}

	protected function evaluateCustomerQuantityProductOrdered( $subFields ): bool {
		$products = $subFields['products'] ?? null;
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? null );
		$user_id  = get_current_user_id();

		if ( 0 === $user_id || ! is_array( $products ) || empty( $products ) ) {
			return $this->compareValues( 0, $operator, $value );
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'status'      => array( 'wc-completed' ),
			)
		);

		$ordered_products = cfw_get_product_information_from_orders( $orders );

		if ( empty( $ordered_products ) ) {
			return $this->compareValues( 0, $operator, $value );
		}

		$product_ids_to_check = array_map(
			function ( $product ) {
				return $product['key'];
			},
			$products
		);

		$quantity = 0;

		foreach ( $ordered_products as $product ) {
			if ( in_array( $product['id'], $product_ids_to_check, true ) || in_array( $product['var_id'], $product_ids_to_check, true ) ) {
				$quantity += $product['quantity'];
			}
		}

		return $this->compareValues( $quantity, $operator, $value );
	}

	protected function evaluateCustomerQuantityCategoryOrdered( $subFields ): bool {
		$categories = $subFields['categories'] ?? null;
		$operator   = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value      = $subFields['value'] ?? ( $subFields['field_1'] ?? null );
		$user_id    = get_current_user_id();

		if ( 0 === $user_id || ! is_array( $categories ) || empty( $categories ) ) {
			return $this->compareValues( 0, $operator, $value );
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'status'      => array( 'wc-completed' ),
			)
		);

		$ordered_products = cfw_get_product_information_from_orders( $orders );

		if ( empty( $ordered_products ) ) {
			return $this->compareValues( 0, $operator, $value );
		}

		$category_ids_to_check = array_map(
			function ( $category ) {
				return $category['key'];
			},
			$categories
		);

		$quantity = 0;

		foreach ( $ordered_products as $product ) {
			if ( array_intersect( $product['cats'], $category_ids_to_check ) ) {
				$quantity += $product['quantity'];
			}
		}

		return $this->compareValues( $quantity, $operator, $value );
	}

	protected function evaluateCustomerTimeSinceOrder( $subFields ): bool {
		/**
		 * Subfields ExampleArray
		 *
		 * (
		 * [0] => Array
		 * (
		 * [fieldKey] => customerTimeSinceOrder
		 * [subFields] => Array
		 * (
		 * [orderType] => last
		 * [field_1] => lessThan
		 * [field_2] => 2
		 * )
		 *
		 * )
		 *
		 * )
		 */
		$order_type = $subFields['order_type'] ?? 'first';
		$operator   = $subFields['operator'] ?? ( $subFields['field_1'] ?? null );
		$value      = $subFields['value'] ?? ( $subFields['field_2'] ?? null );

		$customer = WC()->customer;

		if ( ! $customer || ! $customer->get_id() ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $customer->get_id(),
				'status'      => array( 'wc-completed' ),
			)
		);

		if ( empty( $orders ) ) {
			return false;
		}

		$order = reset( $orders );

		switch ( $order_type ) {
			case 'first':
				$order = reset( $orders );
				break;
			case 'last':
				$order = end( $orders );
				break;
		}

		$days_since_order = (int) abs( strtotime( gmdate( 'Y-m-d' ) ) - strtotime( $order->get_date_created() ) ) / DAY_IN_SECONDS;

		return $this->compareValues( $days_since_order, $operator, $value );
	}

	protected function evaluateCustomerTimeSinceProductOrdered( $subFields ): bool {
		/**
		 * Subfields example:
		 * [
		 *     'orderType' => '',
		 *     'products'  => [
		 *         ['key' => 299, 'label' => 'Roti Enamel Pin'],
		 *     ],
		 *     'field_2'   => 'greaterThanEqual',
		 *     'field_3'   => 1,
		 * ]
		 */
		$order_type = $subFields['orderType'] ?? 'first';
		$operator   = $subFields['operator'] ?? ( $subFields['field_2'] ?? null );
		$value      = $subFields['value'] ?? ( $subFields['field_3'] ?? null );
		$products   = $subFields['products'] ?? null;
		$user_id    = get_current_user_id();

		if ( 0 === $user_id || ! is_array( $products ) || empty( $products ) ) {
			return false;
		}

		$product_ids = array_map(
			function ( $product ) {
				return $product['key'];
			},
			$products
		);

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'wc-completed' ),
				'limit'       => -1,
			)
		);

		$dates = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( in_array( $item->get_product_id(), $product_ids, true ) || in_array( $item->get_variation_id(), $product_ids, true ) ) {
					$dates[] = $order->get_date_created()->getTimestamp();
					break; // Found the product in this order
				}
			}
		}

		if ( empty( $dates ) ) {
			return false;
		}

		asort( $dates );

		$order_date       = ( 'first' === $order_type ) ? min( $dates ) : max( $dates );
		$days_since_order = floor( ( time() - $order_date ) / DAY_IN_SECONDS );

		return $this->compareValues( $days_since_order, $operator, $value );
	}

	protected function evaluateCustomerTimeSinceCategoryOrdered( $subFields ): bool {
		/**
		 * Subfields example:
		 * (
		 * [orderType] => first
		 * [categories] => Array
		 * (
		 * [0] => Array
		 * (
		 * [key] => 56
		 * [label] => Gift Card
		 * )
		 *
		 * )
		 *
		 * [field_2] => equal
		 * [field_3] => 0
		 * )
		 */
		$order_type = $subFields['orderType'] ?? 'first';
		$operator   = $subFields['operator'] ?? ( $subFields['field_2'] ?? null );
		$value      = $subFields['value'] ?? ( $subFields['field_3'] ?? null );
		$categories = $subFields['categories'] ?? null;
		$user_id    = get_current_user_id();

		if ( 0 === $user_id || ! is_array( $categories ) || empty( $categories ) ) {
			return false;
		}

		$category_ids = array_map(
			function ( $category ) {
				return $category['key'];
			},
			$categories
		);

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'wc-completed' ),
				'limit'       => -1,
			)
		);

		$dates = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_cats = wc_get_product_term_ids( $item->get_product_id(), 'product_cat' );
				if ( array_intersect( $product_cats, $category_ids ) ) {
					$dates[] = $order->get_date_created()->getTimestamp();
					break; // Found the category in this order
				}
			}
		}

		if ( empty( $dates ) ) {
			return false;
		}

		asort( $dates );

		$order_date       = ( 'first' === $order_type ) ? min( $dates ) : max( $dates );
		$days_since_order = floor( ( time() - $order_date ) / DAY_IN_SECONDS );

		return $this->compareValues( $days_since_order, $operator, $value );
	}

	protected function evaluateCustomerDateOfOrder( $subFields ): bool {
		/**
		 * Subfields example:
		 * (
		 *  [field_0] => last
		 *  [field_1] => equal
		 *  [field_2] => 2024-10-17T12:37:00
		 * )
		 */
		$order_type = $subFields['field_0'] ?? 'first';
		$operator   = $subFields['field_1'] ?? null;
		$value      = $subFields['field_2'] ?? null;
		$user_id    = get_current_user_id();

		if ( 0 === $user_id || ! $operator || ! $value ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'wc-completed' ),
				'limit'       => -1,
			)
		);

		if ( empty( $orders ) ) {
			return false;
		}

		$order_dates = array_map(
			function ( $order ) {
				return $order->get_date_created()->getTimestamp();
			},
			$orders
		);

		asort( $order_dates );

		$order_date      = ( 'first' === $order_type ) ? min( $order_dates ) : max( $order_dates );
		$value_timestamp = strtotime( $value );

		if ( false === $value_timestamp ) {
			return false;
		}

		return $this->compareValues( $order_date, $operator, $value_timestamp );
	}

	protected function evaluateCustomerDateOfProductOrdered( $subFields ): bool {
		/**
		 * Subfields example:
		 * [
		 *     'orderType' => '',
		 *     'products'  => [
		 *         ['key' => 299, 'label' => 'Roti Enamel Pin'],
		 *     ],
		 *     'field_2'   => 'notEqual',
		 *     'field_3'   => '2024-11-05T09:41:11',
		 * ]
		 */
		$order_type = $subFields['orderType'] ?? 'first';
		$operator   = $subFields['field_2'] ?? null;
		$value      = $subFields['field_3'] ?? null;
		$products   = $subFields['products'] ?? null;
		$user_id    = get_current_user_id();

		if ( 0 === $user_id || ! is_array( $products ) || empty( $products ) || ! $operator || ! $value ) {
			return false;
		}

		$product_ids = array_map(
			function ( $product ) {
				return $product['key'];
			},
			$products
		);

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'wc-completed' ),
				'limit'       => -1,
			)
		);

		$dates = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( in_array( $item->get_product_id(), $product_ids, true ) || in_array( $item->get_variation_id(), $product_ids, true ) ) {
					$dates[] = $order->get_date_created()->getTimestamp();
					break;
				}
			}
		}

		if ( empty( $dates ) ) {
			return false;
		}

		asort( $dates );

		$order_date      = ( 'first' === $order_type ) ? min( $dates ) : max( $dates );
		$value_timestamp = strtotime( $value );

		if ( false === $value_timestamp ) {
			return false;
		}

		return $this->compareValues( $order_date, $operator, $value_timestamp );
	}

	protected function evaluateCustomerDateOfCategoryOrdered( $subFields ): bool {
		/**
		 * Subfields example:
		 * [
		 *     'orderType'  => '',
		 *     'categories' => [
		 *         ['key' => 56, 'label' => 'Gift Card'],
		 *     ],
		 *     'field_2'    => 'notEqual',
		 *     'field_3'    => '',
		 * ]
		 */
		$order_type = $subFields['orderType'] ?? 'first';
		$operator   = $subFields['field_2'] ?? null;
		$value      = $subFields['field_3'] ?? null;
		$categories = $subFields['categories'] ?? null;
		$user_id    = get_current_user_id();

		if ( 0 === $user_id || ! is_array( $categories ) || empty( $categories ) || ! $operator || ! $value ) {
			return false;
		}

		$category_ids = array_map(
			function ( $category ) {
				return $category['key'];
			},
			$categories
		);

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'wc-completed' ),
				'limit'       => -1,
			)
		);

		$dates = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_cats = wc_get_product_term_ids( $item->get_product_id(), 'product_cat' );
				if ( array_intersect( $product_cats, $category_ids ) ) {
					$dates[] = $order->get_date_created()->getTimestamp();
					break;
				}
			}
		}

		if ( empty( $dates ) ) {
			return false;
		}

		asort( $dates );

		$order_date      = ( 'first' === $order_type ) ? min( $dates ) : max( $dates );
		$value_timestamp = strtotime( $value );

		if ( false === $value_timestamp ) {
			return false;
		}

		return $this->compareValues( $order_date, $operator, $value_timestamp );
	}

	protected function evaluateProductQuantityInStock( $subFields ): bool {
		/**
		 * Subfields example:
		 * [
		 *     'products' => [
		 *         ['key' => 299, 'label' => 'Roti Enamel Pin'],
		 *     ],
		 *     'field_1' => 'greaterThanEqual',
		 *     'field_2' => 0,
		 * ]
		 */
		$products = $subFields['products'] ?? null;
		$operator = $subFields['field_1'] ?? null;
		$value    = $subFields['field_2'] ?? null;

		if ( ! is_array( $products ) || empty( $products ) || ! $operator || null === $value ) {
			return false;
		}

		$product_ids = array_map(
			function ( $product ) {
				return $product['key'];
			},
			$products
		);

		$total_stock = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			if ( $product->managing_stock() ) {
				$stock_quantity = $product->get_stock_quantity();
				$total_stock   += $stock_quantity ? $stock_quantity : 0;
			} else {
				// If not managing stock, assume 0 for this logic
				$total_stock += 0;
			}
		}

		return $this->compareValues( $total_stock, $operator, $value );
	}

	protected function getCartProductQuantity( $product_ids ) {
		$quantity = 0;

		foreach ( $this->cart->get_cart() as $cart_item ) {
			// Maybe skip bump items
			if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				continue;
			}

			if ( in_array( $cart_item['product_id'], $product_ids, true ) || in_array( $cart_item['variation_id'], $product_ids, true ) ) {
				$quantity += $cart_item['quantity'];
			}
		}

		return $quantity;
	}

	protected function getCartCategoryQuantity( $category_ids ): int {
		$quantity = 0;

		foreach ( $category_ids as $category_id ) {
			$quantity += $this->quantityOfNormalCartItemsInCategory( $category_id );
		}

		return $quantity;
	}

	public function quantityOfNormalCartItemsInCategory( int $needle_category_id ): int {
		$needle_category = get_term_by( 'term_id', $needle_category_id, 'product_cat' );

		if ( ! $needle_category ) {
			return 0;
		}

		$found = 0;

		foreach ( $this->cart->get_cart() as $cart_item ) {
			// Maybe skip bump items
			if ( ! is_null( $this->exclude_bump_id ) && isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				continue;
			}

			$cart_item_terms = wp_get_post_terms( $cart_item['product_id'], 'product_cat' );

			/** @var WP_Term $cart_item_term */
			foreach ( $cart_item_terms as $cart_item_term ) {
				if ( $cart_item_term->term_id === $needle_category_id ) {
					++$found;
				}
			}
		}

		return $found;
	}

	protected function evaluateProductInventory( $subFields ): bool {
		$products  = $subFields['products'] ?? null;
		$statusKey = $subFields['status'] ?? ( $subFields['field_1'] ?? null );

		if ( ! is_array( $products ) || empty( $products ) || ! $statusKey ) {
			return false;
		}

		$status = $this->stockStatusMap[ $statusKey ] ?? null;
		if ( ! $status ) {
			return false;
		}

		$product_ids = array_map(
			function ( $product ) {
				return $product['key'];
			},
			$products
		);

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			$stock_status = $product->get_stock_status();

			if ( $stock_status !== $status ) {
				return false;
			}
		}
		return true;
	}

	protected function evaluateCustomerTotalSpent( $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? null );

		$customer = WC()->customer;

		if ( ! $customer || ! $customer->get_id() ) {
			return false;
		}

		$total_spent = $customer->get_total_spent();

		return $this->compareValues( $total_spent, $operator, $value );
	}

	protected function evaluateCustomerAverageOrderValue( $subFields ): bool {
		$operator  = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value     = $subFields['value'] ?? ( $subFields['field_1'] ?? null );
		$customer  = WC()->customer;
		$avg_spent = 0;

		if ( ! $customer || ! $customer->get_id() ) {
			return $this->compareValues( $avg_spent, $operator, $value );
		}

		$total_spend = $customer->get_total_spent();
		$order_count = $customer->get_order_count();

		if ( $order_count > 0 && $total_spend > 0 ) {
			$avg_spent = $total_spend / $order_count;
		}

		return $this->compareValues( $avg_spent, $operator, $value );
	}

	protected function evaluateCustomerTotalOrders( $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? null );
		$customer = WC()->customer;

		return $this->compareValues( $customer->get_order_count(), $operator, $value );
	}

	protected function evaluateShippingCountry( $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? array() );

		$search_countries = array_column( $value, 'value' );
		$shipping_country = WC()->checkout()->get_value( 'shipping_country' );

		switch ( $operator ) {
			case 'atLeastOne':
				return count( array_intersect( array( $shipping_country ), $search_countries ) ) > 0;
			case 'all':
				return count( array_diff( $search_countries, array( $shipping_country ) ) ) === 0;
			case 'none':
				return count( array_intersect( array( $shipping_country ), $search_countries ) ) === 0;
			default:
				return false;
		}
	}

	protected function evaluateBillingCountry( $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? array() );

		$search_countries = array_column( $value, 'value' );
		$billing_country  = WC()->checkout()->get_value( 'billing_country' );

		switch ( $operator ) {
			case 'atLeastOne':
				return count( array_intersect( array( $billing_country ), $search_countries ) ) > 0;
			case 'all':
				return count( array_diff( $search_countries, array( $billing_country ) ) ) === 0;
			case 'none':
				return count( array_intersect( array( $billing_country ), $search_countries ) ) === 0;
			default:
				return false;
		}
	}

	/**
	 * Evaluate whether current user has one of the specified roles
	 *
	 * @param array $subFields The rule subfields.
	 *
	 * @return bool Whether the rule evaluates to true
	 */
	protected function evaluateUserRole( array $subFields ): bool {
		$operator = $subFields['operator'] ?? ( $subFields['field_0'] ?? null );
		$value    = $subFields['value'] ?? ( $subFields['field_1'] ?? array() );

		// Check if user has required plan level (Pro or higher, level 3) to use this rule
		if ( ! PlanManager::has_premium_plan_or_higher( 'pro' ) ) {
			return true; // Skip rule evaluation for users without Pro plan or higher
		}

		// If user is not logged in, use 'guest' as role
		if ( ! is_user_logged_in() ) {
			$user_roles = array( 'guest' );
		} else {
			$user       = wp_get_current_user();
			$user_roles = $user->roles;
		}

		$search_roles = array_column( $value, 'key' );

		switch ( $operator ) {
			case 'atLeastOne':
				return count( array_intersect( $user_roles, $search_roles ) ) > 0;
			case 'all':
				return count( array_diff( $search_roles, $user_roles ) ) === 0;
			case 'none':
				return count( array_intersect( $user_roles, $search_roles ) ) === 0;
			default:
				return false;
		}
	}

	protected function compareValues( $left, $operator, $right ): bool {
		$left  = floatval( $left );
		$right = floatval( $right );

		switch ( $operator ) {
			case 'equal':
				return $left === $right;
			case 'notEqual':
				return $left !== $right;
			case 'greaterThan':
				return $left > $right;
			case 'lessThan':
				return $left < $right;
			case 'greaterThanEqual':
				return $left >= $right;
			case 'lessThanEqual':
				return $left <= $right;
			case 'contains':
				return strpos( $left, $right ) !== false;
			case 'notContains':
				return strpos( $left, $right ) === false;
			default:
				return false;
		}
	}

	public function get_subtotal() {
		$subtotal = 0;

		foreach ( $this->cart->get_cart() as $cart_item ) {
			if ( is_null( $this->exclude_bump_id ) || ! isset( $cart_item['_cfw_order_bump_id'] ) || (int) $cart_item['_cfw_order_bump_id'] !== (int) $this->exclude_bump_id ) {
				$subtotal += $this->get_cart_item_subtotal( $cart_item['data'], $cart_item['quantity'] );
			}
		}

		return $subtotal;
	}

	public function get_cart_discount_total_excluding_bump() {
		$full_discount = (float) $this->cart->get_cart_discount_total();

		if ( is_null( $this->exclude_bump_id ) ) {
			return $full_discount;
		}

		$bump_discount = 0;
		foreach ( $this->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				$line_subtotal = (float) ( $cart_item['line_subtotal'] ?? 0 );
				$line_total    = (float) ( $cart_item['line_total'] ?? 0 );
				$bump_discount += ( $line_subtotal - $line_total );
			}
		}

		return max( 0, $full_discount - $bump_discount );
	}

	public function get_cart_discount_tax_total_excluding_bump() {
		$full_discount_tax = (float) $this->cart->get_cart_discount_tax_total();

		if ( is_null( $this->exclude_bump_id ) ) {
			return $full_discount_tax;
		}

		$bump_discount_tax = 0;
		foreach ( $this->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['_cfw_order_bump_id'] ) && (int) $cart_item['_cfw_order_bump_id'] === (int) $this->exclude_bump_id ) {
				$line_subtotal_tax = (float) ( $cart_item['line_subtotal_tax'] ?? 0 );
				$line_tax          = (float) ( $cart_item['line_tax'] ?? 0 );
				$bump_discount_tax += ( $line_subtotal_tax - $line_tax );
			}
		}

		return max( 0, $full_discount_tax - $bump_discount_tax );
	}

	public function get_subtotal_after_discounts() {
		$subtotal = $this->get_subtotal();

		if ( $this->cart->display_prices_including_tax() ) {
			$discount = $this->get_cart_discount_total_excluding_bump() + $this->get_cart_discount_tax_total_excluding_bump();
		} else {
			$discount = $this->get_cart_discount_total_excluding_bump();
		}

		return $subtotal - $discount;
	}

	public function get_cart_item_subtotal( \WC_Product $product, int $quantity ) {
		$price = $product->get_price();

		if ( $product->is_taxable() ) {
			if ( $this->cart->display_prices_including_tax() ) {
				$row_price = wc_get_price_including_tax( $product, array( 'qty' => $quantity ) );
			} else {
				$row_price = wc_get_price_excluding_tax( $product, array( 'qty' => $quantity ) );
			}
		} else {
			$row_price = (float) $price * (float) $quantity;
		}

		return $row_price;
	}
}
