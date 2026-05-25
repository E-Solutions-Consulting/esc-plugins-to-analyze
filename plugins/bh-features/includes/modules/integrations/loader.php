<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integrations loader.
 * Add new integrations here only — do not modify modules/loader.php.
 */

$integrations_base = plugin_dir_path( __FILE__ );

// Server-side tracking
// $this->safe_require( $integrations_base . 'tracking/container.php' );
// $this->safe_require( $integrations_base . 'tracking/frontend.php' );

// TripleWhale
$this->safe_require( $integrations_base . 'triplewhale/loader.php' );

// Podscribe
$this->safe_require( $integrations_base . 'podscribe/loader.php' );

// Attentive
$this->safe_require( $integrations_base . 'attentive/loader.php' );

// Uscreen
$this->safe_require( $integrations_base . 'uscreen/loader.php' );

// Friendbuy
// $this->safe_require( $integrations_base . 'friendbuy/frontend-tracking.php' );
$this->safe_require( $integrations_base . 'friendbuy/loader.php' );

// Everflow
$this->safe_require( $integrations_base . 'everflow/loader.php' );

// Lumen
$this->safe_require( $integrations_base . 'lumen/loader.php' );

// MNTN
$this->safe_require( $integrations_base . 'mntn/loader.php' );

// Vibe (TV Ads) 
$this->safe_require( $integrations_base . 'vibe/loader.php' );