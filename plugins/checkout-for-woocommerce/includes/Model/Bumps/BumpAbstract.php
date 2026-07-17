<?php

namespace Objectiv\Plugins\Checkout\Model\Bumps;

use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;
use Objectiv\Plugins\Checkout\Model\MatchedVariationResult;
use Objectiv\Plugins\Checkout\Model\RulesProcessor;
use stdClass;
use WC_Cart;
use WC_Product;
use WP_Post;

abstract class BumpAbstract implements BumpInterface {
	protected $id;
	protected $title;
	protected $upsell_product = array();
	protected $location;
	protected $discount_type;
	protected $offer_product;
	protected $auto_add = false;
	protected $offer_discount;
	protected $offer_language;
	protected $offer_description;
	protected $offer_heading;
	protected $offer_subheading;
	protected $offer_cancel_button_text;
	protected $upsell = false;
	protected $offer_quantity;
	protected $match_offer_product_quantity;
	protected $match_quantity_product = array();

	protected $auto_match_variations   = false;
	protected $variation_match_product = array();
	protected $can_quantity_be_updated = false;
	protected $products_to_remove      = array();

	protected $rules = array();

	public function __construct() {}

	/**get_offer_product_price
	 * Load the bump
	 *
	 * @param WP_Post $post The bump post object.
	 *
	 * @return bool
	 */
	public function load( WP_Post $post ): bool {
		$this->id                           = $post->ID;
		$this->title                        = $post->post_title;
		$this->location                     = get_post_meta( $post->ID, 'cfw_ob_display_location', true );
		$this->discount_type                = get_post_meta( $post->ID, 'cfw_ob_discount_type', true );
		$this->offer_product                = $this->parse_select_data( get_post_meta( $post->ID, 'cfw_ob_offer_product_v9', true ) );
		$this->auto_add                     = get_post_meta( $post->ID, 'cfw_ob_auto_add', true ) === 'yes';
		$this->offer_discount               = get_post_meta( $post->ID, 'cfw_ob_offer_discount', true );
		$this->offer_language               = get_post_meta( $post->ID, 'cfw_ob_offer_language', true );
		$this->offer_description            = get_post_meta( $post->ID, 'cfw_ob_offer_description', true );
		$this->offer_heading                = get_post_meta( $post->ID, 'cfw_ob_offer_heading', true );
		$this->offer_subheading             = get_post_meta( $post->ID, 'cfw_ob_offer_subheading', true );
		$this->offer_cancel_button_text     = get_post_meta( $post->ID, 'cfw_ob_offer_cancel_button_text', true );
		$this->upsell                       = get_post_meta( $post->ID, 'cfw_ob_upsell', true ) === 'yes';
		$this->upsell_product               = (array) $this->parse_select_data( get_post_meta( $post->ID, 'cfw_ob_upsell_product', true ) );
		$this->offer_quantity               = get_post_meta( $post->ID, 'cfw_ob_offer_quantity', true );
		$this->match_offer_product_quantity = get_post_meta( $post->ID, 'cfw_ob_match_offer_product_quantity', true ) === 'yes';
		$this->match_quantity_product       = $this->parse_select_data( get_post_meta( $post->ID, 'cfw_ob_quantity_match_product', true ) );
		$this->auto_match_variations        = get_post_meta( $post->ID, 'cfw_ob_enable_auto_match', true );
		$this->variation_match_product      = $this->parse_select_data( get_post_meta( $post->ID, 'cfw_ob_variation_match_product', true ) );
		$this->can_quantity_be_updated      = get_post_meta( $post->ID, 'cfw_ob_enable_quantity_updates', true ) === 'yes';
		$this->products_to_remove           = (array) $this->parse_select_data( get_post_meta( $post->ID, 'cfw_ob_products_to_remove_v9', true ) );
		$this->rules                        = (array) get_post_meta( $post->ID, 'cfw_ob_rules', true );

		// Get post type of offer_product
		$this->offer_product = cfw_apply_filters( 'wpml_object_id', $this->offer_product, get_post_type( $this->offer_product ), true );

		if ( empty( $this->offer_discount ) ) {
			$this->offer_discount = 0;
		}

		if ( empty( $this->offer_quantity ) ) {
			$this->offer_quantity = 1;
		}

		return true;
	}

