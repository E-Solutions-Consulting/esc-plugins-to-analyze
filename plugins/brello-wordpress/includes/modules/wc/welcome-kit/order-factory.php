<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Welcome_Kit_Order_Factory {

    private WC_Order   $parent_order;
    private WC_Product $kit_product;

    public function __construct( WC_Order $parent_order, WC_Product $kit_product ) {
        $this->parent_order = $parent_order;
        $this->kit_product  = $kit_product;
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
        $kit_order->add_product( $this->kit_product );
        $kit_order->calculate_totals();

        $kit_order->update_meta_data( '_welcome_kit_parent_order_id', $this->parent_order->get_id() );
        $kit_order->update_meta_data( '_welcome_kit_origin', 'auto' );
        $kit_order->update_meta_data( '_welcome_kit_price_type', $this->kit_product->get_price() == 0 ? 'free' : 'paid' );

        $kit_order->save();

        return $kit_order;
    }

    /**
     * Free kits go straight to the configured fulfilment status; a priced kit
     * (defensive fallback, not expected in practice) stays pending.
     */
    private function resolve_status(): string {
        if ( $this->kit_product->get_price() == 0 ) {
            return get_option( 'ah_welcome_kit_default_status', KIT_READY_TO_SEND );
        }

        return 'pending';
    }
}
