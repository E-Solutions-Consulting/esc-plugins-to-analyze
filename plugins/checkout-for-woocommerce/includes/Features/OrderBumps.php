<?php

namespace Objectiv\Plugins\Checkout\Features;

use Exception;
use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Features\OneClick\GatewayRegistry;
use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;
use Objectiv\Plugins\Checkout\Interfaces\SettingsGetterInterface;
use Objectiv\Plugins\Checkout\Managers\AssetManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Model\Bumps\BumpAbstract;
use function WordpressEnqueueChunksPlugin\get as cfwChunkedScriptsConfigGet;
use WC_Cart;
use WC_Order;
use WC_Order_Item;

class OrderBumps extends FeaturesAbstract {
	public function init() {
		parent::init();

		BumpAbstract::init( PageAbstract::get_parent_slug() );

		add_action( 'init', array( $this, 'register_order_bump_meta' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'force_block_editor' ), 200, 2 );
		add_filter( 'classic_editor_enabled_editors_for_post_type', array( $this, 'enable_gutenberg_for_order_bumps' ), 10, 2 );

		// Hook post-purchase functionality to cfw_permissioned_init (only runs if cfw_is_enabled())
		add_action( 'cfw_permissioned_init', array( $this, 'init_post_purchase_hooks' ) );
	}

	/**
	 * Initialize post-purchase hooks
	 * This runs on cfw_permissioned_init which only fires if cfw_is_enabled()
	 *
	 * @return void
	 */
	public function init_post_purchase_hooks(): void {
		// Only initialize if the feature is active
		if ( ! $this->is_active() ) {
			return;
		}

		// Session initialization - already universal
		add_action( 'woocommerce_new_order', array( $this, 'init_post_purchase_offers' ), 1, 2 );

		// Output root div using WooCommerce hook (works everywhere)
		add_action( 'woocommerce_thankyou', array( $this, 'output_post_purchase_root_universal' ), 5 );

		// ALWAYS enqueue separate post-purchase assets on order-received pages
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_post_purchase_assets' ), 20 );
	}

