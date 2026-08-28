<?php
/**
 * AH Everflow Tracking Script
 *
 * Outputs the Everflow JS click script in wp_footer.
 * - Standard script: all pages except the affiliate landing page.
 * - Dual-network script: only on the affiliate landing page (page ID configured below).
 *
 * Hardened: persist TID from URL even if SDK blocked; load SDK then EF.click.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Everflow_Tracking {

    /**
     * WordPress page ID for the affiliate landing page.
     * /start-glp1-affiliate
     */
    const AFFILIATE_LANDING_PAGE_ID = 579685;

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'output_click_script' ], 20 );
    }

    public function output_click_script() {

        // Thank-you: only Event 2 conversion — do not fire a new empty EF.click.
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return;
        }

        // Checkout: already attributed; empty EF.click → “Missing offer_id or transaction_id”.
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return;
        }

        if ( is_page( self::AFFILIATE_LANDING_PAGE_ID ) ) {
            $this->render_dual_network_script();
        } else {
            $this->render_standard_script();
        }
    }

    private function render_standard_script() {
        $sdk = class_exists( 'BH_Everflow_Helper' )
            ? BH_Everflow_Helper::TRACKING_SCRIPT
            : 'https://www.p9wkp5ctrk.com/scripts/main.js';
        ?>
        <!-- BH Everflow click tracking (standard) -->
        <script type="text/javascript">
            (function () {
                var SDK = <?php echo wp_json_encode( $sdk ); ?>;

                function persistEftid(tid) {
                    if (!tid) { return; }
                    try {
                        var maxAge = 60 * 60 * 24 * 30;
                        document.cookie = 'eftid=' + encodeURIComponent(tid) + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
                        if (window.localStorage) {
                            localStorage.setItem('eftid', tid);
                        }
                        if (typeof console !== 'undefined' && console.log) {
                            console.log('[BH Everflow] eftid saved:', tid);
                        }
                    } catch (e) {}
                }

                function urlParam(name) {
                    try {
                        var m = new RegExp('[?&]' + name + '=([^&#]*)').exec(window.location.search);
                        if (!m || typeof m[1] === 'undefined' || m[1] === null) {
                            return '';
                        }
                        return decodeURIComponent(String(m[1]).replace(/\+/g, ' '));
                    } catch (e) {
                        return '';
                    }
                }

                // Seed cookie from Everflow redirect params even if SDK is blocked.
                var fromUrl = urlParam('_ef_transaction_id') || urlParam('transaction_id') || urlParam('ef_transaction_id');
                if (fromUrl) {
                    persistEftid(fromUrl);
                }

                function runClick() {
                    if (typeof EF === 'undefined' || typeof EF.click !== 'function') {
                        return;
                    }
                    try {
                        var oid = EF.urlParameter('oid');
                        var affid = EF.urlParameter('affid');
                        var tidParam = EF.urlParameter('_ef_transaction_id') || urlParam('_ef_transaction_id') || urlParam('transaction_id');
                        var tidCookie = '';
                        try {
                            var cm = document.cookie.match(/(?:^|; )eftid=([^;]*)/);
                            tidCookie = cm ? decodeURIComponent(cm[1]) : '';
                        } catch (e) {}
                        var transactionId = tidParam || tidCookie || '';

                        // Avoid Everflow error: “Missing offer_id or transaction_id”.
                        if (!transactionId && !(oid && affid)) {
                            return;
                        }

                        EF.click({
                            offer_id:       oid,
                            affiliate_id:   affid,
                            source_id:      EF.urlParameter('source_id'),
                            sub1:           EF.urlParameter('sub1'),
                            sub2:           EF.urlParameter('sub2'),
                            sub3:           EF.urlParameter('sub3'),
                            sub4:           EF.urlParameter('sub4'),
                            sub5:           EF.urlParameter('sub5'),
                            uid:            EF.urlParameter('uid'),
                            transaction_id: transactionId,
                        }).then(function (transaction_id) {
                            persistEftid(transaction_id);
                        }).catch(function () {});
                    } catch (e) {}
                }

                if (typeof EF !== 'undefined') {
                    runClick();
                    return;
                }

                var s = document.createElement('script');
                s.src = SDK;
                s.async = true;
                s.onload = runClick;
                s.onerror = function () {};
                document.head.appendChild(s);
            })();
        </script>
        <?php
    }

    private function render_dual_network_script() {
        ?>
        <!-- BH Everflow click tracking (dual-network) -->
        <script type="text/javascript">
        (function () {
            function persistEftid(tid) {
                if (!tid) { return; }
                try {
                    var maxAge = 60 * 60 * 24 * 30;
                    document.cookie = 'eftid=' + encodeURIComponent(tid) + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
                    if (window.localStorage) {
                        localStorage.setItem('eftid', tid);
                    }
                    if (typeof console !== 'undefined' && console.log) {
                        console.log('[BH Everflow] eftid saved:', tid);
                    }
                } catch (e) {}
            }

            function urlParam(name) {
                try {
                    var m = new RegExp('[?&]' + name + '=([^&#]*)').exec(window.location.search);
                    if (!m || typeof m[1] === 'undefined' || m[1] === null) {
                        return '';
                    }
                    return decodeURIComponent(String(m[1]).replace(/\+/g, ' '));
                } catch (e) {
                    return '';
                }
            }

            var fromUrl = urlParam('_ef_transaction_id') || urlParam('transaction_id') || urlParam('ef_transaction_id');
            if (fromUrl) {
                persistEftid(fromUrl);
            }

            function runDual() {
                if (typeof EF === 'undefined' || typeof EF.click !== 'function') {
                    return;
                }

                if ( EF.urlParameter('affid2') ) {
                    EF.click({
                        tracking_domain: 'https://www.acgr5tvb4ktrk.com',
                        offer_id:        EF.urlParameter('oid2'),
                        affiliate_id:    EF.urlParameter('affid2'),
                        sub1:            EF.urlParameter('sub1'),
                        sub2:            EF.urlParameter('sub2'),
                        sub3:            EF.urlParameter('sub3'),
                        sub4:            EF.urlParameter('sub4'),
                        sub5:            EF.urlParameter('sub5'),
                    }).then(function( transaction_id ) {
                        EF.click({
                            tracking_domain: 'https://www.p9wkp5ctrk.com',
                            offer_id:        EF.urlParameter('oid'),
                            affiliate_id:    EF.urlParameter('affid'),
                            sub1:            EF.urlParameter('sub1'),
                            sub2:            EF.urlParameter('sub2'),
                            sub3:            EF.urlParameter('sub3'),
                            sub4:            EF.urlParameter('sub4'),
                            sub5:            transaction_id,
                            uid:             EF.urlParameter('uid'),
                            source_id:       EF.urlParameter('source_id'),
                        }).then(function (tid) {
                            persistEftid(tid);
                        });
                    });
                } else {
                    EF.click({
                        tracking_domain: 'https://www.p9wkp5ctrk.com',
                        offer_id:        EF.urlParameter('oid'),
                        affiliate_id:    EF.urlParameter('affid'),
                        sub1:            EF.urlParameter('sub1'),
                        sub2:            EF.urlParameter('sub2'),
                        sub3:            EF.urlParameter('sub3'),
                        sub4:            EF.urlParameter('sub4'),
                        sub5:            EF.urlParameter('sub5'),
                        uid:             EF.urlParameter('uid'),
                        source_id:       EF.urlParameter('source_id'),
                        transaction_id:  EF.urlParameter('_ef_transaction_id'),
                    }).then(function (tid) {
                        persistEftid(tid);
                    });
                }
            }

            var SDK = <?php
                echo wp_json_encode(
                    class_exists( 'BH_Everflow_Helper' )
                        ? BH_Everflow_Helper::TRACKING_SCRIPT
                        : 'https://www.p9wkp5ctrk.com/scripts/main.js'
                );
            ?>;

            if (typeof EF !== 'undefined') {
                runDual();
                return;
            }

            var s = document.createElement('script');
            s.src = SDK;
            s.async = true;
            s.onload = runDual;
            s.onerror = function () {};
            document.head.appendChild(s);
        })();
        </script>
        <?php
    }
}

new AH_Everflow_Tracking();
