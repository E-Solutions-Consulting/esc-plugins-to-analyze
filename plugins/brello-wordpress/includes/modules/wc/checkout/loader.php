<?php
/**
 * Checkout module loader
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base = __DIR__ . '/';

//$this->safe_require( $base . 'checkout-gate.php' );
$this->safe_require( $base . 'bh-checkout-validations.php' );
$this->safe_require( $base . 'bh-phone-standardization.php' );
//$this->safe_require( $base . 'address-flow.php' );
$this->safe_require( $base . 'checkout-ui.php' );
//$this->safe_require( $base . 'checkout-layout.php' );
