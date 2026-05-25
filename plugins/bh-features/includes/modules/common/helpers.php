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