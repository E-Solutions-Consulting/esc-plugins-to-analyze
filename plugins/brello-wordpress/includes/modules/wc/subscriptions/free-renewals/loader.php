<?php
/**
 * Free Renewals Module
 *
 * One up-front payment + X uncharged deliveries every N days, until a fixed term.
 * Config lives on the subscription variation (_bh_free_renewals, _bh_plan_duration_days),
 * alongside the existing _bh_renewal_days field.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-ah-free-renewals.php';
require_once __DIR__ . '/class-ah-free-renewals-admin.php';

add_action( 'woocommerce_loaded', function () {

	new AH_Free_Renewals();

	if ( is_admin() ) {
		new AH_Free_Renewals_Admin();
	}
} );