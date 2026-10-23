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
     * Dedup is per order (the _welcome_kit_created flag and the kit plan
     * live on the parent order). Renewals are excluded so only the initial
     * order/subscription fires.
     */
    public function passes(): bool {
        if ( get_option( 'ah_welcome_kit_enabled' ) !== 'yes' ) return false;
        if ( wcs_order_contains_renewal( $this->order ) ) return false;
        if ( $this->order->get_meta( '_welcome_kit_created' ) ) return false;
        if ( $this->order->get_meta( '_welcome_kit_plan' ) ) return false;
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
        $gift_ids = [];
        foreach ( $this->resolve_matched_entries() as $entry ) {
            $gift_ids[ $entry['gift'] ] = true;
        }

        return array_keys( $gift_ids );
    }

    /**
     * Groups the matched gifts into kit orders.
     *
     * Each map row can override the kit order status and send delay, so gifts
     * that resolve to a different status/delay can't share one kit order.
     * Gifts with the same effective status and delay are grouped together.
     *
     * @return array<string,array{status:string,delay:int,gifts:int[]}> Keyed by
     *         AH_Welcome_Kit::group_key().
     */
    public function resolve_kit_groups(): array {
        $default_status   = AH_Welcome_Kit::get_default_status();
        $default_schedule = AH_Welcome_Kit::get_default_schedule();

        $groups = [];
        foreach ( $this->resolve_matched_entries() as $entry ) {
            $status = $entry['status'] !== '' ? $entry['status'] : $default_status;
            $delay  = AH_Welcome_Kit::schedule_to_delay( $entry['schedule'] !== '' ? $entry['schedule'] : $default_schedule );
            $key    = AH_Welcome_Kit::group_key( $status, $delay );

            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = [ 'status' => $status, 'delay' => $delay, 'gifts' => [] ];
            }
            if ( ! in_array( $entry['gift'], $groups[ $key ]['gifts'], true ) ) {
                $groups[ $key ]['gifts'][] = $entry['gift'];
            }
        }

        return $groups;
    }

    /**
     * The map entry each order item resolves to (variation override first,
     * then its parent product), skipping items with no entry.
     *
     * @return array<int,array{gift:int,status:string,schedule:string}>
     */
    private function resolve_matched_entries(): array {
        $product_map = self::get_product_map();
        if ( empty( $product_map ) ) return [];

        $entries = [];

        foreach ( $this->order->get_items() as $item ) {
            $product_id   = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            if ( $variation_id && isset( $product_map[ $variation_id ] ) ) {
                $entries[] = $product_map[ $variation_id ];
            } elseif ( isset( $product_map[ $product_id ] ) ) {
                $entries[] = $product_map[ $product_id ];
            }
        }

        return $entries;
    }

    /**
     * The trigger-product => gift map, keyed by whichever ID was checked in
     * settings: a parent (variable) product ID means "all its variations", a
     * variation ID is a specific per-variation override.
     *
     * Each entry carries the gift product plus optional per-row overrides for
     * the kit order status and send schedule ('' = use the global default).
     * Entries saved by the previous version (a plain gift ID) are read as a
     * row with no overrides, so existing settings keep working unchanged.
     *
     * No automatic migration from the old single-gift-product settings --
     * the old data (a parent product ID meaning "all variations") can't
     * reliably be translated into the new per-variation model without
     * knowing the admin's actual intent, so sites upgrading from that
     * version must reconfigure this screen explicitly instead of trusting
     * an auto-migrated guess.
     *
     * @return array<int,array{gift:int,status:string,schedule:string}>
     */
    public static function get_product_map(): array {
        $raw = get_option( 'ah_welcome_kit_product_map', [] );
        if ( ! is_array( $raw ) ) return [];

        $map = [];
        foreach ( $raw as $trigger_id => $value ) {
            $entry = is_array( $value ) ? $value : [ 'gift' => $value ];

            $gift_id = (int) ( $entry['gift'] ?? 0 );
            if ( $gift_id <= 0 ) continue;

            $status   = (string) ( $entry['status'] ?? '' );
            $schedule = (string) ( $entry['schedule'] ?? '' );

            $map[ (int) $trigger_id ] = [
                'gift'     => $gift_id,
                'status'   => isset( AH_Welcome_Kit::status_options()[ $status ] ) ? $status : '',
                'schedule' => isset( AH_Welcome_Kit::schedule_options()[ $schedule ] ) ? $schedule : '',
            ];
        }
        return $map;
    }
}
