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
     * An order matches if at least one of its items resolves to a gift
     * product (see resolve_gift_product_ids()).
     */
    private function matches_trigger(): bool {
        return ! empty( $this->resolve_gift_product_ids() );
    }

    /**
     * Resolves which gift product(s) to include in the kit for this order.
     *
     * Each order item is matched against the product map: a variation-level
     * entry (a specific override) takes priority over its parent product's
     * entry (checking the parent means "all its variations").
     *
     * @return int[] Deduplicated gift product IDs.
     */
    public function resolve_gift_product_ids(): array {
        $product_map = self::get_product_map();
        if ( empty( $product_map ) ) return [];

        $gift_ids = [];

        foreach ( $this->order->get_items() as $item ) {
            $product_id   = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            $gift_id = 0;
            if ( $variation_id && isset( $product_map[ $variation_id ] ) ) {
                $gift_id = $product_map[ $variation_id ];
            } elseif ( isset( $product_map[ $product_id ] ) ) {
                $gift_id = $product_map[ $product_id ];
            }

            if ( $gift_id ) {
                $gift_ids[ $gift_id ] = true;
            }
        }

        return array_keys( $gift_ids );
    }

    /**
     * The trigger-product => gift-product map, keyed by whichever ID was
     * checked in settings: a parent (variable) product ID means "all its
     * variations", a variation ID is a specific per-variation override.
     *
     * No automatic migration from the old single-gift-product settings --
     * the old data (a parent product ID meaning "all variations") can't
     * reliably be translated into the new per-variation model without
     * knowing the admin's actual intent, so sites upgrading from that
     * version must reconfigure this screen explicitly instead of trusting
     * an auto-migrated guess.
     *
     * @return array<int,int>
     */
    public static function get_product_map(): array {
        $raw = get_option( 'ah_welcome_kit_product_map', [] );
        if ( ! is_array( $raw ) ) return [];

        $map = [];
        foreach ( $raw as $trigger_id => $gift_id ) {
            $map[ (int) $trigger_id ] = (int) $gift_id;
        }
        return $map;
    }
}
