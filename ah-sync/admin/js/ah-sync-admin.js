/**
 * ah-sync-admin.js
 * Handles the Telegra WC Sync UI:
 *   - Preview batch loop
 *   - Apply (cached or fresh) batch loop
 *   - Error handling + reset
 *   - Live table updates
 */
(function ($) {
    'use strict';

    // ── State ────────────────────────────────────────────────
    const state = {
        active:        false,
        stopRequested: false,
        mode:          null,      // 'preview' | 'apply_cached' | 'apply_fresh'
        offset:        0,
        total:         0,
        processed:     0,
        hasCache:      false,
        formData:      null,
    };

    // ── Counters ─────────────────────────────────────────────
    const counts = { updated:0, preview:0, pharmacyOk:0, pharmacyErr:0, conflict:0, inSync:0, skipped:0, errors:0 };

    // ── DOM refs (populated on ready) ────────────────────────
    let $progressContainer, $progressBar, $progressText, $progressPercent;
    let $stopBtn, $downloadLink, $stopMessage, $errorMessage;
    let $previewActions, $applyActions;
    let $tbody, $tableContainer, $legend, $counters;

    // ─────────────────────────────────────────────────────────
    // Init
    // ─────────────────────────────────────────────────────────
    $(document).ready(function () {

        $progressContainer = $('#ah-sync-progress-container');
        $progressBar       = $('#ah-sync-progress-bar');
        $progressText      = $('#ah-sync-progress-text');
        $progressPercent   = $('#ah-sync-progress-percent');
        $stopBtn           = $('#ah-sync-stop-btn');
        $downloadLink      = $('#ah-sync-download-link');
        $stopMessage       = $('#ah-sync-stop-message');
        $errorMessage      = $('#ah-sync-error-message');
        $previewActions    = $('#ah-sync-preview-actions');
        $applyActions      = $('#ah-sync-apply-actions');
        $tbody             = $('#ah-sync-tbody');
        $tableContainer    = $('#ah-sync-table-container');
        $legend            = $('#ah-sync-legend');
        $counters          = $('#ah-sync-counters');

        // ── Form submit → Preview ──────────────────────────────
        $('#ah-sync-form').on('submit', function (e) {
            e.preventDefault();
            if (state.active) return;
            startRun('preview', $(this).serialize());
        });

        // ── Apply Cached ───────────────────────────────────────
        $('#ah-sync-apply-cached-btn').on('click', function () {
            if (state.active) return;
            startRun('apply_cached', state.formData);
        });

        // ── Apply Fresh ────────────────────────────────────────
        $('#ah-sync-apply-fresh-btn').on('click', function () {
            if (state.active) return;
            startRun('apply_fresh', state.formData);
        });

        // ── Stop ───────────────────────────────────────────────
        $stopBtn.on('click', function () {
            if (!state.active) return;
            state.stopRequested = true;
            $stopBtn.prop('disabled', true).text('Stopping...');
            $progressText.text('Stopping...');
        });

        // ── Start new sync (after error) ───────────────────────
        $('#ah-sync-restart-btn').on('click', function () {
            resetAll();
            ajaxReset();
        });

        // ── Export partial ─────────────────────────────────────
        $('#ah-sync-export-partial-btn').on('click', function () {
            ajaxExport();
        });
    });

    // ─────────────────────────────────────────────────────────
    // Run flow
    // ─────────────────────────────────────────────────────────

    function startRun(mode, formData) {
        state.active        = true;
        state.stopRequested = false;
        state.mode          = mode;
        state.offset        = 0;
        state.total         = 0;
        state.processed     = 0;
        state.formData      = formData;

        // Reset UI
        resetCounts();
        $tbody.empty();
        $tableContainer.hide();
        $legend.hide();
        $counters.hide();
        $previewActions.hide();
        $applyActions.hide();
        $downloadLink.hide().attr('href', '#');
        $stopMessage.hide();
        $errorMessage.hide().text('');

        $progressContainer.show();
        $progressBar.removeAttr('value').removeAttr('max');
        $progressText.text('Starting...');
        $stopBtn.show().prop('disabled', false).text('Stop');

        processBatch();
    }

    function processBatch() {
        if (state.stopRequested) {
            finishStopped();
            return;
        }

        const action = state.mode === 'preview' ? 'ah_sync_telegra_wc_preview' : 'ah_sync_telegra_wc_apply';

        const payload = {
            action:     action,
            nonce:      ahSync.nonce,
            form_data:  state.formData,
            offset:     state.offset,
            total:      state.total,
            apply_mode: state.mode,
        };

        $.ajax({
            url:     ahSync.ajaxurl,
            method:  'POST',
            data:    payload,
            timeout: 120000, // 2 minutes per batch
            success: function (response) {
                if (!response.success) {
                    handleServerError(response.data);
                    return;
                }
                handleBatchSuccess(response.data);
            },
            error: function (xhr, status, error) {
                const msg = status === 'timeout'
                    ? 'Request timed out. The server took too long to respond.'
                    : 'AJAX error: ' + (error || status);
                handleFatalError(msg);
            }
        });
    }

    function handleBatchSuccess(data) {
        // First batch: set total
        if (state.offset === 0) {
            state.total = data.total;
            $progressBar.attr('max', data.total);

            if (data.total === 0) {
                $progressText.text('No orders found for the selected criteria.');
                finishComplete(data);
                return;
            }
        }

        state.processed = data.processed;
        state.offset    = data.next_offset;

        if (data.has_cached !== undefined) {
            state.hasCache = data.has_cached;
        }

        // Update progress bar
        const pct = Math.round((data.processed / data.total) * 100);
        $progressBar.attr('value', data.processed);
        $progressPercent.text(pct + '%');
        $progressText.text('Processing: ' + data.processed + ' of ' + data.total + ' orders (' + pct + '%)');

        // Append rows to live table
        if (data.rows && data.rows.length) {
            appendRows(data.rows);
        }

        if (data.complete) {
            finishComplete(data);
        } else if (state.stopRequested) {
            finishStopped();
        } else {
            setTimeout(processBatch, 300);
        }
    }

    function finishComplete(data) {
        state.active = false;
        $stopBtn.hide();
        $progressText.text('Completed: ' + state.total + ' orders processed.');
        $progressBar.attr('value', state.total);

        if (state.mode === 'preview') {
            // Show apply buttons
            $previewActions.show();
            if (!state.hasCache) {
                $('#ah-sync-apply-cached-btn').prop('disabled', true)
                    .attr('title', 'No changes detected — nothing to apply.');
            }
        }

        // CSV download button if file available
        if (data.file_url) {
            $downloadLink.attr('href', data.file_url).show();
        }
    }

    function finishStopped() {
        state.active = false;
        $stopBtn.hide().prop('disabled', false).text('Stop');
        $stopMessage.show();
        $progressText.text('Stopped at ' + state.processed + ' of ' + state.total + ' orders.');
        // Offer partial CSV export
        $('#ah-sync-export-partial-btn').show();
        $('#ah-sync-restart-btn').show();
    }

    // ─────────────────────────────────────────────────────────
    // Error handling
    // ─────────────────────────────────────────────────────────

    function handleServerError(errorData) {
        state.active = false;
        $stopBtn.hide();

        let msg = 'Server error.';
        if (errorData && errorData.message) msg = errorData.message;
        if (errorData && errorData.code === 'cache_expired') {
            msg += ' Use "Apply Fresh" to re-fetch all orders.';
            $applyActions.show();
        }

        showError(msg);
    }

    function handleFatalError(msg) {
        state.active = false;
        $stopBtn.hide();
        showError(msg);
        // Auto-clear server transient so a new run can start
        ajaxReset();
    }

    function showError(msg) {
        $progressText.text('Error — see details below.');
        $errorMessage.text('⚠ ' + msg).show();
        $('#ah-sync-restart-btn').show();
    }

    // ─────────────────────────────────────────────────────────
    // AJAX helpers
    // ─────────────────────────────────────────────────────────

    function ajaxReset() {
        $.post(ahSync.ajaxurl, {
            action: 'ah_sync_telegra_wc_reset',
            nonce:  ahSync.nonce,
        });
    }

    function ajaxExport() {
        $.post(ahSync.ajaxurl, {
            action: 'ah_sync_telegra_wc_export',
            nonce:  ahSync.nonce,
        }, function (response) {
            if (response.success && response.data.file_url) {
                $downloadLink.attr('href', response.data.file_url).show();
            } else {
                $stopMessage.after('<p style="color:red;">No partial file available.</p>');
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    // Table rendering
    // ─────────────────────────────────────────────────────────

    function appendRows(rows) {
        $tableContainer.show();
        $legend.show();
        $counters.show();

        rows.forEach(function (row) {
            const cls   = rowClass(row);
            updateCount(cls);

            const orderLink = row.order.link
                ? '<a href="' + row.order.link + '" target="_blank">#' + row.order.id + '</a>'
                  + '<br><small style="color:#999">' + row.order.date_created + '</small>'
                : '#' + row.order.id;

            const before = statusBadge(row.order.status_before);
            const after  = row.order.status_after !== row.order.status_before
                ? statusBadge(row.order.status_after)
                : '<span style="color:#aaa">—</span>';

            const telegraCell = row.telegra.link
                ? '<a href="' + row.telegra.link + '" target="_blank">' + row.telegra.status + '</a>'
                  + '<br><small style="color:#999">' + row.telegra.status_raw + '</small>'
                : row.telegra.status;

            const tr = $('<tr>').addClass(cls).html(
                '<td>' + getRowIndex() + '</td>' +
                '<td>' + orderLink + '</td>' +
                '<td>' + before + '</td>' +
                '<td>' + after + '</td>' +
                '<td>' + telegraCell + '</td>' +
                '<td>' + actionBadge(row) + '</td>' +
                '<td><small>' + (row.sync.result || '') + '</small></td>'
            );
            $tbody.append(tr);
        });
    }

    let _rowIndex = 0;
    function getRowIndex() { return ++_rowIndex; }

    function rowClass(row) {
        const action = row.sync.action;
        const ph     = row.sync.pharmacy_result;
        const result = row.sync.result || '';
        if (action === 'error')                              return 'ah-sync-row--error';
        if (action === 'cancel_conflict')                    return 'ah-sync-row--conflict';
        if (ph === 'retriggered_error')                      return 'ah-sync-row--pharmacy-err';
        if (ph === 'retriggered_ok')                         return 'ah-sync-row--pharmacy-ok';
        if (ph === 'would_retrigger')                        return 'ah-sync-row--preview';
        if (result.startsWith('error') || result.startsWith('webhook error')) return 'ah-sync-row--error';
        if (action === 'skip')                               return 'ah-sync-row--skip';
        if (action === 'in_sync')                            return 'ah-sync-row--in-sync';
        if (row.sync.updated)                                return 'ah-sync-row--updated';
        if (result.startsWith('preview'))                    return 'ah-sync-row--preview';
        return '';
    }

    function actionBadge(row) {
        const action = row.sync.action;
        const ph     = row.sync.pharmacy_result;
        const result = row.sync.result || '';
        if (action === 'error' || result.startsWith('error'))     return badge('error', '✖ Error');
        if (action === 'cancel_conflict')                          return badge('conflict', '⚠ Cancel conflict');
        if (ph === 'retriggered_error')                            return badge('pharmacy-err', '💊 Pharmacy error');
        if (ph === 'retriggered_ok')                               return badge('pharmacy-ok', '💊 Pharmacy re-triggered');
        if (ph === 'would_retrigger')                              return badge('preview', '◎ Would re-trigger pharmacy');
        if (action === 'skip')                                     return badge('skip', '⊘ Skipped');
        if (action === 'in_sync')                                  return badge('in-sync', '→ In sync');
        if (row.sync.updated)                                      return badge('updated', '✔ Updated');
        if (result.startsWith('preview'))                          return badge('preview', '◎ Would update');
        return action;
    }

    function badge(type, label) {
        return '<span class="ah-sync-badge ah-sync-badge--' + type + '">' + label + '</span>';
    }

    function statusBadge(status) {
        return '<span class="ah-status-badge ah-status-badge--' + status + '">' + status + '</span>';
    }

    // ─────────────────────────────────────────────────────────
    // Counters
    // ─────────────────────────────────────────────────────────

    function updateCount(cls) {
        const map = {
            'ah-sync-row--updated':      ['updated',     'cnt-updated'],
            'ah-sync-row--preview':      ['preview',     'cnt-preview'],
            'ah-sync-row--pharmacy-ok':  ['pharmacyOk',  'cnt-pharmacy-ok'],
            'ah-sync-row--pharmacy-err': ['pharmacyErr', 'cnt-pharmacy-err'],
            'ah-sync-row--conflict':     ['conflict',    'cnt-conflict'],
            'ah-sync-row--in-sync':      ['inSync',      'cnt-in-sync'],
            'ah-sync-row--skip':         ['skipped',     'cnt-skipped'],
            'ah-sync-row--error':        ['errors',      'cnt-errors'],
        };
        if (map[cls]) {
            counts[map[cls][0]]++;
            $('#' + map[cls][1]).text(counts[map[cls][0]]);
        }
    }

    function resetCounts() {
        _rowIndex = 0;
        Object.keys(counts).forEach(k => counts[k] = 0);
        ['cnt-updated','cnt-preview','cnt-pharmacy-ok','cnt-pharmacy-err',
         'cnt-conflict','cnt-in-sync','cnt-skipped','cnt-errors'].forEach(function(id) {
            $('#' + id).text('0');
        });
    }

    function resetAll() {
        state.active        = false;
        state.stopRequested = false;
        state.offset        = 0;
        state.total         = 0;
        state.processed     = 0;
        resetCounts();
        $tbody.empty();
        $tableContainer.hide();
        $legend.hide();
        $counters.hide();
        $previewActions.hide();
        $applyActions.hide();
        $progressContainer.hide();
        $stopBtn.show().prop('disabled', false).text('Stop');
        $stopMessage.hide();
        $errorMessage.hide().text('');
        $downloadLink.hide().attr('href', '#');
        $('#ah-sync-restart-btn').hide();
        $('#ah-sync-export-partial-btn').hide();
    }

})(jQuery);
