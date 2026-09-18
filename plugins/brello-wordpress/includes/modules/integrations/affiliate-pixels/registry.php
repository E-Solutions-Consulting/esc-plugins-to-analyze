<?php
/**
 * Affiliate-gated pixel registry.
 *
 * Key = Everflow affid (string). Only matching traffic receives these snippets.
 * Add new partners here — do not dump partner pixels into sitewide snippets.php.
 *
 * @return array<string, array{name:string, head?:string[], purchase?:string[]}>
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    // Example / test partner — replace or extend when Bay sends atmospheric pixels.
    // '42' => [
    //     'name'     => 'Testing Everflow',
    //     'head'     => [
    //         '<!-- BH affiliate pixel affid=42 page view -->',
    //     ],
    //     'purchase' => [
    //         '<!-- BH affiliate pixel affid=42 purchase -->',
    //     ],
    // ],
];