	private function parse_select_data( $data ) {
		// Check if $data is an array
		if ( ! is_array( $data ) ) {
			return null;
		}

		// Check if the array is non-empty and has the expected structure
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['key'] ) ) {
				return null;
			}
		}

		// If $data contains only one element, return the 'key' of that element
		if ( count( $data ) === 1 ) {
			return $data[0]['key'];
		}

		// If $data contains multiple elements, return an array of 'key' values
		return array_column( $data, 'key' );
	}

	/**
	 * Get bump title
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Is displayable
	 *
	 * @return bool
	 */
	public function is_displayable( $cart = null ): bool {
		$cart = $cart ?? WC()->cart;

		if ( ! $cart || $cart->is_empty() ) {
			return false;
		}

		if ( ! $this->is_published() ) {
			return false;
		}

		if ( ! $this->can_offer_product_be_added_to_the_cart() ) {
			cfw_debug_log( 'Order Bump Rule Failure: Offer Product cannot be added to the cart. Bump:' . $this->get_id() );
			return false;
		}

		/**
		 * Filters whether to show a bump if the offer product is already in the cart
		 *
		 * @since 8.2.18
		 * @param bool $show_bump_if_offer_product_in_cart Whether to show a bump if the offer product is already in the cart
		 */
		if ( apply_filters( 'cfw_hide_bump_if_offer_product_in_cart', true ) && $this->quantity_of_product_in_cart( $this->offer_product ) && ! $this->rules_explicitly_check_for_offer_product() ) {
			cfw_debug_log( 'Order Bump Rule Failure: Offer product is in the cart and no explicit rule for it. Bump: ' . $this->get_id() );
			return false;
		}

		if ( $this->bump_is_in_cart() ) {
			cfw_debug_log( 'Order Bump Rule Failure: Bump is already in the cart. Bump: ' . $this->get_id() );
			return false;
		}

		$offer_product           = $this->get_offer_product();
		$search_product          = false;
		$auto_match_variations   = false;
		$upsell_product          = $this->get_upsell_product();
		$variation_match_product = $this->get_variation_match_product();

		// Variation Auto Match Conditions
		// 1. Upsell is turned on and both upsell and offer product are variable
		if ( $this->upsell && $offer_product->is_type( 'variable' ) && $upsell_product && $upsell_product->is_type( 'variable' ) ) {
			$search_product        = $upsell_product;
			$auto_match_variations = true;
		}

		// 2. Upsell is not turned on, but variation auto matching is and both match product and offer product are variable
		if ( ! $this->upsell && get_post_meta( $this->get_id(), 'cfw_ob_enable_auto_match', true ) === 'yes' && $variation_match_product && $variation_match_product->is_type( 'variable' ) && $offer_product->is_type( 'variable' ) ) {
			$search_product        = $variation_match_product;
			$auto_match_variations = true;
		}

		if ( $auto_match_variations && $search_product && ! $this->get_matched_variation_attributes_from_cart_search_product( $search_product, $offer_product )->get_id() ) {
			cfw_debug_log( 'Order Bump Rule Failure: Could not find matching variation. Bump:' . $this->get_id() );
			return false;
		}

		// Special handling for upsells that are already in cart (completed upsells)
		if ( $this->is_valid_upsell() && $this->is_in_cart() ) {
			return true;
		}

		// Upsell bump should only be displayed if upsell product is in the cart
		if ( $this->upsell && $this->get_upsell_product() && ! count( $this->get_cart_item_for_product( $this->get_upsell_product()->get_id() ) ) ) {
			return false;
		}

		// Or if an upsell is misconfigured, don't show it
		if ( $this->upsell && ! $this->get_upsell_product() ) {
			return false;
		}

		$rules_processor = new RulesProcessor( $this->rules, $this->id );

		if ( ! $rules_processor->evaluate() ) {
			cfw_debug_log( 'Order Bump Rule Failure: Bump rules failed: ' . print_r( $rules_processor->lastRuleEvaluated, true ) . 'Bump: ' . $this->get_title() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			return false;
		}

		return true;
	}

	protected function get_matched_variation_attributes_from_cart_search_product( WC_Product $search_product, WC_Product $offer_product ): MatchedVariationResult {
		$variation_data = null;

		if ( ! $offer_product->is_type( 'variable' ) || ! $search_product->is_type( 'variable' ) ) {
			return new MatchedVariationResult();
		}

		// Attempt to match variation attributes to search product
		$search_product_id = $search_product->get_id();
		$cart_item         = $this->get_cart_item_for_product( $search_product_id );
		$variation_id      = cfw_get_variation_id_from_attributes( $offer_product, $cart_item['variation'] ?? array() );

		if ( empty( $cart_item ) || empty( $variation_id ) ) {
			return new MatchedVariationResult();
		}

		foreach ( $cart_item['variation'] as $taxonomy => $term_names ) {
			$taxonomy                                = str_replace( 'attribute_', '', $taxonomy );
			$attribute_label_name                    = str_replace( 'attribute_', '', wc_attribute_label( $taxonomy ) );
			$variation_data[ $attribute_label_name ] = $term_names;
		}

		return new MatchedVariationResult( $variation_id, $variation_data );
	}

	protected function get_cart_item_for_product( $search_product_id ) {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $this->cart_item_is_product( $cart_item, $search_product_id ) ) {
				return $cart_item;
			}
		}

		return array();
	}

	/**
	 * Is cart bump valid
	 *
	 * @return bool
	 */
	public function is_cart_bump_valid(): bool {
		$offer_product = $this->get_offer_product();

		if ( ! $this->is_published() ) {
			return $this->filtered_is_cart_bump_valid( false );
		}

		// Skip this check for upsells since they can legitimately be the only item in cart
		if ( WC()->cart->get_cart_contents_count() <= $this->quantity_of_product_in_cart( $this->offer_product ) && ! $this->is_valid_upsell() ) {
			return $this->filtered_is_cart_bump_valid( false );
		}

		if ( ! $offer_product ) {
			return $this->filtered_is_cart_bump_valid( false );
		}

		// If it's an upsell, and it's in the cart, it's valid.
		if ( $this->is_valid_upsell() && $this->is_in_cart() ) {
			return $this->filtered_is_cart_bump_valid( true );
		}

		// Quantity matching is set up, but there's not enough of the offer product to match the quantity.
		if ( $this->should_match_offer_product_quantity() && $offer_product->get_manage_stock() && $this->quantity_of_normal_product_in_cart( $this->get_match_quantity_product()->get_id() ) > $offer_product->get_stock_quantity() ) {
			return $this->filtered_is_cart_bump_valid( false );
		}

		$rules_processor = new RulesProcessor( $this->rules, $this->id );

		if ( ! $rules_processor->evaluate() ) {
			cfw_debug_log( 'Order Bump Rule Failure (is_cart_bump_valid): Bump rules failed: ' . print_r( $rules_processor->lastRuleEvaluated, true ) . 'Bump: ' . $this->get_title() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			return false;
		}

		return $this->filtered_is_cart_bump_valid( true );
	}

	private function filtered_is_cart_bump_valid( bool $result ) {
		/**
		 * Filters whether the bump is valid
		 *
		 * @param string $is_cart_bump_valid Whether the categories bump in the cart is still valid
		 * @since 6.3.0
		 */
		return apply_filters( 'cfw_is_cart_bump_valid', $result, $this );
	}


	public function is_published(): bool {
		return get_post_status( $this->id ) === 'publish';
	}

	/**
	 * Is valid upsell
	 *
	 * @return bool
	 */
	public function is_valid_upsell(): bool {
		return $this->upsell && count( $this->upsell_product ) === 1;
	}

	public function can_offer_product_be_added_to_the_cart(): bool {
		$product = $this->get_offer_product();

		return $product && $product->is_purchasable() && ( $product->is_in_stock() || $product->backorders_allowed() );
	}

	public function add_bump_meta_to_order_item( $item, $values ) {
		if ( ! $this->is_cart_bump_valid() ) {
			return;
		}

		$item->update_meta_data( '_cfw_order_bump_id', $values['_cfw_order_bump_id'] );
	}

	public function get_cfw_cart_item_discount( string $price_html, $cart_item ): string {
		if ( ! $this->is_cart_bump_valid() ) {
			return $price_html;
		}

		/**
		 * Action fired before calculating cart item display price for order bumps
		 *
		 * This allows compatibility classes to temporarily disable price filters
		 * to prevent double-discounting in display calculations.
		 *
		 * @param BumpInterface $bump The bump object
		 * @param array $cart_item The cart item data
		 * @since 10.2.8
		 */
		do_action( 'cfw_before_bump_display_price_calculation', $this, $cart_item );

		$display_price = $this->get_offer_product_price( $cart_item['variation_id'] ?? 0 );

		/**
		 * Action fired after calculating cart item display price for order bumps
		 *
		 * This allows compatibility classes to re-enable price filters after
		 * display price calculations are complete.
		 *
		 * @param BumpInterface $bump The bump object
		 * @param array $cart_item The cart item data
		 * @since 10.2.8
		 */
		do_action( 'cfw_after_bump_display_price_calculation', $this, $cart_item );

		return $display_price;
	}

	/**
	 * @param WC_Cart $cart The WooCommerce cart object.
	 *
	 * @return bool|string
	 */
	public function add_to_cart( WC_Cart $cart ) {
		$offer_product  = $this->get_offer_product();
		$variation_id   = $offer_product->is_type( 'variable' ) ? $offer_product->get_id() : null;
		$product_id     = $offer_product->is_type( 'variable' ) && 0 !== $offer_product->get_parent_id() ? $offer_product->get_parent_id() : $offer_product->get_id();
		$variation_data = null;
		$metadata       = array(
			'_cfw_order_bump_id' => $this->id,
		);

		if ( $offer_product->is_type( 'variation' ) ) {
			$variation_data = array();

			foreach ( $offer_product->get_variation_attributes() as $taxonomy => $term_names ) {
				$taxonomy                                = str_replace( 'attribute_', '', $taxonomy );
				$attribute_label_name                    = str_replace( 'attribute_', '', wc_attribute_label( $taxonomy ) );
				$variation_data[ $attribute_label_name ] = $term_names;
			}
		}

		$search_product          = false;
		$auto_match_variations   = false;
		$upsell_product          = $this->get_upsell_product();
		$variation_match_product = $this->get_variation_match_product();

		// Variation Auto Match Conditions
		// 1. Upsell is turned on and both upsell and offer product are variable
		if ( $this->upsell && $offer_product->is_type( 'variable' ) && $upsell_product && $upsell_product->is_type( 'variable' ) ) {
			$search_product        = $upsell_product;
			$auto_match_variations = true;
		}

		// 2. Upsell is not turned on, but variation auto matching is and both match product and offer product are variable
		if ( ! $this->upsell && get_post_meta( $this->get_id(), 'cfw_ob_enable_auto_match', true ) === 'yes' && $variation_match_product && $variation_match_product->is_type( 'variable' ) && $offer_product->is_type( 'variable' ) ) {
			$search_product        = $variation_match_product;
			$auto_match_variations = true;
		}

		// Attempt to match variation attributes to search product
		if ( $auto_match_variations && $search_product ) {
			$matched_variation_data = $this->get_matched_variation_attributes_from_cart_search_product( $search_product, $offer_product );

			if ( $matched_variation_data->get_id() ) {
				$variation_id   = $matched_variation_data->get_id();
				$variation_data = $matched_variation_data->get_attributes();
			}
		}

		$quantity = $this->get_offer_quantity();

		// Snapshot the match-offer quantity before any cart mutations. When the upsell branch
		// removes the source product, a deferred read of the match product's quantity (if it is
		// the same product, the typical configuration) returns 0 — and WC_Cart::add_to_cart
		// silently early-returns false on $quantity <= 0, leaving the cart empty.
		$matched_quantity = null;
		if ( $this->should_match_offer_product_quantity() ) {
			$matched_quantity = $this->quantity_of_normal_product_in_cart( $this->get_match_quantity_product()->get_id() );
		}

		if ( $this->is_valid_upsell() ) {
			/**
			 * The max number of items that upsell can replace (-1 is unlimited)
			 *
			 * @since 7.6.1
			 *
			 * @param int $replace_up_to_quantity
			 * @param BumpInterface $bump
			 */
			$replace_up_to_quantity = apply_filters( 'cfw_order_bump_upsell_quantity_to_replace', -1, $this );

			$replace_up_to_quantity = $replace_up_to_quantity < 0 ? PHP_INT_MAX : $replace_up_to_quantity;
			$quantity               = $this->quantity_of_product_in_cart( $this->get_upsell_product()->get_id() );
			$quantity               = min( $quantity, $replace_up_to_quantity );

			cfw_remove_product_from_cart( $this->get_upsell_product()->get_id(), $quantity );
		}

		if ( null !== $matched_quantity ) {
			$quantity = $matched_quantity;
		}

		/**
		 * Fires before order bump is added to the cart
		 *
		 * @since 9.0.0
		 * @param BumpInterface $bump The order bump
		 */
		do_action( 'cfw_before_order_bump_add_to_cart', $this );

		$product_type = $offer_product->get_type();

		if ( has_action( 'cfw_order_bump_add_to_cart_product_type_' . $product_type ) ) {
			/**
			 * Fires before order bump is added to the cart
			 *
			 * @since 9.0.0
			 * @param string $product_type The product type
			 * @param int $product_id The product ID
			 * @param int $quantity The quantity
			 * @param int $variation_id The variation ID
			 * @param array $variation_data The variation data
			 * @param array $metadata The metadata
			 * @param WC_Product $offer_product The product
			 */
			do_action( 'cfw_order_bump_add_to_cart_product_type_' . $product_type, $product_id, $quantity, $variation_id, $variation_data, $metadata, $offer_product );

			return true;
		}

		try {
			return $cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_data, $metadata );
		} catch ( \Exception $e ) {
			wc_get_logger()->error( 'CheckoutWC: Failed to add order bump to cart. Error: ' . $e->getMessage(), array( 'source' => 'checkout-wc' ) );

			return false;
		}
	}

	/**
	 * @param int $needle_product_id The product we are searching for.
	 *
	 * @return int
	 */
	public function quantity_of_product_in_cart( int $needle_product_id ): int {
		$needle_product = wc_get_product( $needle_product_id );

		if ( ! $needle_product ) {
			return 0;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $this->cart_item_is_product( $cart_item, $needle_product_id ) ) {
				return $cart_item['quantity'];
			}
		}

		return 0;
	}

	/**
	 * @param int $needle_category_id The category we are looking for.
	 *
	 * @return int
	 */
	public function quantity_of_normal_cart_items_in_category( int $needle_category_id ): int {
		$needle_category = get_term_by( 'term_id', $needle_category_id, 'product_cat' );

		if ( ! $needle_category ) {
			return 0;
		}

		$found = 0;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['_cfw_order_bump_id'] ) ) {
				continue;
			}

			$cart_item_terms = wp_get_post_terms( $cart_item['product_id'], 'product_cat' );

			/** @var \WP_Term $cart_item_term */
			foreach ( $cart_item_terms as $cart_item_term ) {
				if ( $cart_item_term->term_id === $needle_category_id ) {
					++$found;
				}
			}
		}

		return $found;
	}

	/**
	 * @return bool
	 */
	public function bump_is_in_cart(): bool {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['_cfw_order_bump_id'] ) && $cart_item['_cfw_order_bump_id'] === $this->id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $needle_product_id The product we are looking for.
	 *
	 * @return int
	 */
	public function quantity_of_normal_product_in_cart( int $needle_product_id ): int {
		$needle_product = wc_get_product( $needle_product_id );

		if ( ! $needle_product ) {
			return 0;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['_cfw_order_bump_id'] ) ) {
				continue;
			}

			if ( $this->cart_item_is_product( $cart_item, $needle_product_id ) ) {
				return $cart_item['quantity'];
			}
		}

		return 0;
	}

	protected function cart_item_is_product( array $cart_item, int $product_id ): bool {
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

	public function record_purchased() {
		$this->increment_displayed_on_purchases_count();
		$this->increment_purchased_count();
		$this->update_conversion_rate();

		$offer_product = $this->get_offer_product();

		if ( ! $offer_product ) {
			return;
		}

		if ( $this->is_valid_upsell() ) {
			$base_product = $this->get_upsell_product();

			if ( ! $base_product ) {
				return;
			}

			$new_revenue = $this->get_price() - $base_product->get_price(); // upsell product minus original product
		} else {
			$new_revenue = $this->get_price();
		}

		$this->add_captured_revenue( $new_revenue );
	}

	public function display( string $location ): bool {
		if ( ! $this->can_be_displayed( $location ) ) {
			return false;
		}

		$link_wrap        = '<div class="cfw-order-bump-image"><a target="_blank" href="%s">%s</a></div>';
		$offer_product    = $this->get_offer_product();
		$thumb            = $offer_product->get_image( 'cfw_order_bump_thumb' );
		$wrapped_thumb    = $offer_product->is_visible() ? sprintf( $link_wrap, $offer_product->get_permalink(), $thumb ) : $thumb;
		$variation_parent = $this->get_offer_product()->is_type( 'variable' ) && 0 === $this->get_offer_product()->get_parent_id() && 'no' === get_post_meta( $this->get_id(), 'cfw_ob_enable_auto_match', true );
		?>
		<div class="cfw-order-bump cfw-module">
			<input type="hidden" name="cfw_displayed_order_bump[]" value="<?php echo esc_attr( $this->get_id() ); ?>"/>

			<div class="cfw-order-bump-header">
				<label>
					<input type="checkbox" class="cfw_order_bump_check"
							name="<?php echo $variation_parent ? 'placeholder' : 'cfw_order_bump[]'; ?>"
							value="<?php echo esc_attr( $this->get_id() ); ?>"
							data-variable="<?php echo $variation_parent ? 'true' : 'false'; ?>"
							data-product="<?php echo esc_attr( $this->get_offer_product()->get_id() ); ?>"/>
					<span>
						<?php echo do_shortcode( $this->get_offer_language() ); ?>
					</span>
				</label>
			</div>
			<div class="cfw-order-bump-body">
				<div class="row">
					<div class="col-2">
						<?php echo wp_kses_post( $wrapped_thumb ); ?>
					</div>
					<div class="col-10 cfw-order-bump-content">
						<?php echo do_shortcode( $this->get_offer_description() ); ?>

						<div class="cfw-order-bump-total">
							<?php echo wp_kses_post( $this->get_offer_product_price() ); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		return true;
	}

	public function can_be_displayed( $location = 'all' ): bool {
		$display_bump = $this->is_displayable() && $this->is_published() && ( 'all' === $location || $this->get_display_location() === $location );

		/**
		 * Filter whether to display the bump
		 *
		 * @param bool $display_bump Whether to display the bump
		 *
		 * @since 8.0.0
		 */
		$filtered_display_bump = apply_filters( 'cfw_display_bump', $display_bump, $this, $location );

		if ( ! $filtered_display_bump ) {
			return false;
		}

		return true;
	}

	/**
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function get_offer_language(): string {
		return $this->offer_language;
	}

	/**
	 * @return string
	 */
	public function get_offer_description(): string {
		return $this->offer_description;
	}

	/**
	 * @return string
	 */
	public function get_offer_heading(): string {
		return $this->offer_heading ?? '';
	}

	/**
	 * @return string
	 */
	public function get_offer_subheading(): string {
		return $this->offer_subheading ?? '';
	}

	/**
	 * @return string
	 */
	public function get_offer_cancel_button_text(): string {
		return $this->offer_cancel_button_text ?? '';
	}

	/**
	 * @return mixed
	 */
	public function get_offer_quantity() {
		return $this->offer_quantity;
	}

	/**
	 * @return false|WC_Product|null
	 */
	public function get_offer_product() {
		return wc_get_product( $this->offer_product );
	}

	/**
	 * Apply tax to price for display in post-purchase context
	 *
	 * @param float      $price The price to potentially add tax to.
	 * @param WC_Product $product The product for tax calculation.
	 *
	 * @return float The price with tax applied if applicable.
	 */
	private function apply_tax_for_display( float $price, $product ): float {
		$tax_enabled = get_option( 'woocommerce_calc_taxes' );

		if ( 'yes' !== $tax_enabled ) {
			return $price;
		}

		if ( wc_prices_include_tax() ) {
			return $price; // Price already includes tax
		}

		// Tax-exclusive store - calculate tax-inclusive price
		return wc_get_price_including_tax(
			$product,
			array( 'price' => $price )
		);
	}

	/**
	 * Get the order bump offer formatted price
	 *
	 * @param int $variation_id The variation ID.
	 *
	 * @return string
	 */
	public function get_offer_product_price( int $variation_id = 0 ): string {
		$product = $this->get_offer_product();

		// If variable product
		if ( $product->is_type( 'variable' ) && $variation_id > 0 ) {
			$product = wc_get_product( $variation_id );
		}

		if ( $product->is_type( 'variable' ) && ! $variation_id ) {
			// Get min and max prices of variable product variations
			$prices = $product->get_variation_prices( true );

			// Get first and last key
			$min_id = key( $prices['price'] );
			$max_id = key( array_slice( $prices['price'], - 1, 1, true ) );

			$min_price = $this->get_price( 'view', $min_id );
			$max_price = $this->get_price( 'view', $max_id );

			// Apply tax for post-purchase displays
			if ( $this->get_display_location() === 'post_purchase_one_click' ) {
				$min_price = $this->apply_tax_for_display( $min_price, wc_get_product( $min_id ) );
				$max_price = $this->apply_tax_for_display( $max_price, wc_get_product( $max_id ) );
			}

			if ( $min_price !== $max_price ) {
				return wc_format_price_range( $min_price, $max_price );
			}
		}

		// Get base prices and format for display
		$price      = wc_get_price_to_display(
			$product,
			array(
				'price'           => $product->get_regular_price(),
				'display_context' => 'cart',
			)
		);
		$sale_price = wc_get_price_to_display(
			$product,
			array(
				'price'           => $this->get_price( 'view', $variation_id ),
				'display_context' => 'cart',
			)
		);

		// Apply tax for post-purchase displays
		if ( $this->get_display_location() === 'post_purchase_one_click' ) {
			$price      = $this->apply_tax_for_display( $price, $product );
			$sale_price = $this->apply_tax_for_display( $sale_price, $product );
		}

		$sale_price_formatted = wc_price( $sale_price );

		if ( 0.00 === $price ) {
			return '<span class="woocommerce-Price-amount amount">' . __( 'Free!', 'woocommerce' ) . '</span>';
		} elseif ( $price > $sale_price ) {
			return wc_format_sale_price( $price, $sale_price );
		}

		return $sale_price_formatted;
	}

	/**
	 * Get the bump price
	 *
	 * @param string $context The context of the price.
	 * @param int    $variation_id The variation ID.
	 *
	 * @return float
	 */
	public function get_price( string $context = 'view', int $variation_id = 0 ): float {
		$product = $this->get_offer_product();

		$discount_type = $this->discount_type;
		$discount      = $this->offer_discount;

		if ( $product->is_type( 'variable' ) && $variation_id > 0 ) {
			// Get product variation
			$product = wc_get_product( $variation_id );
		}

		$price = floatval( $product->get_price( $context ) );

		if ( 'percent' === $discount_type && $discount > 0 ) {
			$discount_value = $price * ( $discount / 100 );
		} else {
			$discount_value = $discount;
		}

		// Run amount off discounts through product price filter if we're in a view context
		// This is to allow currency plugins can adjust the currency of the discounted amount properly
		if ( 'view' === $context && 'percent' !== $discount_type ) {
			$discount_value = cfw_apply_filters( 'woocommerce_product_get_price', $discount_value, $product );
		}

		/**
		 * Filter the order bump price.
		 *
		 * @param float $price The price of the order bump.
		 * @param string $context The context of the price.
		 * @param BumpInterface $order_bump The order bump object.
		 *
		 * @since 5.0.0
		 */
		return apply_filters( 'cfw_order_bump_get_price', (float) $price - (float) $discount_value, $context, $this );
	}

	public function get_display_location(): string {
		return $this->location ?? 'below_cart_items';
	}

	/**
	 * Get Displayed On Purchases Count
	 *
	 * The number of times this bump was displayed and a purchase was subsequently made.
	 *
	 * @return integer
	 */
	private function get_displayed_on_purchases_count(): int {
		return intval( get_post_meta( $this->id, 'times_bump_displayed_on_purchases', true ) );
	}

	/**
	 * Get Purchase Count
	 *
	 * The number of times this bump was added to the cart and purchased.
	 *
	 * @return integer
	 */
	public function get_purchase_count(): int {
		return intval( get_post_meta( $this->id, 'times_bump_purchased', true ) );
	}

	public function record_displayed() {
		$this->increment_displayed_on_purchases_count();
		$this->update_conversion_rate();
	}

	public function increment_displayed_on_purchases_count() {
		update_post_meta( $this->id, 'times_bump_displayed_on_purchases', $this->get_displayed_on_purchases_count() + 1 );
	}

	public function increment_purchased_count() {
		update_post_meta( $this->id, 'times_bump_purchased', $this->get_purchase_count() + 1 );
	}

	public function add_captured_revenue( float $new_revenue ) {
		$captured_revenue = max( (float) get_post_meta( $this->id, 'captured_revenue', true ), 0.0 );

		/**
		 * Filter the captured revenue
		 *
		 * @param float $new_revenue The new captured revenue
		 * @param BumpInterface $bump The bump
		 *
		 * @since 9.0.0
		 */
		$new_revenue = apply_filters( 'cfw_order_bump_captured_revenue', $new_revenue, $this );

		update_post_meta(
			$this->id,
			'captured_revenue',
			$captured_revenue + $new_revenue
		);
	}

	public function update_conversion_rate() {
		$purchase_count  = $this->get_purchase_count();
		$displayed_count = $this->get_displayed_on_purchases_count();
		$not_calculable  = min( $purchase_count, $displayed_count ) < 1;

		$value = $not_calculable ? 0 : round( $purchase_count / $displayed_count * 100, 2 );

		update_post_meta( $this->id, 'conversion_rate', $value );
	}

	public function get_conversion_rate(): string {
		$value = get_post_meta( $this->id, 'conversion_rate', true );

		return '' === $value ? '--' : floatval( $value ) . '%';
	}

	public function get_item_removal_behavior(): string {
		$value = get_post_meta( $this->id, 'cfw_ob_item_removal_behavior', true );

		return empty( $value ) ? 'keep' : $value;
	}

	public function get_estimated_revenue(): float {
		$offer_product = $this->get_offer_product();

		if ( ! $offer_product ) {
			return 0.0;
		}

		if ( $this->is_valid_upsell() ) {
			$base_product = $this->get_upsell_product();

			if ( ! $base_product ) {
				return 0.0;
			}

			return ( $base_product->get_price() - $this->get_price() ) * $this->get_purchase_count();
		}

		return $this->get_purchase_count() * $this->get_price();
	}

	public function get_captured_revenue(): float {
		return floatval( get_post_meta( $this->id, 'captured_revenue', true ) );
	}

	/**
	 * @return false|WC_Product|null
	 */
	public function get_upsell_product() {
		$upsell_product_id = is_array( $this->upsell_product ) ? $this->upsell_product[0] ?? 0 : $this->upsell_product;

		return wc_get_product( $upsell_product_id );
	}

	/**
	 * @return false|WC_Product|null
	 */
	public function get_match_quantity_product() {
		$match_quantity_product_id = is_array( $this->match_quantity_product ) ? $this->match_quantity_product[0] ?? 0 : $this->match_quantity_product;

		return wc_get_product( $match_quantity_product_id );
	}

	/**
	 * @return false|WC_Product|null
	 */
	public function get_variation_match_product() {
		$variation_match_product_id = is_array( $this->variation_match_product ) ? $this->variation_match_product[0] ?? 0 : $this->variation_match_product;

		return wc_get_product( $variation_match_product_id );
	}

	public static function get_post_type(): string {
		return 'cfw_order_bumps';
	}

	public static function init( $parent_menu_slug ) {
		$post_type = self::get_post_type();

		add_action(
			'init',
			function () use ( $post_type, $parent_menu_slug ) {
				register_post_type(
					$post_type,
					array(
						'labels'             => array(
							'name'               => __( 'Order Bumps', 'checkout-wc' ),
							'singular_name'      => __( 'Order Bump', 'checkout-wc' ),
							'add_new'            => __( 'Add New', 'checkout-wc' ),
							'add_new_item'       => __( 'Add New Order Bump', 'checkout-wc' ),
							'edit_item'          => __( 'Edit Order Bump', 'checkout-wc' ),
							'new_item'           => __( 'New Order Bump', 'checkout-wc' ),
							'view_item'          => __( 'View Order Bump', 'checkout-wc' ),
							'search_items'       => __( 'Find Order Bump', 'checkout-wc' ),
							'not_found'          => __( 'No order bumps were found.', 'checkout-wc' ),
							'not_found_in_trash' => __( 'Not found in trash', 'checkout-wc' ),
							'menu_name'          => __( 'Order Bumps', 'checkout-wc' ),
						),
						'public'             => false,
						'publicly_queryable' => false,
						'show_ui'            => true,
						'show_in_menu'       => false,
						'show_in_rest'       => true,
						'query_var'          => false,
						'rewrite'            => false,
						'has_archive'        => false,
						'hierarchical'       => false,
						'supports'           => array( 'title', 'page-attributes', 'editor', 'custom-fields' ),
						'template'           => array(
							array(
								'cfw/order-bump-preview',
								array(
									'lock' => array(
										'move'   => true,
										'remove' => true,
									),
								),
							),
						),
						'capabilities'       => array(
							'edit_post'          => 'cfw_manage_order_bumps',
							'read_post'          => 'cfw_manage_order_bumps',
							'delete_post'        => 'cfw_manage_order_bumps',
							'edit_posts'         => 'cfw_manage_order_bumps',
							'edit_others_posts'  => 'cfw_manage_order_bumps',
							'delete_posts'       => 'cfw_manage_order_bumps',
							'publish_posts'      => 'cfw_manage_order_bumps',
							'read_private_posts' => 'cfw_manage_order_bumps',
						),
					)
				);

				add_filter(
					"rest_prepare_{$post_type}",
					function ( $response ) {
						if ( empty( $response->data['content']['raw'] ) ) {
							$response->data['template'] = array(
								array(
									'cfw/order-bump-preview',
									array(
										'lock' => array(
											'move'   => true,
											'remove' => true,
										),
									),
								),
							);
						}

					return $response;
					},
					10,
					2
				);
			}
		);
	}

	/**
	 * @return bool
	 */
	public function is_in_cart(): bool {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['_cfw_order_bump_id'] ) && $cart_item['_cfw_order_bump_id'] === $this->id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function can_quantity_be_updated(): bool {
		return $this->can_quantity_be_updated && ! $this->match_offer_product_quantity;
	}

	public function get_products_to_remove(): array {
		return $this->products_to_remove;
	}

	public function should_be_auto_added(): bool {
		return $this->auto_add;
	}

	public function should_match_offer_product_quantity(): bool {
		return $this->match_offer_product_quantity && $this->get_match_quantity_product();
	}

	public function has_custom_add_to_cart_handler(): bool {
		return $this->is_valid_upsell();
	}

	public function get_custom_add_to_cart_handler(): string {
		return $this->has_custom_add_to_cart_handler() ? 'cfw_upsell_bump_add_to_cart_handler' : '';
	}

	/**
	 * Check if any rule explicitly references the offer product
	 *
	 * @return bool True if rules explicitly check for the offer product in cart
	 */
	protected function rules_explicitly_check_for_offer_product(): bool {
		if ( empty( $this->rules ) || ! is_array( $this->rules ) ) {
			return false;
		}

		$offer_product = $this->get_offer_product();

		if ( ! $offer_product ) {
			return false;
		}

		$offer_product_id = $offer_product->get_id();

		// Get parent product ID if this is a variation
		$parent_product_id = 0;
		if ( $offer_product->is_type( 'variation' ) ) {
			$parent_product_id = $offer_product->get_parent_id();
		}

		foreach ( $this->rules as $rule ) {
			// Skip if not a cart contains products rule
			if ( ! isset( $rule['fieldKey'] ) || 'cartContents' !== $rule['fieldKey'] ) {
				continue;
			}

			if ( ! isset( $rule['subFields']['cartContents'] ) || 'containsProducts' !== $rule['subFields']['cartContents'] ) {
				continue;
			}

			// Skip if no products list in the rule
			if ( ! isset( $rule['subFields']['field_2'] ) || ! is_array( $rule['subFields']['field_2'] ) ) {
				continue;
			}

			foreach ( $rule['subFields']['field_2'] as $product ) {
				if ( ! isset( $product['key'] ) ) {
					continue;
				}

				$rule_product_id = (int) $product['key'];

				// Check if rule matches the offer product ID or its parent ID (for variations)
				if ( $rule_product_id === (int) $offer_product_id ||
					( $parent_product_id && $rule_product_id === (int) $parent_product_id ) ) {
					// Found explicit rule for the offer product
					cfw_debug_log( 'Order Bump has explicit rule for offer product. Bump: ' . $this->get_id() );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get discount type
	 *
	 * @return string
	 */
	public function get_discount_type(): string {
		return $this->discount_type ?? '';
	}

	/**
	 * Get offer discount amount
	 *
	 * @return float
	 */
	public function get_offer_discount(): float {
		return (float) ( $this->offer_discount ?? 0 );
	}
}
