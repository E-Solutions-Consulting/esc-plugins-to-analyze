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

if(!defined('SEND_TO_TELEGRA'))
    define('SEND_TO_TELEGRA', 'send_to_telegra');

if(!defined('CANCEL_CUSTOMER_REQUEST'))
    define('CANCEL_CUSTOMER_REQUEST', 'cancel_cus_req');

if(!defined('CANCEL_AUTHORIZATION_EXPIRED'))
    define('CANCEL_AUTHORIZATION_EXPIRED', 'cancel_auth_exp');

if(!defined('CANCEL_PATIENT_REJECTED'))
    define('CANCEL_PATIENT_REJECTED', 'cancel_pat_rej');


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
