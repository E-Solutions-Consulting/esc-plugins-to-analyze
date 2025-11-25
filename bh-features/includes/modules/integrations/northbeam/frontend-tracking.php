<?php
/**
 * Northbeam Purchase Tracking (Thank You page)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_footer', function() {

    if ( ! is_order_received_page() ) return;

    global $tracking_data;
    if ( ! $tracking_data ) return;

    $order = wc_get_order( $tracking_data['order_id'] );
    if ( ! $order ) return;

    $line_items = [];

    foreach ( $order->get_items() as $item_id => $item ) {

        $product = $item->get_product();
        if ( ! $product ) continue;

        $parent_id  = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $parent     = wc_get_product( $parent_id );
        $parent_sku = $parent ? $parent->get_sku() : '';

        $productId = $parent_sku ? $parent_sku : (string) $parent_id;

        if ( $product->is_type('variation') ) {
            $variant_sku = $product->get_sku();
            $variantId   = $variant_sku ? $variant_sku : (string) $product->get_id();
        } else {
            $variantId = $productId;
        }

        $variantName = '';
        if ( $product->is_type('variation') ) {
            $attributes = $product->get_variation_attributes();
            $clean      = [];
            foreach ( $attributes as $key => $value ) {
                $clean[] = ucfirst( str_replace( 'attribute_', '', $key ) ) . ': ' . ucfirst( $value );
            }
            $variantName = implode( ', ', $clean );
        }

        $line_items[] = [
            'productId'   => (string) $productId,
            'variantId'   => (string) $variantId,
            'productName' => $item->get_name(),
            'variantName' => $variantName,
            'price'       => (float) $item->get_total(),
            'quantity'    => (int) $item->get_quantity(),
        ];
    }

    $coupons = [];
    foreach ( $order->get_items('coupon') as $coupon_item ) {
        $coupons[] = $coupon_item->get_code();
    }

    $nb_data = [
        'id'            => (string) $order->get_id(),
        'totalPrice'    => (float) $order->get_total(),
        'shippingPrice' => (float) $order->get_shipping_total(),
        'taxPrice'      => (float) $order->get_total_tax(),
        'coupons'       => implode(',', $coupons),
        'currency'      => $order->get_currency(),
        'lineItems'     => $line_items,
    ];
    ?>
    <script>
        // Northbeam task
        BrelloTrackingContainer.addTask("northbeam", function() {
            try {
                window.Northbeam.firePurchaseEvent(<?php echo wp_json_encode($nb_data, JSON_UNESCAPED_UNICODE); ?>);
                console.log("[Northbeam] firePurchaseEvent fired");
            } catch (e) {
                console.error("[Northbeam] Error firing purchase event:", e);
            }
        });

        // Wait for Northbeam pixel
        (function() {
            let attempts = 0;
            const maxAttempts = 30; // ~3s
            const interval = setInterval(function(){
                attempts++;
                if (window.Northbeam && typeof window.Northbeam.firePurchaseEvent === "function") {
                    clearInterval(interval);
                    BrelloTrackingContainer.markReady("northbeam");
                    console.log("[Northbeam] Pixel ready");
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    console.warn("[Northbeam] Pixel not ready after max attempts");
                    // We still let fallback timer handle execution
                }
            }, 100);
        })();
    </script>
    <?php
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '???';
    if ( function_exists('bh_plugins_log') ) {
        bh_plugins_log( $ip_address . ' ' . wp_json_encode($nb_data, JSON_UNESCAPED_UNICODE), 'bh_plugins-northbeam_firePurchaseEvent' );
    }
}, 120);
