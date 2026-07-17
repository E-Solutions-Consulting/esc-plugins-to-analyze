<?php

namespace Objectiv\Plugins\Checkout\Model\Bumps;

use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;

class NullBump implements BumpInterface {
	public function get_id(): int {
		return 0;
	}

	public function has_custom_add_to_cart_handler(): bool {
		return false;
	}

	public function get_custom_add_to_cart_handler(): string {
		return '';
	}

	public function add_to_cart( \WC_Cart $cart ): bool {
		return false;
	}

	public function record_displayed() {}

	public function display( string $location ) {}

	public function record_purchased() {}

	public function add_bump_meta_to_order_item( $item, $values ) {}

	public function get_cfw_cart_item_discount( string $price_html, array $cart_item ): string {
		return $price_html;
	}

	public function get_conversion_rate(): string {
		return '--';
	}

	public function get_captured_revenue(): float {
		return 0.0;
	}

	public function is_displayable( $cart = null ): bool {
		return false;
	}

	public function is_in_cart(): bool {
		return false;
	}

	public function get_display_location(): string {
		return '';
	}

	public function get_offer_product_price( int $variation_id = 0 ): string {
		return '';
	}

	public function get_offer_language(): string {
		return '';
	}

	public function get_offer_description(): string {
		return '';
	}

	public function get_offer_heading(): string {
		return '';
	}

	public function get_offer_subheading(): string {
		return '';
	}

	public function get_offer_cancel_button_text(): string {
		return '';
	}

	public function get_offer_product() {
		return null;
	}

	public function get_item_removal_behavior(): string {
		return 'delete';
	}

	public function is_cart_bump_valid(): bool {
		return false;
	}

	public function is_published(): bool {
		return false;
	}

	public function can_quantity_be_updated(): bool {
		return false;
	}

	public function get_price( string $context = 'view', int $variation_id = 0 ): float {
		return 0.0;
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

	public function get_discount_type(): string {
		return '';
	}

	public function get_offer_discount(): float {
		return 0.0;
	}
}
