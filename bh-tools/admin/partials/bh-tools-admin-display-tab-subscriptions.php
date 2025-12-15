<form method="post" id="export-form" class="box-form">
    <label for="states"><?php esc_html_e('US States', 'your-textdomain'); ?>
        <select id="states" name="states[]" multiple="multiple" class="wc-enhanced-select" style="width: 100%">
            <option value="">All</option>
            <?php foreach ($states as $code => $name) : ?>
                <option data-state="<?php echo esc_attr($code); ?>" value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="input-filters">
        Date
        <label>
            <input type="radio" id="date_created" name="filter_date"value="date_created" checked>
            Created
        </label>
        <label>
            <input type="radio" id="date_next_payment" name="filter_date"value="date_next_payment">
            Next Payment
        </label>

    </div>
    <div class="input-filters">
        <div>
            <label>Start date:</label> <input type="date" name="start_date">
        </div>
        <div>
            <label>End date:</label> <input type="date" name="end_date">
        </div>
        <div>
            <label>Batch size:</label>
            <select name="batch_size">
                <option value="100" selected>100 records</option>
            </select>					
        </div>
        <div>
            <input type="submit" name="start_export" class="button button-primary" value="Start Export" <?php echo $active_process ? 'disabled' : ''; ?>>
        </div>
        <?php if($active_process) :	?>
        <div>
            <button id="stop-current-export" class="button button-secondary" style="margin-right:10px;">Stop Current Export</button>
        </div>
        <?php endif; ?>
    </div>

    
    <div class="input-filters">
        Type:
        <label>
            <input type="radio" id="type" name="filter_type"value="" checked>
            Both
        </label>
        <label>
            <input type="radio" id="type" name="filter_type"value="initial">
            Initial
        </label>
        <label>
            <input type="radio" id="type" name="filter_type"value="renewal">
            Renewal
        </label>
    </div>

    <div class="input-filters">
        <label class="cont-checkbox">
            <input type="checkbox" id="include-amount-paid" name="include-amount-paid" value="yes">
            <span>Include Amount Paid</span>
        </label>
    </div>
</form>


<div id="progress-container" class="box-rounded" style="display:none;">
    <h2>Export Progress</h2>
    <div id="progress-info">
        <div id="progress-percent"></div>
        <div id="progress-text"></div>
    </div>
    <progress id="new-progress-bar" class="progress-bar" style="width:100%"></progress>	
    <button id="stop-export" class="button button-secondary" style="display:none; margin-right:10px;">Stop Export</button>
    <a id="download-link" href="#" style="display:none;" class="button button-primary">Download CSV File</a>
    <p id="stop-message" style="color:#d63638; display:none;">Export stopped. You can download the data processed so far.</p>
</div>
<div id="info-container" class="box-rounded" style="display:none;">
    <p style="color:red">No records found for the selected criteria.</p>
</div>

<script>
    var exportProcess = createExportProcess();
    var progress;

    function createExportProcess() {
        return {
            active: false,
            stopRequested: false,
            currentOffset: 0,
            currentTotal: 0,
            currentPercent: 0,
            currentProcessed: 0,
            file_url: ''
        };
    }
    jQuery(document).ready(function($) {
        progress = document.getElementById("new-progress-bar");

        $('#export-form').on('submit', function(e) {
            e.preventDefault();
            if (exportProcess.active) return;
            
            exportProcess.active = true;
            exportProcess.stopRequested = false;
            $('#info-container').hide();
            $('#progress-container').show();
            $('#progress').css('width', 0);
            $('#progress-text').html('Preparing for export...');
            $('#new-progress-bar').removeAttr('value');
            $('#new-progress-bar').removeAttr('max');
            $('#stop-export').show();
            $('#stop-message').hide();
            
            const formData = $(this).serialize();
            exportProcess.currentOffset = 0;
            processBatch(formData, 0);
        });
        
        $('#stop-export').on('click', function() {
            console.log('Stop Button clicked', exportProcess);
            if (exportProcess.active) {
                exportProcess.stopRequested = true;
                $('#stop-export').prop('disabled', true).text('Stopping...');
                $('#progress-text').html('Stopping process...');
            }
        });
        $('#stop-current-export').on('click', function() {
            console.log('Stop Button clicked', exportProcess);
            $('#stop-current-export').prop('disabled', true).text('Stopping...');
            finishExport(true, true);
            return ;
        });
        
        function processBatch(formData, offset) {
            if (exportProcess.stopRequested) {
                finishExport(true);
                return;
            }
            var _action	=	'process_export_subscriptions_batch';
            let checkbox = document.getElementById('include-amount-paid');
            console.log('checkbox.checked', checkbox.checked);
            if (checkbox.checked) {							
                _action	=	'pprocess_subscriptions_batch';
            }
            $.post(ajaxurl, {
                action: _action,
                form_data: formData,
                offset: offset,
                total: exportProcess.currentTotal
            }, function(response) {
                console.log('process batch response', response);
                if (response.success) {
                    if(response.data.total==0){
                        progress.max = 0;
                        progress.value = 0;
                        $('#progress-container').hide();
                        $('#info-container').show();
                        finishExport(true);
                        return;
                    }
                    if (offset === 0) {
                        exportProcess.currentTotal = response.data.total;
                        progress.max = parseFloat(response.data.total);
                    }
                                                
                    const percent = Math.round((response.data.processed / response.data.total) * 100);
                    exportProcess.currentPercent = percent;
                    exportProcess.currentProcessed = response.data.processed;

                    $('#progress').css('width', percent + '%');
                    progress.value = parseFloat(response.data.processed);

                    $('#progress-text').html(`Processing(${percent}%): ${response.data.processed} of ${response.data.total} records`);

                    exportProcess.currentOffset = response.data.next_offset;
                    
                    if (response.data.complete) {
                        exportProcess.file_url	=	response.data.file_url;
                        finishExport(false);
                    } else{
                        if (!exportProcess.stopRequested) {
                            setTimeout(function() {
                                processBatch(formData, response.data.next_offset);
                            }, 500);
                        }else{
                            finishExport(true);
                        }
                    } 
                } else {
                    $('#progress-text').html('Error: ' + response.data);
                    finishExport(true);
                }
            }, 'json').fail(function() {
                $('#progress-text').html('Error in AJAX request');
                finishExport(true);
            });
        }
        
        function finishExport(stopped, stopCurrentExport=false) {console.log('finishExport', stopped);
            exportProcess.active = false;
            $('#stop-export').hide().prop('disabled', false).text('Stop Export');
            
            if (stopped) {
                $('#stop-message').show();
                if(stopCurrentExport){
                    $('#progress-container').show();
                    progress.max = 100;								
                    progress.value = 100;
                }

                var _action	=	'check_export_file';
                let checkbox = document.getElementById('include-amount-paid');
                console.log('checkbox.checked', checkbox.checked);
                if (checkbox.checked) {								
                    _action	=	'ccheck_export_file';
                }
                $.post(ajaxurl, {
                    action: _action
                }, function(response) {
                    console.log('finish export', response)
                    if (response.success && response.data.file_url) {
                        $('#download-link').attr('href', response.data.file_url).show();
                        $('#progress-text').html(`Stopped Process(${exportProcess.currentPercent}%): ${exportProcess.currentProcessed} of ${exportProcess.currentTotal} records`);
                        $('#stop-current-export').hide();
                    }
                });
            } else {
                $('#download-link').attr('href', exportProcess.file_url).show();
                $('#progress-text').html(`Process Completed: ${exportProcess.currentTotal} records`);
            }		
            
            $('input[name="start_export"]').prop('disabled', false);
        }
    });
</script>