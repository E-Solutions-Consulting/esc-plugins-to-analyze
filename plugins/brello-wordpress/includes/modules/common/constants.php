<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plan Days
if(!defined('BH_DAYS_MONTHLY_PLAN'))
    define('BH_DAYS_MONTHLY_PLAN', 25);

if (!defined('BH_DAYS_THREE_MONTH_PLAN')) {
    // Previous value: 70 days.
    // Updated on Jun 26, 2026.
    // Set to 77 days to trigger renewal before the full 90-day subscription period.
    define('BH_DAYS_THREE_MONTH_PLAN', 77);
}

if (!defined('BH_WEEKS_THREE_MONTH_PLAN')) {
    // Previous value: 10 Weeks.
    // Updated on Jun 26, 2026.
    // Set to 11 Weeks days to trigger renewal before the full 90-day subscription period.
    define('BH_WEEKS_THREE_MONTH_PLAN', 11);
}

/**
 * Billing schedule values allowed in a subscription's own Schedule box
 * (admin/js/bh-features-admin-subscription-billing.js). Single source of
 * truth: when a product is created with a new plan length, add it here —
 * nothing else needs to change. Anything outside these lists — e.g.
 * "every 1 day", which the Add subscription screen shows by default — makes
 * the subscription come due again right after its first renewal. Products
 * themselves are not restricted.
 */
if(!defined('BH_ALLOWED_BILLING_PERIODS'))
    define('BH_ALLOWED_BILLING_PERIODS', array( 'month' ));

if(!defined('BH_ALLOWED_BILLING_INTERVALS'))
    define('BH_ALLOWED_BILLING_INTERVALS', array( '1', '3', '6' ));

/**
 * Preselected for a new subscription. 3 months: the plan most subscriptions
 * use, and a wrong 3-month default is noticed long before it renews.
 */
if(!defined('BH_DEFAULT_BILLING_PERIOD'))
    define('BH_DEFAULT_BILLING_PERIOD', 'month');

if(!defined('BH_DEFAULT_BILLING_INTERVAL'))
    define('BH_DEFAULT_BILLING_INTERVAL', '3');

if(!defined('SEND_TO_TELEGRA'))
    define('SEND_TO_TELEGRA', 'send_to_telegra');

/**
 * Order status: the order reached SEND_TO_TELEGRA but the Telegra plugin
 * failed to create it on Telegra's side (API error, timeout, invalid
 * address...). The plugin itself flags this with wp_update_post(), which
 * under HPOS only touches the placeholder post, so the real order used to
 * stay in SEND_TO_TELEGRA until the Stripe authorization expired unnoticed.
 * See AH_Orders_Telegra_Workflow::flag_order_not_sent_to_telegra().
 *
 * 'wc-not_sent_telegra' is 19 chars — the status column is varchar(20),
 * so this slug cannot grow.
 */
if(!defined('NOT_SENT_TO_TELEGRA'))
    define('NOT_SENT_TO_TELEGRA', 'not_sent_telegra');

if(!defined('CANCEL_CUSTOMER_REQUEST'))
    define('CANCEL_CUSTOMER_REQUEST', 'cancel_cus_req');

if(!defined('CANCEL_AUTHORIZATION_EXPIRED'))
    define('CANCEL_AUTHORIZATION_EXPIRED', 'cancel_auth_exp');

if(!defined('CANCEL_PATIENT_REJECTED'))
    define('CANCEL_PATIENT_REJECTED', 'cancel_pat_rej');

/**
 * Subscription status: the previous renewal order paid but its Telegra
 * pipeline never closed out — stalled anywhere between SEND_TO_TELEGRA and
 * "completed" (send_to_telegra, on-hold, waiting_room, provider_review,
 * collect_payment, error_review, admin_review, prerequisites...). Confirmed
 * against real order history: only the very first of these (send_to_telegra)
 * is actually waiting on the customer (their intake form); every status
 * after it means the customer already did their part and the order is
 * waiting on Telegra or CS to act. Used instead of immediately cancelling
 * the subscription — parks it for Telegra/CS follow-up, with an automatic
 * grace-period cancellation (see AH_Renewal_Intake_Review) if nobody
 * resolves it. Does NOT cover 'pending'/'failed' — those are genuine
 * payment failures (bad card, dead payment method), confirmed separately
 * against real data to warrant immediate cancellation, not a review queue.
 * See includes/modules/wc/subscriptions/renewal-intake-review.php.
 */
if(!defined('RENEWAL_PENDING_REVIEW'))
    // Kept short on purpose: order/subscription status columns are
    // varchar(20), and the stored value always carries the 'wc-' prefix
    // (3 chars) on top of this — 'wc-renewal_review' is 17 chars, right at
    // the edge. A longer slug here gets silently truncated by MySQL and
    // every has_status()/update_status() check against this constant then
    // silently stops matching.
    define('RENEWAL_PENDING_REVIEW', 'renewal_review');


if(!defined('BH_TELEGRA_DASHBOARD_URL')){
    $_url = 'https://affiliate-admin.telegramd.com';
    define('BH_TELEGRA_DASHBOARD_URL', $_url);
}

/**
 * Landing-only async Telegra kill-switch option key (legacy; ignored unless
 * BH_ASYNC_TELEGRA_LANDING constant is set). Default path is sync.
 * Classic WP checkout never uses async.
 */
if ( ! defined( 'BH_ASYNC_TELEGRA_LANDING_OPTION' ) ) {
    define( 'BH_ASYNC_TELEGRA_LANDING_OPTION', 'bh_async_telegra_landing' );
}

/** @deprecated Legacy option; no longer drives classic WP checkout. */
if ( ! defined( 'BH_ASYNC_TELEGRA_OPTION' ) ) {
    define( 'BH_ASYNC_TELEGRA_OPTION', 'bh_async_telegra_checkout' );
}
