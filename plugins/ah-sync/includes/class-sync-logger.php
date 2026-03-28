<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Sync_Logger {

    // Log sources — one per execution context
    const SOURCE_MANUAL = 'ah-sync-manual';
    const SOURCE_CRON   = 'ah-sync-cron';
    const SOURCE_CLI    = 'ah-sync-cli';

    private $source;
    private $logger;

    public function __construct( string $source = self::SOURCE_MANUAL ) {
        $this->source = $source;
    }

    private function get_logger() {
        if ( ! $this->logger ) {
            $this->logger = wc_get_logger();
        }
        return $this->logger;
    }

    public function info( string $message, array $context = [] ) {
        $this->log( 'info', $message, $context );
    }

    public function warning( string $message, array $context = [] ) {
        $this->log( 'warning', $message, $context );
    }

    public function error( string $message, array $context = [] ) {
        $this->log( 'error', $message, $context );
    }

    public function debug( string $message, array $context = [] ) {
        $this->log( 'debug', $message, $context );
    }

    private function log( string $level, string $message, array $context = [] ) {
        $context['source'] = $this->source;
        $this->get_logger()->$level( $message, $context );
    }

    /**
     * Log a sync result row.
     * Called once per order after evaluation.
     */
    public function log_order_result( array $row ) {
        $order   = $row['order'];
        $telegra = $row['telegra'];
        $sync    = $row['sync'];

        $message = sprintf(
            'Order #%d | WC: %s → %s | Telegra: %s | Action: %s | %s',
            $order['id'],
            $order['status_before'],
            $order['status_after'],
            $telegra['status_raw'],
            $sync['action'],
            $sync['result']
        );

        if ( $sync['action'] === 'cancel_conflict' || ! empty( $sync['pharmacy_result'] ) && $sync['pharmacy_result'] === 'retriggered_error' ) {
            $this->warning( $message );
        } elseif ( str_starts_with( $sync['result'] ?? '', 'error' ) || str_starts_with( $sync['result'] ?? '', 'webhook error' ) ) {
            $this->error( $message );
        } elseif ( $sync['updated'] || ! empty( $sync['pharmacy_result'] ) ) {
            $this->info( $message );
        } else {
            $this->debug( $message );
        }
    }

    /**
     * Log a summary at the end of a full sync run.
     */
    public function log_summary( array $stats, bool $dry_run = false ) {
        $mode = $dry_run ? '[DRY RUN] ' : '';
        $this->info( sprintf(
            '%sSummary — Total: %d | Updated: %d | Pharmacy re-triggered: %d | Pharmacy errors: %d | Cancel conflicts: %d | In sync: %d | Skipped: %d | Errors: %d',
            $mode,
            $stats['total']           ?? 0,
            $stats['updated']         ?? 0,
            $stats['pharmacy_ok']     ?? 0,
            $stats['pharmacy_error']  ?? 0,
            $stats['cancel_conflict'] ?? 0,
            $stats['in_sync']         ?? 0,
            $stats['skipped']         ?? 0,
            $stats['errors']          ?? 0
        ) );
    }
}
