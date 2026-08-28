<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MNTN TV tracking integration.
 *
 * Renders the global tracking pixel on all pages and the conversion
 * pixel exclusively on the WooCommerce order-received page.
 */
class AH_MNTN {

    public function __construct() {
        add_action( 'wp_head',   [ $this, 'insert_tracking_pixel' ],    20 );
        add_action( 'wp_footer', [ $this, 'insert_conversion_pixel' ], 100 );
    }

    /**
     * Global MNTN tracking pixel — rendered on every page.
     */
    public function insert_tracking_pixel() {
        ?>
        <script type="text/javascript">
            (function(){"use strict";var e=null,n="60131",additional="",t,r,i;try{t=top.document.referer!==""?encodeURIComponent(top.document.referrer.substring(0,2048)):""}catch(o){t=document.referrer!==null?document.referrer.toString().substring(0,2048):""}
          try{i=parent.location.href!==""?encodeURIComponent(parent.location.href.toString().substring(0,2048)):""}catch(a){try{i!==null?encodeURIComponent(i.toString().substring(0,2048)):""}catch(f){i=""}}
          var l,c=document.createElement("script"),h=null,p=document.getElementsByTagName("script"),d=Number(p.length)-1,v=document.getElementsByTagName("script")[d];if(typeof l==="undefined"){l=Math.floor(Math.random()*1e17)}
          h="https://dx.mountain.com/spx?"+"shaid="+n+"&tdr="+t+"&plh="+i+"&cb="+l+additional;c.type="text/javascript";c.src=h;v.parentNode.insertBefore(c,v)})();
        </script>
        <?php
    }

    /**
     * MNTN conversion pixel — rendered only on the order confirmation page.
     */
    public function insert_conversion_pixel() {

        if ( ! is_order_received_page() ) {
            return;
        }

        global $tracking_data;

        if ( empty( $tracking_data ) ) {
            return;
        }

        $order_id  = isset( $tracking_data['order_id'] )   ? $tracking_data['order_id']   : '';
        $order_amt = isset( $tracking_data['sale_amount'] ) ? $tracking_data['sale_amount'] : '';

        if ( ! $order_id ) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function(){var x=null,p,q,m,
            o="60131",
            conversion_type="Purchase",
            order_id="<?php echo esc_js( $order_id ); ?>",
            order_amt="<?php echo esc_js( $order_amt ); ?>",
            b="",c="",k="",g="",j="",u="";try{p=top.document.referer!==""?encodeURIComponent(top.document.referrer.substring(0,2048)):""}catch(n){p=document.referrer!==null?document.referrer.toString().substring(0,2048):""}try{m=parent.location.href!==""?encodeURIComponent(parent.location.href.toString().substring(0,2048)):""}catch(z){try{m!==null?encodeURIComponent(i.toString().substring(0,2048)):""}catch(h){m=""}}
        var A,y=document.createElement("script"),w=null,v=document.getElementsByTagName("script"),t=Number(v.length)-1,r=document.getElementsByTagName("script")[t];if(typeof A==="undefined"){A=Math.floor(Math.random()*1e17)}
        w="https://dx.mountain.com/spx?conv=1"+"&shaid="+o+"&tdr="+p+"&plh="+m+"&cb="+A+"&shoid="+order_id+"&shoamt="+order_amt+"&type="+conversion_type+"&shocur="+c+"&shopid="+k+"&shoq="+g+"&shoup="+j+"&shpil="+u+b;y.type="text/javascript";y.src=w;r.parentNode.insertBefore(y,r)}());
        </script>
        <?php
    }
}