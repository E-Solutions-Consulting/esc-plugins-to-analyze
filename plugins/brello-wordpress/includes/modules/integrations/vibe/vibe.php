<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vibe pixel integration.
 *
 * Pixel O17U5n — page_view on all pages; purchase on all orders.
 * Pixel I9qTCM — page_view on the NAD product page only;
 *                purchase on orders containing product ID 518309 only.
 */
class AH_Vibe {

    const PIXEL_GLOBAL     = 'O17U5n';
    const PIXEL_NAD        = 'I9qTCM';
    const PRODUCT_ID_NAD   = 518309;

    public function __construct() {
        add_action( 'wp_head',   [ $this, 'print_page_view_pixels' ], 20  );
        add_action( 'wp_footer', [ $this, 'print_purchase_pixels'  ], 100 );
    }

    /**
     * Emits page_view pixels in <head>.
     * O17U5n fires on every page.
     * I9qTCM fires only on the NAD product page.
     */
    public function print_page_view_pixels() {
        ?>
        <script>
        !function(v,i,b,e,c,o){if(!v[c]){var s=v[c]=function(){s.process?s.process.apply(s,arguments):s.queue.push(arguments)};s.queue=[],s.b=1*new Date;var t=i.createElement(b);t.async=!0,t.src=e;var n=i.getElementsByTagName(b)[0];n.parentNode.insertBefore(t,n)}}(window,document,"script","https://s.vibe.co/vbpx.js","vbpx");
        vbpx('init', '<?php echo esc_js( self::PIXEL_GLOBAL ); ?>');
        vbpx('event', 'page_view');
        </script>
        <?php

        if ( ! $this->is_nad_product_page() ) {
            return;
        }
        ?>
        <script>
        vbpx('init', '<?php echo esc_js( self::PIXEL_NAD ); ?>');
        vbpx('event', 'page_view');
        </script>
        <?php
    }

    /**
     * Emits purchase pixels in footer on the order-received page.
     * O17U5n fires for all orders.
     * I9qTCM fires only when the order contains the NAD product.
     */
    public function print_purchase_pixels() {
        if ( ! is_order_received_page() ) {
            return;
        }

        global $tracking_data;
        if ( empty( $tracking_data ) ) {
            return;
        }

        $sale_amount = esc_js( $tracking_data['sale_amount'] );
        $order_id    = esc_js( $tracking_data['order_id']    );
        ?>
        <script>
        !function(v,i,b,e,c,o){if(!v[c]){var s=v[c]=function(){s.process?s.process.apply(s,arguments):s.queue.push(arguments)};s.queue=[],s.b=1*new Date;var t=i.createElement(b);t.async=!0,t.src=e;var n=i.getElementsByTagName(b)[0];n.parentNode.insertBefore(t,n)}}(window,document,"script","https://s.vibe.co/vbpx.js","vbpx");
        vbpx('init', '<?php echo esc_js( self::PIXEL_GLOBAL ); ?>');
        vbpx('event', 'purchase', {
            price_usd:   '<?php echo $sale_amount; ?>',
            purchase_id: '<?php echo $order_id; ?>'
        });
        </script>
        <?php

        if ( ! $this->order_contains_nad( $tracking_data['order_id'] ) ) {
            return;
        }
        ?>
        <script>
        vbpx('init', '<?php echo esc_js( self::PIXEL_NAD ); ?>');
        vbpx('event', 'purchase', {
            price_usd:   '<?php echo $sale_amount; ?>',
            purchase_id: '<?php echo $order_id; ?>'
        });
        </script>
        <?php
    }

    /**
     * Returns true when the current page is the NAD product page.
     */
    private function is_nad_product_page() {
        return is_singular( 'product' ) && get_the_ID() === self::PRODUCT_ID_NAD;
    }

    /**
     * Returns true when the given order contains the NAD product.
     *
     * @param int $order_id
     */
    private function order_contains_nad( $order_id ) {
        $order = wc_get_order( absint( $order_id ) );
        if ( ! $order ) {
            return false;
        }

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( (int) $item->get_product_id() === self::PRODUCT_ID_NAD ) {
                return true;
            }
        }

        return false;
    }
}