<?php
/**
 * Everflow Event 8 — Checkout Started
 *
 * Fire: Woo /checkout page load (JS) + landing S2S (async-sender).
 * Skip: Get Started quiz, plan-only, order-received.
 * TID: cookie `eftid` / `ef_entry_tid` only.
 *
 * Intentionally does NOT patch PaymentRequest / window.Stripe — those hooks
 * caused Stripe Link / express wallets to flash open and close.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Everflow_Funnel {

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'output_begin_checkout_script' ], 5 );
    }

    public function output_begin_checkout_script() {
        if ( is_admin() ) {
            return;
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return;
        }

        $on_checkout = function_exists( 'is_checkout' ) && is_checkout();
        if ( ! $on_checkout ) {
            return;
        }

        $aid   = class_exists( 'BH_Everflow_Helper' ) ? (int) BH_Everflow_Helper::AID : 2;
        $event = class_exists( 'BH_Everflow_Helper' ) ? (int) BH_Everflow_Helper::EVENT_BEGIN_CHECKOUT : 8;
        $sdk   = class_exists( 'BH_Everflow_Helper' )
            ? BH_Everflow_Helper::TRACKING_SCRIPT
            : 'https://www.p9wkp5ctrk.com/scripts/main.js';

        $amount = 0.0;
        $items  = [];
        if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
            $amount = (float) WC()->cart->get_total( 'edit' );
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
                if ( ! $product instanceof WC_Product ) {
                    continue;
                }
                $ps = $product->get_sku();
                if ( $ps === '' || $ps === null ) {
                    $ps = $product->get_slug();
                }
                $qty  = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
                $line = isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : (float) $product->get_price() * $qty;
                $items[] = [
                    'ps'  => sanitize_title( (string) $ps ),
                    'qty' => max( 1, $qty ),
                    'p'   => $line,
                ];
            }
        }

        $cfg = [
            'aid'    => $aid,
            'event'  => $event,
            'sdk'    => $sdk,
            'amount' => $amount,
            'items'  => $items,
        ];
        ?>
        <!-- BH Everflow Event 8 (checkout page — no Stripe patches) -->
        <script type="text/javascript">
        (function () {
            var CFG = <?php echo wp_json_encode( $cfg ); ?>;
            var DEDUPE = 'ah_ef_event_8_begin';

            function signal() {
                try { console.log.apply(console, ['[BH Everflow]'].concat([].slice.call(arguments))); } catch (e) {}
            }

            function getBackendEftid() {
                try {
                    var m = document.cookie.match(/(?:^|; )ef_entry_tid=([^;]*)/) ||
                        document.cookie.match(/(?:^|; )eftid=([^;]*)/);
                    var tid = m ? decodeURIComponent(m[1]) : '';
                    if (!tid && window.localStorage) {
                        tid = localStorage.getItem('ef_entry_tid') || localStorage.getItem('eftid') || '';
                    }
                    if (tid && (tid.indexOf('|') !== -1 || tid.indexOf(',') !== -1)) {
                        tid = tid.split(/[|,]/)[0].trim();
                    }
                    return tid || '';
                } catch (e) {
                    return '';
                }
            }

            function waitForEftid(cb, maxMs) {
                maxMs = maxMs || 5000;
                var start = Date.now();
                (function tick() {
                    var tid = getBackendEftid();
                    if (tid) { cb(tid); return; }
                    if (Date.now() - start >= maxMs) { cb(''); return; }
                    setTimeout(tick, 150);
                })();
            }

            function alreadyFired() {
                try { return !!(window.sessionStorage && sessionStorage.getItem(DEDUPE)); } catch (e) { return false; }
            }
            function markFired() {
                try { if (window.sessionStorage) sessionStorage.setItem(DEDUPE, String(Date.now())); } catch (e) {}
            }

            function ensureSdk(cb) {
                if (typeof EF !== 'undefined' && typeof EF.conversion === 'function') { cb(); return; }
                var s = document.createElement('script');
                s.src = CFG.sdk;
                s.async = true;
                s.onload = function () { cb(); };
                s.onerror = function () { signal('Event 8 SDK failed'); };
                document.head.appendChild(s);
            }

            function fireBeginCheckout(reason) {
                if (alreadyFired()) {
                    signal('Event 8 already fired — skip', reason);
                    return;
                }
                waitForEftid(function (tid) {
                    if (alreadyFired()) {
                        signal('Event 8 already fired — skip', reason);
                        return;
                    }
                    if (!tid) {
                        signal('Event 8 skipped — no eftid');
                        return;
                    }
                    ensureSdk(function () {
                        if (alreadyFired()) {
                            signal('Event 8 already fired — skip', reason);
                            return;
                        }
                        if (typeof EF === 'undefined' || typeof EF.conversion !== 'function') {
                            signal('Event 8 skipped — EF.conversion missing');
                            return;
                        }
                        try {
                            EF.conversion({
                                aid: CFG.aid,
                                adv_event_id: CFG.event,
                                amount: CFG.amount || 0,
                                transaction_id: tid,
                                order: {
                                    oid: 'woo_begin_checkout',
                                    amt: CFG.amount || 0,
                                    items: CFG.items || []
                                }
                            });
                            markFired();
                            signal('Event 8 FIRED via', reason);
                        } catch (e) {
                            signal('Event 8 error', e);
                        }
                    });
                }, 5000);
            }

            fireBeginCheckout('checkout_page');
        })();
        </script>
        <?php
    }
}

new BH_Everflow_Funnel();
