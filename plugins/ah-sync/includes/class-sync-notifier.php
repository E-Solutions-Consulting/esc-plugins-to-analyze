<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AH_Sync_Notifier
 *
 * Sends a Slack notification at the end of a full sync run.
 *
 * Requirements:
 *   - bh_send_slack_notification() must be available (bh-features plugin)
 *   - AH_SYNC_SLACK_WEBHOOK constant must be defined in wp-config.php
 *
 * Disabled by default — only active when explicitly enabled via
 * the --notify flag in WP-CLI (intended for cron use).
 */
class AH_Sync_Notifier {

    /**
     * Whether notifications are enabled for this run.
     */
    private bool $enabled;

    /**
     * Execution context label shown in the message.
     * e.g. 'cron' | 'cli' | 'manual'
     */
    private string $source;

    public function __construct( bool $enabled, string $source = 'cron' ) {
        $this->enabled = $enabled;
        $this->source  = $source;
    }

    /**
     * Send summary notification after a completed run.
     *
     * @param array $stats   Stats array from AH_Telegra_WC_Sync::run_full()
     * @param bool  $dry_run Whether this was a dry run
     */
    public function notify( array $stats, bool $dry_run = false ): void {
        if ( ! $this->enabled ) return;
        if ( ! $this->is_available() ) return;

        $message = $this->build_message( $stats, $dry_run );

        $webhook_url = defined( 'AH_SYNC_SLACK_WEBHOOK' ) ? AH_SYNC_SLACK_WEBHOOK : '';

        bh_send_slack_notification( $message, $webhook_url );
    }

    /**
     * Build the short Slack message.
     *
     * Format:
     *   [AH Sync] 2026-03-31 18:00 | LIVE | cron
     *   Updated: 75 | Pharmacy OK: 3 | Conflicts: 2 | Errors: 1 | In sync: 44 | Skipped: 32
     */
    private function build_message( array $stats, bool $dry_run ): string {
        $mode      = $dry_run ? 'DRY RUN' : 'LIVE';
        $timestamp = current_time( 'Y-m-d H:i' );

        $header = sprintf( '[AH Sync] %s | %s | %s', $timestamp, $mode, $this->source );

        $summary = sprintf(
            'Updated: %d | Pharmacy OK: %d | Conflicts: %d | Errors: %d | In sync: %d | Skipped: %d',
            $stats['updated']         ?? 0,
            $stats['pharmacy_ok']     ?? 0,
            $stats['cancel_conflict'] ?? 0,
            $stats['errors']          ?? 0,
            $stats['in_sync']         ?? 0,
            $stats['skipped']         ?? 0
        );

        return $header . "\n" . $summary;
    }

    /**
     * Check that the function and webhook constant are available.
     */
    private function is_available(): bool {
        if ( ! function_exists( 'bh_send_slack_notification' ) ) {
            // bh-features not active — silently skip
            return false;
        }
        if ( ! defined( 'AH_SYNC_SLACK_WEBHOOK' ) || empty( AH_SYNC_SLACK_WEBHOOK ) ) {
            // Webhook URL not configured in wp-config.php — silently skip
            return false;
        }
        return true;
    }
}
