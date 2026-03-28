<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1>Telegra WC Sync</h1>
    <p class="description">Synchronizes WooCommerce order statuses with Telegra. Telegra is always the source of truth.</p>

    <!-- ── Filter Form ─────────────────────────────────────── -->
    <form id="ah-sync-form" class="ah-sync-form box-rounded">

        <div class="ah-sync-filters ah-sync-field--enhanced">
            <!-- States -->
            <?php if ( $states ) : ?>
            <div class="ah-sync-field ah-sync-field--wide">
                <label>US States <small>(empty = all)</small></label>
                <select name="states[]" multiple class="wc-enhanced-select" style="width:100%">
                    <option value="">All states</option>
                    <?php foreach ( $states as $code => $name ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="ah-sync-filters">

            <!-- Date range -->
            <div class="ah-sync-field">
                <label>Start date</label>
                <input type="date" name="start_date">
            </div>
            <div class="ah-sync-field">
                <label>End date</label>
                <input type="date" name="end_date">
            </div>


            <!-- WC Status (multi-select) -->
            <div class="ah-sync-field ah-sync-field--wide">
                <label>WC Status <small>(empty = all )</small></label>
                <select name="status[]" multiple class="wc-enhanced-select" style="width:100%">
                    <?php
                    $excluded_from_filter = [ 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-on-hold', 'wc-draft', 'wc-failed', 'wc-pending' ];
                    foreach ( wc_get_order_statuses() as $value => $label ) :
                        if ( in_array( $value, $excluded_from_filter, true ) ) continue;
                    ?>
                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Exclude renewals -->
            <div class="ah-sync-field">
                <label>&nbsp;</label>
                <label>
                    <input type="checkbox" name="exclude_sync" value="1">
                    Exclude syncronized
                </label>
                <label>
                    <input type="checkbox" name="exclude_renewals" value="1">
                    Exclude renewal orders
                </label>
            </div>

        </div><!-- .ah-sync-filters -->

        <!-- Advanced options -->
        <div class="ah-sync-advanced-toggle">
            <a href="#" id="ah-sync-toggle-advanced">⚙ Advanced options ▼</a>
        </div>
        <div id="ah-sync-advanced" style="display:none; padding:10px 0;">
            <div class="ah-sync-filters">

                <div class="ah-sync-field">
                    <label>Batch size</label>
                    <select name="batch_size">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                    </select>
                </div>

                <div class="ah-sync-field">
                    <label>Update method</label>
                    <label><input type="radio" name="update_method" value="direct" checked> Direct</label>
                    <label><input type="radio" name="update_method" value="webhook"> Webhook</label>
                </div>

            </div>
        </div>

        <div style="margin-top:12px;">
            <button type="submit" class="button button-primary">Preview Changes</button>
        </div>

        <?php wp_nonce_field( 'ah_sync_nonce', 'nonce' ); ?>
    </form>

    <!-- ── Progress ────────────────────────────────────────── -->
    <div id="ah-sync-progress-container" style="display:none; margin-top:20px;" class="box-rounded">
        <h3 id="ah-sync-progress-percent" style="margin:0 0 6px;"></h3>
        <progress id="ah-sync-progress-bar" style="width:100%; height:20px;"></progress>
        <p id="ah-sync-progress-text" style="margin:6px 0 10px;"></p>

        <button id="ah-sync-stop-btn" class="button button-secondary" style="display:none;">Stop</button>
        <button id="ah-sync-export-partial-btn" class="button" style="display:none;">Export partial CSV</button>
        <button id="ah-sync-restart-btn" class="button button-secondary" style="display:none; margin-left:8px;">↺ Start new sync</button>
        <a id="ah-sync-download-link" href="#" class="button button-primary" style="display:none; margin-left:8px;">Download CSV</a>

        <p id="ah-sync-stop-message" style="color:#d63638; display:none;">Process stopped. You can download the data processed so far.</p>
        <p id="ah-sync-error-message" style="color:#d63638; font-weight:600; display:none;"></p>
    </div>

    <!-- ── Post-preview actions ────────────────────────────── -->
    <div id="ah-sync-preview-actions" style="display:none; margin-top:12px; padding:12px; background:#f0f6fc; border:1px solid #c3d9f0; border-radius:4px;">
        <strong>Preview complete.</strong>
        Select how to apply changes:
        <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <button id="ah-sync-apply-cached-btn" class="button button-primary">
                ✔ Apply cached <small>(only orders flagged in preview)</small>
            </button>
            <button id="ah-sync-apply-fresh-btn" class="button button-secondary">
                ↺ Apply fresh <small>(re-fetch all from Telegra)</small>
            </button>
            <span style="color:#888; font-size:12px;">Cache valid for 30 minutes from preview.</span>
        </div>
    </div>

    <!-- Apply actions (shown after cache expired error) -->
    <div id="ah-sync-apply-actions" style="display:none; margin-top:12px;">
        <button id="ah-sync-apply-fresh-btn" class="button button-primary">↺ Apply Fresh (re-fetch)</button>
    </div>

    <!-- ── Legend ──────────────────────────────────────────── -->
    <div id="ah-sync-legend" style="display:none; margin-top:16px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
        <span class="ah-sync-badge ah-sync-badge--updated">✔ Updated</span>
        <span class="ah-sync-badge ah-sync-badge--preview">◎ Would update</span>
        <span class="ah-sync-badge ah-sync-badge--pharmacy-ok">💊 Pharmacy re-triggered</span>
        <span class="ah-sync-badge ah-sync-badge--pharmacy-err">💊 Pharmacy error</span>
        <span class="ah-sync-badge ah-sync-badge--conflict">⚠ Cancel conflict</span>
        <span class="ah-sync-badge ah-sync-badge--in-sync">→ In sync</span>
        <span class="ah-sync-badge ah-sync-badge--skip">⊘ Skipped</span>
        <span class="ah-sync-badge ah-sync-badge--error">✖ Error</span>
    </div>

    <!-- ── Counters ─────────────────────────────────────────── -->
    <div id="ah-sync-counters" style="display:none; margin-top:8px; font-size:13px; padding:8px 0; border-bottom:1px solid #ddd;">
        Updated: <strong id="cnt-updated">0</strong> &nbsp;|&nbsp;
        Would update: <strong id="cnt-preview">0</strong> &nbsp;|&nbsp;
        Pharmacy OK: <strong id="cnt-pharmacy-ok">0</strong> &nbsp;|&nbsp;
        Pharmacy error: <strong id="cnt-pharmacy-err" style="color:#d63638;">0</strong> &nbsp;|&nbsp;
        <span style="color:#856404;">Cancel conflicts: <strong id="cnt-conflict">0</strong></span> &nbsp;|&nbsp;
        In sync: <strong id="cnt-in-sync">0</strong> &nbsp;|&nbsp;
        Skipped: <strong id="cnt-skipped">0</strong> &nbsp;|&nbsp;
        Errors: <strong id="cnt-errors" style="color:#d63638;">0</strong>
    </div>

    <!-- ── Results table ────────────────────────────────────── -->
    <div id="ah-sync-table-container" style="display:none; margin-top:16px; overflow-x:auto;">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:30px;">#</th>
                    <th>Order</th>
                    <th>WC Before</th>
                    <th>WC After</th>
                    <th>Telegra</th>
                    <th>Action</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody id="ah-sync-tbody"></tbody>
        </table>
    </div>

</div><!-- .wrap -->

<script>
document.getElementById('ah-sync-toggle-advanced').addEventListener('click', function(e) {
    e.preventDefault();
    const sec = document.getElementById('ah-sync-advanced');
    const open = sec.style.display !== 'none';
    sec.style.display = open ? 'none' : 'block';
    this.innerHTML = open ? '⚙ Advanced options ▼' : '⚙ Advanced options ▲';
});

// Init Select2 for multi-selects
jQuery(document).ready(function($) {
    $('.wc-enhanced-select').select2({ width: '100%' });
});
</script>

<style>
    .ah-sync-form { background:#fff; padding:20px; border:1px solid #ddd; border-radius:4px; margin-top:12px; }
    .ah-sync-filters { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start; }
    .ah-sync-field { display:flex; flex-direction:column; gap:4px; min-width:160px; }
    .ah-sync-field--wide { min-width:320px; }
    /*.ah-sync-field label { font-weight:600; font-size:12px; }*/
    .ah-sync-field small { font-weight:400; color:#888; }
    .ah-sync-advanced-toggle { margin-top:12px; }

    /* Row colours */
    tr.ah-sync-row--updated      > td { background:#d7f7c2; }
    tr.ah-sync-row--preview      > td { background:#fff3cd; }
    tr.ah-sync-row--pharmacy-ok  > td { background:#d1ecf1; }
    tr.ah-sync-row--pharmacy-err > td { background:#ffe6e6; }
    tr.ah-sync-row--conflict     > td { background:#fff0c2; border-left:3px solid #ffa500; }
    tr.ah-sync-row--in-sync      > td { color:#888; background:#f9f9f9; }
    tr.ah-sync-row--skip         > td { color:#bbb; font-style:italic; }
    tr.ah-sync-row--error        > td { background:#ffe6e6; }

    /* Action badges */
    .ah-sync-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; }
    .ah-sync-badge--updated      { background:#c6e1c6; color:#2e6b00; }
    .ah-sync-badge--preview      { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
    .ah-sync-badge--pharmacy-ok  { background:#bee5eb; color:#0c5460; }
    .ah-sync-badge--pharmacy-err { background:#f8d7da; color:#721c24; }
    .ah-sync-badge--conflict     { background:#fff0c2; color:#7a5a00; border:1px solid #ffa500; }
    .ah-sync-badge--in-sync      { background:#e5e5e5; color:#555; }
    .ah-sync-badge--skip         { background:#f0f0f0; color:#999; }
    .ah-sync-badge--error        { background:#ffe6e6; color:#d63638; }

    /* WC status badges */
    .ah-status-badge { display:inline-block; padding:2px 7px; border-radius:3px; font-size:11px; font-weight:600; background:#e0e0e0; color:#333; }
    .ah-status-badge--completed       { background:#c6e1c6; color:#5b841b; }
    .ah-status-badge--cancelled       { background:#e5e5e5; color:#777; }
    .ah-status-badge--refunded        { background:#f8dda7; color:#94660c; }
    .ah-status-badge--on-hold         { background:#f8dda7; color:#94660c; }
    .ah-status-badge--processing      { background:#c6cbef; color:#2e4453; }
    .ah-status-badge--provider_review { background:#d1ecf1; color:#0c5460; }
    .ah-status-badge--collect_payment { background:#cce5ff; color:#004085; }
    .ah-status-badge--error_review    { background:#f8d7da; color:#721c24; }
    .ah-status-badge--waiting_room    { background:#e2e3e5; color:#383d41; }
    .ah-status-badge--send_to_telegra { background:#d4edda; color:#155724; }
    .ah-status-badge--admin_review    { background:#fce8b2; color:#7a5a00; }
    .ah-status-badge--prerequisites   { background:#e2d9f3; color:#4a235a; }
</style>
