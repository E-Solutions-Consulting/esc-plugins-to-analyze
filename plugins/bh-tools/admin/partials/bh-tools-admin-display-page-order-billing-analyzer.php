<form method="post" id="billing-analyzer-form" class="filter-form box-rounded">
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-filters.php'; ?>
    <br>
    
    <div>
        <h3>Billing Analysis Options</h3>
        <div style="display: flex; gap: 20px; margin: 15px 0;">
            <label>
                <input type="checkbox" name="include_telegra_billing" id="include_telegra_billing" checked>
                Include Telegra Billing Data ($18 async / $28 sync)
            </label>
            <label>
                <input type="checkbox" name="include_stripe_details" id="include_stripe_details" checked>
                Include Stripe Payment Details
            </label>
            <label>
                <input type="checkbox" name="include_visit_type" id="include_visit_type" checked>
                Include Visit Type Analysis
            </label>
        </div>
        
        <div style="display: flex; gap: 20px; margin: 15px 0;">
            <label>
                <input type="checkbox" name="only_billing_discrepancies" id="only_billing_discrepancies">
                Only show billing discrepancies
            </label>
            <label>
                <input type="checkbox" name="group_by_state" id="group_by_state">
                Group results by state (for visit type analysis)
            </label>
        </div>
        
        <div style="display: flex; gap: 20px; margin: 15px 0;">
            <label>
                <input type="checkbox" name="save_telegra_jsons" id="save_telegra_jsons" checked>
                Save Telegra JSON responses for posterior analysis
            </label>
            <small style="color: #666; margin-left: 5px;">
                (Saves complete API responses to /wp-content/uploads/bh-exports/telegra-json-responses/)
            </small>
        </div>
    </div>
    
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-dev-mode.php'; ?>
    <hr>
    <br>

    <input type="hidden" name="action" value="process_billing_analyzer">
    <?php wp_nonce_field('process_billing_analyzer', 'billing_analyzer_nonce'); ?>
    <button type="submit" class="button button-primary">Analyze Orders</button>
</form>

<script>
    const _action_batch = 'process_billing_analyzer_batch';
    const _action_export = 'process_billing_analyzer_export_file';
    
    const BillingDataPreviewer = {
        previewProcessedData: function(data) {
            jQuery('#process-progress-container').hide();
            jQuery('#process-progress-info-container').show();
            jQuery('#process-progress-info-container').html('<p>Total Orders Analyzed: <strong>' + data.total + '</strong>.</p><p style="color:green">Preview ' + data.processed + ' orders with billing analysis.</p>');
            
            let table = `<table class="widefat striped">
                            <thead>
                                <tr>
                                    <th rowspan="2">#</th>
                                    <th colspan="4">WC Order</th>
                                    <th colspan="4">Telegra</th>
                                    <th colspan="3">Stripe</th>
                                    <th colspan="3">Billing Analysis</th>
                                </tr>
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>State</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th>Visit ID</th>
                                    <th>Visit Type</th>
                                    <th>Link</th>
                                    <th>Status</th>
                                    <th>Captured</th>
                                    <th>Link</th>
                                    <th>Expected Cost</th>
                                    <th>Actual Cost</th>
                                    <th>Discrepancy</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            let rowCount = 1;
            data.rows.forEach(row => {
                console.log(row);
                
                // Status styling
                let class_row_status = '';
                if (row.billing_analysis.has_discrepancy) {
                    class_row_status = ' class="error"';
                }
                
                let class_telegra_status = row.telegra.status === 'completed' ? ' class="succeeded"' : '';
                let class_stripe_status = row.stripe.status === 'succeeded' ? ' class="succeeded"' : '';
                let class_billing_status = row.billing_analysis.has_discrepancy ? ' class="error"' : ' class="succeeded"';

                // Links
                let link_order = row.order.link ? 
                    `<a target="_blank" href="${row.order.link}">${row.order.id}</a>` : 
                    row.order.id;
                    
                let link_telegra = row.order.telemdnow_entity_id ? 
                    `<a target="_blank" href="https://affiliate-admin.telegramd.com/orders/${row.order.telemdnow_entity_id}">${truncateString(row.order.telemdnow_entity_id)}</a>` : 
                    'N/A';
                    
                let link_stripe = row.stripe.intent_id ? 
                    `<a target="_blank" href="https://dashboard.stripe.com/payments/${row.stripe.intent_id}">${truncateString(row.stripe.intent_id, 12)}</a>` : 
                    'N/A';

                // Visit type display
                let visit_type_display = row.telegra.visit_type || 'Unknown';
                if (row.telegra.visit_type) {
                    visit_type_display += ` (${row.billing_analysis.expected_cost})`;
                }

                // Billing analysis
                let expected_cost = row.billing_analysis.expected_cost || 'N/A';
                let actual_cost = row.billing_analysis.actual_cost || 'N/A';
                let discrepancy_text = row.billing_analysis.has_discrepancy ? 'YES' : 'NO';

                table += `<tr${class_row_status}>
                    <td>${rowCount}</td>
                    <td>${link_order}</td>
                    <td>${row.order.total} ${row.order.currency}</td>
                    <td>${row.order.shipping_state || 'N/A'}</td>
                    <td>${row.order.date_created}</td>
                    <td><span${class_telegra_status}>${row.telegra.status}</span></td>
                    <td>${row.telegra.visit_id || 'N/A'}</td>
                    <td>${visit_type_display}</td>
                    <td>${link_telegra}</td>
                    <td><span${class_stripe_status}>${row.stripe.status}</span></td>
                    <td>${row.stripe.captured ? 'Yes' : 'No'}</td>
                    <td>${link_stripe}</td>
                    <td>${expected_cost}</td>
                    <td>${actual_cost}</td>
                    <td><span${class_billing_status}>${discrepancy_text}</span></td>
                </tr>`;
                rowCount++;
            });
            
            table += '</tbody></table>';
            jQuery('#process-progress-info-container').append(table);
            
            // Add summary statistics
            if (data.summary) {
                let summary = `<div class="billing-summary" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <h3>Billing Analysis Summary</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div><strong>Total Orders:</strong> ${data.summary.total_orders}</div>
                        <div><strong>Async Visits ($18):</strong> ${data.summary.async_count}</div>
                        <div><strong>Sync Visits ($28):</strong> ${data.summary.sync_count}</div>
                        <div><strong>Expected Revenue:</strong> $${data.summary.expected_revenue}</div>
                        <div><strong>Discrepancies:</strong> ${data.summary.discrepancies_count}</div>
                        <div><strong>Unknown Visits:</strong> ${data.summary.unknown_visits}</div>
                    </div>
                </div>`;
                jQuery('#process-progress-info-container').append(summary);
            }
        }
    };

    function truncateString(value, prefixLen = 6, suffixLen = 5) {
        if (typeof value !== 'string') return value;
        if (value.length <= (prefixLen + suffixLen)) {
            return value;
        }
        return value.slice(0, prefixLen) + '...' + value.slice(-suffixLen);
    }

    jQuery(document).ready(function($) {
        jQuery(document).on('previewData', function(event, data) {
            BillingDataPreviewer.previewProcessedData(data);
        });
    });
</script>

<style>
    tr.error > td {
        border-top: 1px solid red;
        background-color: #ffe6e6;
    }
    .succeeded { color: green; }
    .widefat td.succeeded {
        color: #228403;
        background: rgb(215, 247, 194);
    }
    .billing-summary {
        font-size: 14px;
    }
    .billing-summary h3 {
        margin-top: 0;
        color: #333;
    }
</style>