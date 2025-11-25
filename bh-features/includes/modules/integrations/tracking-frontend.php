<?php
/**
 * Simple Tracking Providers (Thank You Page Only)
 *
 * Included here ONLY if:
 *   - provider has no API,
 *   - no server-side order events,
 *   - no webhooks,
 *   - and only prints JS tracking.
 */

if (!defined('ABSPATH')) exit;

/**
 * FACEBOOK PIXEL
 */
add_action('wp_footer', function() {

    if (!is_order_received_page()) return;
    global $tracking_data;

    if (!$tracking_data) return;
?>
    <script>
        BrelloTrackingContainer.addTask("facebook", function(){
            try {
                if (typeof fbq !== "undefined") {
                    fbq("track", "Purchase", {
                        value: <?php echo esc_js($tracking_data['sale_amount']); ?>,
                        currency: "USD"
                    });
                }
            } catch (e) { console.error(e); }
        });

        let fb_int = setInterval(function(){
            if (typeof fbq !== "undefined") {
                clearInterval(fb_int);
                BrelloTrackingContainer.markReady("facebook");
            }
        },100);
    </script>
<?php
}, 120);

/**
 * GOOGLE ADS
 */
add_action('wp_footer', function(){

    if (!is_order_received_page()) return;
    global $tracking_data;

    if (!$tracking_data) return;
?>
    <script>
        BrelloTrackingContainer.addTask("google_ads", function(){
            try {
                if (typeof gtag !== "undefined") {
                    gtag("event", "conversion", {
                        "send_to": "AW-16978798190/SHNSCJWTkLkaEO7Mj6A_",
                        "value": <?php echo esc_js($tracking_data['sale_amount']); ?>,
                        "currency": "USD",
                        "transaction_id": "<?php echo esc_js($tracking_data['order_id']); ?>"
                    });
                }
            } catch (e) { console.error(e); }
        });

        let ga_int = setInterval(function(){
            if (typeof gtag !== "undefined") {
                clearInterval(ga_int);
                BrelloTrackingContainer.markReady("google_ads");
            }
        },100);
    </script>
<?php
}, 120);

/**
 * VIBE
 */
add_action('wp_footer', function() {

    if (!is_order_received_page()) return;
    global $tracking_data;

    if (!$tracking_data) return;
?>
    <script>
        BrelloTrackingContainer.addTask("vibe", function(){
            try {
                !function(v,i,b,e,c,o){
                    if(!v[c]){
                        var s=v[c]=function(){ s.process ? s.process.apply(s,arguments) : s.queue.push(arguments) };
                        s.queue=[],s.b=1*new Date;
                        var t=i.createElement(b);t.async=!0;t.src=e;
                        var n=i.getElementsByTagName(b)[0];n.parentNode.insertBefore(t,n)
                    }
                }(window,document,"script","https://s.vibe.co/vbpx.js","vbpx");
                vbpx("init","O17U5n");
                vbpx("event","purchase", {"price_usd": "<?php echo esc_js($tracking_data['sale_amount']); ?>"});
            } catch (e){ console.error(e); }
        });

        setTimeout(()=>BrelloTrackingContainer.markReady("vibe"),150);
    </script>
<?php
}, 120);

/**
 * KATALYS
 */
add_action('wp_footer', function(){

    if (!is_order_received_page()) return;
    global $tracking_data;

    if (!$tracking_data) return;
?>
    <script>
        BrelloTrackingContainer.addTask("katalys", function(){
            if (typeof _revoffers_track !== "undefined") {
                _revoffers_track.push({
                    action: "convert",
                    order_id: "<?php echo esc_js($tracking_data['order_id']); ?>",
                    sale_amount: "<?php echo esc_js($tracking_data['sale_amount']); ?>",
                    subtotal_amount: "<?php echo esc_js($tracking_data['subtotal_amount']); ?>",
                    email_address: "<?php echo esc_js($tracking_data['email']); ?>",
                    discount_1_code: "<?php echo esc_js($tracking_data['discount_code']); ?>"
                });
            }
        });

        let kat_int = setInterval(function(){
            if (typeof _revoffers_track !== "undefined") {
                clearInterval(kat_int);
                BrelloTrackingContainer.markReady("katalys");
            }
        },100);
    </script>
<?php
}, 120);
