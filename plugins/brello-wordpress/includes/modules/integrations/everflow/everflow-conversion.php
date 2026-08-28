<?php
/**
 * Everflow — WooCommerce classic / Blocks checkout + thank-you Event 2.
 * TID logic lives in BH_Everflow_Helper (single place).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Everflow_Conversion {

    /** @var bool Prevent double output in same request. */
    private $pixel_rendered = false;

    public function __construct() {
        add_action( 'woocommerce_thankyou', [ $this, 'track_conversion' ], 20, 1 );
        // Backup: some themes / Blocks setups skip thankyou content hooks.
        add_action( 'wp_footer', [ $this, 'track_conversion_on_order_received' ], 5 );

        add_action( 'woocommerce_init', [ $this, 'capture_eftid_to_session' ], 20 );

        // Classic checkout.
        add_action( 'woocommerce_checkout_create_order', [ $this, 'save_eftid_to_order' ], 20, 1 );
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'save_eftid_from_order_id' ], 20, 1 );

        // Blocks / Store API.
        add_action( 'woocommerce_store_api_checkout_update_order_meta', [ $this, 'save_eftid_to_order' ], 20, 1 );
        add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'save_eftid_to_order' ], 20, 1 );
    }

    public function capture_eftid_to_session() {
        BH_Everflow_Helper::capture_to_session();
    }

    public function save_eftid_from_order_id( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            BH_Everflow_Helper::attach_to_order( $order, '', 'woo_checkout' );
            $order->save();
        }
    }

    public function save_eftid_to_order( $order ) {
        BH_Everflow_Helper::attach_to_order( $order, '', 'woo_checkout' );
    }

    /**
     * Backup when theme does not fire woocommerce_thankyou content.
     */
    public function track_conversion_on_order_received() {
        if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
            return;
        }

        global $wp;
        $order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
        if ( ! $order_id && isset( $_GET['order-received'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order_id = absint( $_GET['order-received'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ( ! $order_id ) {
            return;
        }

        $this->track_conversion( $order_id );
    }

    public function track_conversion( $order_id ) {
        if ( $this->pixel_rendered ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( ! BH_Everflow_Helper::get_order_eftid( $order ) ) {
            BH_Everflow_Helper::attach_to_order( $order, '', 'woo_thankyou' );
            $order->save();
        }

        $transaction_id = BH_Everflow_Helper::get_order_eftid( $order );
        if ( $transaction_id === '' ) {
            wc_get_logger()->warning(
                'Event 2 thank-you skip order ' . (int) $order_id . ' — no eftid',
                [ 'source' => 'ah-everflow' ]
            );
            return;
        }

        $efOrder  = $this->build_order_data( $order );
        $coupons  = $this->get_order_coupons( $order );
        $this->render_conversion_script( $order, $efOrder, $coupons, $transaction_id );
        $this->pixel_rendered = true;

        if ( ! $order->get_meta( '_ah_everflow_event_2_pixel' ) ) {
            $order->update_meta_data( '_ah_everflow_event_2_pixel', time() );
            $order->save_meta_data();
            $order->add_order_note(
                'Everflow Event 2 (WooCommerce conversion): thank-you pixel rendered (transaction_id present).'
            );
        }

        wc_get_logger()->info(
            sprintf( 'Event 2 thank-you pixel rendered for order %d', $order_id ),
            [ 'source' => 'ah-everflow' ]
        );
    }

    private function build_order_data( $order ) {
        $efOrder = [
            'items' => [],
            'oid'   => $order->get_id(),
            'amt'   => $order->get_total(),
            'bs'    => $order->get_billing_state(),
            'bc'    => $order->get_billing_country(),
        ];

        $efItems = [];
        foreach ( $order->get_items() as $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                continue;
            }
            $efItems[] = [
                'ps'  => BH_Everflow_Helper::line_item_product_key( $item ),
                'qty' => (int) $item->get_quantity(),
                'p'   => $order->get_line_total( $item, true, true ),
            ];
        }
        $efOrder['items'] = $efItems;
        $efOrder['cc']    = $this->get_order_coupons( $order );

        return $efOrder;
    }

    private function get_order_coupons( $order ) {
        if ( method_exists( $order, 'get_coupon_codes' ) ) {
            return implode( ',', $order->get_coupon_codes() );
        }
        return implode( ',', $order->get_used_coupons() );
    }

    /**
     * @param WC_Order $order
     * @param array    $efOrder
     * @param string   $coupons
     * @param string   $transaction_id
     */
    private function render_conversion_script( $order, $efOrder, $coupons, $transaction_id ) {
        $script = BH_Everflow_Helper::TRACKING_SCRIPT;
        $aid    = (int) BH_Everflow_Helper::AID;
        $event  = (int) BH_Everflow_Helper::EVENT_WOO_CONVERSION;
        $amount = (float) ( $order->get_total() - $order->get_shipping_total() );
        ?>
        <script type="text/javascript">
            (function () {
                var cfg = {
                    aid: <?php echo (int) $aid; ?>,
                    adv_event_id: <?php echo (int) $event; ?>,
                    amount: <?php echo wp_json_encode( $amount ); ?>,
                    order_id: <?php echo wp_json_encode( (string) $order->get_id() ); ?>,
                    order: <?php echo wp_json_encode( $efOrder ); ?>,
                    coupon_code: <?php echo wp_json_encode( (string) $coupons ); ?>,
                    email: <?php echo wp_json_encode( (string) $order->get_billing_email() ); ?>,
                    transaction_id: <?php echo wp_json_encode( (string) $transaction_id ); ?>
                };

                function fireConversion() {
                    if (typeof EF === 'undefined' || typeof EF.conversion !== 'function') {
                        return false;
                    }
                    try {
                        EF.conversion(cfg);
                        return true;
                    } catch (e) {
                        return false;
                    }
                }

                if (fireConversion()) {
                    return;
                }

                var s = document.createElement('script');
                s.src = <?php echo wp_json_encode( $script ); ?>;
                s.async = true;
                s.onload = function () {
                    fireConversion();
                };
                document.head.appendChild(s);
            })();
        </script>
        <?php
    }
}

new BH_Everflow_Conversion();
