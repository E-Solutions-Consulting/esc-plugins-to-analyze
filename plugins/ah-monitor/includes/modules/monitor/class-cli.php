<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

WP_CLI::add_command( 'ah-monitor run', function( $args, $assoc_args ) {

    $dry_run    = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
    $send_slack = ! $dry_run;

    WP_CLI::log( 'Starting AH Monitor...' );
    WP_CLI::log( 'dry_run: ' . ( $dry_run ? 'yes' : 'no' ) );

    $monitor = new AH_Hourly_Orders_Monitor([
        'dry_run'    => $dry_run,
        'send_slack' => $send_slack,
    ]);

    $result = $monitor->run();

    WP_CLI::log( 'Window: ' . $result['window']['start'] . ' → ' . $result['window']['end'] );
    WP_CLI::log( 'Orders: ' . $result['stats']['orders_created'] );
    WP_CLI::log( 'Revenue: $' . number_format( $result['stats']['total_revenue'] ) );
    WP_CLI::log( 'Slack: ' . $result['slack'] );

    WP_CLI::success( 'Monitor executed.' );

});