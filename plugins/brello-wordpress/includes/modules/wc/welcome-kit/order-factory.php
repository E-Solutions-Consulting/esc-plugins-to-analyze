<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Welcome_Kit_Order_Factory {

    private WC_Order $parent_order;

    /** @var WC_Product[] */
    private array $gift_products;

    /**
     * @param WC_Product[] $gift_products One or more gift products to add to
     *                                    the kit order (one per distinct
     *                                    trigger item in the parent order).
     */
    public function __construct( WC_Order $parent_order, array $gift_products ) {
        $this->parent_order  = $parent_order;
        $this->gift_products = $gift_products;
    }

    /**
     * Creates and persists the welcome kit WC order.
     *
     * @return WC_Order|WP_Error
     */
    public function create() {
        $status    = $this->resolve_status();
        $kit_order = wc_create_order( [
            'customer_id' => $this->parent_order->get_user_id(),
            'status'      => $status,
        ] );

        if ( is_wp_error( $kit_order ) ) {
            return $kit_order;
        }

        $kit_order->set_address( $this->parent_order->get_address( 'billing' ), 'billing' );
        $kit_order->set_address( $this->parent_order->get_address( 'shipping' ), 'shipping' );

        foreach ( $this->gift_products as $gift_product ) {
            $kit_order->add_product( $gift_product );
        }

        $kit_order->calculate_totals();

        $kit_order->update_meta_data( '_welcome_kit_parent_order_id', $this->parent_order->get_id() );
        $kit_order->update_meta_data( '_welcome_kit_origin', 'auto' );
        $kit_order->update_meta_data( '_welcome_kit_price_type', $this->is_free() ? 'free' : 'paid' );

        $kit_order->save();

        return $kit_order;
    }

    /**
     * Free only if every gift product in the kit is free.
     */
    private function is_free(): bool {
        foreach ( $this->gift_products as $gift_product ) {
            if ( $gift_product->get_price() != 0 ) return false;
        }
        return true;
    }

    /**
     * Free kits go straight to the configured fulfilment status; a priced kit
     * (defensive fallback, not expected in practice) stays pending.
     */
    private function resolve_status(): string {
        if ( $this->is_free() ) {
            return get_option( 'ah_welcome_kit_default_status', KIT_READY_TO_SEND );
        }

        return 'pending';
    }
}
