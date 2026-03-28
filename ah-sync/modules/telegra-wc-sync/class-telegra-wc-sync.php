<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AH_Telegra_WC_Sync
 *
 * Core runner — shared by AJAX (manual UI), WP-CLI, and cron.
 * Stateless: every public method receives its config as $args.
 */
class AH_Telegra_WC_Sync {

    // Transient keys
    const TRANSIENT_ACTIVE       = 'ah_sync_telegra_wc_active';
    const TRANSIENT_PREVIEW_IDS  = 'ah_sync_telegra_wc_preview_ids';   // IDs that need update
    const TRANSIENT_EXCLUDED_IDS = 'ah_sync_telegra_wc_excluded_ids';  // IDs to skip in apply
    const TRANSIENT_TTL          = 1800; // 30 minutes

    // WC statuses excluded from query (terminal / protected)
    const EXCLUDED_STATUSES = [
        'wc-completed',
        'wc-cancelled',
        'wc-refunded',
        'wc-on-hold',
        'wc-draft',
        'wc-failed',
        'wc-pending',
    ];

    private AH_Sync_Logger $logger;
    private string $telegra_rest_url = 'https://telegramd-rest.telegramd.com';
    private ?string $telegra_token   = null;

    public function __construct( AH_Sync_Logger $logger ) {
        $this->logger = $logger;
    }

    // ─────────────────────────────────────────────────────────
    // PUBLIC — called by AJAX handler (one batch per AJAX tick)
    // ─────────────────────────────────────────────────────────

