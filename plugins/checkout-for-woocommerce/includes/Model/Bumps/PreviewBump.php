<?php

namespace Objectiv\Plugins\Checkout\Model\Bumps;

use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;
use WC_Product;

/**
 * Preview Bump class for rendering order bump modal previews in the admin editor.
 *
 * This class implements BumpInterface with arbitrary data passed from the editor,
 * allowing the preview to use unsaved values while reusing the same rendering functions
 * as production.
 */
class PreviewBump implements BumpInterface {
	/**
	 * @var array
	 */
	private $data;

	/**
	 * @var WC_Product
	 */
	private $product;

	/**
	 * Constructor.
	 *
	 * @param array      $data    The bump data from the editor.
	 * @param WC_Product $product The offer product.
	 */
	public function __construct( array $data, WC_Product $product ) {
		$this->data    = $data;
		$this->product = $product;
	}

	public function get_id(): int {
		return 0;
	}

	public function get_offer_product() {
		return $this->product;
	}

	public function get_offer_product_price( int $variation_id = 0 ): string {
		return $this->product->get_price_html();
	}

	public function get_offer_description(): string {
		return $this->data['offer_description'] ?? '';
	}

	public function get_offer_language(): string {
		return $this->data['offer_language'] ?? '';
	}

	public function get_offer_heading(): string {
		return $this->data['offer_heading'] ?? '';
	}

	public function get_offer_subheading(): string {
		return $this->data['offer_subheading'] ?? '';
	}

	public function get_offer_cancel_button_text(): string {
		return $this->data['offer_cancel_button_text'] ?? '';
	}

	public function get_display_location(): string {
		return $this->data['display_location'] ?? '';
	}

	// Stub methods - not used in preview rendering

	public function add_to_cart( \WC_Cart $cart ) {
		return false;
	}

	public function record_displayed() {}

	public function record_purchased() {}

	public function add_bump_meta_to_order_item( $item, $values ) {}

	public function get_cfw_cart_item_discount( string $price_html, array $cart_item ): string {
		return $price_html;
	}

	public function display( string $location ) {
		return false;
	}

	public function get_captured_revenue(): float {
		return 0.0;
	}

	public function get_conversion_rate() {
		return '--';
	}

	public function is_in_cart(): bool {
		return false;
	}

	public function get_item_removal_behavior(): string {
		return 'keep';
	}

	public function is_displayable( $cart = null ): bool {
		return true;
	}

	public function can_quantity_be_updated(): bool {
		return false;
	}

	public function is_cart_bump_valid(): bool {
		return true;
	}

	public function is_published(): bool {
		return true;
	}

	public function get_price( string $context = 'view', int $variation_id = 0 ): float {
		return (float) $this->product->get_price();
	}

	public function get_products_to_remove(): array {
		return array();
	}

	public function should_be_auto_added(): bool {
		return false;
	}

	public function should_match_offer_product_quantity(): bool {
		return false;
	}

	public function has_custom_add_to_cart_handler(): bool {
		return false;
	}

	public function get_custom_add_to_cart_handler(): string {
		return '';
	}

	public function get_discount_type(): string {
		return '';
	}

	public function get_offer_discount(): float {
		return 0.0;
	}
}
