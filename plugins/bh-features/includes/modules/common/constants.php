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

if(!defined('SEND_TO_TELEGRA'))
    define('SEND_TO_TELEGRA', 'send_to_telegra');

if(!defined('CANCEL_CUSTOMER_REQUEST'))
    define('CANCEL_CUSTOMER_REQUEST', 'cancel_cus_req');

if(!defined('CANCEL_AUTHORIZATION_EXPIRED'))
    define('CANCEL_AUTHORIZATION_EXPIRED', 'cancel_auth_exp');

if(!defined('CANCEL_PATIENT_REJECTED'))
    define('CANCEL_PATIENT_REJECTED', 'cancel_pat_rej');


if(!defined('TELEGRA_REST_URL')){
    $telemdnow_rest_url = 'https://affiliate-admin.telegramd.com';
    define('TELEGRA_REST_URL', $telemdnow_rest_url);
}

