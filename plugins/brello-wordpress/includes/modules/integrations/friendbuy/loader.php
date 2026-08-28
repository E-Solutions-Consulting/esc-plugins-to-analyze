<?php
/**
 * Friendbuy integration module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Intercepts the /friends route and serves a lightweight, standalone
 * version of the page that loads ONLY the FriendBuy snippet.
 *
 * Why: this page is the landing destination for FriendBuy referral
 * links, which include a heavy `fbuy` JWT (~388 bytes) in the URL.
 * Confirmed via live testing that this parameter is REQUIRED for
 * FriendBuy to resolve advocate attribution (getVisitorStatus returns
 * empty payload without it) -- so it cannot be stripped from the URL.
 *
 * The problem: every other tracking script on the site (Metorik's
 * bundled Sourcebuster, WooCommerce's native Sourcebuster/Order
 * Attribution, ProveSource, Attentive) independently captures the full
 * referrer URL into its own cookie, duplicating the fbuy JWT 4-6x.
 * This pushes the total Cookie header to 7-8+ KB, exceeding
 * SiteGround's header size limit and causing intermittent 400 errors
 * -- which interrupts the coupon redemption flow and costs real
 * referral conversions.
 *
 * Fix: bypass WordPress entirely for this route. Serve a static page
 * with minimal header/footer and only the FriendBuy script. No other
 * tracker ever sees this page, so no duplication occurs. Users
 * complete the coupon flow here, then click through to
 * /start-wellness, which loads normally with full tracking (safe,
 * since the URL is clean by then).
 *
 * Maintenance note: this page's design is intentionally static and
 * does not pull from Elementor. If the main site nav changes
 * (menu items added/removed), update the <nav> block in
 * friends-lite.php manually to match.
 */

add_action('template_redirect', 'bh_serve_lightweight_friends_page', 1);
function bh_serve_lightweight_friends_page() {
    if ( ! is_page('friends') ) {
        return;
    }

    $template = plugin_dir_path(__FILE__) . '/friendbuy-friends-lite.php';

    if ( ! file_exists($template) ) {
        // Fail safe: if the static file is missing, fall through to
        // normal WordPress rendering rather than showing a blank page.
        return;
    }

    include $template;
    exit;
}
//
// update_option( 'ah_friendbuy_beta_emails', [
//     'test123@gmail.com',
//     'jaime+qa_150126@gmail.com'
// ] );

require_once plugin_dir_path( __FILE__ ) . '/friendbuy-webhook-handler.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-myaccount.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-api.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-admin.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-coupon-generator.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-referral-tracker.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-events.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy-feature-gate.php';
require_once plugin_dir_path( __FILE__ ) . '/friendbuy.php';
require_once __DIR__ . '/frontend.php';

add_action( 'plugins_loaded', function() {
    new AH_Friendbuy_Frontend();
});