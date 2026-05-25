<?php
/**
 * AH Everflow Tracking Script
 *
 * Outputs the Everflow JS click script in wp_footer.
 * - Standard script: all pages except the affiliate landing page.
 * - Dual-network script: only on the affiliate landing page (page ID configured below).
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

        if ( is_page( self::AFFILIATE_LANDING_PAGE_ID ) ) {
            $this->render_dual_network_script();
        } else {
            $this->render_standard_script();
        }
    }

    private function render_standard_script() {
        ?>
        <script type="text/javascript" src="https://www.p9wkp5ctrk.com/scripts/main.js"></script>
        <script type="text/javascript">
            EF.click({
                offer_id:       EF.urlParameter('oid'),
                affiliate_id:   EF.urlParameter('affid'),
                source_id:      EF.urlParameter('source_id'),
                sub1:           EF.urlParameter('sub1'),
                sub2:           EF.urlParameter('sub2'),
                sub3:           EF.urlParameter('sub3'),
                sub4:           EF.urlParameter('sub4'),
                sub5:           EF.urlParameter('sub5'),
                uid:            EF.urlParameter('uid'),
                transaction_id: EF.urlParameter('_ef_transaction_id'),
            });
        </script>
        <?php
    }

    private function render_dual_network_script() {
        ?>
        <!-- Everflow Dual-Network Click Script -->
        <script type="text/javascript" src="https://www.p9wkp5ctrk.com/scripts/main.js"></script>
        <script type="text/javascript">
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
            });

        }
        </script>
        <!-- /Everflow Dual-Network Click Script -->
        <?php
    }
}

new AH_Everflow_Tracking();