    /**
     * Process one batch.
     *
     * $args = [
     *   offset           int
     *   batch_size       int
     *   dry_run          bool
     *   mode             'preview' | 'apply_cached' | 'apply_fresh'
     *   update_method    'direct' | 'webhook'
     *   exclude_sync bool
     *   exclude_renewals bool
     *   status           string[]   WC statuses to include (empty = all active)
     *   states           string[]   US state codes (empty = all)
     *   start_date       string
     *   end_date         string
     * ]
     *
     * Returns array consumed by AJAX handler → JS progress bar.
     */
    public function process_batch( array $args ): array {
        global $wpdb;

        $offset           = intval( $args['offset'] ?? 0 );
        $batch_size       = intval( $args['batch_size'] ?? 25 );
        $dry_run          = boolval( $args['dry_run'] ?? false );
        $mode             = $args['mode'] ?? 'apply_fresh';
        $update_method    = $args['update_method'] ?? 'direct';
        $exclude_sync = boolval( $args['exclude_sync'] ?? false );
        $exclude_renewals = boolval( $args['exclude_renewals'] ?? false );

        set_transient( self::TRANSIENT_ACTIVE, true, 3600 );

        // ── Build query ────────────────────────────────────────
        [ $joins_sql, $where_sql ] = $this->build_query( $args, $mode );

        // ── Total (first tick only) ────────────────────────────
        if ( $offset === 0 ) {
            $total = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT o.id)
                 FROM {$wpdb->prefix}wc_orders o
                 {$joins_sql}
                 WHERE {$where_sql}"
            );
        } else {
            $total = intval( $args['total'] ?? 0 );
        }

        // ── Fetch batch ────────────────────────────────────────
        $order_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT o.id AS order_id
                 FROM {$wpdb->prefix}wc_orders o
                 {$joins_sql}
                 WHERE {$where_sql}
                 ORDER BY o.id DESC
                 LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            )
        );

        // ── Telegra token ──────────────────────────────────────
        $this->maybe_refresh_token();

        // ── Status map ─────────────────────────────────────────
        $order_status_map  = $this->build_status_map();
        $telegra_label_map = TelemdNow::get_telegra_order_status();

        // ── Loop ───────────────────────────────────────────────
        $rows      = [];
        $processed = $offset;
        $stats     = $this->empty_stats();

        // IDs to persist across batches for preview/apply_cached
        $preview_ids  = $offset === 0 ? [] : ( get_transient( self::TRANSIENT_PREVIEW_IDS )  ?: [] );
        $excluded_ids = $offset === 0 ? [] : ( get_transient( self::TRANSIENT_EXCLUDED_IDS ) ?: [] );

        foreach ( $order_rows as $order_item ) {
            $row = $this->process_single_order(
                (int) $order_item->order_id,
                $order_status_map,
                $telegra_label_map,
                $update_method,
                $dry_run,
                $exclude_sync,
                $exclude_renewals
            );

            if ( $row === null ) {
                $processed++;
                continue;
            }

            $rows[] = $row;
            $this->tally_stats( $stats, $row );
            $this->logger->log_order_result( $row );

            // Accumulate IDs for transient
            if ( in_array( $row['sync']['action'], [ 'update' ], true ) ) {
                $preview_ids[] = $row['order']['id'];
            } elseif ( in_array( $row['sync']['action'], [ 'in_sync', 'skip' ], true ) ) {
                $excluded_ids[] = $row['order']['id'];
            }

            $processed++;
        }

        // ── Persist transients after each batch ────────────────
        set_transient( self::TRANSIENT_PREVIEW_IDS,  $preview_ids,  self::TRANSIENT_TTL );
        set_transient( self::TRANSIENT_EXCLUDED_IDS, $excluded_ids, self::TRANSIENT_TTL );

        // ── Completion ─────────────────────────────────────────
        $next_offset = $offset + $batch_size;
        $complete    = $processed >= $total;

        if ( $complete ) {
            $this->logger->log_summary( $stats, $dry_run );
            if ( ! $dry_run ) {
                delete_transient( self::TRANSIENT_ACTIVE );
            }
        }

        return [
            'processed'    => $processed,
            'total'        => $total,
            'next_offset'  => $next_offset,
            'complete'     => $complete,
            'rows'         => $rows,
            'stats'        => $stats,
            'preview'      => $dry_run,
            'has_cached'   => ! empty( $preview_ids ),
        ];
    }

    /**
     * Run a full sync in one go — used by WP-CLI and cron.
     * Loops internally until all orders are processed.
     *
     * Returns summary stats array.
     */
    public function run_full( array $args ): array {
        $offset     = 0;
        $total      = 0;
        $stats      = $this->empty_stats();
        $batch_size = intval( $args['batch_size'] ?? 25 );

        $this->logger->info( 'Starting full sync run', [ 'args' => $args ] );

        do {
            $args['offset'] = $offset;
            $args['total']  = $total;
            $args['mode']   = 'apply_fresh';

            $result = $this->process_batch( $args );

            if ( $offset === 0 ) {
                $total = $result['total'];
                $this->logger->info( 'Total orders to process: ' . $total );
            }

            // Merge stats
            foreach ( $stats as $key => $val ) {
                $stats[ $key ] += $result['stats'][ $key ] ?? 0;
            }

            $offset = $result['next_offset'];

        } while ( ! $result['complete'] );

        $this->logger->log_summary( $stats, $args['dry_run'] ?? false );

        return $stats;
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE — single order processing
    // ─────────────────────────────────────────────────────────

    /**
     * Process one order — fully wrapped in try/catch.
     * Returns null if the order should be silently skipped (no entity_id, renewal filter, etc.)
     * Returns a row array in all other cases (including errors).
     */
    private function process_single_order(
        int    $order_id,
        array  $order_status_map,
        array  $telegra_label_map,
        string $update_method,
        bool   $dry_run,
        bool   $exclude_sync,
        bool   $exclude_renewals
    ): ?array {

        try {
            $order = wc_get_order( $order_id );
            if ( ! $order ) return null;

            if ( $exclude_renewals && function_exists( 'wcs_order_contains_subscription' ) && ! wcs_order_contains_subscription( $order ) ) {
                return null;
            }

            $telemdnow_entity_id = $order->get_meta( 'telemdnow_entity_id', true );
            if ( empty( $telemdnow_entity_id ) ) return null;

            // ── Telegra API ────────────────────────────────────
            $telegra_status = $this->fetch_telegra_status( $telemdnow_entity_id );
            if ( $telegra_status === null ) {
                return $this->error_row( $order, $telemdnow_entity_id, 'Telegra API error or empty response' );
            }

            // ── Evaluate ───────────────────────────────────────
            $wc_current_status = $order->get_status();
            $decision          = AH_Telegra_WC_Evaluator::evaluate( $wc_current_status, $telegra_status, $order_status_map );

            $update_result    = $decision['reason'];
            $actually_updated = false;
            $pharmacy_result  = null;
            $new_wc_status    = $decision['target_status'] ?? $wc_current_status;

            // ── Sub-decision: pharmacy check ───────────────────
            if (
                $decision['action'] === 'in_sync'
                && $wc_current_status === 'collect_payment'
                && $telegra_status    === 'requires_order_processing'
            ) {
                [ $update_result, $actually_updated, $pharmacy_result ] =
                    $this->handle_pharmacy_check( $order, $telemdnow_entity_id, $dry_run );
            }

            if ( $exclude_sync && $decision['action'] === 'in_sync' ) {
                return null;
            }

            // ── Execute status update ──────────────────────────
            if ( $decision['action'] === 'update' ) {
                [ $update_result, $actually_updated ] =
                    $this->execute_update( $order, $decision, $telegra_status, $telemdnow_entity_id, $update_method, $dry_run, $wc_current_status );
            }

            return [
                'order'   => [
                    'id'            => $order->get_id(),
                    'link'          => admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() ),
                    'status_before' => $wc_current_status,
                    'status_after'  => $new_wc_status,
                    'date_created'  => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
                    'total'         => $order->get_total(),
                    'currency'      => $order->get_currency(),
                ],
                'telegra' => [
                    'status_raw' => $telegra_status,
                    'status'     => $telegra_label_map[ $telegra_status ] ?? $telegra_status,
                    'link'       => 'https://affiliate-admin.telegramd.com/orders/' . $telemdnow_entity_id,
                ],
                'sync'    => [
                    'action'          => $decision['action'],
                    'updated'         => $actually_updated,
                    'update_method'   => $update_method,
                    'result'          => $update_result,
                    'reason'          => $decision['reason'],
                    'pharmacy_result' => $pharmacy_result,
                ],
            ];

        } catch ( \Throwable $th ) {
            $this->logger->error( 'Exception on order #' . $order_id . ': ' . $th->getMessage() );
            // Return an error row so it shows in the UI — don't kill the whole batch
            $order = wc_get_order( $order_id );
            return $order
                ? $this->error_row( $order, '', 'Exception: ' . $th->getMessage() )
                : null;
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE — helpers
    // ─────────────────────────────────────────────────────────

    private function fetch_telegra_status( string $entity_id ): ?string {
        $url      = $this->telegra_rest_url . '/orders/' . $entity_id . '?access_token=' . $this->telegra_token;
        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Telegra API wp_error: ' . $response->get_error_message() );
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 200 || empty( $body['status'] ) ) {
            $this->logger->error( 'Telegra API HTTP ' . $code . ' for entity ' . $entity_id );
            return null;
        }

        return $body['status'];
    }

    private function handle_pharmacy_check( $order, string $entity_id, bool $dry_run ): array {
        $pharmacy_sent = get_post_meta( $order->get_id(), 'order_sent_pharmacy', true );

        if ( $pharmacy_sent ) {
            return [ 'In sync — pharmacy already notified', false, null ];
        }

        if ( $dry_run ) {
            return [ 'preview — pharmacy NOT notified, would re-trigger sendToPharmacy', false, 'would_retrigger' ];
        }

        // Re-trigger using the existing class with built-in 3-attempt retry
        $order_actions = TelemdNow_Order_Actions::get_instance();
        $order_actions->send_order_to_pharmacy( $order->get_id(), $entity_id, $order );

        $sent_now = get_post_meta( $order->get_id(), 'order_sent_pharmacy', true );
        if ( $sent_now ) {
            return [ 'pharmacy re-triggered successfully', true, 'retriggered_ok' ];
        }
        return [ 'pharmacy re-trigger failed — check order notes', false, 'retriggered_error' ];
    }

    private function execute_update( $order, array $decision, string $telegra_status, string $entity_id, string $update_method, bool $dry_run, string $wc_current_status ): array {
        if ( $dry_run ) {
            return [ 'preview — would update to "' . $decision['target_status'] . '"', false ];
        }

        if ( $update_method === 'webhook' ) {
            $response = wp_remote_post( site_url( '/wp-json/telegra/webhook' ), [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'timeout' => 30,
                'body'    => json_encode( [
                    'targetEntity' => [
                        'id'                 => $entity_id,
                        'externalIdentifier' => $order->get_id(),
                    ],
                    'eventTitle' => 'status_changed',
                    'eventData'  => [ 'newStatus' => $telegra_status ],
                    'brello'     => [ 'action'    => 'update_status' ],
                ] ),
            ] );

            if ( is_wp_error( $response ) ) {
                return [ 'webhook error: ' . $response->get_error_message(), false ];
            }
            $code = wp_remote_retrieve_response_code( $response );
            $ok   = ( $code >= 200 && $code < 300 );
            return [ $ok ? 'updated (webhook)' : 'webhook error HTTP ' . $code, $ok ];
        }

        // Direct update
        $order->update_status(
            $decision['target_status'],
            sprintf(
                '[AH Sync] "%s" → "%s" (Telegra: %s)',
                $wc_current_status,
                $decision['target_status'],
                $telegra_status
            )
        );
        return [ 'updated (direct)', true ];
    }

    private function error_row( $order, string $entity_id, string $message ): array {
        return [
            'order'   => [
                'id'            => $order->get_id(),
                'link'          => admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() ),
                'status_before' => $order->get_status(),
                'status_after'  => $order->get_status(),
                'date_created'  => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
                'total'         => $order->get_total(),
                'currency'      => $order->get_currency(),
            ],
            'telegra' => [
                'status_raw' => '',
                'status'     => 'N/A',
                'link'       => $entity_id ? 'https://affiliate-admin.telegramd.com/orders/' . $entity_id : '',
            ],
            'sync'    => [
                'action'          => 'error',
                'updated'         => false,
                'update_method'   => '',
                'result'          => 'error: ' . $message,
                'reason'          => $message,
                'pharmacy_result' => null,
            ],
        ];
    }

    private function build_query( array $args, string $mode ): array {
        global $wpdb;

        $joins = "INNER JOIN {$wpdb->prefix}wc_orders_meta om ON om.order_id = o.id";

        $excluded_statuses   = self::EXCLUDED_STATUSES;
        $excluded_statuses_q = implode( ',', array_map( fn($s) => "'" . esc_sql($s) . "'", $excluded_statuses ) );

        $where = [
            "o.type        = 'shop_order'",
            "om.meta_key   = 'telemdnow_entity_id'",
            "om.meta_value IS NOT NULL",
            "o.status NOT IN ({$excluded_statuses_q})",
            "o.status NOT LIKE 'wc-cancel\\_%'",
        ];

        // Date filters
        if ( ! empty( $args['start_date'] ) ) {
            $where[] = $wpdb->prepare( "o.date_created_gmt >= %s", $args['start_date'] );
        }
        if ( ! empty( $args['end_date'] ) ) {
            $where[] = $wpdb->prepare( "o.date_created_gmt <= %s", $args['end_date'] );
        }

        // WC status filter (one or more)
        if ( ! empty( $args['status'] ) ) {
            $statuses = is_array( $args['status'] ) ? $args['status'] : explode( ',', $args['status'] );
            $statuses = array_filter( array_map( 'trim', $statuses ) );
            if ( ! empty( $statuses ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
                $where[]      = $wpdb->prepare( "o.status IN ({$placeholders})", ...$statuses );
            }
        }

        // US States filter
        if ( ! empty( $args['states'] ) ) {
            $states = is_array( $args['states'] ) ? $args['states'] : explode( ',', $args['states'] );
            $states = array_filter( array_map( 'trim', $states ) );
            if ( ! empty( $states ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );
                $where[]      = $wpdb->prepare( "a.state IN ({$placeholders})", ...$states );
                $joins       .= " LEFT JOIN {$wpdb->prefix}wc_order_addresses a ON a.order_id = o.id AND a.address_type = 'billing'";
            }
        }

        // apply_cached mode: only process IDs that preview flagged as needing update
        if ( $mode === 'apply_cached' ) {
            $cached_ids = get_transient( self::TRANSIENT_PREVIEW_IDS ) ?: [];
            if ( ! empty( $cached_ids ) ) {
                $ids_q   = implode( ',', array_map( 'intval', $cached_ids ) );
                $where[] = "o.id IN ({$ids_q})";
            }
        }

        return [ $joins, implode( ' AND ', $where ) ];
    }

    private function build_status_map(): array {
        $action = get_option( 'telemdnow_trigger_action' );
        $map    = json_decode( get_option( 'telegra_woo_status' ), true ) ?: [];
        $map['started'] = 'wc-' . $action;
        return array_map( function( $v ) {
            return str_starts_with( $v, 'wc-' ) ? substr( $v, 3 ) : $v;
        }, $map );
    }

    private function maybe_refresh_token(): void {
        if ( ! empty( $this->telegra_token ) ) return;
        // Reuse bh-tools token helper if available, otherwise get from transient
        $token = get_transient( 'telemdnow_token' );
        if ( $token ) {
            $this->telegra_token = $token;
            return;
        }
        // Fallback: try the bh-tools admin method via a fresh instance
        if ( class_exists( 'Bh_Tools_Admin' ) ) {
            $admin = new Bh_Tools_Admin( 'bh-tools', AH_SYNC_VERSION );
            $this->telegra_token = $admin->telegramd_getToken();
        }
    }

    public function clear_transients(): void {
        delete_transient( self::TRANSIENT_ACTIVE );
        delete_transient( self::TRANSIENT_PREVIEW_IDS );
        delete_transient( self::TRANSIENT_EXCLUDED_IDS );
    }

    private function empty_stats(): array {
        return [
            'total'           => 0,
            'updated'         => 0,
            'pharmacy_ok'     => 0,
            'pharmacy_error'  => 0,
            'cancel_conflict' => 0,
            'in_sync'         => 0,
            'skipped'         => 0,
            'errors'          => 0,
        ];
    }

    private function tally_stats( array &$stats, array $row ): void {
        $stats['total']++;
        $action = $row['sync']['action'];
        $ph     = $row['sync']['pharmacy_result'];

        if ( $action === 'error' )                         $stats['errors']++;
        elseif ( $action === 'cancel_conflict' )           $stats['cancel_conflict']++;
        elseif ( $ph === 'retriggered_ok' )                $stats['pharmacy_ok']++;
        elseif ( $ph === 'retriggered_error' )             $stats['pharmacy_error']++;
        elseif ( $ph === 'would_retrigger' )               $stats['pharmacy_ok']++; // preview
        elseif ( $action === 'skip' )                      $stats['skipped']++;
        elseif ( $action === 'in_sync' )                   $stats['in_sync']++;
        elseif ( $action === 'update' && $row['sync']['updated'] ) $stats['updated']++;
    }
}
