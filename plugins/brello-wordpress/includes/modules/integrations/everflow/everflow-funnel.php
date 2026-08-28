<?php
/**
 * Everflow Event 8 — Checkout Started
 *
 * Link / Apple Pay / Google Pay often sit in Stripe iframes or are created
 * BEFORE our footer runs — so we patch PaymentRequest + window.Stripe in wp_head
 * (early), and also listen for Stripe postMessage / wallet UI.
 *
 * Fire: express wallet click/popup OR /checkout page load.
 * Skip: Get Started quiz, plan-only.
 * TID: cookie `eftid` only.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Everflow_Funnel {

    public function __construct() {
        // BEFORE Stripe.js loads — critical for Link / GPay / Apple Pay.
        add_action( 'wp_head', [ $this, 'output_early_hooks' ], 1 );
        add_action( 'wp_footer', [ $this, 'output_begin_checkout_script' ], 5 );
    }

    /**
     * Early hooks so Stripe cannot create Payment Element before we wrap APIs.
     */
    public function output_early_hooks() {
        if ( is_admin() ) {
            return;
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return;
        }
        ?>
        <!-- BH Everflow Event 8 early hooks -->
        <script>
        (function () {
            window.__ahEf8Queue = window.__ahEf8Queue || [];
            window.ahEf8Fire = window.ahEf8Fire || function (reason) {
                window.__ahEf8Queue.push(reason);
            };

            // 1) Native wallet sheet
            try {
                if (window.PaymentRequest && window.PaymentRequest.prototype && !window.PaymentRequest.prototype.show.__ahEf8) {
                    var origShow = window.PaymentRequest.prototype.show;
                    var patched = function () {
                        window.ahEf8Fire('PaymentRequest.show');
                        return origShow.apply(this, arguments);
                    };
                    patched.__ahEf8 = true;
                    window.PaymentRequest.prototype.show = patched;
                }
            } catch (e) {}

            // 2) Intercept window.Stripe assignment (CFW loads Stripe after head)
            try {
                var current = window.Stripe;
                function wrapStripe(Orig) {
                    if (!Orig || Orig.__ahEf8Wrapped) {
                        return Orig;
                    }
                    function Wrapped() {
                        var inst = Orig.apply(this, arguments);
                        return wrapInstance(inst);
                    }
                    Wrapped.__ahEf8Wrapped = true;
                    try {
                        Object.keys(Orig).forEach(function (k) {
                            try { Wrapped[k] = Orig[k]; } catch (e) {}
                        });
                    } catch (e) {}
                    return Wrapped;
                }
                function wrapPR(pr) {
                    if (!pr || pr.__ahEf8) { return pr; }
                    pr.__ahEf8 = true;
                    if (typeof pr.show === 'function') {
                        var os = pr.show.bind(pr);
                        pr.show = function () {
                            window.ahEf8Fire('stripe.paymentRequest.show');
                            return os.apply(this, arguments);
                        };
                    }
                    return pr;
                }
                function wrapInstance(stripe) {
                    if (!stripe || stripe.__ahEf8) { return stripe; }
                    stripe.__ahEf8 = true;
                    if (typeof stripe.paymentRequest === 'function') {
                        var opr = stripe.paymentRequest.bind(stripe);
                        stripe.paymentRequest = function (o) { return wrapPR(opr(o)); };
                    }
                    if (typeof stripe.elements === 'function') {
                        var oel = stripe.elements.bind(stripe);
                        stripe.elements = function () {
                            var elements = oel.apply(this, arguments);
                            if (elements && typeof elements.create === 'function' && !elements.__ahEf8Create) {
                                elements.__ahEf8Create = true;
                                var oc = elements.create.bind(elements);
                                elements.create = function (type, options) {
                                    var el = oc(type, options);
                                    if (el && typeof el.on === 'function') {
                                        if (type === 'expressCheckout' || type === 'paymentRequestButton') {
                                            el.on('click', function () {
                                                window.ahEf8Fire('stripe_' + type + '_click');
                                            });
                                        }
                                    }
                                    return el;
                                };
                            }
                            return elements;
                        };
                    }
                    return stripe;
                }
                Object.defineProperty(window, 'Stripe', {
                    configurable: true,
                    enumerable: true,
                    get: function () { return current; },
                    set: function (v) {
                        current = wrapStripe(v);
                    }
                });
                if (typeof current === 'function') {
                    current = wrapStripe(current);
                }
            } catch (e) {}

            // Apple Pay sheet open
            try {
                if (window.ApplePaySession && window.ApplePaySession.prototype && !window.ApplePaySession.prototype.begin.__ahEf8) {
                    var origBegin = window.ApplePaySession.prototype.begin;
                    var patchedBegin = function () {
                        window.ahEf8Fire('ApplePaySession.begin');
                        return origBegin.apply(this, arguments);
                    };
                    patchedBegin.__ahEf8 = true;
                    window.ApplePaySession.prototype.begin = patchedBegin;
                }
            } catch (e) {}

            // Do NOT listen to ambient stripe.com postMessage (false "link" matches).
        })();
        </script>
        <?php
    }

    public function output_begin_checkout_script() {
        if ( is_admin() ) {
            return;
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return;
        }

        $on_checkout = function_exists( 'is_checkout' ) && is_checkout();
        $aid         = class_exists( 'BH_Everflow_Helper' ) ? (int) BH_Everflow_Helper::AID : 2;
        $event       = class_exists( 'BH_Everflow_Helper' ) ? (int) BH_Everflow_Helper::EVENT_BEGIN_CHECKOUT : 8;
        $sdk         = class_exists( 'BH_Everflow_Helper' )
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
            'aid'            => $aid,
            'event'          => $event,
            'sdk'            => $sdk,
            'amount'         => $amount,
            'items'          => $items,
            'autoOnCheckout' => $on_checkout ? 1 : 0,
        ];
        ?>
        <!-- BH Everflow Event 8 footer -->
        <script type="text/javascript">
        (function () {
            var CFG = <?php echo wp_json_encode( $cfg ); ?>;
            var DEDUPE = 'ah_ef_event_8_begin';

            function signal() {
                try { console.log.apply(console, ['[BH Everflow]'].concat([].slice.call(arguments))); } catch (e) {}
            }

            function getBackendEftid() {
                try {
                    var m = document.cookie.match(/(?:^|; )eftid=([^;]*)/);
                    var tid = m ? decodeURIComponent(m[1]) : '';
                    if (!tid && window.localStorage) {
                        tid = localStorage.getItem('eftid') || '';
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

            window.ahEf8Reset = function () {
                try { sessionStorage.removeItem(DEDUPE); } catch (e) {}
            };

            function ensureSdk(cb) {
                if (typeof EF !== 'undefined' && typeof EF.conversion === 'function') { cb(); return; }
                var s = document.createElement('script');
                s.src = CFG.sdk;
                s.async = true;
                s.onload = function () { cb(); };
                s.onerror = function () { signal('Event 8 SDK failed'); };
                document.head.appendChild(s);
            }

            var lastSkipAt = 0;
            function fireBeginCheckout(reason) {
                if (alreadyFired()) {
                    var now = Date.now();
                    if (now - lastSkipAt > 400) {
                        lastSkipAt = now;
                        signal('Event 8 already fired — skip', reason);
                    }
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

            window.ahEf8Fire = fireBeginCheckout;
            if (window.__ahEf8Queue && window.__ahEf8Queue.length) {
                var q = window.__ahEf8Queue.slice();
                window.__ahEf8Queue = [];
                q.forEach(function (r) { fireBeginCheckout(r); });
            }

            if (CFG.autoOnCheckout) {
                fireBeginCheckout('checkout_page');
            }

            // Express row only (not card Payment Element — that would spam on card focus)
            var EXPRESS_ROOT = [
                '#cfw-express-checkout',
                '.cfw-express-checkout',
                '#cfw-payment-request-buttons',
                '.cfw-payment-request-buttons',
                '#payment-request-button',
                '#wc-stripe-payment-request-wrapper',
                '.wc-stripe-payment-request-wrapper',
                '.wc-stripe-payment-request-button-wrapper',
                '.wcpay-express-checkout-element',
                '[class*="express-checkout"]',
                '[id*="express-checkout"]',
                '[class*="payment-request"]',
                '[id*="payment-request"]',
                '[class*="PaymentRequestButton"]',
                '[data-testid*="apple"]',
                '[data-testid*="google"]'
            ].join(',');

            function closestExpress(el) {
                try {
                    return el && el.closest ? el.closest(EXPRESS_ROOT) : null;
                } catch (e) {
                    return null;
                }
            }

            function looksLikeExpressIframe(frame) {
                if (!frame) return false;
                if (closestExpress(frame)) return true;
                var src = String(frame.getAttribute('src') || frame.src || '').toLowerCase();
                var name = String(frame.getAttribute('name') || frame.name || '').toLowerCase();
                var title = String(frame.getAttribute('title') || '').toLowerCase();
                var hay = src + ' ' + name + ' ' + title;
                return (
                    hay.indexOf('payment-request') !== -1 ||
                    hay.indexOf('paymentrequest') !== -1 ||
                    hay.indexOf('express-checkout') !== -1 ||
                    hay.indexOf('apple') !== -1 ||
                    hay.indexOf('google') !== -1 ||
                    hay.indexOf('gpay') !== -1
                );
            }

            // Clicks inside Stripe express iframes do not bubble. When user
            // clicks the wallet button, focus moves into that iframe → window blur.
            function checkExpressIframeFocus(reason) {
                try {
                    var el = document.activeElement;
                    if (!el || String(el.tagName).toLowerCase() !== 'iframe') {
                        return;
                    }
                    if (looksLikeExpressIframe(el)) {
                        fireBeginCheckout(reason || 'express_iframe_focus');
                    }
                } catch (e) {}
            }
            window.addEventListener('blur', function () {
                setTimeout(function () { checkExpressIframeFocus('express_iframe_blur'); }, 0);
            }, true);
            document.addEventListener('focusin', function (e) {
                var t = e && e.target;
                if (t && String(t.tagName).toLowerCase() === 'iframe' && looksLikeExpressIframe(t)) {
                    fireBeginCheckout('express_iframe_focusin');
                }
            }, true);

            function bindExpressRoots(root) {
                var nodes = (root || document).querySelectorAll(EXPRESS_ROOT);
                Array.prototype.forEach.call(nodes, function (node) {
                    if (node.getAttribute('data-ah-ef8-express')) return;
                    node.setAttribute('data-ah-ef8-express', '1');
                    ['pointerdown', 'mousedown', 'touchstart', 'click'].forEach(function (evt) {
                        node.addEventListener(evt, function () {
                            fireBeginCheckout('express_shell_' + evt);
                        }, true);
                    });
                });
                var iframes = (root || document).querySelectorAll('iframe');
                Array.prototype.forEach.call(iframes, function (frame) {
                    if (!looksLikeExpressIframe(frame)) return;
                    if (frame.getAttribute('data-ah-ef8-iframe')) return;
                    frame.setAttribute('data-ah-ef8-iframe', '1');
                    ['pointerdown', 'mousedown', 'touchstart', 'click', 'focus'].forEach(function (evt) {
                        frame.addEventListener(evt, function () {
                            fireBeginCheckout('express_iframe_' + evt);
                        }, true);
                    });
                });
            }
            bindExpressRoots(document);
            setTimeout(function () { bindExpressRoots(document); }, 800);
            setTimeout(function () { bindExpressRoots(document); }, 2000);
            setTimeout(function () { bindExpressRoots(document); }, 4000);
            if (typeof MutationObserver !== 'undefined') {
                new MutationObserver(function () { bindExpressRoots(document); })
                    .observe(document.documentElement, { childList: true, subtree: true });
            }

            [
                'wc_stripe_paymentrequest_button_click',
                'wc_stripe_payment_request_button_click',
                'wcpay.express-checkout.click',
                'payment_request_button_click',
                'cfw_payment_method_selected'
            ].forEach(function (name) {
                document.addEventListener(name, function () { fireBeginCheckout('dom_' + name); });
                if (typeof jQuery !== 'undefined') {
                    jQuery(document.body).on(name, function () { fireBeginCheckout('jq_' + name); });
                }
            });
        })();
        </script>
        <?php
    }
}

new BH_Everflow_Funnel();
