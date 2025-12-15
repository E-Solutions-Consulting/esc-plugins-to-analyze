<form method="post" id="delay-form" class="filter-form box-rounded">
    <div>
        <a href="javascript:void(0);" id="toggleAdvanced" class="toggle-btn">
            ⚙️ Advanced Options ▼
        </a>
    </div>
    <div id="advancedFilterSection" style="display:none;">
        <div class="input-filters">
            <div>
                <label>Batch size:</label>
                <select name="batch_size">
                    <option value="10"selected>10 records</option>
                    <option value="100">100 records</option>
                    <option value="250">250 records</option>
                    <option value="500">500 records</option>
                    <option value="1000">1,000 records</option>
                    <option value="5000" >5,000 records</option>
                    <option value="10000">10,000 records</option>
                </select>					
            </div>
            <div style="justify-content:space-around;">
                <label>Test Mode:</label>
                <label>
                    <input type="checkbox" name="test_mode">Preview simulate changes
                </label>
            </div>
        </div>
    </div>
    <br>
    <hr>
    <br>

    <input type="hidden" name="action" value="process_delayed_subscriptions">
    <?php wp_nonce_field('process_delayed_subscriptions', 'delayed_subscriptions_nonce'); ?>
    <button type="submit" class="button button-primary">Process Delayed Subscriptions</button>
</form>
<script>
    const _action_batch =   'process_subscriptions_delayed_batch';
    const _action_export=   'process_subscriptions_delayed_export_file';
    const DataPreviewer = {
                    previewProcessedData: function(data) {
                        jQuery('#process-progress-container').hide();
                        jQuery('#process-progress-info-container').show();
                        jQuery('#process-progress-info-container').html('<p>Total Subscriptions to Info: <strong>' + data.total + '</strong>.</p><p style="color:green">Preview ' + data.processed + ' of successfully processed data.</p>');
                        
                        let table = `<table class="widefat striped">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Subscriptions</th>
                                                <th colspan="4">Last Order Completed</th>
                                                <th>Action</th>
                                                <th>Tracking Number</th>
                                            </tr>
                                            <tr>
                                                <th>#</th>
                                                <th>Subscription #</th>                                                
                                                <th>Order ID</th>
                                                <th>Order Created</th>
                                                <th>Order Modified</th>
                                                <th>Order Days</th>
                                                <th>Action</th>
                                                <th>Code</th>s
                                            </tr>
                                        </thead>
                                        <tbody>`;
                        
                        let rowCount = 1;
                        data.rows.forEach(row => {
                            table += `<tr>
                                <td>${rowCount}</td>
                                <td>${row.id}</td>                                
                                <td>${row.last_order_id}</td>
                                <td>${row.last_order_date_created}</td>
                                <td>${row.last_order_date_modified}</td>
                                <td>${row.last_order_days_passed}</td>
                                <td>${row.action}</td>
                                <td>${row.tracking_number}</td>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleAdvanced');
    const advancedSection = document.getElementById('advancedFilterSection');
    let isAdvancedVisible = false;
    toggleBtn.addEventListener('click', function() {
        isAdvancedVisible = !isAdvancedVisible;
        
        if (isAdvancedVisible) {
        advancedSection.style.display = 'flex';
        toggleBtn.innerHTML = '⚙️ Advanced Options ▲';
        } else {
        advancedSection.style.display = 'none';
        toggleBtn.innerHTML = '⚙️ Advanced Options ▼';
        }
    });
    });
</script>