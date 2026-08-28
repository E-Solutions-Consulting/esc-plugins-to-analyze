<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Welcome_Kit_Eligibility {

    private WC_Order $order;

    public function __construct( WC_Order $order ) {
        $this->order = $order;
    }

    /**
     * Returns true if the order qualifies for a welcome kit.
     *
     * Dedup is per order (the _welcome_kit_created flag lives on the parent
     * order). Renewals are excluded so only the initial order/subscription
     * fires.
     */
    public function passes(): bool {
        if ( get_option( 'ah_welcome_kit_enabled' ) !== 'yes' ) return false;
        if ( wcs_order_contains_renewal( $this->order ) ) return false;
        if ( $this->order->get_meta( '_welcome_kit_created' ) ) return false;
        if ( ! $this->matches_trigger() ) return false;

        return true;
    }

    /**
     * Returns true if the order contains at least one item matching
     * an allowed category OR an allowed trigger product (OR logic).
     */
    private function matches_trigger(): bool {
        $allowed_categories  = get_option( 'ah_welcome_kit_allowed_categories', [] );
        $trigger_product_ids = get_option( 'ah_welcome_kit_trigger_product_ids', [] );

        foreach ( $this->order->get_items() as $item ) {
            $product_id = $item->get_product_id();

            if ( ! empty( $trigger_product_ids ) && in_array( $product_id, array_map( 'intval', $trigger_product_ids ), true ) ) {
                return true;
            }

            if ( ! empty( $allowed_categories ) ) {
                $terms = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );
                if ( array_intersect( $allowed_categories, $terms ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}
