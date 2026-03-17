<form method="post" id="delay-form" class="filter-form box-rounded">
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-filters.php'; ?>
    <br>
    <?php require_once plugin_dir_path(__FILE__) . 'common/bh-tools-admin-display-dev-mode.php'; ?>
    <hr>
    <br>

    <input type="hidden" name="action" value="process_export_gender_subscriptions">
    <?php wp_nonce_field('process_export_gender_subscriptions', 'process_export_gender_subscriptions_nonce'); ?>
    <button type="submit" class="button button-primary">Export Gender Subscriptions</button>
</form>
<script>
    const _action_batch =   'process_export_gender_subscriptions_batch';
    const _action_export=   'process_export_gender_file';
    const DataPreviewer = {
                    previewProcessedData: function(data) {
                        jQuery('#process-progress-container').hide();
                        jQuery('#process-progress-info-container').show();
                        jQuery('#process-progress-info-container').html('<p>Total Subscriptions to Info: <strong>' + data.total + '</strong>.</p><p style="color:green">Preview ' + data.processed + ' of successfully processed data.</p>');
                        
                        let table = `<table class="widefat striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>ID</th>
                                                <td>status</td>
                                                <td>date_created</td>
                                                <td>customer_email</td>
                                                <td>gender</td>
                                                <td>first_name</td>
                                                <td>last_name</td>
                                                <td>state</td>
                                                <td>state_name</td>
                                                <td>city</td>
                                            </tr>
                                        </thead>
                                        <tbody>`;
                        
                        let rowCount = 1;
                        data.rows.forEach(row => {
                            let link_order= row.order_link ? `<a target="_blank" href="${row.link}">${row.id}</a>` : row.id;
                            table += `<tr>
                                <td>${rowCount}</td>
                                <td>${link_order}</td>
                                <td>${row.status}</td>
                                <td>${row.date_created}</td>
                                <td>${row.customer_email}</td>
                                <td>${row.gender}</td>
                                <td>${row.first_name}</td>
                                <td>${row.last_name}</td>
                                <td>${row.state}</td>
                                <td>${row.state_name}</td>
                                <td>${row.city}</td>
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