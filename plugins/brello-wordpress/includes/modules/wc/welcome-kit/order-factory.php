<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Welcome_Kit_Order_Factory {

    private WC_Order $parent_order;

    /** @var WC_Product[] */
    private array $gift_products;

    /** Status for the kit order; '' falls back to the global default status. */
    private string $status;

    /**
     * @param WC_Product[] $gift_products One or more gift products to add to
     *                                    the kit order (one per distinct
     *                                    trigger item in the parent order).
     * @param string       $status        Status for the kit order; '' uses
     *                                    the global default status.
     */
    public function __construct( WC_Order $parent_order, array $gift_products, string $status = '' ) {
        $this->parent_order  = $parent_order;
        $this->gift_products = $gift_products;
        $this->status        = $status;
    }

    /**
     * Creates and persists the welcome kit WC order.
     *
     * The kit is a gift, so it's always a $0 order no matter what the gift
     * products cost in the catalog (e.g. a Printful product priced for its
     * fulfilment cost) — same approach as free-renewal deliveries. A priced
     * line would leave the kit awaiting payment instead of reaching its
     * fulfilment status.
     *
     * The order is built as "pending" and only moved to its final status once
     * items and totals are in place, so the status hooks/emails for that
     * status (e.g. "processing") see the complete order, not an empty one.
     *
     * @return WC_Order|WP_Error
     */
    public function create() {
        $kit_order = wc_create_order( [
            'customer_id' => $this->parent_order->get_user_id(),
        ] );

        if ( is_wp_error( $kit_order ) ) {
            return $kit_order;
        }

        $kit_order->set_address( $this->parent_order->get_address( 'billing' ), 'billing' );
        $kit_order->set_address( $this->parent_order->get_address( 'shipping' ), 'shipping' );

        foreach ( $this->gift_products as $gift_product ) {
            $kit_order->add_product( $gift_product, 1, [
                'subtotal' => 0,
                'total'    => 0,
            ] );
        }

        $kit_order->calculate_totals();

        $kit_order->update_meta_data( '_welcome_kit_parent_order_id', $this->parent_order->get_id() );
        $kit_order->update_meta_data( '_welcome_kit_origin', 'auto' );
        $kit_order->update_meta_data( '_welcome_kit_price_type', 'free' );

        $kit_order->save();

        $kit_order->update_status(
            $this->resolve_status(),
            'Welcome Kit gift order created at $0 (no charge).'
        );

        return $kit_order;
    }

    /**
     * The gift row's status override, or the global default status.
     */
    private function resolve_status(): string {
        return $this->status !== '' ? $this->status : AH_Welcome_Kit::get_default_status();
    }
}
