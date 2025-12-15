<form method="post" id="process-form" class="box-form">
    <label for="rs_states"><?php esc_html_e('US States', 'your-textdomain'); ?>
        <select id="rs_states" name="states[]" multiple="multiple" class="wc-enhanced-select" style="width: 100%">
            <option value="">All</option>
            <?php foreach ($states as $code => $name) : ?>
                <option data-state="<?php echo esc_attr($code); ?>" value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="input-filters">
        <div>
            <label>Start date:</label> <input type="date" name="start_date">
        </div>
        <div>
            <label>End date:</label> <input type="date" name="end_date">
        </div>
        <div>
            <label>Extend next payment by:</label>
            <div style="display: flex; gap: 10px;">
                <select name="extend_next_payment_interval" required>
                    <option value="day">Day(s)</option>
                    <option selected value="week">Week(s)</option>
                    <option value="month">Month(s)</option>
                </select>
                <input type="number" name="extend_next_payment_value" value="1" min="1" style="width: 80px;" required>
            </div>
        </div>
        <div>        
            <label>Process type:</label>
            <div style="display: flex; gap: 10px;">
                <label><input type="radio" name="process_type" value="dryrun" checked> Dry Run</label>
                <label><input type="radio" name="process_type" value="run"> Run</label>
            </div>
        </div>
        <div>
            <label>Batch size:</label>
            <select name="batch_size">
                <option value="10">10 records</option>
                <option value="100">100 records</option>
                <option value="250">250 records</option>
                <option value="500">500 records</option>
                <option value="1000">1,000 records</option>
                <option value="5000" selected>5,000 records</option>
                <option value="10000">10,000 records</option>
            </select>					
        </div>
        <div>
            <input type="submit" name="start_rs" class="button button-primary" value="Start Process">
        </div>
    </div>
</form>



<div id="progress-container-a" class="box-rounded" style="display:none;">
    <h2>Export Progress</h2>
    <div id="progress-info-a">
        <div id="progress-percent-a"></div>
        <div id="progress-text-a"></div>
    </div>
    <progress id="new-progress-bar-a" class="progress-bar" style="width:100%"></progress>	
    <button id="stop-export-a" class="button button-secondary" style="display:none; margin-right:10px;">Stop Export</button>
    <a id="download-link-a" href="#" style="display:none;" class="button button-primary">Download CSV File</a>
    <p id="stop-message-a" style="color:#d63638; display:none;">Export stopped. You can download the data processed so far.</p>
</div>
<div id="info-container-a" class="box-rounded" style="display:none;">
    <p style="color:red">No records found for the selected criteria.</p>
</div>
<script>
    var exportProcess_a = createExportProcess_a();
    var progress;

    function createExportProcess_a() {
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
        progress = document.getElementById("new-progress-bar-a");

        $('#process-form').on('submit', function(e) {
            e.preventDefault();
            if (exportProcess_a.active) return;

            if($('input[name="process_type"]:checked').val() === 'run') {
                if(!confirm('This will update the next payment date of all renewal subscriptions. Are you sure you want to proceed?')){
                    return ;
                }
            }           
            
            
            exportProcess_a.active = true;
            exportProcess_a.stopRequested = false;
            $('#info-container-a').hide();
            $('#progress-container-a').show();
            $('#progress').css('width', 0);
            $('#progress-text-a').html('Preparing for export...');
            $('#new-progress-bar-a').removeAttr('value');
            $('#new-progress-bar-a').removeAttr('max');
            $('#stop-export-a').show();
            $('#stop-message-a').hide();
            
            $('#download-link-a').attr('href', '#').hide();
            const formData = $(this).serialize();
            exportProcess_a.currentOffset = 0;
            processBatch(formData, 0);
        });
        
        $('#stop-export-a').on('click', function() {
            console.log('Stop Button clicked', exportProcess_a);
            if (exportProcess_a.active) {
                exportProcess_a.stopRequested = true;
                $('#stop-export-a').prop('disabled', true).text('Stopping...');
                $('#progress-text-a').html('Stopping process...');
            }
        });
        
        function processBatch(formData, offset) {
            if (exportProcess_a.stopRequested) {
                finishExport(true);
                return;
            }
            var _action	=	'process_subscriptions_renewal_batch';

            $.post(ajaxurl, {
                action: _action,
                form_data: formData,
                offset: offset,
                total: exportProcess_a.currentTotal
            }, function(response) {
                console.log('process batch response', response);
                if (response.success) {
                    if(response.data.total==0){
                        progress.max = 0;
                        progress.value = 0;
                        $('#progress-container-a').hide();
                        $('#info-container-a').show();
                        finishExport(true);
                        return;
                    }
                    if (offset === 0) {
                        exportProcess_a.currentTotal = response.data.total;
                        progress.max = parseFloat(response.data.total);
                    }
                                                
                    const percent = Math.round((response.data.processed / response.data.total) * 100);
                    exportProcess_a.currentPercent = percent;
                    exportProcess_a.currentProcessed = response.data.processed;

                    $('#progress').css('width', percent + '%');
                    progress.value = parseFloat(response.data.processed);

                    $('#progress-text-a').html(`Processing(${percent}%): ${response.data.processed} of ${response.data.total} records`);

                    exportProcess_a.currentOffset = response.data.next_offset;
                    
                    if (response.data.complete) {
                        exportProcess_a.file_url	=	response.data.file_url;
                        finishExport(false);
                    } else{
                        if (!exportProcess_a.stopRequested) {
                            setTimeout(function() {
                                processBatch(formData, response.data.next_offset);
                            }, 500);
                        }else{
                            finishExport(true);
                        }
                    } 
                } else {
                    $('#progress-text-a').html('Error: ' + response.data);
                    finishExport(true);
                }
            }, 'json').fail(function() {
                $('#progress-text-a').html('Error in AJAX request');
                finishExport(true);
            });
        }
        
        function finishExport(stopped, stopCurrentExport=false) {console.log('finishExport', stopped);
            exportProcess_a.active = false;
            $('#stop-export-a').hide().prop('disabled', false).text('Stop Export');
            
            if (stopped) {
                $('#stop-message-a').show();
                if(stopCurrentExport){
                    $('#progress-container-a').show();
                    progress.max = 100;								
                    progress.value = 100;
                }

                var _action	=	'cccheck_export_file';
                
                $.post(ajaxurl, {
                    action: _action
                }, function(response) {
                    console.log('finish export', response)
                    if (response.success && response.data.file_url) {
                        $('#download-link-a').attr('href', response.data.file_url).show();
                        $('#progress-text-a').html(`Stopped Process(${exportProcess_a.currentPercent}%): ${exportProcess_a.currentProcessed} of ${exportProcess_a.currentTotal} records`);
                    }
                });
            } else {
                $('#download-link-a').attr('href', exportProcess_a.file_url).show();
                $('#progress-text-a').html(`Process Completed: ${exportProcess_a.currentTotal} records`);
            }		
            $('#download-link-a').show();
            $('input[name="start_rs"]').prop('disabled', false);
        }
    });
</script>
<style>
    .toggle-btn {cursor: pointer;color:#666;text-decoration: none;outline:none !important;box-shadow:none !important}
</style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleAdvanced');
    const advancedSection = document.getElementById('advancedSection');
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