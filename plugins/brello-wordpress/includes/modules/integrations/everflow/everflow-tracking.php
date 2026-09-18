<?php
/**
 * AH Everflow Tracking Script
 *
 * Outputs the Everflow JS click script in wp_footer.
 * - Standard script: all pages except the affiliate landing page.
 * - Dual-network script: only on the affiliate landing page (page ID configured below).
 *
 * First-touch: do not overwrite ef_entry_tid when mid-routing.
 * Cookie domain=.brellohealth.com so quiz → www TID survives.
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

        // Checkout: keep first-touch cookie warm with existing TID only (no new offer click).
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            $this->render_checkout_tid_keepalive();
            return;
        }

        if ( is_page( self::AFFILIATE_LANDING_PAGE_ID ) ) {
            $this->render_dual_network_script();
        } else {
            $this->render_standard_script();
        }
    }

    /**
     * Shared JS helpers for cookie + first-touch (inlined in each snippet).
     */
    private function js_helpers_block() {
        return <<<'JS'
                var COOKIE_DOMAIN = '.brellohealth.com';
                var MAX_AGE = 60 * 60 * 24 * 30;

                function log() {
                    try {
                        if (typeof console !== 'undefined' && console.log) {
                            console.log.apply(console, ['[BH Everflow]'].concat([].slice.call(arguments)));
                        }
                    } catch (e) {}
                }

                function setCookie(name, value) {
                    if (!value) { return; }
                    try {
                        var base = name + '=' + encodeURIComponent(value) + ';path=/;max-age=' + MAX_AGE + ';SameSite=Lax';
                        document.cookie = base;
                        if (/brellohealth\.com$/i.test(location.hostname)) {
                            document.cookie = base + ';domain=' + COOKIE_DOMAIN;
                        }
                    } catch (e) {}
                }

                function getCookie(name) {
                    try {
                        var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
                        return m ? decodeURIComponent(m[1]) : '';
                    } catch (e) { return ''; }
                }

                function normalizeTid(raw) {
                    var t = String(raw || '').trim();
                    if (!t) { return ''; }
                    if (t.indexOf('|') !== -1 || t.indexOf(',') !== -1) {
                        t = t.split(/[|,]/)[0].trim();
                    }
                    return t;
                }

                function persistEftid(tid) {
                    tid = normalizeTid(tid);
                    if (!tid) { return; }
                    setCookie('eftid', tid);
                    try { if (window.localStorage) { localStorage.setItem('eftid', tid); } } catch (e) {}
                    if (!getCookie('ef_entry_tid')) {
                        setCookie('ef_entry_tid', tid);
                        try { if (window.localStorage) { localStorage.setItem('ef_entry_tid', tid); } } catch (e) {}
                    }
                    log('eftid saved:', tid);
                }

                function persistEntryMeta(oid, affid) {
                    if (oid && !getCookie('ef_entry_oid')) {
                        setCookie('ef_entry_oid', oid);
                        try { if (window.localStorage) { localStorage.setItem('ef_entry_oid', oid); } } catch (e) {}
                    }
                    if (affid && !getCookie('ef_entry_affid')) {
                        setCookie('ef_entry_affid', affid);
                        try { if (window.localStorage) { localStorage.setItem('ef_entry_affid', affid); } } catch (e) {}
                    }
                }

                function getEntryTid() {
                    var fromLs = '';
                    try { fromLs = window.localStorage ? (localStorage.getItem('ef_entry_tid') || localStorage.getItem('eftid') || '') : ''; } catch (e) {}
                    return normalizeTid(getCookie('ef_entry_tid') || getCookie('eftid') || fromLs || '');
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
JS;
    }

    private function render_checkout_tid_keepalive() {
        ?>
        <!-- BH Everflow checkout TID keepalive (no new EF.click) -->
        <script type="text/javascript">
            (function () {
                <?php echo $this->js_helpers_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                var tid = getEntryTid() || urlParam('_ef_transaction_id') || urlParam('transaction_id');
                if (tid) {
                    persistEftid(tid);
                    log('checkout keepalive — entry TID preserved');
                }
            })();
        </script>
        <?php
    }

    private function render_standard_script() {
        $sdk = class_exists( 'BH_Everflow_Helper' )
            ? BH_Everflow_Helper::TRACKING_SCRIPT
            : 'https://www.p9wkp5ctrk.com/scripts/main.js';
        ?>
        <!-- BH Everflow click tracking (standard, first-touch) -->
        <script type="text/javascript">
            (function () {
                var SDK = <?php echo wp_json_encode( $sdk ); ?>;
                <?php echo $this->js_helpers_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                var fromUrl = urlParam('_ef_transaction_id') || urlParam('transaction_id') || urlParam('ef_transaction_id');
                if (fromUrl) {
                    persistEftid(fromUrl);
                    persistEntryMeta(urlParam('oid'), urlParam('affid'));
                }

                function runClick() {
                    var entryTid = getEntryTid();
                    // Mid-routing: keep first-touch; do not fire a new offer click that remaps attribution.
                    if (entryTid && !fromUrl) {
                        var newOid = '';
                        var newAff = '';
                        try {
                            if (typeof EF !== 'undefined' && typeof EF.urlParameter === 'function') {
                                newOid = EF.urlParameter('oid') || '';
                                newAff = EF.urlParameter('affid') || '';
                            }
                        } catch (e) {}
                        newOid = newOid || urlParam('oid');
                        newAff = newAff || urlParam('affid');
                        if (newOid || newAff) {
                            log('click skip — first-touch lock (mid-route oid/affid ignored for remap)');
                            persistEftid(entryTid);
                            return;
                        }
                        // Continuity click with existing transaction_id only.
                    }

                    if (typeof EF === 'undefined' || typeof EF.click !== 'function') {
                        log('click skipped — EF SDK missing');
                        return;
                    }
                    try {
                        var oid = EF.urlParameter('oid') || urlParam('oid') || '';
                        var affid = EF.urlParameter('affid') || urlParam('affid') || '';
                        var tidParam = EF.urlParameter('_ef_transaction_id') || fromUrl || entryTid || '';
                        var transactionId = tidParam || '';

                        if (!transactionId && !(oid && affid)) {
                            log('click skipped — Missing offer_id or transaction_id');
                            return;
                        }

                        // If entry TID exists, never pass a different fresh oid remap — use entry only.
                        if (entryTid) {
                            transactionId = entryTid;
                            oid = getCookie('ef_entry_oid') || oid;
                            affid = getCookie('ef_entry_affid') || affid;
                        }

                        persistEntryMeta(oid, affid);

                        EF.click({
                            offer_id:       oid || undefined,
                            affiliate_id:   affid || undefined,
                            source_id:      EF.urlParameter('source_id'),
                            sub1:           EF.urlParameter('sub1'),
                            sub2:           EF.urlParameter('sub2'),
                            sub3:           EF.urlParameter('sub3'),
                            sub4:           EF.urlParameter('sub4'),
                            sub5:           EF.urlParameter('sub5'),
                            uid:            EF.urlParameter('uid'),
                            transaction_id: transactionId || undefined,
                        }).then(function (transaction_id) {
                            persistEftid(transaction_id || transactionId);
                        }).catch(function (err) {
                            log('click error', err);
                            if (transactionId) { persistEftid(transactionId); }
                        });
                    } catch (e) {
                        log('click exception', e);
                    }
                }

                if (typeof EF !== 'undefined') {
                    runClick();
                    return;
                }

                var s = document.createElement('script');
                s.src = SDK;
                s.async = true;
                s.onload = runClick;
                s.onerror = function () { log('SDK failed to load'); };
                document.head.appendChild(s);
            })();
        </script>
        <?php
    }

    private function render_dual_network_script() {
        $sdk = class_exists( 'BH_Everflow_Helper' )
            ? BH_Everflow_Helper::TRACKING_SCRIPT
            : 'https://www.p9wkp5ctrk.com/scripts/main.js';
        ?>
        <!-- BH Everflow click tracking (dual-network, first-touch aware) -->
        <script type="text/javascript">
        (function () {
            var SDK = <?php echo wp_json_encode( $sdk ); ?>;
            <?php echo $this->js_helpers_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            var fromUrl = urlParam('_ef_transaction_id') || urlParam('transaction_id') || urlParam('ef_transaction_id');
            if (fromUrl) {
                persistEftid(fromUrl);
                persistEntryMeta(urlParam('oid'), urlParam('affid'));
            }

            function runDual() {
                if (typeof EF === 'undefined' || typeof EF.click !== 'function') {
                    log('dual click skipped — EF SDK missing');
                    return;
                }

                var entryTid = getEntryTid();
                if (entryTid && !fromUrl && !urlParam('oid') && !urlParam('affid') && !urlParam('affid2')) {
                    persistEftid(entryTid);
                    log('dual skip — first-touch TID already set');
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
                        transaction_id:  entryTid || fromUrl || undefined,
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
                            transaction_id:  entryTid || fromUrl || undefined,
                        }).then(function (tid) {
                            persistEftid(tid || entryTid);
                            persistEntryMeta(EF.urlParameter('oid'), EF.urlParameter('affid'));
                        });
                    });
                } else {
                    var oid = EF.urlParameter('oid') || '';
                    var affid = EF.urlParameter('affid') || '';
                    persistEntryMeta(oid, affid);
                    EF.click({
                        tracking_domain: 'https://www.p9wkp5ctrk.com',
                        offer_id:        oid,
                        affiliate_id:    affid,
                        sub1:            EF.urlParameter('sub1'),
                        sub2:            EF.urlParameter('sub2'),
                        sub3:            EF.urlParameter('sub3'),
                        sub4:            EF.urlParameter('sub4'),
                        sub5:            EF.urlParameter('sub5'),
                        uid:             EF.urlParameter('uid'),
                        source_id:       EF.urlParameter('source_id'),
                        transaction_id:  entryTid || EF.urlParameter('_ef_transaction_id') || fromUrl || undefined,
                    }).then(function (tid) {
                        persistEftid(tid || entryTid);
                    });
                }
            }

            if (typeof EF !== 'undefined') {
                runDual();
                return;
            }

            var s = document.createElement('script');
            s.src = SDK;
            s.async = true;
            s.onload = runDual;
            s.onerror = function () { log('SDK failed to load'); };
            document.head.appendChild(s);
        })();
        </script>
        <?php
    }
}

new AH_Everflow_Tracking();
