<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Podscribe {

    public function __construct() {

        add_action( 'wp_head', [ $this, 'insert_view_pixel' ], 20 );
        add_action( 'wp_footer', [ $this, 'insert_purchase_pixel'], 100);
    }

    /**
     * Base Podscribe pixel (ALL pages).
     */
    public function insert_view_pixel() {
        ?>
        <!-- Podscribe Pixel -->
        <script>
        (function (w, d) {
            var id = 'podscribe-capture',
                n  = 'script';

            if (d.getElementById(id)) {
                return;
            }

            var e = d.createElement(n);
            e.id = id;
            e.async = true;
            e.src = 'https://d34r8q7sht0t9k.cloudfront.net/tag.js';

            var s = d.getElementsByTagName(n)[0];
            s.parentNode.insertBefore(e, s);

            e.addEventListener('load', function() {
                w.podscribe('init', {
                    user_id: '5c244146-22a5-49e0-95f7-4d6845bb0124',
                    advertiser: 'brello'
                });
                w.podscribe('view');
            });
        })(window, document);
        </script>
        <!-- /Podscribe Pixel -->
        <?php
    }

    /**
     * Purchase event (order-received only).
     */
    public function insert_purchase_pixel() {

        if ( ! is_order_received_page() ) {
            return;
        }

        global $tracking_data;

        if ( empty( $tracking_data ) ) {
            return;
        }

        $logger  = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

        // Defensive checks
        $sale_amount   = isset( $tracking_data['sale_amount'] ) ? $tracking_data['sale_amount'] : '';
        $order_id      = isset( $tracking_data['order_id'] ) ? $tracking_data['order_id'] : '';
        $discount_code = isset( $tracking_data['discount_code'] ) ? $tracking_data['discount_code'] : '';
        $hashed_email  = md5( strtolower( trim( $tracking_data['email'] ) ) );

        $context    =   [ 
            'sale_amount' => $sale_amount, 
            'order_id' => $order_id, 
            'discount_code' => $discount_code, 
            'hashed_email' => $hashed_email, 
            'source' => 'ah-tracking-podscribe'
        ];

        

        if ( ! $order_id ) {
            $logger->log( 'error', 'tracking_data_error', $context );
            return;
        }

        ?>
        <!-- Podscribe Purchase Pixel -->
        <script>
            (function(w){
                if (typeof w.podscribe !== 'function') {
                    return;
                }
                console.log('podscribe_purchase', '<?php echo esc_js( $order_id ); ?>');
                w.podscribe('purchase', {
                    value: '<?php echo esc_js( $sale_amount ); ?>',
                    order_number: '<?php echo esc_js( $order_id ); ?>',
                    discount_code: '<?php echo esc_js( $discount_code ); ?>',
                    hashed_email: '<?php echo esc_js( $hashed_email ); ?>'
                });
            })(window);
        </script>
        <!-- /Podscribe Purchase Pixel -->
        <?php
        $logger->log( 'info', 'tracking_data', $context );
    }
    

}
