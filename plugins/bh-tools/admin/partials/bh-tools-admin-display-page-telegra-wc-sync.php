<form method="post" id="delay-form" class="filter-form box-rounded">
    <?php require_once plugin_dir_path( __FILE__ ) . 'common/bh-tools-admin-display-filters.php'; ?>
    <br>

    <?php /* ── Update Method selector ── */ ?>
    <div style="margin-bottom: 12px;">
        <strong>Update method:</strong>
        <label style="margin-left: 16px;">
            <input type="radio" name="update_method" value="direct" checked>
            Direct <small style="color:#666;">($order->update_status — fires all WC hooks)</small>
        </label>
        <label style="margin-left: 20px;">
            <input type="radio" name="update_method" value="webhook">
            Via Webhook <small style="color:#666;">(POST to /wp-json/telegra/webhook)</small>
        </label>
    </div>

    <?php require_once plugin_dir_path( __FILE__ ) . 'common/bh-tools-admin-display-dev-mode.php'; ?>
    <hr><br>

    <input type="hidden" name="action" value="process_telegra_wc_sync">
    <?php wp_nonce_field( 'process_telegra_wc_sync', 'telegra_wc_sync_nonce' ); ?>
    <button type="submit" class="button button-primary">Run Sync</button>
</form>

<?php /* ── Live results table (appended row by row via JS) ── */ ?>
<div id="telegra-sync-table-container" style="display:none; margin-top: 20px;">
    <h2>Results</h2>
    <table class="widefat striped" id="telegra-sync-results-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Order</th>
                <th>Previous WC Status</th>
                <th>New WC Status</th>
                <th>Telegra Status</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody id="telegra-sync-results-tbody"></tbody>
    </table>
    <p id="telegra-sync-summary" style="margin-top:10px; font-weight:bold;"></p>
</div>

<script>
    /* Action names consumed by the shared progress-bar partial */
    const _action_batch  = 'process_telegra_wc_sync_batch';
    const _action_export = 'process_telegra_wc_sync_export_file';

    /* ── Counters ── */
    let _syncRowIndex   = 0;
    let _syncUpdated    = 0;
    let _syncInSync     = 0;
    let _syncErrors     = 0;

    /* ── Colour helpers ── */
    function syncResultClass( result ) {
        if ( result.startsWith('updated') )  return 'sync-updated';
        if ( result.startsWith('preview') )  return 'sync-preview';
        if ( result.startsWith('error') )    return 'sync-error';
        return 'sync-in-sync';
    }

    /* ── Append rows to the live table ── */
    function appendSyncRows( rows ) {
        if ( ! rows || rows.length === 0 ) return;

        const tbody = document.getElementById('telegra-sync-results-tbody');
        document.getElementById('telegra-sync-table-container').style.display = 'block';

        rows.forEach( function( row ) {
            _syncRowIndex++;

            const resultClass = syncResultClass( row.sync.result );

            if      ( row.sync.result.startsWith('updated') ) _syncUpdated++;
            else if ( row.sync.result.startsWith('error') )   _syncErrors++;
            else if ( row.sync.was_in_sync )                   _syncInSync++;
            else if ( row.sync.result.startsWith('preview') ) _syncUpdated++; // count previews as "would update"

            const orderLink = row.order.link
                ? `<a href="${row.order.link}" target="_blank">#${row.order.id}</a>`
                : `#${row.order.id}`;

            const telegraLink = row.telegra.link
                ? `<a href="${row.telegra.link}" target="_blank">${row.telegra.status}</a>`
                : row.telegra.status;

            const statusBefore = `<span class="status-badge status-${row.order.status_before}">${row.order.status_before}</span>`;
            const statusAfter  = `<span class="status-badge status-${row.order.status_after}">${row.order.status_after}</span>`;

            const tr = document.createElement('tr');
            tr.className = resultClass;
            tr.innerHTML = `
                <td>${_syncRowIndex}</td>
                <td>${orderLink}<br><small>${row.order.date_created}</small></td>
                <td>${statusBefore}</td>
                <td>${statusAfter}</td>
                <td>${telegraLink}</td>
                <td>${row.sync.result}</td>
            `;
            tbody.appendChild( tr );
        } );

        /* Update summary counters */
        document.getElementById('telegra-sync-summary').innerHTML =
            `Updated: <span style="color:green">${_syncUpdated}</span> &nbsp;|&nbsp; ` +
            `Already in sync: <span style="color:#888">${_syncInSync}</span> &nbsp;|&nbsp; ` +
            `Errors: <span style="color:red">${_syncErrors}</span>`;
    }

    jQuery( document ).ready( function ( $ ) {

        /* Reset counters + table on each new run */
        $( '.filter-form' ).on( 'submit', function () {
            _syncRowIndex = 0;
            _syncUpdated  = 0;
            _syncInSync   = 0;
            _syncErrors   = 0;
            $( '#telegra-sync-results-tbody' ).empty();
            $( '#telegra-sync-table-container' ).hide();
            $( '#telegra-sync-summary' ).html( '' );
        } );

        /*
         * Hook into the shared progress-bar's AJAX callback.
         * The progress-bar partial fires jQuery(document).trigger('previewData', [data])
         * when test_mode=true.  For live mode we tap into the raw AJAX success
         * by overriding batchProcessing's success handler via a custom event.
         */
        $( document ).on( 'previewData', function ( event, data ) {
            appendSyncRows( data.rows );
        } );

        /*
         * For live (non-preview) mode, rows are also returned in each batch
         * response.  We intercept them via a custom event dispatched from the
         * patched progress-bar below.
         */
        $( document ).on( 'batchRowsReady', function ( event, data ) {
            appendSyncRows( data.rows );
        } );
    } );
