<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/triplewhale.php';

add_action( 'plugins_loaded', [ 'AH_TripleWhale', 'init' ] );
