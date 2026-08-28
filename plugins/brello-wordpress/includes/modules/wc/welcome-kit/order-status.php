<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the "Kit Ready to Send" order status.
 */
add_action( 'init', function() {
    register_post_status( 'wc-' . KIT_READY_TO_SEND, [
        'label'                     => 'Kit Ready to Send',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Kit Ready to Send (%s)', 'Kit Ready to Send (%s)' ),
    ] );
} );

/**
 * Add it to the WooCommerce visible status list.
 */
add_filter( 'wc_order_statuses', function( $statuses ) {
    $statuses[ 'wc-' . KIT_READY_TO_SEND ] = _x( 'Kit Ready to Send', 'Order status', 'woocommerce' );
    return $statuses;
} );

/**
 * Expose the status to the REST schema enum.
 *
 * Required so the external API can filter (GET ?status=kit_ready_to_send),
 * read the kit orders, and write the status change when it ships the package.
 * Without this WooCommerce rejects the request with rest_invalid_param.
 */
add_filter( 'woocommerce_rest_shop_order_schema', function( $schema ) {
    if ( isset( $schema['properties']['status']['enum'] ) && ! in_array( KIT_READY_TO_SEND, $schema['properties']['status']['enum'], true ) ) {
        $schema['properties']['status']['enum'][] = KIT_READY_TO_SEND;
    }
    return $schema;
} );
