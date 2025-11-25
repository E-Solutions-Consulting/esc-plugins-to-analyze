<?php
/**
 * Global Tracking Container (Thank You Page Controller)
 *
 * This file MUST be loaded before any integration that uses the
 * `BrelloTrackingContainer` object. It provides a unified system
 * to manage multiple tracking scripts firing *before redirecting*
 * the customer away from the Thank You page.
 *
 * ------------------------------------------------------------
 * 🔥 IMPORTANT ARCHITECTURAL RULES (for future developers)
 * ------------------------------------------------------------
 *
 * 1. If an integration ONLY prints JavaScript tracking on the 
 *    Thank You page (e.g. FB Pixel, Google Ads, Vibe, Katalys):
 *
 *       → ADD IT TO: `tracking-frontend.php`
 *
 *    These providers don't have server-side logic, webhooks,
 *    or order event hooks, and should remain simple.
 *
 * ------------------------------------------------------------
 *
 * 2. If an integration ALSO requires:
 *       - server-side order events,
 *       - webhooks,
 *       - REST API callbacks,
 *       - or internal API clients,
 *
 *       → CREATE A DEDICATED DIRECTORY:
 *
 *          /modules/integrations/<provider>/
 *
 *          Example: northbeam/, friendbuy/
 *
 *    And put:
 *       - frontend-tracking.php
 *       - order-events.php
 *       - webhook.php
 *       - api.php
 *       - loader.php
 *
 *    This keeps advanced integrations properly isolated.
 *
 * ------------------------------------------------------------
 *
 * 3. To DISABLE any integration module or file:
 *
 *       → Prefix its directory or file with "__"
 *
 *       Example:
 *           __northbeam/
 *           __frontend-tracking.php
 *
 *    The BH_ModuleLoader will automatically skip them.
 *
 * ------------------------------------------------------------
 *
 * 4. This container ensures:
 *       • All tracking scripts fire in order
 *       • Each provider's script waits until its pixel is ready
 *       • A fallback timer prevents stalls
 *       • Redirect always happens (no tracking lost)
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_footer', function() {

    if (!is_order_received_page()) return;

    global $tracking_data;
    if (empty($tracking_data) || empty($tracking_data['redirect_url'])) return;

    $redirect_url = esc_url($tracking_data['redirect_url']);
?>
    <script>
        // GLOBAL TRACKING CONTAINER
        window.BrelloTrackingContainer = window.BrelloTrackingContainer || (function() {

            const container = {
                queue: [],
                ready: {},
                done: false,
                redirectUrl: "",

                addTask(tag, fn) {
                    this.queue.push({ tag, fn });
                },

                markReady(tag) {
                    this.ready[tag] = true;
                    this.checkAllReady();
                },

                checkAllReady() {
                    if (this.done || this.queue.length === 0) return;

                    const allReady = this.queue.every(item => this.ready[item.tag] === true);

                    if (allReady) {
                        this.done = true;
                        this.executeAll();
                    }
                },

                executeAll() {
                    try {
                        this.queue.forEach(item => {
                            try { item.fn(); } 
                            catch(e){ console.error("Task error:", item.tag, e); }
                        });
                    } catch(e) {
                        console.error("Execution error:", e);
                    }

                    setTimeout(() => {
                        if (this.redirectUrl) window.location.href = this.redirectUrl;
                    }, 500);
                },

                setRedirect(url) {
                    this.redirectUrl = url;
                },

                startFallbackTimer(seconds = 4) {
                    setTimeout(() => {
                        if (this.done) return;
                        this.done = true;
                        this.executeAll();
                        if (this.redirectUrl) window.location.href = this.redirectUrl;
                    }, seconds * 1000);
                }
            };

            return container;
        })();

        BrelloTrackingContainer.setRedirect("<?php echo $redirect_url; ?>");
        BrelloTrackingContainer.startFallbackTimer(4);
    </script>
<?php
}, 50);