	protected function run_if_cfw_is_enabled() {
		// Store line item bump information and record order stats
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'handle_order_meta' ) );
		add_action(
			'woocommerce_checkout_create_order_line_item',
			array(
				$this,
				'save_bump_meta_to_order_items',
			),
			10,
			4
		);

		// Output bumps placeholder
		add_action( 'cfw_checkout_cart_summary', array( $this, 'output_cart_summary_bumps' ), 41 );
		add_action( 'cfw_checkout_cart_summary', array( $this, 'output_checkout_cart_summary_bumps' ), 41 );
		add_action( 'cfw_checkout_payment_method_tab', array( $this, 'output_payment_tab_bumps' ), 38 );
		add_action( 'cfw_checkout_customer_info_tab', array( $this, 'output_above_express_checkout_bumps' ), 8 );
		add_action( 'cfw_checkout_customer_info_tab', array( $this, 'output_bottom_information_tab_bumps' ), 55 );
		add_action( 'cfw_checkout_shipping_method_tab', array( $this, 'output_bottom_shipping_tab_bumps' ), 25 );
		add_action( 'cfw_checkout_payment_method_tab', array( $this, 'output_below_complete_order_button_bumps' ), 55 );
		add_action( 'cfw_checkout_payment_method_tab', array( $this, 'output_mobile_bumps' ), 38 );

		// Add to Cart
		add_action( 'cfw_update_checkout_after_customer_save', array( $this, 'handle_adding_order_bump_to_cart' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_auto_add_bumps' ) );
		add_action( 'cfw_cart_updated', array( $this, 'maybe_auto_add_bumps' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_match_quantity' ) );
		add_action( 'cfw_cart_updated', array( $this, 'maybe_match_quantity' ) );
		add_filter( 'woocommerce_update_cart_action_cart_updated', array( $this, 'maybe_match_quantity' ) );

		// Pricing overrides
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'sync_bump_cart_prices' ), 100000 );
		add_filter( 'cfw_cart_item_discount', array( $this, 'show_bump_discount_on_cart_item' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_product', array( $this, 'correct_cart_bump_subtotals' ), 10, 2 );

		// Prevent cart editing (maybe)
		add_filter(
			'cfw_disable_cart_editing',
			array(
				$this,
				'maybe_disable_cart_editing',
			),
			10,
			2
		);

		add_filter(
			'cfw_disable_side_cart_item_quantity_control',
			array(
				$this,
				'maybe_disable_cart_editing',
			),
			10,
			2
		);

		add_filter(
			'woocommerce_cart_item_quantity',
			array(
				$this,
				'maybe_disable_cart_editing_on_cart_page',
			),
			10,
			3
		);

		// Admin filters
		add_action( 'restrict_manage_posts', array( $this, 'admin_filter_select' ), 60 );

		// Handle invalidations
		add_action( 'woocommerce_cart_item_removed', array( $this, 'maybe_remove_bump_from_cart' ), 10 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_remove_bump_from_cart' ), 10 );
		add_action( 'cfw_cart_updated', array( $this, 'maybe_remove_bump_from_cart' ), 10 );

		// Add filter to queries on admin orders screen to filter on order type. To avoid WC overriding our query args, we have to hook at 11+
		add_filter( 'request', array( $this, 'filter_orders_query' ), 11 );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add place order bumps
		add_filter( 'cfw_checkout_data', array( $this, 'add_after_checkout_bumps' ), 10, 1 );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 1 );

		// After Add to Cart Actions
		add_action( 'cfw_order_bump_added_to_cart', array( $this, 'after_add_to_cart_actions' ), 10 );
		add_action( 'woocommerce_ajax_added_to_cart', array( $this, 'maybe_run_after_add_to_cart_actions' ), 10 );
		add_filter(
			'woocommerce_shipping_free_shipping_is_available',
			array(
				$this,
				'maybe_enable_free_shipping',
			),
			10,
			1
		);

		add_filter(
			'cfw_disable_cart_variation_editing',
			array(
				$this,
				'maybe_disable_cart_item_variation_editing',
			),
			10,
			2
		);

		add_filter(
			'cfw_disable_cart_variation_editing_checkout',
			array(
				$this,
				'maybe_disable_cart_item_variation_editing',
			),
			10,
			2
		);

		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'exclude_bumps_from_discounts' ), 10, 4 );

		// Output placeholder div for react place order bumps
		add_action( 'cfw_checkout_after_main_container', array( $this, 'output_after_checkout_bumps_root' ) );

		// Add bumps to data
		add_filter( 'cfw_checkout_data', array( $this, 'add_bumps_to_checkout_data' ), 10, 1 );

		// Special handler for adding bumps to cart
		add_action( 'woocommerce_add_to_cart_handler', array( $this, 'intercept_add_to_cart_handler' ) );
		add_action( 'woocommerce_add_to_cart_handler_cfw_upsell_bump_add_to_cart_handler', array( $this, 'add_bump_to_cart' ) );

		add_filter(
			'woocommerce_hidden_order_itemmeta',
			function ( $meta_keys ) {
				$meta_keys[] = '_cfw_order_bump_id';
				$meta_keys[] = '_cfw_one_click_upsell';

				return $meta_keys;
			}
		);
	}

	public function unhook_order_bumps_output() {
		remove_action( 'cfw_checkout_cart_summary', array( $this, 'output_cart_summary_bumps' ), 41 );
		remove_action( 'cfw_checkout_payment_method_tab', array( $this, 'output_payment_tab_bumps' ), 38 );
		remove_action( 'cfw_checkout_payment_method_tab', array( $this, 'output_mobile_bumps' ), 38 );
		remove_action( 'woocommerce_update_order_review_fragments', array( $this, 'add_bumps_to_update_checkout' ) );
	}

	/**
	 * Handle order meta
	 *
	 * @param int $order_id The order ID.
	 *
	 * @throws Exception If the order meta cannot be handled.
	 */
	public function handle_order_meta( int $order_id ) {
		$purchased_bump_ids = $this->get_purchased_bump_ids( $order_id );

		if ( ! empty( $purchased_bump_ids ) ) {
			$order = \wc_get_order( $order_id );

			$order->add_meta_data( 'cfw_has_bump', true );

			foreach ( $purchased_bump_ids as $purchased_bump_id ) {
				$order->add_meta_data( 'cfw_bump_' . $purchased_bump_id, true );
			}

			$order->save();
		}

		$this->record_bump_stats( $purchased_bump_ids );
	}

	/**
	 * Record bump stats
	 *
	 * @param array $purchased_bump_ids The purchased bump IDs.
	 * @throws Exception If the bump stats cannot be recorded.
	 */
	public function record_bump_stats( array $purchased_bump_ids ) {
		foreach ( $purchased_bump_ids as $purchased_bump_id ) {
			BumpFactory::get( $purchased_bump_id )->record_purchased();
		}

		$raw_displayed_bump_ids = wp_unslash( $_POST['cfw_displayed_order_bump'] ?? array() ); // phpcs:ignore
		$displayed_bump_ids     = array_unique( $raw_displayed_bump_ids );

		foreach ( $displayed_bump_ids as $displayed_bump_id ) {
			BumpFactory::get( (int) $displayed_bump_id )->record_displayed();
		}
	}

	protected function get_purchased_bump_ids( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array();
		}

		$items = $order->get_items();

		if ( empty( $items ) ) {
			return array();
		}

		$ids = array();

		foreach ( $items as $item ) {
			$bump_id = $item->get_meta( '_cfw_order_bump_id', true );

			if ( ! $bump_id ) {
				continue;
			}

			$ids[] = $bump_id;
		}

		return $ids;
	}

	/**
	 * Output cart summary bumps
	 */
	public function output_cart_summary_bumps() {
		echo '<div id="cfw_bumps_below_cart_items"></div>';
	}

	/**
	 * Output cart summary bumps
	 */
	public function output_checkout_cart_summary_bumps() {
		echo '<div id="cfw_bumps_below_checkout_cart_items"></div>';
	}

	/**
	 * Output payment tab bumps
	 */
	public function output_payment_tab_bumps() {
		echo '<div id="cfw_bumps_above_terms_and_conditions"></div>';
	}

	/**
	 * Output above express checkout bumps
	 */
	public function output_above_express_checkout_bumps() {
		echo '<div id="cfw_bumps_above_express_checkout"></div>';
	}

	/**
	 * Output bottom information tab bumps
	 */
	public function output_bottom_information_tab_bumps() {
		echo '<div id="cfw_bumps_bottom_information_tab"></div>';
	}

	/**
	 * Output bottom shipping tab bumps
	 */
	public function output_bottom_shipping_tab_bumps() {
		echo '<div id="cfw_bumps_bottom_shipping_tab"></div>';
	}

	/**
	 * Output below complete order button bumps
	 */
	public function output_below_complete_order_button_bumps() {
		echo '<div id="cfw_bumps_below_complete_order_button"></div>';
	}

	/**
	 * Output mobile bumps
	 */
	public function output_mobile_bumps() {
		echo '<div id="cfw_bumps_mobile_output"></div>';
	}

	/**
	 * Handle adding order bump to cart
	 *
	 * @param array $post_data The post data.
	 *
	 * @return bool
	 */
	public function handle_adding_order_bump_to_cart( $post_data ): bool {
		// turn the string of post data into an array
		// We don't use the $_POST object because $post_data here is preprocessed for us.
		if ( ! is_array( $post_data ) ) {
			parse_str( $post_data, $post_data );
		}

		$bump_ids = $post_data['cfw_order_bump'] ?? array();

		if ( empty( $bump_ids ) ) {
			return false;
		}

		foreach ( $bump_ids as $bump_id ) {
			BumpFactory::get( $bump_id )->add_to_cart( WC()->cart );

			/**
			 * Action hook to run after an order bump is added to the cart
			 *
			 * @param int $bump_id The ID of the order bump
			 *
			 * @since 8.0.0
			 */
			do_action( 'cfw_order_bump_added_to_cart', $bump_id );
		}

		return true;
	}

	/**
	 * @throws Exception If the bumps cannot be auto-added.
	 */
	public function maybe_auto_add_bumps() {
		$bumps = BumpFactory::get_all( 'publish' );

		// Filter bumps that are not displayable or should not be auto-added early
		$bumps = array_filter(
			$bumps,
			function ( $bump ) {
			return $bump->is_displayable() && $bump->should_be_auto_added() && $bump->get_offer_product()->get_type() !== 'variable';
			}
		);

		if ( empty( $bumps ) ) {
			return;
		}

		// Create a set of removed bump IDs for quick lookup
		$removed_bump_ids = array_reduce(
			WC()->cart->get_removed_cart_contents(),
			function ( $carry, $item ) {
				if ( isset( $item['_cfw_order_bump_id'] ) ) {
					$carry[ $item['_cfw_order_bump_id'] ] = true;
				}

			return $carry;
			},
			array()
		);

		foreach ( $bumps as $bump ) {
			$bump_id = $bump->get_id();

			// Check if the bump ID is in the set of removed bump IDs
			if ( isset( $removed_bump_ids[ $bump_id ] ) && ! apply_filters( 'cfw_order_bumps_allow_auto_add_after_removal', false, $bump_id ) ) {
				continue;
			}

			// Skip if bump is already in cart to prevent quantity multiplication
			// when woocommerce_add_to_cart fires recursively
			// @since 11.0.1
			if ( $bump->is_in_cart() ) {
				continue;
			}

			$bump->add_to_cart( WC()->cart );
		}
	}

	/**
	 * @param bool $cart_updated Whether the cart has been updated.
	 *
	 * @return bool
	 */
	public function maybe_match_quantity( $cart_updated ) {
		$bumps = array_filter(
			BumpFactory::get_all( 'publish' ),
			function ( $bump ) {
				return $bump->is_in_cart() && $bump->should_match_offer_product_quantity() && ! $bump->is_valid_upsell();
			}
		);

		if ( empty( $bumps ) ) {
			return $cart_updated;
		}

		// Map cart items to bump IDs for direct access
		$cart_item_map = array();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['_cfw_order_bump_id'] ) ) {
				$cart_item_map[ $cart_item['_cfw_order_bump_id'] ] = $cart_item_key;
			}
		}

		/** @var BumpAbstract $bump */
		foreach ( $bumps as $bump ) {
			$bump_id = $bump->get_id();

			if ( isset( $cart_item_map[ $bump_id ] ) ) {
				$cart_item_key = $cart_item_map[ $bump_id ];
				$quantity      = $bump->quantity_of_normal_product_in_cart( $bump->get_match_quantity_product()->get_id() );

				if ( WC()->cart->get_cart()[ $cart_item_key ]['quantity'] !== $quantity ) {
					WC()->cart->set_quantity( $cart_item_key, $quantity, true );
				}
			}
		}

		return $cart_updated;
	}

	public function after_add_to_cart_actions( $bump_id ) {
		$bump               = BumpFactory::get( $bump_id );
		$products_to_remove = $bump->get_products_to_remove();

		if ( ! empty( $products_to_remove ) ) {
			foreach ( $products_to_remove as $product_to_remove ) {
				cfw_remove_product_from_cart( $product_to_remove );
			}
		}
	}

	public function maybe_run_after_add_to_cart_actions() {
		if ( empty( $_POST['cfw_ob_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$this->after_add_to_cart_actions( intval( $_POST['cfw_ob_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public function maybe_enable_free_shipping( $is_available ): bool {
		if ( $is_available ) {
			return (bool) $is_available;
		}

		// Check cart for bumps that enable free shipping
		foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
			$bump                = BumpFactory::get( $cart_item['_cfw_order_bump_id'] ?? 0 );
			$apply_free_shipping = get_post_meta( $bump->get_id(), 'cfw_ob_apply_free_shipping', true ) === 'yes';

			if ( $apply_free_shipping ) {
				return true;
			}
		}

		return (bool) $is_available;
	}

	/**
	 * Sync bump cart prices
	 *
	 * @param WC_Cart $cart The cart.
	 *
	 * @throws Exception If the cart prices cannot be synced.
	 */
	public function sync_bump_cart_prices( WC_Cart $cart ) {
		foreach ( $cart->get_cart_contents() as $cart_item ) {
			$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] ?? 0 );

			if ( ! $bump->is_cart_bump_valid() ) {
				continue;
			}

			/**
			 * Filter to determine if cart item pricing should be skipped
			 *
			 * @param bool  $skip Whether to skip this cart item
			 * @param array $cart_item The cart item data
			 *
			 * @since 10.2.0
			 */
			if ( apply_filters( 'cfw_skip_bump_cart_item_pricing', false, $cart_item ) ) {
				continue;
			}

			$bump_price = $bump->get_price(
				/**
				 * Filter the context for the bump price
				 *
				 * @param string $context The context for the bump price
				 * @param array $cart_item The cart item
				 * @param BumpInterface $bump The bump
				 *
				 * @since 8.1.6
				 */
				apply_filters( 'cfw_order_bump_get_price_context', 'cart', $cart_item, $bump ),
				! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0
			);

			$cart_product = $cart_item['data'] ?? false;

			if ( ! ( $cart_product instanceof \WC_Product ) ) {
				continue;
			}

			$cart_product->set_price( $bump_price );
		}

		WC()->cart->set_session();
	}

	/**
	 * Save bump meta to order items
	 *
	 * @param WC_Order_Item $item The item.
	 * @param string        $cart_item_key The cart item key.
	 * @param array         $values The values.
	 *
	 * @throws Exception If the bump meta cannot be saved to the order items.
	 */
	public function save_bump_meta_to_order_items( $item, $cart_item_key, array $values ) {
		$bump = BumpFactory::get( $values['_cfw_order_bump_id'] ?? 0 );

		$bump->add_bump_meta_to_order_item( $item, $values );
	}

	/**
	 * Show bump discount on cart item
	 *
	 * @param string $price_html The price HTML.
	 * @param array  $cart_item The cart item.
	 *
	 * @return string
	 */
	public function show_bump_discount_on_cart_item( string $price_html, array $cart_item ): string {
		/**
		 * Filter to determine if cart item discount HTML should be skipped
		 *
		 * @param bool  $skip Whether to skip this cart item discount HTML
		 * @param array $cart_item The cart item data
		 *
		 * @since 10.2.0
		 */
		if ( apply_filters( 'cfw_skip_bump_cart_item_discount_html', false, $cart_item ) ) {
			return $price_html;
		}

		$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] ?? 0 );

		return $bump->get_cfw_cart_item_discount( $price_html, $cart_item );
	}

	/**
	 * @throws Exception If the select cannot be filtered.
	 */
	public function admin_filter_select() {
		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return;
		}

		$all_bumps = BumpFactory::get_all();

		if ( count( $all_bumps ) === 0 ) {
			return;
		}

		?>
		<select name="cfw_order_bump_filter" id="cfw_order_bump_filter">
			<option value=""><?php esc_html_e( 'All orders', 'woocommerce-subscriptions' ); ?></option>
			<?php
			$bump_filters = array(
				'any' => __( 'Contains Any Order Bump', 'checkout-wc' ),
			);

			foreach ( $all_bumps as $bump ) {
				$bump_filters[ $bump->get_id() ] = sprintf(
					/* translators: %s: Order bump title */
					__( 'Has Bump: %s', 'checkout-wc' ),
					$bump->get_title()
				);
			}

			foreach ( $bump_filters as $bump_key => $bump_filter_description ) {
				echo '<option value="' . esc_attr( $bump_key ) . '"';

				if ( ! empty( $_GET['cfw_order_bump_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					selected( $bump_key, sanitize_text_field( wp_unslash( $_GET['cfw_order_bump_filter'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}

				echo '>' . esc_html( $bump_filter_description ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * Filter orders query
	 *
	 * @param array $vars The query vars.
	 *
	 * @return array
	 */
	public static function filter_orders_query( $vars ): array {
		global $typenow;

		$filter_setting = sanitize_text_field( wp_unslash( $_GET['cfw_order_bump_filter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$should_filter  = 'shop_order' === $typenow && ! empty( $filter_setting );

		if ( ! $should_filter ) {
			return $vars;
		}

		$vars['meta_query']['relation'] = 'AND';

		$key = 'any' === $filter_setting ? 'cfw_has_bump' : 'cfw_bump_' . (int) $filter_setting;

		$vars['meta_query'][] = array(
			'key'     => $key,
			'compare' => 'EXISTS',
		);

		return $vars;
	}

	public function maybe_remove_bump_from_cart() {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['_cfw_order_bump_id'] ) ) {
				continue;
			}

			$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] );

			if ( ! $bump->is_cart_bump_valid() && $bump->get_item_removal_behavior() !== 'keep' ) {
				WC()->cart->remove_cart_item( $cart_item_key );
			}
		}

		WC()->cart->set_session();
	}

	public function maybe_disable_cart_editing( $result, $cart_item ): bool {
		if ( empty( $cart_item['_cfw_order_bump_id'] ) ) {
			return $result;
		}

		$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] );

		return ! $bump->can_quantity_be_updated();
	}

	public function maybe_disable_cart_editing_on_cart_page( $html_result, $cart_item_key, $cart_item ) {
		if ( ! is_cart() ) {
			return $html_result;
		}

		if ( empty( $cart_item['_cfw_order_bump_id'] ) ) {
			return $html_result;
		}

		$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] );

		if ( ! $bump->can_quantity_be_updated() ) {
			return $cart_item['quantity'];
		}

		return $html_result;
	}

	public function correct_cart_bump_subtotals( $product, $cart_item ) {
		$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] ?? 0 );

		if ( ! $bump->is_cart_bump_valid() ) {
			return $product;
		}

		// Get variation id (0 for non-variable products)
		$variation_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;

		$product->set_price(
			$bump->get_price(
				/**
				 * Filter the context for the bump price
				 *
				 * @param string $context The context for the bump price
				 * @param array $cart_item The cart item
				 * @param BumpInterface $bump The bump
				 *
				 * @since 8.1.6
				 */
				apply_filters( 'cfw_order_bump_get_price_context', 'cart', $cart_item, $bump ),
				$variation_id
			)
		);

		return $product;
	}

	public function enqueue_scripts() {
		// Enqueue variation scripts.
		wp_enqueue_script( 'wc-add-to-cart-variation' );
	}

	/**
	 * Get place order bumps
	 *
	 * @param array $theObject The checkout data object.
	 * @throws Exception If the place order bumps cannot be retrieved.
	 */
	public function add_after_checkout_bumps( $theObject ): array {
		$theObject['after_checkout_bumps'] = array();

		if ( WC()->cart && ! WC()->cart->needs_payment() ) {
			return $theObject;
		}

		foreach ( BumpFactory::get_all() as $bump ) {
			if ( 'complete_order' !== $bump->get_display_location() ) {
				continue;
			}

			$display_bump = $bump->is_displayable() && $bump->is_published();

			/**
			 * Filter whether to display the bump
			 *
			 * @param bool $display_bump Whether to display the bump
			 *
			 * @since 8.0.0
			 */
			$filtered_display_bump = apply_filters( 'cfw_display_bump', $display_bump, $bump, 'complete_order' );

			if ( ! $filtered_display_bump ) {
				continue;
			}

			$theObject['after_checkout_bumps'][ $bump->get_id() ] = array(
				'full_screen' => get_post_meta( $bump->get_id(), 'cfw_ob_full_screen', true ) === 'yes',
			);
		}

		$max_after_checkout_bumps = SettingsManager::instance()->get_setting( 'max_after_checkout_bumps' );
		$max_after_checkout_bumps = $max_after_checkout_bumps < 0 ? 999 : $max_after_checkout_bumps;
		$max_after_checkout_bumps = min( $max_after_checkout_bumps, count( $theObject['after_checkout_bumps'] ) );

		// Return only the allowed amount of place order bumps
		$theObject['after_checkout_bumps'] = array_slice( $theObject['after_checkout_bumps'], 0, $max_after_checkout_bumps, true );

		return $theObject;
	}

	public function add_cart_item_data( $data ) {
		if ( empty( $_POST['cfw_ob_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $data;
		}

		$data['_cfw_order_bump_id'] = (int) $_POST['cfw_ob_id']; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return $data;
	}

	public function maybe_disable_cart_item_variation_editing( $disable, $cart_item ): bool {
		if ( empty( $cart_item['_cfw_order_bump_id'] ) ) {
			return $disable;
		}

		$bump = BumpFactory::get( $cart_item['_cfw_order_bump_id'] );

		if ( ! $bump->is_cart_bump_valid() ) {
			return $disable;
		}

		$variation_parent = $bump->get_offer_product()->is_type( 'variable' ) && 0 === $bump->get_offer_product()->get_parent_id() && 'no' === get_post_meta( $bump->get_id(), 'cfw_ob_enable_auto_match', true );

		return ! $variation_parent;
	}

	public function exclude_bumps_from_discounts( $valid, $product, $coupon, $values ): bool {
		if ( empty( $values['_cfw_order_bump_id'] ) ) {
			return (bool) $valid;
		}

		/**
		 * Filter whether to allow order bump coupons
		 *
		 * @param bool $allow Whether to allow order bump coupons
		 * @param int $bump_id The ID of the order bump
		 *
		 * @since 8.2.14
		 */
		return apply_filters( 'cfw_allow_order_bump_coupons', false, $values['_cfw_order_bump_id'] );
	}

	public function register_order_bump_meta() {
		$meta = array(
			'cfw_ob_display_for'                  => array(
				'default'      => 'all_products',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'all_products',
					),
				),
			),
			'cfw_ob_products_v9'                  => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'default' => array(),
						'type'    => 'array',
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_exclude_products_v9'          => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'default' => array(),
						'type'    => 'array',
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_exclude_categories_v9'        => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'default' => array(),
						'type'    => 'array',
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_minimum_subtotal'             => array(
				'default'      => '',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			'cfw_ob_categories_v9'                => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'default' => array(),
						'type'    => 'array',
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_any_product'                  => array(
				'default'      => 'yes',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'yes',
					),
				),
			),
			'cfw_ob_full_screen'                  => array(
				'default'      => 'no',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'no',
					),
				),
			),
			'cfw_ob_display_location'             => array(
				'default'      => 'below_cart_items',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'below_cart_items',
					),
				),
			),
			'cfw_ob_item_removal_behavior'        => array(
				'default'      => 'keep',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'keep',
					),
				),
			),
			'cfw_ob_discount_type'                => array(
				'default'      => 'percent',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'percent',
					),
				),
			),
			'cfw_ob_offer_product_v9'             => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'default' => array(),
						'type'    => 'array',
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_offer_discount'               => array(
				'default'      => '',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			'cfw_ob_offer_language'               => array(
				'default'      => 'Yes! Please add this offer to my order',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'Yes! Please add this offer to my order',
					),
				),
			),
			'cfw_ob_offer_description'            => array(
				'default'      => 'Limited time offer! Get an EXCLUSIVE discount right now! Click the checkbox above to add this product to your order now.',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'Limited time offer! Get an EXCLUSIVE discount right now! Click the checkbox above to add this product to your order now.',
					),
				),
			),
			'cfw_ob_upsell'                       => array(
				'default'      => 'no',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'no',
					),
				),
			),
			'cfw_ob_offer_quantity'               => array(
				'default'      => '1',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '1',
					),
				),
			),
			'cfw_ob_enable_auto_match'            => array(
				'default'      => 'no',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'no',
					),
				),
			),
			'cfw_ob_enable_quantity_updates'      => array(
				'default'      => 'no',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'no',
					),
				),
			),
			'cfw_ob_offer_heading'                => array(
				'default'      => '',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			'cfw_ob_offer_subheading'             => array(
				'default'      => '',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			'cfw_ob_offer_cancel_button_text'     => array(
				'default'      => '',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			'cfw_ob_apply_free_shipping'          => array(
				'default'      => 'no',
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'    => 'string',
						'default' => 'no',
					),
				),
			),
			'cfw_ob_products_to_remove_v9'        => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_match_offer_product_quantity' => array(
				'default' => 'no',
				'type'    => 'string',
			),
			'cfw_ob_auto_add'                     => array(
				'default' => 'no',
				'type'    => 'string',
			),
			'cfw_ob_rules'                        => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'fieldKey'  => array(
									'type' => 'string',
								),
								'subFields' => array(
									'type'                 => 'object',
									'additionalProperties' => array(
										'type' => array( 'string', 'number', 'array', 'boolean', 'null' ),
									),
								),
							),
						),
					),
				),
			),
			'cfw_ob_upsell_product'               => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_quantity_match_product'       => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_ob_variation_match_product'      => array(
				'default'      => array(),
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'key'   => array(
									'type' => 'integer',
								),
								'label' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
			'cfw_new_bump_modal_open'             => array(
				'default'      => false,
				'type'         => 'boolean',
				'show_in_rest' => true,
			),
		);

		foreach ( $meta as $meta_key => $settings ) {
			register_post_meta(
				BumpAbstract::get_post_type(),
				$meta_key,
				array(
					'show_in_rest' => $settings['show_in_rest'] ?? true,
					'type'         => $settings['type'],
					'single'       => true,
					'default'      => $settings['default'],
				)
			);
		}
	}

	public function output_after_checkout_bumps_root() {
		echo '<div id="cfw_after_checkout_bumps_root"></div>';
	}

	public static function force_block_editor( $use_block_editor, $post_type ): bool {
		if ( BumpAbstract::get_post_type() === $post_type ) {
			return true;
		}

		return (bool) $use_block_editor;
	}

	public function enable_gutenberg_for_order_bumps( $editors, $post_type ): array {
		if ( BumpAbstract::get_post_type() === $post_type ) {
			return array( 'block_editor' => true );
		}

		return (array) $editors;
	}

	/**
	 * @param array $data The checkout data.
	 * @throws Exception If the bumps cannot be added to the checkout data.
	 */
	public function add_bumps_to_checkout_data( $data ) {
		$data['bumps'] = cfw_get_order_bumps_data();

		return $data;
	}

	public function intercept_add_to_cart_handler( $handler ) {
		$bump_id = (int) wp_unslash( $_POST['cfw_ob_id'] ?? 0 ); // phpcs:ignore

		if ( ! $bump_id ) {
			return $handler;
		}

		$bump = BumpFactory::get( $bump_id );

		if ( $bump->has_custom_add_to_cart_handler() ) {
			return $bump->get_custom_add_to_cart_handler();
		}

		return $handler;
	}

	public function add_bump_to_cart() {
		if ( ! isset( $_POST['cfw_ob_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$bump_id = (int) $_POST['cfw_ob_id']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$bump    = BumpFactory::get( $bump_id );

		$bump->add_to_cart( WC()->cart );
	}

	/**
	 * Initialize post-purchase offers after order completion
	 *
	 * Sets up session data for post-purchase bumps to be displayed
	 * as modals on the thank you page.
	 *
	 * @since 10.4.0
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order The order.
	 * @return void
	 */
	public function init_post_purchase_offers( $order_id, $order ) {
		if ( ! $order ) {
			cfw_debug_log( 'CheckoutWC: Order not found, returning' );
			return;
		}

		// Check if payment method supports one-click
		$payment_method = $order->get_payment_method();
		$registry       = GatewayRegistry::instance();
		$is_supported   = $registry->is_gateway_supported( $payment_method );

		if ( ! $is_supported ) {
			cfw_debug_log( 'CheckoutWC: Gateway not supported, returning' );
			return;
		}

		// Get eligible post-purchase bumps
		$bump_ids = $this->get_post_purchase_bump_ids( $order );

		if ( empty( $bump_ids ) ) {
			cfw_debug_log( 'CheckoutWC: No post purchase bumps - aborting' );
			return;
		}

		// Initialize session data
		$session_data = array(
			'order_id'      => $order_id,
			'order_key'     => $order->get_order_key(),
			'pending_bumps' => $bump_ids,
			'handled_bumps' => array(),
		);

		WC()->session->set( 'cfw_post_purchase_data', $session_data );

		cfw_debug_log( 'Post-purchase session initialized with ' . count( $bump_ids ) . ' bumps for order ' . $order_id );
	}

	/**
	 * Get displayable post-purchase one-click bump IDs
	 *
	 * @param WC_Order $order Optional order to evaluate bumps against.
	 *
	 * @return array Bump IDs.
	 * @since 10.4.0
	 */
	private function get_post_purchase_bump_ids( WC_Order $order ): array {
		$bump_ids = array();

		foreach ( BumpFactory::get_all() as $bump ) {
			if ( 'post_purchase_one_click' !== $bump->get_display_location() ) {
				continue;
			}

			if ( ! $bump->is_published() || ! $this->is_bump_displayable_for_order( $bump, $order ) ) {
				continue;
			}

			$bump_ids[] = $bump->get_id();
		}

		// Respect max limit (reuse max_after_checkout_bumps setting)
		$max = SettingsManager::instance()->get_setting( 'max_after_checkout_bumps' ) ?? 1;
		$max = $max < 0 ? 999 : $max;

		return array_slice( $bump_ids, 0, $max );
	}

	/**
	 * Check if bump is displayable for a given order
	 *
	 * Creates a temporary cart from order and evaluates bump against it.
	 *
	 * @since 10.4.0
	 * @param BumpInterface $bump Bump to evaluate.
	 * @param WC_Order      $order Order to evaluate against.
	 * @return bool
	 */
	private function is_bump_displayable_for_order( $bump, $order ): bool {
		$temp_cart = $this->create_cart_from_order( $order );
		return $bump->is_displayable( $temp_cart );
	}

	/**
	 * Create a temporary cart instance from order items
	 *
	 * @since 10.4.0
	 * @param WC_Order $order Order to create cart from.
	 * @return \WC_Cart Temporary cart instance.
	 */
	private function create_cart_from_order( $order ): \WC_Cart {
		$temp_cart = new \WC_Cart();

		foreach ( $order->get_items() as $item ) {
			$temp_cart->add_to_cart(
				$item->get_product_id(),
				$item->get_quantity(),
				$item->get_variation_id() ?: 0
			);
		}

		return $temp_cart;
	}

	/**
	 * Get next pending bump from session
	 *
	 * @since 10.4.0
	 * @return int|null Bump ID or null if none available.
	 */
	public function get_next_pending_bump(): ?int {
		$session_data = WC()->session->get( 'cfw_post_purchase_data', null );

		if ( ! $session_data ) {
			cfw_debug_log( 'CheckoutWC Debug: Unable to get next post purchase bump - session data does not exist' );
			return null;
		}

		if ( empty( $session_data['pending_bumps'] ) ) {
			return null;
		}

		// Get bumps that aren't handled
		$unhandled = array_diff( $session_data['pending_bumps'], array_keys( $session_data['handled_bumps'] ) );

		// Return the first one or null
		return array_values( $unhandled )[0] ?? null;
	}

	/**
	 * Mark bump as handled in session
	 *
	 * @since 10.4.0
	 * @param int    $bump_id Bump ID.
	 * @param string $status Status (accepted/declined/error).
	 * @return void
	 */
	public function mark_bump_handled( int $bump_id, string $status ): void {
		$session_data = WC()->session->get( 'cfw_post_purchase_data', null );

		if ( ! $session_data ) {
			cfw_debug_log( 'Cannot mark bump as handled - session data not available' );
			return;
		}

		// Add to handled bumps
		$session_data['handled_bumps'][ $bump_id ] = $status;

		// Remove from pending if present
		$session_data['pending_bumps'] = array_diff( $session_data['pending_bumps'], array( $bump_id ) );

		WC()->session->set( 'cfw_post_purchase_data', $session_data );

		cfw_debug_log( 'Marked bump ' . $bump_id . ' as ' . $status );
	}

	/**
	 * Update pending bumps by re-evaluating against current order
	 *
	 * @since 10.4.0
	 * @param WC_Order $order Order to evaluate against.
	 * @return void
	 */
	public function update_pending_bumps( WC_Order $order ): void {
		$session_data = WC()->session->get( 'cfw_post_purchase_data', null );

		if ( ! $session_data || empty( $session_data['pending_bumps'] ) ) {
			return;
		}

		// Recreate the pending bumps list from scratch because previously excluded bumps might be available now
		$all_post_purchase_bumps = $this->get_post_purchase_bump_ids( $order );
		$valid_bumps             = array();

		foreach ( $all_post_purchase_bumps as $bump_id ) {
			$bump = BumpFactory::get( $bump_id );

			if ( $bump->get_id() === 0 || ! $this->is_bump_displayable_for_order( $bump, $order ) ) {
				continue;
			}

			if ( in_array( $bump_id, $session_data['handled_bumps'], true ) ) {
				continue;
			}

			$valid_bumps[] = $bump_id;
		}

		$session_data['pending_bumps'] = $valid_bumps;

		WC()->session->set( 'cfw_post_purchase_data', $session_data );

		cfw_debug_log( 'Updated pending bumps: ' . count( $valid_bumps ) . ' remain valid' );
	}


	/**
	 * Output React root div for post-purchase modal
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function output_post_purchase_root(): void {
		echo '<div id="cfw-post-purchase-root"></div>';
	}

	/**
	 * Add post-purchase data to the cfw event object
	 *
	 * @since 10.4.0
	 * @param array $cfw_event_object The event object.
	 * @return array
	 */
	public function add_post_purchase_data_to_event_object( array $cfw_event_object ): array {
		// Only add on thank you page
		if ( ! is_order_received_page() ) {
			return $cfw_event_object;
		}

		// Get order first to check if we need to initialize session
		$order = cfw_get_order_received_order();

		if ( ! $order ) {
			cfw_debug_log( 'Could not find $order - aborting: add_post_purchase_data_to_event_object()' );
			return $cfw_event_object;
		}

		// Get the next pending bump using our existing method
		$bump_id = $this->get_next_pending_bump();

		if ( ! $bump_id ) {
			return $cfw_event_object;
		}

		$bump = BumpFactory::get( $bump_id );

		if ( $bump->get_id() === 0 ) { // NullBump returns 0 for get_id()
			cfw_debug_log( 'Pending bump is invalid - aborting: add_post_purchase_data_to_event_object()' );
			return $cfw_event_object;
		}

		// Add post-purchase data
		$cfw_event_object['post_purchase_data'] = array(
			'current_bump'       => array(
				'id'          => $bump->get_id(),
				'full_screen' => get_post_meta( $bump->get_id(), 'cfw_ob_full_screen', true ) === 'yes',
				'nonce'       => wp_create_nonce( 'cfw_process_offer_' . $bump->get_id() ),
			),
			'order'              => array(
				'id'  => $order->get_id(),
				'key' => $order->get_order_key(),
			),
			'supported_gateways' => array_keys( GatewayRegistry::instance()->get_supported_gateways() ),
			'api_url'            => rest_url( 'checkoutwc/v1/' ),
			'thank_you_url'      => $order->get_checkout_order_received_url(),
		);

		return $cfw_event_object;
	}

	/**
	 * Output post-purchase root div universally
	 */
	public function output_post_purchase_root_universal( $order_id ): void {
		// Check for pending bumps
		$session_data = WC()->session->get( 'cfw_post_purchase_data' );
		if ( empty( $session_data['pending_bumps'] ) ) {
			return;
		}

		echo '<div id="cfw-post-purchase-root"></div>';
	}

	/**
	 * Enqueue post-purchase assets universally - ALWAYS use separate bundle
	 */
	public function enqueue_post_purchase_assets(): void {
		// Only on order-received pages
		if ( ! is_order_received_page() ) {
			return;
		}

		// Check for pending bumps in session
		$session_data = WC()->session->get( 'cfw_post_purchase_data' );
		if ( empty( $session_data['pending_bumps'] ) ) {
			return;
		}

		// ALWAYS load the separate post-purchase bundle using CFW helper functions
		cfw_register_scripts( array( 'post-purchase' ) );
		wp_enqueue_script( 'cfw-post-purchase' );

		// Load styles using AssetManager helper
		$manifest = cfwChunkedScriptsConfigGet( 'manifest' );
		if ( isset( $manifest['chunks']['post-purchase-styles']['file'] ) ) {
			AssetManager::enqueue_style( 'post-purchase-styles' );
		}

		// ALWAYS create separate object for post-purchase bumps
		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Create separate post-purchase data object (not cfw object to avoid conflicts)
		$post_purchase_data = $this->get_post_purchase_data_array( $order );

		// Also need minimal checkout params for AJAX
		$post_purchase_data['checkout_params'] = array(
			'wc_ajax_url'    => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'cfw_debug_mode' => isset( $_GET['cfw-debug'] ),
		);

		wp_localize_script( 'cfw-post-purchase', 'cfw_post_purchase_data', $post_purchase_data );
	}

	/**
	 * Extract post-purchase data logic to reusable method
	 */
	private function get_post_purchase_data_array( $order ) {
		$session_data = WC()->session->get( 'cfw_post_purchase_data' );

		if ( empty( $session_data ) ) {
			return null;
		}

		$bump_id = $this->get_next_pending_bump();  // Returns int, no parameter needed

		if ( ! $bump_id ) {
			return null;
		}

		$bump_post = get_post( $bump_id );  // Get the WP_Post object

		if ( ! $bump_post ) {
			return null;
		}

		return array(
			'current_bump'       => array(
				'id'          => $bump_post->ID,
				'full_screen' => get_post_meta( $bump_post->ID, 'cfw_ob_full_screen', true ) === 'yes',
				'nonce'       => wp_create_nonce( 'cfw_process_offer_' . $bump_post->ID ),
			),
			'order'              => array(
				'id'  => $order->get_id(),
				'key' => $order->get_order_key(),
			),
			'supported_gateways' => array_keys( GatewayRegistry::instance()->get_supported_gateways() ),
			'api_url'            => get_rest_url( null, 'checkoutwc/v1/' ),
			'thank_you_url'      => $order->get_checkout_order_received_url(),
		);
	}
}
