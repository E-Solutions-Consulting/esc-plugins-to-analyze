<div id="json-analyzer-container">
    <h3>Telegra JSON Response Analyzer</h3>
    <p>Analyze saved JSON responses from Telegra API calls.</p>
    
    <div class="json-analyzer-controls" style="margin: 20px 0;">
        <button id="scan-json-files" class="button button-primary">Scan JSON Files</button>
        <button id="analyze-visit-types" class="button">Analyze Visit Types</button>
        <button id="export-json-summary" class="button">Export Summary</button>
    </div>
    
    <div id="json-analysis-results" style="margin-top: 20px;">
        <!-- Results will be loaded here -->
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    $('#scan-json-files').click(function() {
        $(this).prop('disabled', true).text('Scanning...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'scan_telegra_json_files',
                nonce: '<?php echo wp_create_nonce('scan_telegra_json_files'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayJsonScanResults(response.data);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                $('#scan-json-files').prop('disabled', false).text('Scan JSON Files');
            }
        });
    });
    
    $('#analyze-visit-types').click(function() {
        $(this).prop('disabled', true).text('Analyzing...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'analyze_visit_types_from_json',
                nonce: '<?php echo wp_create_nonce('analyze_visit_types_from_json'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayVisitTypeAnalysis(response.data);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                $('#analyze-visit-types').prop('disabled', false).text('Analyze Visit Types');
            }
        });
    });
    
    function displayJsonScanResults(data) {
        let html = `<div class="json-scan-results">
            <h4>JSON Files Found: ${data.total_files}</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
                <div><strong>Date Range:</strong><br>${data.date_range.earliest} to ${data.date_range.latest}</div>
                <div><strong>Unique Orders:</strong><br>${data.unique_orders}</div>
                <div><strong>Total Size:</strong><br>${data.total_size_mb} MB</div>
                <div><strong>API Response Codes:</strong><br>${Object.entries(data.response_codes).map(([code, count]) => code + ': ' + count).join('<br>')}</div>
            </div>
        </div>`;
        
        if (data.sample_files && data.sample_files.length > 0) {
            html += `<div class="sample-files" style="margin-top: 20px;">
                <h5>Sample Files:</h5>
                <ul>`;
            data.sample_files.forEach(file => {
                html += `<li><code>${file.name}</code> - ${file.order_id} (${file.state}) - ${file.timestamp}</li>`;
            });
            html += `</ul></div>`;
        }
        
        $('#json-analysis-results').html(html);
    }
    
    function displayVisitTypeAnalysis(data) {
        let html = `<div class="visit-type-analysis">
            <h4>Visit Type Analysis</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 15px 0;">`;
        
        // Visit type summary
        html += `<div class="analysis-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
            <h5>Visit Types</h5>
            <ul>`;
        Object.entries(data.visit_types).forEach(([type, count]) => {
            const cost = type.includes('async') ? '$18' : type.includes('sync') ? '$28' : 'Unknown';
            html += `<li>${type}: ${count} orders (${cost} each)</li>`;
        });
        html += `</ul></div>`;
        
        // State analysis
        if (data.by_state) {
            html += `<div class="analysis-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                <h5>By State</h5>
                <ul>`;
            Object.entries(data.by_state).forEach(([state, info]) => {
                html += `<li><strong>${state}:</strong> ${info.count} orders<br>
                    <small>Async: ${info.async || 0}, Sync: ${info.sync || 0}</small></li>`;
            });
            html += `</ul></div>`;
        }
        
        // Revenue calculation
        if (data.revenue_calculation) {
            html += `<div class="analysis-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                <h5>Revenue Calculation</h5>
                <p><strong>Total Expected Revenue:</strong> $${data.revenue_calculation.total}</p>
                <p>Async Revenue: $${data.revenue_calculation.async}</p>
                <p>Sync Revenue: $${data.revenue_calculation.sync}</p>
            </div>`;
        }
        
        html += `</div></div>`;
        $('#json-analysis-results').html(html);
    }
    
});
</script>

<style>
.json-analyzer-controls {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.analysis-card h5 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}

.json-scan-results, .visit-type-analysis {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
    margin-top: 15px;
}
</style>