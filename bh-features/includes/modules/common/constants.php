<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plan Days
if(!defined('BH_DAYS_MONTHLY_PLAN'))
    define('BH_DAYS_MONTHLY_PLAN', 25);

if(!defined('BH_DAYS_THREE_MONTH_PLAN'))
    define('BH_DAYS_THREE_MONTH_PLAN', 50);

if(!defined('SEND_TO_TELEGRA'))
    define('SEND_TO_TELEGRA', 'send_to_telegra');

if(!defined('CANCEL_CUSTOMER_REQUEST'))
    define('CANCEL_CUSTOMER_REQUEST', 'cancel_cus_req');

if(!defined('CANCEL_AUTHORIZATION_EXPIRED'))
    define('CANCEL_AUTHORIZATION_EXPIRED', 'cancel_auth_exp');

if(!defined('CANCEL_PATIENT_REJECTED'))
    define('CANCEL_PATIENT_REJECTED', 'cancel_pat_rej');


