<form method="post" id="delay-form" class="filter-form box-rounded">
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-filters.php'; ?>
    <br>
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-dev-mode.php'; ?>
    <hr>
    <br>

    <input type="hidden" name="action" value="process_check_payment_subscriptions">
    <?php wp_nonce_field('process_check_payment_subscriptions', 'check_payment_subscriptions_nonce'); ?>
    <button type="submit" class="button button-primary">Send Notifications</button>
</form>
<script>
    const _action_batch =   'process_send_notifications_to_complete_subscription_batch';
    const _action_export=   'process_send_notifications_to_complete_subscription_export_file';
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
                                                <th colspan="3">Telegra</th>
                                                <th colspan="2">Stripe</th>
                                            </tr>
                                            <tr>
                                                <th>#</th>
                                                <th>ID</th>
                                                <th>Amount</th>
                                                <th>Created</th>
                                                <th>Status</th>
                                                <th>Link</th>
                                                <th>Reminder Creation Date</th>
                                                <th>Status</th>
                                                <th>Link</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;
                        
                        let rowCount = 1;
                        data.rows.forEach(row => {
                            console.log(row);
                            let class_row_status='';
                            if(row.order.status==='on-hold' && row.telegra.status=='completed' ){
                                class_row_status=' class="error"';
                            }
                            let class_telegra_status='';
                            if(row.telegra.status==='completed'){
                                class_telegra_status=' class="succeeded"';
                            }
                            let class_stripe_status='';
                            if(row.stripe.status==='succeeded'){
                                class_stripe_status=' class="succeeded"';
                            }
                            let link_order= row.order.link ? `<a target="_blank" href="${row.order.link}">${row.order.id} - ${row.order.status}</a>` : row.order.id + ' - ' + row.order.id;
                            let link_telegra = row.order.telemdnow_entity_id ? `<a target="_blank" href="https://affiliate-admin.telegramd.com/orders/${row.order.telemdnow_entity_id}">${row.order.telemdnow_entity_id}</a>` : '';
                            let link_stripe = row.stripe.intent_id ? `<a target="_blank" href="https://dashboard.stripe.com/payments/${row.stripe.intent_id}">${row.stripe.intent_id}</a>` : '';
                            table += `<tr${class_row_status}>
                                <td>${rowCount}</td>
                                <td>${link_order}</td>
                                <td>${row.order.total} ${row.order.currency}</td>
                                <td>${row.order.date_created}</td>
                                <td><span${class_telegra_status}>${row.telegra.status}<span></td>
                                <td>${link_telegra}</td>
                                <td>${row.telegra.reminderCreationDate}</td>
                                <td><span${class_telegra_status}>${row.stripe.status}</span></td>
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
<style>
    tr.error > td {
        border-top: 1px solid red;
        /*border-bottom: 1px solid red;*/
        background-color: #ffe6e6;
    }
    .succeeded{color:green;}
    .widefat td.succeeded {
        color: #228403;
        background: rgb(215, 247, 194);
    }
</style>