<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Tracking_Snippets {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'clarity' ] );
        add_action( 'wp_head', [ $this, 'wire' ] );
        add_action( 'wp_head', [ $this, 'reddit' ] );
        add_action( 'wp_head', [ $this, 'trustpilot' ] );
        add_action( 'wp_body_open', [ $this, 'googletagmanager' ], 1 );
        // add_action( 'wp_body_open', [ $this, 'optinmonster' ] );

    }

    /**
     * Trustpilot TrustBox bootstrap script.
     */
    public function clarity() {
        ?>
        <script type="text/javascript">
            (function(c,l,a,r,i,t,y){
                c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
            })(window, document, "clarity", "script", "ryzk946tg0");
        </script>
        <script type="text/javascript">
            (function(c,l,a,r,i,t,y){
                c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
            })(window, document, "clarity", "script", "s5lvloonrb");
        </script>
        <?php
    }

    /**
     * wire spbx app script.
     */
    public function wire() {
        ?>
        <script type="text/javascript" async src="https://api.wire.spbx.app/wire.js?account_id=694192d56cccf846cd875512&amp;property_id=694192ef6cccf846cd875513"> </script>
        <?php
    }

    /**
     * Reddit Pixel script.
     */
    public function reddit() {
        ?>
        <!-- Reddit Pixel -->
        <script>
        !function(w,d){if(!w.rdt){var p=w.rdt=function(){p.sendEvent?p.sendEvent.apply(p,arguments):p.callQueue.push(arguments)};p.callQueue=[];var t=d.createElement("script");t.src="https://www.redditstatic.com/ads/pixel.js",t.async=!0;var s=d.getElementsByTagName("script")[0];s.parentNode.insertBefore(t,s)}}(window,document);rdt('init','a2_h5hq0hgt6byg');rdt('track', 'PageVisit');
        </script>
        <!-- End Reddit Pixel -->
        <?php
    }

    /**
     * Trustpilot TrustBox bootstrap script.
     */
    public function trustpilot() {
        ?><!-- TrustBox script -->
        <script type="text/javascript" src="//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js" async></script>
        <!-- End TrustBox script -->
        <?php
    }

    /**
     * Google Tag Manager script.
     */
    public function googletagmanager() {
        ?><!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PNK3T42T" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }

    /**
     * OptinMonster script.
     */
    public function optinmonster() {
        ?>
        <!-- This site is converting visitors into subscribers and customers with OptinMonster - https://optinmonster.com -->
        <script>(function(d,u,ac){var s=d.createElement('script');s.type='text/javascript';s.src='https://a.omappapi.com/app/js/api.min.js';s.async=true;s.dataset.user=u;s.dataset.account=ac;d.getElementsByTagName('head')[0].appendChild(s);})(document,143138,156340);</script>
        <!-- / https://optinmonster.com -->
        <?php
    }
}