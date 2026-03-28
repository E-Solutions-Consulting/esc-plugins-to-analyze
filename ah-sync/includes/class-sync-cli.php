<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AH_Sync_CLI
 *
 * WP-CLI command: wp ah-sync telegra-wc-sync [--options]
 *
 * Used primarily by SG Cronjob:
 *   cd /path/to/wp && wp ah-sync telegra-wc-sync --allow-root
 */
class AH_Sync_CLI {

    /**
     * Sync WooCommerce order statuses with Telegra.
     *
     * ## OPTIONS
     *
     * [--start=<date>]
     * : Start date filter. Default: 7 days ago. Format: Y-m-d or Y-m-d H:i:s
     *
     * [--end=<date>]
     * : End date filter. Default: now. Format: Y-m-d or Y-m-d H:i:s
     *
     * [--status=<statuses>]
     * : Comma-separated WC statuses to include. Default: all active statuses.
     * Example: --status="send_to_telegra,collect_payment"
     *
     * [--states=<states>]
     * : Comma-separated US state codes. Default: all.
     * Example: --states="CA,TX,FL"
     *
     * [--batch-size=<n>]
     * : Orders per batch. Default: 25.
     *
     * [--method=<method>]
     * : Update method: direct or webhook. Default: direct.
     *
     * [--exclude-renewals]
     * : Exclude renewal orders.
     *
     * [--dry-run]
     * : Preview changes without applying them.
     *
     * ## EXAMPLES
     *
     *     # Default run (last 7 days, all active statuses)
     *     wp ah-sync telegra-wc-sync --allow-root
     *
     *     # Dry run to preview changes
     *     wp ah-sync telegra-wc-sync --dry-run
     *
     *     # Only specific statuses
     *     wp ah-sync telegra-wc-sync --status="send_to_telegra,collect_payment"
     *
     *     # Specific date range
     *     wp ah-sync telegra-wc-sync --start="2026-03-01" --end="2026-03-22"
     *
     * @when after_wp_load
     */
    public function telegra_wc_sync( array $args, array $assoc_args ) {

        $dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

        WP_CLI::log( '=== AH Sync — Telegra WC Sync ===' );
        WP_CLI::log( 'Mode: ' . ( $dry_run ? 'DRY RUN' : 'LIVE' ) );

        // ── Parse args ─────────────────────────────────────────
        $runner_args = [
            'start_date'       => $assoc_args['start'] ?? date( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
            'end_date'         => $assoc_args['end']   ?? date( 'Y-m-d H:i:s' ),
            'status'           => ! empty( $assoc_args['status'] )
                                    ? array_filter( array_map( 'trim', explode( ',', $assoc_args['status'] ) ) )
                                    : [],
            'states'           => ! empty( $assoc_args['states'] )
                                    ? array_filter( array_map( 'trim', explode( ',', $assoc_args['states'] ) ) )
                                    : [],
            'batch_size'       => intval( $assoc_args['batch-size'] ?? 25 ),
            'update_method'    => sanitize_text_field( $assoc_args['method'] ?? 'direct' ),
            'exclude_sync' => WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-sync', false ),
            'exclude_renewals' => WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude-renewals', false ),
            'dry_run'          => $dry_run,
            'mode'             => 'apply_fresh',
        ];

        WP_CLI::log( sprintf(
            'Date range: %s → %s | Batch: %d | Method: %s',
            $runner_args['start_date'],
            $runner_args['end_date'],
            $runner_args['batch_size'],
            $runner_args['update_method']
        ) );

        if ( ! empty( $runner_args['status'] ) ) {
            WP_CLI::log( 'Status filter: ' . implode( ', ', $runner_args['status'] ) );
        }

        // ── Run ────────────────────────────────────────────────
        $logger = new AH_Sync_Logger( AH_Sync_Logger::SOURCE_CLI );
        $runner = new AH_Telegra_WC_Sync( $logger );

        try {
            $stats = $runner->run_full( $runner_args );

            // Output summary table
            WP_CLI\Utils\format_items( 'table',
                [ $stats ],
                [ 'total', 'updated', 'pharmacy_ok', 'pharmacy_error', 'cancel_conflict', 'in_sync', 'skipped', 'errors' ]
            );

            if ( $stats['errors'] > 0 || $stats['pharmacy_error'] > 0 ) {
                WP_CLI::warning( 'Completed with errors. Check WooCommerce logs → ah-sync-cli for details.' );
                exit( 1 );
            }

            WP_CLI::success( 'Sync completed.' );
            exit( 0 );

        } catch ( \Throwable $th ) {
            WP_CLI::error( 'Fatal error: ' . $th->getMessage() );
            exit( 1 );
        }
    }
}
