<?php

namespace Objectiv\Plugins\Checkout\Interfaces;

interface BumpInterface {
	public function get_id(): int;
	public function add_to_cart( \WC_Cart $cart );
	public function record_displayed();
	public function record_purchased();
	public function add_bump_meta_to_order_item( $item, $values );
	public function get_cfw_cart_item_discount( string $price_html, array $cart_item );
	public function display( string $location );
	public function get_captured_revenue(): float;
	public function get_offer_product();

	public function get_offer_product_price( int $variation_id = 0 ): string;

	public function get_offer_language(): string;

	public function get_offer_description(): string;

	public function get_offer_heading(): string;

	public function get_offer_subheading(): string;

	public function get_offer_cancel_button_text(): string;

	public function get_conversion_rate();
	public function is_in_cart(): bool;
	public function get_item_removal_behavior(): string;
	public function get_display_location(): string;

	public function is_displayable( $cart = null ): bool;

	public function can_quantity_be_updated(): bool;

	public function is_cart_bump_valid(): bool;
	public function is_published(): bool;
	public function get_price( string $context = 'view', int $variation_id = 0 ): float;

	public function get_products_to_remove(): array;

	public function should_be_auto_added(): bool;
	public function should_match_offer_product_quantity(): bool;

	public function has_custom_add_to_cart_handler(): bool;

	public function get_custom_add_to_cart_handler(): string;

	public function get_discount_type(): string;

	public function get_offer_discount(): float;
}
