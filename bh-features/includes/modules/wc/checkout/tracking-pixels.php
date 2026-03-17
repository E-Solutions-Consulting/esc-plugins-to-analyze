<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Tracking_Pixels {

    public function __construct() {

        add_action('ah_dispatch_purchase_tracking', [ $this, 'add_google_ads' ], 999);
        add_action('ah_dispatch_purchase_tracking', [ $this, 'add_metapixel' ], 999);
        add_action('ah_dispatch_purchase_tracking', [ $this, 'add_podscribe' ], 999);
        add_action('ah_dispatch_purchase_tracking', [ $this, 'add_vibe' ], 999);
    }

    function add_google_ads() {
        ?>
            <script>
            AH_TRACKING.register(function(DATA, waitFor){

                waitFor(
                    () => typeof window.gtag === 'function',
                    () => {

                        console.log('gtag ready');

                        gtag('event','conversion',{
                            send_to:'AW-16978798190/SHNSCJWTkLkaEO7Mj6A_',
                            value: DATA.sale_amount,
                            currency: DATA.currency,
                            transaction_id: String(DATA.order_id)
                        });
                        AH_TRACKING.done('gtag');
                    }
                );

            });
            </script>
        <?php
    }

    function add_metapixel() {
        ?>
            <script>
            AH_TRACKING.register(function(DATA, waitFor){

                waitFor(
                    () => typeof window.fbq === 'function',
                    () => {

                        console.log('fbq ready');

                        fbq('track','Purchase',{
                            value: DATA.sale_amount,
                            currency: DATA.currency
                        });

                        AH_TRACKING.done('fbq');
                    }
                );

            });
            </script>
        <?php
    }

    function add_podscribe() {
        ?>
        <script>
        AH_TRACKING.register(function(DATA, waitFor){

            waitFor(
                () => typeof window.podscribe === 'function',
                () => {

                    window.podscribe('purchase',{
                        value: String(DATA.sale_amount),
                        order_number: String(DATA.order_id),
                        discount_code: DATA.discount_code,
                        hashed_email: DATA.hashed_email
                    });
                    AH_TRACKING.done('podscribe');
                }
            );

        });
        </script>
        <?php
    }

    function add_vibe() {
        ?>
        <script>
        AH_TRACKING.register(function(DATA, waitFor){

            (function loadVibe(){

                if (!window.__AH_VIBE_LOADING__) {

                    window.__AH_VIBE_LOADING__ = true;

                    !function(v,i,b,e,c,o){
                        if(!v[c]){
                            var s=v[c]=function(){
                                s.process
                                    ? s.process.apply(s,arguments)
                                    : s.queue.push(arguments)
                            };
                            s.queue=[];
                            s.b=1*new Date;

                            var t=i.createElement(b);
                            t.async=true;
                            t.src=e;

                            var n=i.getElementsByTagName(b)[0];
                            n.parentNode.insertBefore(t,n);
                        }
                    }(window,document,"script","https://s.vibe.co/vbpx.js","vbpx");
                }

                // esperar a que vbpx esté realmente listo
                waitFor(
                    () => typeof window.vbpx === 'function',
                    () => {

                        console.log('vbpx ready');

                        vbpx('init','O17U5n');

                        vbpx('event','purchase',{
                            price_usd: String(DATA.sale_amount)
                        });

                        AH_TRACKING.done('vibe');
                    }
                );

            })();

        });
        </script>
        <?php
    }




}

add_action('woocommerce_loaded', function () {
    new AH_Tracking_Bridge();
});
