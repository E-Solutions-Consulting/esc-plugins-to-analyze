<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Tracking_Bridge {

    public static function init() {

        add_action(
            'wp_footer',
            [ __CLASS__, 'render_tracking_bridge' ],
            999
        );
    }

    /**
     * Stateless order reconstruction.
     */
    private static function get_tracking_data() {

        if ( ! is_order_received_page() ) {
            return false;
        }

        $order_id = absint( get_query_var( 'order-received' ) );
        if ( ! $order_id ) {
            return false;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        return [
            'order_id'      => $order->get_id(),
            'sale_amount'   => (float) $order->get_total(),
            'email'         => $order->get_billing_email(),
            'hashed_email'  => md5( strtolower( trim( $order->get_billing_email() ) ) ),
            'discount_code' => implode(', ', $order->get_coupon_codes()),
            'currency'      => $order->get_currency(),
            'redirect_url'  => apply_filters(
                'bh_wc_thankyou_redirect_url',
                $order->get_meta('telemdnow_visit_link'),
                $order
            ),
        ];
    }

    /**
     * Tracking dispatcher + controlled redirect.
     */
    public static function render_tracking_bridge() {

        $data = self::get_tracking_data();
        if ( ! $data ) {
            return;
        }
        ?>
        <script>
            (function(){

                window.AH_TRACKING = window.AH_TRACKING || {};

                AH_TRACKING.data = <?php echo wp_json_encode($data); ?>;
                AH_TRACKING.pixels = [];
                AH_TRACKING.completed = 0;
                AH_TRACKING.expected = 0;

                console.log('AH Tracking Bridge started', AH_TRACKING.data);

                AH_TRACKING.register = function(callback){
                    AH_TRACKING.expected++;
                    AH_TRACKING.pixels.push(callback);
                };

                AH_TRACKING.done = function(name){
                    AH_TRACKING.completed++;
                    console.log('Pixel finished:', name,
                        AH_TRACKING.completed + '/' + AH_TRACKING.expected);

                    maybeRedirect();
                };

                function waitFor(test, callback, timeout = 4000) {

                    const start = Date.now();

                    (function check(){
                        if (test()) {
                            callback();
                            return;
                        }

                        if (Date.now() - start > timeout) {
                            console.log('Tracker timeout');
                            maybeRedirect();
                            return;
                        }

                        requestAnimationFrame(check);
                    })();
                }

                AH_TRACKING.waitFor = waitFor;

                function maybeRedirect(){

                    if (!AH_TRACKING.data.redirect_url) {
                        return;
                    }

                    // todos terminaron
                    if (AH_TRACKING.completed >= AH_TRACKING.expected) {
                        redirectNow();
                    }
                }

                function redirectNow(){
                    if (AH_TRACKING.__redirected) return;
                    AH_TRACKING.__redirected = true;

                    console.log('Redirecting...');
                    window.location.href = AH_TRACKING.data.redirect_url;
                }

                setTimeout(function(){

                    AH_TRACKING.pixels.forEach(function(run){
                        try {
                            run(AH_TRACKING.data, waitFor);
                        } catch(e){
                            console.log('Pixel error', e);
                            AH_TRACKING.done('error');
                        }
                    });

                }, 50);

                // fallback absoluto (nunca bloquear UX)
                setTimeout(redirectNow, 3500);

            })();
            </script>
        <?php

        /**
         * Allow external modules to hook safely.
         */
        do_action( 'ah_dispatch_purchase_tracking', $data );
    }
}

add_action('woocommerce_loaded', function () {
    AH_Tracking_Bridge::init();
});
