<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the renewal days configured for a given product (variation).
 *
 * Resolution order:
 *   1. _bh_renewal_days meta on the variation (or simple product)
 *   2. BH_DAYS_MONTHLY_PLAN / BH_DAYS_THREE_MONTH_PLAN constants based on billing_interval
 *
 * @param WC_Product $product  The variation (or simple product) object.
 * @return int
 */
function bh_get_renewal_days_for_product( WC_Product $product ): int {
	$meta_days = (int) get_post_meta( $product->get_id(), '_bh_renewal_days', true );

	if ( $meta_days > 0 ) {
		return $meta_days;
	}

	$billing_interval = (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true );

	return $billing_interval === 1 ? BH_DAYS_MONTHLY_PLAN : BH_DAYS_THREE_MONTH_PLAN;
}

/**
 * Whether this order came from the Next/landing bridge (not classic WP checkout).
 *
 * @param WC_Order|int $order Order object or ID.
 * @return bool
 */
function bh_order_is_landing_checkout( $order ): bool {
	if ( is_numeric( $order ) ) {
		$order = wc_get_order( absint( $order ) );
	}
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}
	$source = (string) $order->get_meta( '_brello_landing_source' );
	return $source !== '';
}

/**
 * Whether Telegra should run async for a landing/Next order.
 * Classic WP checkout always returns false (sync / original path).
 *
 * Default: OFF (sync). Async was enabled in df4ddac to avoid REST timeouts /
 * a second Action Scheduler hop, but empty-link retries (30s → 120s → 300s)
 * made post-order questionnaire wait >1 minute. Landing now uses the same
 * sync on-hold → TelemdNow trigger path as the store.
 *
 * Re-enable only via wp-config: define( 'BH_ASYNC_TELEGRA_LANDING', true );
 *
 * @param WC_Order|int|null $order Optional order; classic (non-landing) ⇒ false.
 * @return bool
 */
function bh_is_async_telegra_landing_enabled( $order = null ): bool {
	if ( null !== $order && ! bh_order_is_landing_checkout( $order ) ) {
		return false;
	}

	// Sync by default. Ignore DB option so staging "yes" cannot re-open the gap.
	$enabled = false;
	if ( defined( 'BH_ASYNC_TELEGRA_LANDING' ) ) {
		$enabled = (bool) BH_ASYNC_TELEGRA_LANDING;
	}

	return (bool) apply_filters( 'bh_async_telegra_landing_enabled', $enabled, $order );
}

/**
 * @deprecated Use bh_is_async_telegra_landing_enabled( $order ). Kept for older call sites.
 * Without an order, this no longer enables classic WP async (always false for safety).
 *
 * @return bool
 */
function bh_is_async_telegra_checkout_enabled(): bool {
	return false;
}

/**
 * Purchase / thank-you tracking payload shared by classic WP thank-you and landing bridge.
 * Field names match classic `$tracking_data` plus landing camel aliases for the Next client.
 *
 * @param WC_Order $order Order.
 * @return array<string, mixed>
 */
function bh_build_purchase_tracking_payload( WC_Order $order ): array {
	$order_id      = $order->get_id();
	$sale_amount   = (float) $order->get_total();
	$subtotal      = (float) $order->get_subtotal();
	$email         = (string) $order->get_billing_email();
	$phone         = (string) $order->get_billing_phone();
	$discount_code = implode( ', ', $order->get_coupon_codes() );
	$currency      = (string) $order->get_currency();

	$line_items = array();
	foreach ( $order->get_items() as $item ) {
		$product      = $item->get_product();
		$line_items[] = array(
			'name'      => (string) $item->get_name(),
			'id'        => (string) $item->get_product_id(),
			'quantity'  => (string) $item->get_quantity(),
			'price'     => (string) $item->get_subtotal(),
			'variantId' => (string) $item->get_variation_id(),
			'image'     => ( $product && is_callable( array( $product, 'get_image_id' ) ) )
				? (string) wp_get_attachment_url( $product->get_image_id() )
				: '',
			'type'      => (string) $item->get_type(),
		);
	}

	return array(
		// Classic thank-you `$tracking_data` keys.
		'order_id'         => $order_id,
		'order_key'        => $order->get_order_key(),
		'sale_amount'      => $sale_amount,
		'subtotal_amount'  => $subtotal,
		'email'            => $email,
		'discount_code'    => $discount_code,
		'currency'         => $currency,
		// Landing bridge / client aliases (same values).
		'total'            => $sale_amount,
		'subtotal'         => $subtotal,
		'billing_email'    => $email,
		'billing_phone'    => $phone,
		'discount_codes'   => $discount_code,
		'line_items'       => $line_items,
		'thank_you_url'    => (string) $order->get_checkout_order_received_url(),
	);
}

/**
 * Non-UI thank-you side effects (server). Safe for classic thank-you + landing REST.
 * Idempotent per order via `_bh_purchase_side_effects_done` meta.
 *
 * @param WC_Order|int $order   Order object or ID.
 * @param string       $context Caller label for logs (e.g. classic_thankyou, landing_bridge).
 * @return bool True when side effects ran (or already done).
 */
function bh_run_purchase_side_effects( $order, string $context = '' ): bool {
	if ( is_numeric( $order ) ) {
		$order = wc_get_order( absint( $order ) );
	}
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	$order_id = $order->get_id();
	if ( $order_id < 1 ) {
		return false;
	}

	if ( $order->get_meta( '_bh_purchase_side_effects_done' ) ) {
		return true;
	}

	// Same Attentive AS job as BH_Attentive_Unified_Events::enqueue_order_success.
	// Only when the Attentive worker is registered (integrations may be unloaded).
	if (
		function_exists( 'as_enqueue_async_action' )
		&& has_action( 'bh_attentive_process_order' )
	) {
		as_enqueue_async_action(
			'bh_attentive_process_order',
			array( array( 'order_id' => $order_id ) ),
			'bh-attentive'
		);
	}

	$order->update_meta_data( '_bh_purchase_side_effects_done', gmdate( 'c' ) );
	if ( $context !== '' ) {
		$order->update_meta_data( '_bh_purchase_side_effects_context', sanitize_key( $context ) );
	}
	$order->save();

	/**
	 * After non-UI purchase side effects (Attentive enqueue, etc.).
	 *
	 * @param int    $order_id Order ID.
	 * @param string $context  Caller context.
	 */
	do_action( 'bh_purchase_side_effects_ran', $order_id, $context );

	return true;
}