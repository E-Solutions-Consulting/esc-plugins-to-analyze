<div id="process-progress-container" class="box-rounded" style="display:none;">
    <h2>Progress</h2>
    <div id="process-progress-info">
        <div id="process-progress-percent"></div>
        <div id="process-progress-text"></div>
    </div>

    <progress id="process-progress-bar" class="progress-bar" style="width:100%"></progress>

    <button id="process-progress-stop-button" class="button button-secondary" style="display:none; margin-right:10px;">Stop</button>
    <a id="process-result-download-link" href="#" style="display:none;" class="button button-primary">Download CSV File</a>
    <p id="process-progress-stop-message" style="color:#d63638; display:none;">Process stopped. You can download the data processed so far.</p>
</div>
<div id="process-progress-info-container" class="box-rounded" style="display:none;">
    <p style="color:red">No records found for the selected criteria.</p>
</div>
<script>
    var objectProcess = createObjectProcess();
    var progressBar;

    function createObjectProcess() {
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
        progressBar = document.getElementById("process-progress-bar");

        $('.filter-form').on('submit', function(e) {
            e.preventDefault();
            if (objectProcess.active) return;

            // if($('input[name="process_type"]:checked').val() === 'run') {
            //     if(!confirm('This will update the next payment date of all renewal subscriptions. Are you sure you want to proceed?')){
            //         return ;
            //     }
            // }
            
            
            objectProcess.active = true;
            objectProcess.stopRequested = false;
            $('#process-progress-info-container').hide();
            $('#process-progress-container').show();
            $('#progress').css('width', 0);
            $('#process-progress-text').html('Preparing for export...');
            $('#process-progress-bar').removeAttr('value');
            $('#process-progress-bar').removeAttr('max');
            $('#process-progress-stop-button').show();
            $('#process-progress-stop-message').hide();           

            $('#process-result-download-link').attr('href', '#').hide();
            const formData = $(this).serialize();
            objectProcess.currentOffset = 0;
            batchProcessing(formData, 0);
        });
        
        $('#process-progress-stop-button').on('click', function() {
            console.log('Stop Button clicked', objectProcess);
            if (objectProcess.active) {
                objectProcess.stopRequested = true;
                $('#process-progress-stop-button').prop('disabled', true).text('Stopping...');
                $('#process-progress-text').html('Stopping process...');
            }
        });
        
        function batchProcessing(formData, offset) {
            if (objectProcess.stopRequested) {
                finishProcess(true);
                return;
            }
            
            $.post(ajaxurl, {
                action: _action_batch,
                form_data: formData,
                offset: offset,
                total: objectProcess.currentTotal
            }, function(response) {
                console.log('process batch response', response);
                if (response.success) {
                    if(response.data.total==0){
                        progressBar.max = 0;
                        progressBar.value = 0;
                        $('#process-progress-container').hide();
                        $('#process-progress-info-container').show();
                        finishProcess(true);
                        return;
                    }
                    if (offset === 0) {
                        objectProcess.currentTotal = response.data.total;
                        progressBar.max = parseFloat(response.data.total);
                    }
                                                
                    const percent = Math.round((response.data.processed / response.data.total) * 100);
                    objectProcess.currentPercent = percent;
                    objectProcess.currentProcessed = response.data.processed;

                    $('#progress').css('width', percent + '%');
                    progressBar.value = parseFloat(response.data.processed);

                    $('#process-progress-text').html(`Processing(${percent}%): ${response.data.processed} of ${response.data.total} records`);

                    objectProcess.currentOffset = response.data.next_offset;
                    
                    if (response.data.complete) {
                        if (response.data.preview) {
                            //previewProcessedData(response.data);
                            $(document).trigger('previewData', [response.data]);
                        }else{
                            objectProcess.file_url	=	response.data.file_url;
                        }                        
                        finishProcess(false);

                    } else{
                        if (!objectProcess.stopRequested) {
                            setTimeout(function() {
                                batchProcessing(formData, response.data.next_offset);
                            }, 500);
                        }else{
                            finishProcess(true);
                        }
                    } 
                } else {
                    $('#process-progress-text').html('Error: ' + response.data);
                    finishProcess(true);
                }
            }, 'json').fail(function() {
                $('#process-progress-text').html('Error in AJAX request');
                finishProcess(true);
            });
        }
        
        function finishProcess(stopped, stopCurrentExport=false) {console.log('finishProcess', stopped);
            objectProcess.active = false;
            $('#process-progress-stop-button').hide().prop('disabled', false).text('Stop Export');
            
            if (stopped) {
                $('#process-progress-stop-message').show();
                if(stopCurrentExport){
                    $('#process-progress-container').show();
                    progressBar.max = 100;								
                    progressBar.value = 100;
                }

                var _action	=	'cccheck_export_file';
                
                $.post(ajaxurl, {
                    action: _action_export
                }, function(response) {
                    console.log('finish export', response)
                    if (response.success && response.data.file_url) {
                        $('#process-result-download-link').attr('href', response.data.file_url).show();
                        $('#process-progress-text').html(`Stopped Process(${objectProcess.currentPercent}%): ${objectProcess.currentProcessed} of ${objectProcess.currentTotal} records`);
                    }else{
                        $('#process-progress-text').html('<span style="color:red">Error: ' + response.data.message + '</span>');
                        $('#process-result-download-link').hide();
                        $('#process-progress-stop-message').hide();
                        progressBar.max = 0;
                        progressBar.value = 0;
                    }
                });
            } else {
                $('#process-result-download-link').attr('href', objectProcess.file_url).show();
                $('#process-progress-text').html(`Process Completed: ${objectProcess.currentTotal} records`);
            }		
            $('#process-result-download-link').show();
            $('input[name="start_rs"]').prop('disabled', false);
        }
    });
</script>

