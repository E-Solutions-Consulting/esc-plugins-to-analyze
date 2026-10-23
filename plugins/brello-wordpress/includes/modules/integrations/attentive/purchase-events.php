<?php
/**
 * Attentive Purchase Events
 *
 * Sends Attentive's standard ecommerce purchase event so "Made a Purchase"
 * is populated. Until now the integration only sent custom events
 * (OrderStatus_*, subscription, payment, questionnaire), and the JS tag is
 * limited to blog pages, so no purchase activity reached Attentive at all.
 *
 * Behaviour confirmed with the client (Sep 2026):
 *   1. Fire when the order reaches **completed**.
 *   2. Count **all** orders — initial purchases and subscription renewals.
 *
 * Sent server-side rather than through the pixel so it does not depend on
 * page settings, ad-blockers or the browser.
 *
 * Endpoint: POST /v1/events/ecommerce/purchase
 *   required: items, user        optional: orderId, occurredAt, salesChannel
 *   item required: productId, productVariantId, price
 *   price is an ARRAY of { value, currency } objects, not a single object.
 *
 * The productId / productVariantId values intentionally mirror
 * BH_Attentive_Catalog_Feed so a purchase links to the catalog entry:
 *   productId        = parent SKU, or the parent product ID when there is no SKU
 *   productVariantId = "{productId}-{variation_id}", or "{productId}-DEFAULT"
 * If that ever diverges, purchases stop matching catalog products in Attentive.
 *
 * @package    BH_Features
 * @subpackage Integrations/Attentive
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Purchase_Events {

    const AS_HOOK    = 'bh_attentive_process_purchase';
    const AS_GROUP   = 'bh-attentive';
    const EVENT_TYPE = 'purchase';

    public function __construct() {
        // Client confirmed: fire on completed only.
        add_action( 'woocommerce_order_status_completed', [ $this, 'enqueue_purchase' ], 20, 1 );

        add_action( self::AS_HOOK, [ $this, 'worker_send_purchase' ], 10, 1 );
    }

    /**
     * Enqueue-only handler — runs in the request that completed the order.
     *
     * @param int $order_id
     */
    public function enqueue_purchase( $order_id ) {

        $order_id = (int) $order_id;

        if ( ! $order_id || ! function_exists( 'as_enqueue_async_action' ) ) {
            return;
        }

        as_enqueue_async_action(
            self::AS_HOOK,
            [ [ 'order_id' => $order_id ] ],
            self::AS_GROUP
        );
    }

    /**
     * Background worker — builds and sends the purchase event.
     *
     * @param array $args [ order_id ]
     */
    public function worker_send_purchase( $args ) {

        $order_id = (int) ( $args['order_id'] ?? 0 );

        if ( ! $order_id ) {
            return;
        }

        // One purchase event per order, whatever happens to its status later.
        if ( class_exists( 'BH_Attentive_Events_Log' )
            && BH_Attentive_Events_Log::order_was_triggered( $order_id, self::EVENT_TYPE ) ) {
            BH_Attentive_Helper::log( '[Purchase] Already sent — skipping', [ 'order_id' => $order_id ] );
            return;
        }

        $order = wc_get_order( $order_id );

        // Renewal orders are shop_order too, so they are included — subscription
        // objects themselves are not a purchase.
        if ( ! $order || 'shop_order' !== $order->get_type() ) {
            return;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            BH_Attentive_Helper::log( '[Purchase] API key not configured — skipping', [ 'order_id' => $order_id ] );
            return;
        }

        $phone = BH_Attentive_Helper::normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            BH_Attentive_Helper::log( '[Purchase] No phone or email — skipping', [ 'order_id' => $order_id ] );
            return;
        }

        $items = $this->build_items( $order );

        if ( empty( $items ) ) {
            BH_Attentive_Helper::log( '[Purchase] No line items — skipping', [ 'order_id' => $order_id ] );
            return;
        }

        $user = [];
        if ( ! empty( $phone ) ) { $user['phone'] = $phone; }
        if ( ! empty( $email ) ) { $user['email'] = $email; }

        $payload = [
            'user'       => $user,
            'items'      => $items,
            'orderId'    => (string) $order->get_order_number(),
            'occurredAt' => gmdate( 'c' ),
        ];

        $response = wp_remote_post(
            trailingslashit( $settings['api_base_url'] ?? 'https://api.attentivemobile.com/v1' ) . 'events/ecommerce/purchase',
            [
                'headers'  => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'     => wp_json_encode( $payload ),
                // Blocking: this runs in an Action Scheduler worker, and the
                // response is the only way to know the purchase was accepted.
                'blocking' => true,
                'timeout'  => 20,
            ]
        );

        if ( is_wp_error( $response ) ) {
            BH_Attentive_Helper::log( '[Purchase] Request failed', [
                'order_id' => $order_id,
                'error'    => $response->get_error_message(),
            ] );
            return; // not marked, so a retry can still send it
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            BH_Attentive_Helper::log( '[Purchase] Rejected by Attentive', [
                'order_id' => $order_id,
                'email'    => $email,
                'code'     => $code,
                'body'     => mb_substr( (string) $body, 0, 500 ),
            ] );
            return; // not marked, so it can be retried after a fix
        }

        if ( class_exists( 'BH_Attentive_Events_Log' ) ) {
            BH_Attentive_Events_Log::order_mark_triggered( $order_id, self::EVENT_TYPE, [
                'items' => count( $items ),
                'total' => $order->get_total(),
            ] );
        }

        // Email is logged so a purchase can be traced to an Attentive profile:
        // profiles are searchable by phone/email only, not by order number.
        BH_Attentive_Helper::log( '[Purchase] Sent', [
            'order_id' => $order_id,
            'email'    => $email,
            'code'     => $code,
            'items'    => count( $items ),
            'total'    => $order->get_total(),
        ] );
    }

    /**
     * Build the items array from the order's line items.
     *
     * @param WC_Order $order
     * @return array
     */
    private function build_items( WC_Order $order ) {

        $items = [];

        foreach ( $order->get_items() as $item ) {

            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $parent_id    = (int) $item->get_product_id();
            $variation_id = (int) $item->get_variation_id();

            if ( ! $parent_id ) {
                continue;
            }

            $parent = wc_get_product( $parent_id );

            // Mirrors BH_Attentive_Catalog_Feed::get_catalog_id().
            $sku        = $parent ? (string) $parent->get_sku() : '';
            $catalog_id = $sku !== '' ? $sku : (string) $parent_id;

            $variant_id = $variation_id
                ? $catalog_id . '-' . $variation_id
                : $catalog_id . '-DEFAULT';

            $quantity = max( 1, (int) $item->get_quantity() );

            // Unit price including tax, matching what the customer paid.
            $line_total = (float) $order->get_line_total( $item, true );
            $unit_price = round( $line_total / $quantity, 2 );

            $entry = [
                'productId'        => $catalog_id,
                'productVariantId' => $variant_id,
                'name'             => $item->get_name(),
                'quantity'         => $quantity,
                'price'            => [
                    [
                        'value'    => $unit_price,
                        'currency' => $order->get_currency(),
                    ],
                ],
            ];

            $url = get_permalink( $parent_id );
            if ( $url ) {
                $entry['productUrl'] = $url;
            }

            if ( $parent && $parent->get_image_id() ) {
                $image = wp_get_attachment_url( $parent->get_image_id() );
                if ( $image ) {
                    $entry['productImage'] = $image;
                }
            }

            $items[] = $entry;
        }

        return $items;
    }
}
