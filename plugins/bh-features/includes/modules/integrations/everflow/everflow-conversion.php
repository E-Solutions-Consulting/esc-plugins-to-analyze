<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Everflow_Conversion {

    public function __construct() {
        add_action( 'woocommerce_thankyou', [ $this, 'track_conversion' ] );
        add_action( 'woocommerce_checkout_create_order', [ $this, 'save_eftid_to_order' ], 20, 1 );
    }

    public function save_eftid_to_order( $order ) {
        $eftid = isset( $_COOKIE['ef_tid_c_a_2'] ) ? $_COOKIE['ef_tid_c_a_2'] : '';
        $order->update_meta_data( 'eftid', $eftid );
    }

    public function track_conversion( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }

        $efOrder = $this->build_order_data( $order );
        $coupons = $this->get_order_coupons( $order );
        
        $this->render_conversion_script( $order, $efOrder, $coupons );
    }

    private function build_order_data( $order ) {
        $efOrder = [
            'items' => [],
            'oid' => $order->get_id(),
            'amt' => $order->get_total(),
            'bs' => $order->get_billing_state(),
            'bc' => $order->get_billing_country(),
        ];

        $efItems = [];
        foreach ( $order->get_items() as $item ) {
            $product = $order->get_product_from_item( $item );
            $efItems[] = [
                'ps' => $product->get_sku(),
                'qty' => $item['qty'],
                'p' => $order->get_line_total( $item, true, true ),
            ];
        }
        $efOrder['items'] = $efItems;
        $efOrder['cc'] = $this->get_order_coupons( $order );

        return $efOrder;
    }

    private function get_order_coupons( $order ) {
        return implode( ',', $order->get_used_coupons() );
    }

    private function render_conversion_script( $order, $efOrder, $coupons ) {
        ?>
        <script type="text/javascript" src="https://www.p9wkp5ctrk.com/scripts/main.js"></script>
        <script type="text/javascript">
            EF.conversion({
                aid: 2,
                adv_event_id: 2,
                amount: <?php echo $order->get_total() - $order->get_shipping_total(); ?>,
                order_id: "<?php echo $order->get_id(); ?>",
                order: <?php echo wp_json_encode( $efOrder ); ?>,
                coupon_code: "<?php echo esc_js( $coupons ); ?>",
                adv1: "",
                adv2: "",
                adv3: "",
                adv4: "",
                adv5: "",
                email: "<?php echo esc_js( $order->get_billing_email() ); ?>",
            });
        </script>
        <?php
    }
}

new BH_Everflow_Conversion();