</script>

<script>
    /*
     * Minimal patch so that live-mode batches also fire 'batchRowsReady'.
     * We wrap the native $.post success inside the existing batchProcessing
     * function by re-declaring it after the progress-bar partial loads it.
     *
     * Strategy: store the original and wrap it.
     * This script tag runs AFTER bh-tools-admin-display-progress-bar.php
     * because both are included in telegra_wc_sync_page() in that order.
     */
    jQuery( document ).ready( function ( $ ) {

        /* Wait one tick so the progress-bar's document.ready runs first */
        setTimeout( function () {

            /* After each successful non-preview batch, fire batchRowsReady */
            $( document ).on( 'ajaxSuccess', function ( event, xhr, settings, response ) {

                /* Only intercept our own action */
                if ( typeof settings.data !== 'string' ) return;
                if ( settings.data.indexOf( 'process_telegra_wc_sync_batch' ) === -1 ) return;

                try {
                    const parsed = ( typeof response === 'object' ) ? response : JSON.parse( xhr.responseText );
                    if ( parsed && parsed.success && parsed.data && parsed.data.rows && ! parsed.data.preview ) {
                        $( document ).trigger( 'batchRowsReady', [ parsed.data ] );
                    }
                } catch ( e ) { /* ignore */ }
            } );

        }, 0 );
    } );
</script>

<style>
    /* ── Result row colours ── */
    tr.sync-updated  > td { background-color: #d7f7c2; border-top: 1px solid #a3e07c; }
    tr.sync-preview  > td { background-color: #fff3cd; border-top: 1px solid #ffc107; }
    tr.sync-error    > td { background-color: #ffe6e6; border-top: 1px solid #d63638; }
    tr.sync-in-sync  > td { color: #888; }

    /* ── Status badges (reuse WC colours if available) ── */
    .status-badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        background: #e0e0e0;
        color: #333;
    }
    .status-completed  { background: #c6e1c6; color: #5b841b; }
    .status-cancelled  { background: #e5e5e5; color: #777; }
    .status-on-hold    { background: #f8dda7; color: #94660c; }
    .status-processing { background: #c6cbef; color: #2e4453; }
    .status-pending    { background: #e5e5e5; color: #777; }

    #telegra-sync-summary { font-size: 13px; }
</style>
