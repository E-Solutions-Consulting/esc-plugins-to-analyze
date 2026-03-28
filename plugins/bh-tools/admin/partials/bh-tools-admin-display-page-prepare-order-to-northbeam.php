<form method="post" id="delay-form" class="filter-form box-rounded">
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-filters.php'; ?>
    <br>
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-dev-mode.php'; ?>
    <hr>
    <br>

    <input type="hidden" name="action" value="process_prepare_order_to_northbeam">
    <?php wp_nonce_field('process_prepare_order_to_northbeam', 'check_payment_subscriptions_nonce'); ?>
    <button type="submit" class="button button-primary">Process</button>
</form>
<script>
    const _action_batch =   'process_prepare_order_to_northbeam_batch';
    const _action_export=   'process_prepare_order_to_northbeam_export_file';
    const DataPreviewer = {
                    previewProcessedData: function(data) {
                        jQuery('#process-progress-container').hide();
                        jQuery('#process-progress-info-container').show();
                        jQuery('#process-progress-info-container').html('<p>Total Orders to Info: <strong>' + data.total + '</strong>.</p><p style="color:green">Preview ' + data.processed + ' of successfully processed data.</p>');
                        
                        let table = `<table class="widefat striped">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th colspan="5">WC Order</th>
                                                <th colspan="8">Tags</th>
                                            </tr>
                                            <tr>
                                                <th>#</th>
                                                <th>ID</th>
                                                <th>customer</th>
                                                <th>time_of_purchase</th>
                                                <th>purchase_total</th>
                                                <th>WC created_via</th>
                                                <th>status</th>
                                                <th>type</th>
                                                <th>created_via</th>
                                                <th>origin</th>
                                                <th>source</th>
                                                <th>type</th>
                                                <th>medium</th>
                                                <th>Created In</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;
                        
                        let rowCount = 1;
                        let class_col_status='';
                        data.rows.forEach(row => {
                            console.log(row);                            
                            let link_order= row.order.link ? `<a target="_blank" href="${row.order.link}">${row.order.id}</a>` : row.order.id;                            
                            class_col_status=' class="' + row.tags.status + '"';

                            table += `<tr>
                                <td>${rowCount}</td>
                                <td>${link_order}</td>
                                <td>${row.order.customer_id} - ${row.order.customer_email}</td>
                                <td>${row.order.time_of_purchase}</td>
                                <td>${row.order.currency} ${row.order.purchase_total}</td>
                                <td>${row.order.wc_order_created_via}</td>
                                <td${class_col_status}>${row.tags.status}</td>
                                <td>${row.tags.order_type}</td>
                                <td>${row.tags.created_via}</td>
                                <td>${row.tags.origin}</td>
                                <td>${row.tags.source}</td>
                                <td>${row.tags.type}</td>
                                <td>${row.tags.medium}</td>
                                <td>${row.tags.created_in}</td>

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
    td{color:#666}
    .error {color: red;}
    .succeeded{color:green;}
    
</style>