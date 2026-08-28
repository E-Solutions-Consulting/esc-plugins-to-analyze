<?php
/**
 * Migration WP-CLI commands.
 *
 * Usage:
 *   wp ah-migration enable --file=/path/to/customers.txt
 *   wp ah-migration enable 123 456 patient@example.com
 *   wp ah-migration status 123
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class AH_Migration_CLI {

    /**
     * Enable migration (not_available → available) for a list of customers.
     *
     * ## OPTIONS
     *
     * [<customers>...]
     * : User IDs or emails.
     *
     * [--file=<path>]
     * : Path to a file with one user ID or email per line.
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public function enable( $args, $assoc_args ) {
        $lines = $args;

        if ( ! empty( $assoc_args['file'] ) ) {
            if ( ! file_exists( $assoc_args['file'] ) ) {
                WP_CLI::error( 'File not found: ' . $assoc_args['file'] );
            }
            $file_lines = file( $assoc_args['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            $lines      = array_merge( $lines, array_map( 'trim', $file_lines ) );
        }

        if ( empty( $lines ) ) {
            WP_CLI::error( 'No customers provided. Pass IDs/emails as arguments or use --file.' );
        }

        $report = AH_Migration_Admin::bulk_enable( $lines );

        foreach ( $report['details'] as $detail ) {
            WP_CLI::log( $detail );
        }

        WP_CLI::success( sprintf(
            '%d enabled, %d skipped, %d not found.',
            $report['enabled'],
            $report['skipped'],
            $report['not_found']
        ) );
    }

    /**
     * Show the migration status of a customer.
     *
     * ## OPTIONS
     *
     * <customer>
     * : User ID or email.
     *
     * @param array $args
     * @return void
     */
    public function status( $args ) {
        $line = $args[0];
        $user = is_numeric( $line ) ? get_user_by( 'id', absint( $line ) ) : get_user_by( 'email', $line );

        if ( ! $user ) {
            WP_CLI::error( 'Customer not found: ' . $line );
        }

        WP_CLI::log( 'Customer #' . $user->ID . ' (' . $user->user_email . ')' );
        WP_CLI::log( 'Status: ' . AH_Migration_Status::get( $user->ID ) );
        WP_CLI::log( 'Started: ' . ( get_user_meta( $user->ID, AH_Migration_Status::META_STARTED_AT, true ) ?: '—' ) );
        WP_CLI::log( 'Completed: ' . ( get_user_meta( $user->ID, AH_Migration_Status::META_COMPLETED_AT, true ) ?: '—' ) );

        $error = get_user_meta( $user->ID, AH_Migration_Status::META_ERROR, true );
        if ( $error ) {
            WP_CLI::log( 'Error: ' . $error );
        }
    }
}

WP_CLI::add_command( 'ah-migration', 'AH_Migration_CLI' );