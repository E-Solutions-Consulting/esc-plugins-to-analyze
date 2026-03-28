<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AH_Telegra_WC_Evaluator
 *
 * Pure class — no DB access, no WP globals.
 * Decides what sync action to take for a single order.
 *
 * Actions:
 *   'skip'            — protected status, never touch
 *   'cancel_conflict' — WC is cancel variant but Telegra disagrees → flag for manual review
 *   'in_sync'         — states match (or are equivalent)
 *   'update'          — WC needs to change to match Telegra
 */
class AH_Telegra_WC_Evaluator {

    // WC statuses that are NEVER overwritten regardless of Telegra
    const PROTECTED_STATUSES = [ 'refunded', 'on-hold' ];

    /**
     * evaluate()
     *
     * @param string $wc_status        Current WC status (no "wc-" prefix).
     * @param string $telegra_status   Raw Telegra status key e.g. "completed".
     * @param array  $order_status_map telegra_key → wc_status (no "wc-" prefix).
     *
     * @return array {
     *   action:        string       'skip'|'cancel_conflict'|'in_sync'|'update'
     *   reason:        string       Human-readable explanation
     *   target_status: string|null  WC status to set (only when action=update)
     * }
     */
    public static function evaluate( string $wc_status, string $telegra_status, array $order_status_map ): array {

        // ── RULE 1: Hard-protected statuses ───────────────────
        if ( in_array( $wc_status, self::PROTECTED_STATUSES, true ) ) {
            return [
                'action'        => 'skip',
                'reason'        => 'Protected WC status "' . $wc_status . '" — never synced',
                'target_status' => null,
            ];
        }

        // ── RULE 2: WC cancel variant ─────────────────────────
        // All cancel_* and cancelled are terminal in WC.
        // If Telegra agrees → in_sync.
        // If Telegra disagrees → flag for manual review, never overwrite.
        if ( $wc_status === 'cancelled' || str_starts_with( $wc_status, 'cancel_' ) ) {
            if ( $telegra_status === 'cancelled' ) {
                return [
                    'action'        => 'in_sync',
                    'reason'        => 'Both WC and Telegra are cancelled — in sync',
                    'target_status' => null,
                ];
            }
            return [
                'action'        => 'cancel_conflict',
                'reason'        => 'WC is "' . $wc_status . '" but Telegra is "' . $telegra_status . '" — review manually',
                'target_status' => null,
            ];
        }

        // ── RULE 3: Telegra status not in map → skip ──────────
        if ( ! isset( $order_status_map[ $telegra_status ] ) ) {
            return [
                'action'        => 'skip',
                'reason'        => 'No WC mapping for Telegra status "' . $telegra_status . '"',
                'target_status' => null,
            ];
        }

        $mapped_wc_status = $order_status_map[ $telegra_status ];

        // ── RULE 4: collect_payment ↔ requires_order_processing
        // Same state — pharmacy sub-decision is handled in the runner.
        // Return in_sync here; runner checks order_sent_pharmacy meta.
        if ( $wc_status === 'collect_payment' && $telegra_status === 'requires_order_processing' ) {
            return [
                'action'        => 'in_sync',
                'reason'        => '"collect_payment" = "requires_order_processing" — checking pharmacy',
                'target_status' => null,
            ];
        }

        // ── RULE 5: send_to_telegra ↔ started ─────────────────
        if ( $wc_status === 'send_to_telegra' && $telegra_status === 'started' ) {
            return [
                'action'        => 'in_sync',
                'reason'        => '"send_to_telegra" = "started" — in sync',
                'target_status' => null,
            ];
        }

        // ── RULE 6: Standard map comparison ───────────────────
        if ( $wc_status === $mapped_wc_status ) {
            return [
                'action'        => 'in_sync',
                'reason'        => 'Already in sync ("' . $wc_status . '")',
                'target_status' => null,
            ];
        }

        return [
            'action'        => 'update',
            'reason'        => '"' . $wc_status . '" → "' . $mapped_wc_status . '" (Telegra: ' . $telegra_status . ')',
            'target_status' => $mapped_wc_status,
        ];
    }
}
