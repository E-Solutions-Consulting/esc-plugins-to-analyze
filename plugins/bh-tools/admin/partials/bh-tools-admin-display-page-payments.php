<form method="post" id="delay-form" class="filter-form box-rounded">
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-filters.php'; ?>
    <br>
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-dev-mode.php'; ?>
    <hr>
    <br>

    <input type="hidden" name="action" value="process_check_payment_subscriptions">
    <?php wp_nonce_field('process_check_payment_subscriptions', 'check_payment_subscriptions_nonce'); ?>
    <button type="submit" class="button button-primary">Check Payment Subscriptions</button>
</form>
<script>
    const _action_batch =   'process_check_payment_subscriptions_batch';
    const _action_export=   'process_check_payment_subscriptions_export_file';
    const DataPreviewer = {
                    previewProcessedData: function(data) {
                        jQuery('#process-progress-container').hide();
                        jQuery('#process-progress-info-container').show();
                        jQuery('#process-progress-info-container').html('<p>Total Subscriptions to Info: <strong>' + data.total + '</strong>.</p><p style="color:green">Preview ' + data.processed + ' of successfully processed data.</p>');
                        
                        let table = `<table class="widefat striped">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th colspan="3">Order</th>
                                                <th colspan="2">Telegra</th>
                                                <th colspan="2">Stripe</th>
                                            </tr>
                                            <tr>
                                                <th>#</th>
                                                <th>ID</th>
                                                <th>Amount</th>
                                                <th>Created</th>
                                                <th>Status</th>
                                                <th>Link</th>
                                                <th>Status</th>
                                                <th>Link</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;
                        
                        let rowCount = 1;
                        data.rows.forEach(row => {
                            // table += `<tr>
                            //     <td>${rowCount}</td>
                            //     <td>${row.order_date_created} - ${row.order_id} - ${row.order_status}</td>
                            //     <td><a target="_blank" href="https://affiliate-admin.telegramd.com/orders/${row.telegra_entity_id}">${row.telegra_status} - ${row.telegra_entity_id}</a></td>
                            //     <td><a target="_blank" href="https://dashboard.stripe.com/payments/${row.intent_id}">${row.stripe_status}</a></td>
                            //     <td>${row.total}</td>
                            //     <td>${row.currency}</td>
                            // </tr>`;
                            let link_order= row.order_link ? `<a target="_blank" href="${row.order_link}">${row.order_id}</a>` : row.order_id;
                            let link_telegra = row.telegra_entity_id ? `<a target="_blank" href="https://affiliate-admin.telegramd.com/orders/${row.telegra_entity_id}">${row.telegra_entity_id}</a>` : '';
                            let link_stripe = row.intent_id ? `<a target="_blank" href="https://dashboard.stripe.com/payments/${row.intent_id}">${row.intent_id}</a>` : '';
                            table += `<tr>
                                <td>${rowCount}</td>
                                <td>${link_order}</td>
                                <td>${row.total} ${row.currency}</td>
                                <td>${row.order_date_created}</td>
                                <td>${row.telegra_status}</td>
                                <td>${link_telegra}</td>
                                <td>${row.stripe_status}</td>
                                <td>${link_stripe}</td>
                            </tr>`;
                            rowCount++;
                        });
                        
                        table += '</tbody></table>';
                        jQuery('#process-progress-info-container').append(table);
                    }
        };
    
    jQuery(document).ready(function($) {
        jQuery(document).on('previewData', function(event, data) {
            DataPreviewer.previewProcessedData(data);
        });
        
    });
</script>